<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name_snapshot',
        'sku_snapshot',
        'unit_snapshot',
        'ordered_quantity',
        'cancelled_quantity',
        'reserved_quantity',
        'picked_quantity',
        'dispatched_quantity',
        'delivered_quantity',
        'returned_quantity',
        'unit_price',
        'is_price_overridden',
        'price_override_reason',
        'price_override_approved_by',
        'tax_profile_id',
        'tax_profile_code_snapshot',
        'tax_profile_name_snapshot',
        'tax_rate_snapshot',
        'taxable_amount',
        'tax_amount',
        'line_total',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'ordered_quantity' => 'integer',
        'cancelled_quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'picked_quantity' => 'integer',
        'dispatched_quantity' => 'integer',
        'delivered_quantity' => 'integer',
        'returned_quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'is_price_overridden' => 'boolean',
        'tax_rate_snapshot' => 'decimal:4',
        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'order_id' => 'integer',
        'product_id' => 'integer',
        'tax_profile_id' => 'integer',
        'price_override_approved_by' => 'integer',
    ];

    /**
     * Get the order that this line item belongs to.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Get the product referenced by this line item.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the tax profile referenced by this line item snapshot.
     *
     * @return BelongsTo<TaxProfile, $this>
     */
    public function taxProfile(): BelongsTo
    {
        return $this->belongsTo(TaxProfile::class, 'tax_profile_id');
    }

    /**
     * Get the user who authorized the price override, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function priceOverrideApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'price_override_approved_by');
    }

    /**
     * Calculate current fulfillable quantity adhering to conservation rule.
     * ordered_quantity = cancelled_quantity + fulfillable_quantity
     */
    public function fulfillableQuantity(): int
    {
        return max(0, $this->ordered_quantity - $this->cancelled_quantity);
    }
}
