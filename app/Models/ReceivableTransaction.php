<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReceivableTransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivableTransaction extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'receivable_transactions';

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::updating(function (ReceivableTransaction $txn) {
            $dirty = array_keys($txn->getDirty());
            $allowedOperationalFields = [
                'running_balance',
                'notes',
                'updated_at',
            ];

            $disallowed = array_diff($dirty, $allowedOperationalFields);

            if (! empty($disallowed)) {
                throw new \LogicException(sprintf(
                    'Posted receivable transactions are immutable financial records. Cannot modify fields: [%s].',
                    implode(', ', $disallowed)
                ));
            }
        });

        static::deleting(function () {
            throw new \LogicException('Receivable transactions are permanent immutable financial ledger records and cannot be deleted.');
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transaction_number',
        'customer_id',
        'order_id',
        'invoice_id',
        'payment_id',
        'credit_note_id',
        'source_type',
        'source_id',
        'source_number',
        'type',
        'amount',
        'debit_amount',
        'credit_amount',
        'running_balance',
        'transaction_date',
        'posting_date',
        'due_date',
        'currency',
        'description',
        'notes',
        'created_by',
        'idempotency_key',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'type' => ReceivableTransactionType::class,
        'amount' => 'decimal:2',
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'running_balance' => 'decimal:2',
        'transaction_date' => 'date',
        'posting_date' => 'date',
        'due_date' => 'date',
    ];

    /**
     * Relationship: Customer
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relationship: Order (if applicable)
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Relationship: Invoice (if applicable)
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Relationship: Payment (if applicable)
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Relationship: Credit Note (if applicable)
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    /**
     * Relationship: Creator / Actor
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope: Filter by Customer ID
     */
    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope: Filter by Date Range
     */
    public function scopeInPeriod(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Debits only
     */
    public function scopeDebits(Builder $query): Builder
    {
        return $query->where('debit_amount', '>', 0);
    }

    /**
     * Scope: Credits only
     */
    public function scopeCredits(Builder $query): Builder
    {
        return $query->where('credit_amount', '>', 0);
    }

    /**
     * Helper: Check if this transaction is a debit
     */
    public function isDebit(): bool
    {
        return $this->type->isDebit();
    }

    /**
     * Helper: Check if this transaction is a credit
     */
    public function isCredit(): bool
    {
        return $this->type->isCredit();
    }
}
