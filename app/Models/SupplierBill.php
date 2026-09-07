<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupplierBillStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierBill extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'supplier_bills';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'bill_number',
        'supplier_invoice_number',
        'supplier_id',
        'bill_date',
        'due_date',
        'subtotal',
        'tax_total',
        'total_amount',
        'amount_paid',
        'amount_due',
        'status',
        'description',
        'notes',
        'created_by',
        'posted_at',
        'posted_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => SupplierBillStatus::class,
        'bill_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    /**
     * Relationship: Supplier
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Relationship: Payments
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
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
     * Relationship: Poster
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * Scope: Open / Payable Bills (POSTED or PARTIALLY_PAID)
     */
    public function scopePayable(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SupplierBillStatus::POSTED->value,
            SupplierBillStatus::PARTIALLY_PAID->value,
        ]);
    }

    /**
     * Scope: For specific supplier
     */
    public function scopeForSupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
    }
}
