<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RefundRequest extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'refund_requests';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'refund_number',
        'credit_note_id',
        'customer_id',
        'order_id',
        'status',
        'requested_amount',
        'payment_method',
        'reason',
        'notes',
        'requested_by',
        'requested_at',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'idempotency_key',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => RefundStatus::class,
        'payment_method' => PaymentMethod::class,
        'requested_amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Parent credit note relationship.
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class, 'credit_note_id');
    }

    /**
     * Customer receiving the refund.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Originating order relationship if applicable.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Requester relationship.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Reviewer relationship.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Approver relationship.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Rejector relationship.
     */
    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Canceller relationship.
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Immutable audit event trail.
     */
    public function events(): HasMany
    {
        return $this->hasMany(RefundRequestEvent::class, 'refund_request_id')->orderBy('created_at', 'asc');
    }

    /**
     * The executed refund transaction if processed.
     */
    public function transaction(): HasOne
    {
        return $this->hasOne(RefundTransaction::class, 'refund_request_id');
    }

    /**
     * Scope query to active pending refund requests.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            RefundStatus::REQUESTED->value,
            RefundStatus::UNDER_REVIEW->value,
            RefundStatus::APPROVED->value,
        ]);
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
            $q->where('refund_number', $like, "%{$term}%")
                ->orWhereHas('creditNote', fn ($cn) => $cn->where('credit_number', $like, "%{$term}%"))
                ->orWhereHas('customer', fn ($c) => $c->where('name', $like, "%{$term}%")->orWhere('customer_code', $like, "%{$term}%"));
        });
    }
}
