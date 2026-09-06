<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentRejectionReason;
use App\Enums\PaymentReversalReason;
use App\Enums\PaymentTransactionStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => PaymentTransactionStatus::PENDING_VERIFICATION,
        'version' => 1,
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            if (empty($payment->version)) {
                $payment->version = 1;
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'payment_number',
        'customer_id',
        'order_id',
        'payment_method',
        'status',
        'amount',
        'payment_date',
        'cheque_number',
        'bank_name',
        'cheque_date',
        'money_order_number',
        'issuer_name',
        'receipt_reference',
        'evidence_object_key',
        'evidence_original_name',
        'evidence_mime_type',
        'evidence_size_bytes',
        'evidence_uploaded_at',
        'notes',
        'recorded_by',
        'verified_by',
        'verified_at',
        'rejected_by',
        'rejection_reason_code',
        'rejection_notes',
        'rejected_at',
        'reversed_by',
        'reversal_reason_code',
        'reversal_notes',
        'reversed_at',
        'version',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payment_method' => PaymentMethod::class,
        'status' => PaymentTransactionStatus::class,
        'rejection_reason_code' => PaymentRejectionReason::class,
        'reversal_reason_code' => PaymentReversalReason::class,
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'cheque_date' => 'date',
        'evidence_size_bytes' => 'integer',
        'evidence_uploaded_at' => 'datetime',
        'verified_at' => 'datetime',
        'rejected_at' => 'datetime',
        'reversed_at' => 'datetime',
        'customer_id' => 'integer',
        'order_id' => 'integer',
        'recorded_by' => 'integer',
        'verified_by' => 'integer',
        'rejected_by' => 'integer',
        'reversed_by' => 'integer',
        'version' => 'integer',
    ];

    /**
     * Get the customer associated with this payment.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the order linked to this payment, if any.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Get the user who recorded this payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get the user who verified this payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get the user who rejected this payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get the user who reversed this payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /**
     * Determine if this payment is pending verification.
     */
    public function isPending(): bool
    {
        return $this->status === PaymentTransactionStatus::PENDING_VERIFICATION;
    }

    /**
     * Determine if this payment is verified.
     */
    public function isVerified(): bool
    {
        return $this->status === PaymentTransactionStatus::VERIFIED;
    }

    /**
     * Determine if this payment is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === PaymentTransactionStatus::REJECTED;
    }

    /**
     * Determine if this payment is reversed.
     */
    public function isReversed(): bool
    {
        return $this->status === PaymentTransactionStatus::REVERSED;
    }

    /**
     * Determine if this payment has visual evidence attached.
     */
    public function hasEvidence(): bool
    {
        return ! empty($this->evidence_object_key);
    }

    /**
     * Scope query to pending payments.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PaymentTransactionStatus::PENDING_VERIFICATION);
    }

    /**
     * Scope query to verified payments.
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('status', PaymentTransactionStatus::VERIFIED);
    }

    /**
     * Scope query to rejected payments.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', PaymentTransactionStatus::REJECTED);
    }

    /**
     * Scope query to reversed payments.
     */
    public function scopeReversed(Builder $query): Builder
    {
        return $query->where('status', PaymentTransactionStatus::REVERSED);
    }

    /**
     * Scope query by customer ID.
     */
    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope query by order ID.
     */
    public function scopeForOrder(Builder $query, int $orderId): Builder
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Scope query based on the authenticated actor's resource scope.
     */
    public function scopeForUser(Builder $query, ?User $user): Builder
    {
        if ($user && $user->role === UserRole::SALESMAN) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('recorded_by', $user->id)
                    ->orWhereHas('customer', function (Builder $custQ) use ($user) {
                        $custQ->where('salesman_id', $user->id);
                    });
            });
        }

        return $query;
    }

    /**
     * Scope query by search term across payment number, customer name/code, cheque/MO number.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = trim($term);
        $isPgsql = $query->getConnection()->getDriverName() === 'pgsql';
        $like = $isPgsql ? 'ilike' : 'like';

        return $query->where(function (Builder $q) use ($term, $like) {
            $q->where('payments.payment_number', $like, "%{$term}%")
                ->orWhere('payments.cheque_number', $like, "%{$term}%")
                ->orWhere('payments.money_order_number', $like, "%{$term}%")
                ->orWhere('payments.receipt_reference', $like, "%{$term}%")
                ->orWhereHas('customer', function (Builder $custQ) use ($term, $like) {
                    $custQ->where('name', $like, "%{$term}%")
                        ->orWhere('code', $like, "%{$term}%");
                })
                ->orWhereHas('order', function (Builder $ordQ) use ($term, $like) {
                    $ordQ->where('order_number', $like, "%{$term}%");
                });
        });
    }

    /**
     * Scope query by transaction status filter.
     */
    public function scopeFilterByStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status) || strtoupper($status) === 'ALL') {
            return $query;
        }

        $resolved = PaymentTransactionStatus::tryFrom(strtoupper(trim($status)));

        if ($resolved) {
            return $query->where('status', $resolved);
        }

        return $query;
    }

    /**
     * Scope query by payment method filter.
     */
    public function scopeFilterByMethod(Builder $query, ?string $method): Builder
    {
        if (blank($method) || strtoupper($method) === 'ALL') {
            return $query;
        }

        $resolved = PaymentMethod::tryFrom(strtoupper(trim($method)));

        if ($resolved) {
            return $query->where('payment_method', $resolved);
        }

        return $query;
    }
}
