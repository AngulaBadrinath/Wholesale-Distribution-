<?php

namespace App\Models;

use App\Enums\ReturnReasonCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnRequestItem extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'return_request_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'return_request_id',
        'order_item_id',
        'product_id',
        'requested_quantity',
        'received_quantity',
        'accepted_good_quantity',
        'accepted_damaged_quantity',
        'rejected_quantity',
        'unit_price_snapshot',
        'tax_rate_snapshot',
        'tax_profile_code_snapshot',
        'tax_profile_name_snapshot',
        'tax_amount_snapshot',
        'line_total',
        'reason_code',
        'item_notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'requested_quantity' => 'integer',
        'received_quantity' => 'integer',
        'accepted_good_quantity' => 'integer',
        'accepted_damaged_quantity' => 'integer',
        'rejected_quantity' => 'integer',
        'unit_price_snapshot' => 'decimal:2',
        'tax_rate_snapshot' => 'decimal:4',
        'tax_amount_snapshot' => 'decimal:2',
        'line_total' => 'decimal:2',
        'reason_code' => ReturnReasonCode::class,
    ];

    /**
     * Parent return request relationship.
     */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_request_id');
    }

    /**
     * Original order item relationship.
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /**
     * Product relationship.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Total accepted return quantity (good + damaged).
     */
    public function getApprovedQuantityAttribute(): int
    {
        return $this->accepted_good_quantity + $this->accepted_damaged_quantity;
    }

    /**
     * Total dispositioned quantity (good + damaged + rejected).
     */
    public function getDispositionTotalAttribute(): int
    {
        return $this->accepted_good_quantity + $this->accepted_damaged_quantity + $this->rejected_quantity;
    }
}
