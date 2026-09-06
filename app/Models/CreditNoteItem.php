<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteItem extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'credit_note_items';

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('Credit note items are permanent immutable financial records and cannot be modified.');
        });

        static::deleting(function () {
            throw new \LogicException('Credit note items are permanent financial records and cannot be deleted.');
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'credit_note_id',
        'order_item_id',
        'return_request_item_id',
        'product_id',
        'product_name_snapshot',
        'sku_snapshot',
        'quantity',
        'unit_price_snapshot',
        'tax_rate_snapshot',
        'tax_amount_snapshot',
        'line_subtotal',
        'line_total',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
        'unit_price_snapshot' => 'decimal:2',
        'tax_rate_snapshot' => 'decimal:4',
        'tax_amount_snapshot' => 'decimal:2',
        'line_subtotal' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    /**
     * Parent credit note relationship.
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class, 'credit_note_id');
    }

    /**
     * Original order item relationship.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /**
     * Source return request item relationship if applicable.
     */
    public function returnRequestItem(): BelongsTo
    {
        return $this->belongsTo(ReturnRequestItem::class, 'return_request_item_id');
    }

    /**
     * Product relationship.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
