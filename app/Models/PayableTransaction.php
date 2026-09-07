<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PayableTransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayableTransaction extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payable_transactions';

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::updating(function (PayableTransaction $txn) {
            $dirty = array_keys($txn->getDirty());
            $allowedOperationalFields = [
                'running_balance',
                'notes',
                'updated_at',
            ];

            $disallowed = array_diff($dirty, $allowedOperationalFields);

            if (! empty($disallowed)) {
                throw new \LogicException(sprintf(
                    'Posted payable transactions are immutable financial records. Cannot modify fields: [%s].',
                    implode(', ', $disallowed)
                ));
            }
        });

        static::deleting(function () {
            throw new \LogicException('Payable transactions are permanent immutable financial ledger records and cannot be deleted.');
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transaction_number',
        'supplier_id',
        'supplier_bill_id',
        'supplier_payment_id',
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
        'type' => PayableTransactionType::class,
        'amount' => 'decimal:2',
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'running_balance' => 'decimal:2',
        'transaction_date' => 'date',
        'posting_date' => 'date',
        'due_date' => 'date',
    ];

    /**
     * Relationship: Supplier
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Relationship: Supplier Bill
     */
    public function supplierBill(): BelongsTo
    {
        return $this->belongsTo(SupplierBill::class);
    }

    /**
     * Relationship: Supplier Payment
     */
    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class);
    }

    /**
     * Relationship: Creator / Actor
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope: Filter by Supplier ID
     */
    public function scopeForSupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Scope: Filter by Date Range
     */
    public function scopeInPeriod(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Debits only (reductions in liability)
     */
    public function scopeDebits(Builder $query): Builder
    {
        return $query->where('debit_amount', '>', 0);
    }

    /**
     * Scope: Credits only (increases in liability)
     */
    public function scopeCredits(Builder $query): Builder
    {
        return $query->where('credit_amount', '>', 0);
    }

    /**
     * Helper: Check if this transaction increases liability
     */
    public function isLiabilityIncrease(): bool
    {
        return $this->type->isLiabilityIncrease();
    }

    /**
     * Helper: Check if this transaction decreases liability
     */
    public function isLiabilityDecrease(): bool
    {
        return $this->type->isLiabilityDecrease();
    }
}
