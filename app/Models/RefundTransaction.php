<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\RefundTransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundTransaction extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'refund_transactions';

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('Refund transactions are permanent immutable financial records and cannot be updated.');
        });

        static::deleting(function () {
            throw new \LogicException('Refund transactions are permanent financial records and cannot be deleted.');
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transaction_number',
        'refund_request_id',
        'credit_note_id',
        'customer_id',
        'status',
        'amount',
        'payment_method',
        'reference_number',
        'failure_reason',
        'processed_by',
        'processed_at',
        'idempotency_key',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => RefundTransactionStatus::class,
        'payment_method' => PaymentMethod::class,
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    /**
     * Originating refund request.
     */
    public function refundRequest(): BelongsTo
    {
        return $this->belongsTo(RefundRequest::class, 'refund_request_id');
    }

    /**
     * Source credit note.
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class, 'credit_note_id');
    }

    /**
     * Customer receiving refund.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Processor user identity.
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
