<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditNoteStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditNote extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'credit_notes';

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::updating(function (CreditNote $creditNote) {
            $dirty = array_keys($creditNote->getDirty());
            $allowedOperationalFields = [
                'status',
                'allocated_to_refunds',
                'remaining_balance',
                'updated_at',
            ];

            $disallowed = array_diff($dirty, $allowedOperationalFields);

            if (! empty($disallowed)) {
                throw new \LogicException(sprintf(
                    'Issued credit notes are immutable financial records. Cannot modify fields: [%s].',
                    implode(', ', $disallowed)
                ));
            }
        });

        static::deleting(function () {
            throw new \LogicException('Issued credit notes are permanent financial records and cannot be deleted.');
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'credit_number',
        'customer_id',
        'order_id',
        'invoice_id',
        'return_request_id',
        'status',
        'currency',
        'subtotal',
        'tax_total',
        'total_amount',
        'allocated_to_refunds',
        'remaining_balance',
        'reason',
        'issued_by',
        'issued_at',
        'customer_name_snapshot',
        'customer_code_snapshot',
        'customer_contact_snapshot',
        'customer_email_snapshot',
        'customer_phone_snapshot',
        'billing_address_line1_snapshot',
        'billing_city_snapshot',
        'billing_state_snapshot',
        'billing_postal_code_snapshot',
        'billing_country_snapshot',
        'company_legal_name_snapshot',
        'company_address_snapshot',
        'company_tax_id_snapshot',
        'idempotency_key',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => CreditNoteStatus::class,
        'issued_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'allocated_to_refunds' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
    ];

    /**
     * Customer relationship.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Original order relationship.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Associated invoice relationship where applicable.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * Source return request relationship where applicable.
     */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_request_id');
    }

    /**
     * Issuing user relationship.
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Line items relationship.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class, 'credit_note_id');
    }

    /**
     * Refund requests referencing this credit note.
     */
    public function refundRequests(): HasMany
    {
        return $this->hasMany(RefundRequest::class, 'credit_note_id');
    }

    /**
     * Processed refund transactions against this credit note.
     */
    public function refundTransactions(): HasMany
    {
        return $this->hasMany(RefundTransaction::class, 'credit_note_id');
    }

    /**
     * Scope query to credit notes having refundable balance remaining.
     */
    public function scopeRefundable(Builder $query): Builder
    {
        return $query->where('remaining_balance', '>', 0)
            ->whereIn('status', [CreditNoteStatus::ISSUED->value, CreditNoteStatus::PARTIALLY_REFUNDED->value]);
    }

    /**
     * Scope query for multi-column search.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $isPgsql = $query->getConnection()->getDriverName() === 'pgsql';
        $like = $isPgsql ? 'ilike' : 'like';

        return $query->where(function (Builder $q) use ($term, $like) {
            $q->where('credit_number', $like, "%{$term}%")
                ->orWhere('customer_name_snapshot', $like, "%{$term}%")
                ->orWhere('customer_code_snapshot', $like, "%{$term}%")
                ->orWhereHas('order', fn ($oq) => $oq->where('order_number', $like, "%{$term}%"));
        });
    }

    /**
     * Determine if this credit note has refundable balance available.
     */
    public function isRefundable(): bool
    {
        return bccomp((string) $this->remaining_balance, '0.00', 2) > 0 && $this->status->isRefundable();
    }
}
