<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupplierPaymentMethod;
use App\Enums\SupplierPaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPayment extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'supplier_payments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'payment_number',
        'supplier_id',
        'supplier_bill_id',
        'payment_date',
        'amount',
        'payment_method',
        'reference_number',
        'status',
        'notes',
        'created_by',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payment_method' => SupplierPaymentMethod::class,
        'status' => SupplierPaymentStatus::class,
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'reversed_at' => 'datetime',
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
     * Relationship: Payable Transactions
     */
    public function payableTransactions(): HasMany
    {
        return $this->hasMany(PayableTransaction::class);
    }

    /**
     * Relationship: Creator
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Reverser
     */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /**
     * Scope: Completed payments
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', SupplierPaymentStatus::COMPLETED->value);
    }

    /**
     * Scope: For specific supplier
     */
    public function scopeForSupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
    }
}
