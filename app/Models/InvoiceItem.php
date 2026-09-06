<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('Invoice items are immutable historical records and cannot be modified.');
        });

        static::deleting(function () {
            throw new \LogicException('Invoice items are permanent financial records and cannot be deleted.');
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'invoice_id',
        'order_item_id',
        'product_id',
        'product_name_snapshot',
        'sku_snapshot',
        'unit_snapshot',
        'quantity',
        'unit_price',
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
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'tax_rate_snapshot' => 'decimal:4',
        'taxable_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'invoice_id' => 'integer',
        'order_item_id' => 'integer',
        'product_id' => 'integer',
    ];

    /**
     * Get the invoice that this line belongs to.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * Get the originating order item.
     *
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /**
     * Get the referenced product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
