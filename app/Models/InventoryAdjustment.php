<?php

namespace App\Models;

use App\Enums\InventoryAdjustmentReason;
use App\Enums\InventoryAdjustmentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustment extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'inventory_adjustments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'adjustment_number',
        'warehouse_id',
        'product_id',
        'inventory_balance_id',
        'adjustment_type',
        'reason_code',
        'quantity',
        'on_hand_before',
        'on_hand_after',
        'reserved_before',
        'reserved_after',
        'available_before',
        'available_after',
        'damaged_before',
        'damaged_after',
        'notes',
        'actor_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'warehouse_id' => 'integer',
        'product_id' => 'integer',
        'inventory_balance_id' => 'integer',
        'adjustment_type' => InventoryAdjustmentType::class,
        'reason_code' => InventoryAdjustmentReason::class,
        'quantity' => 'integer',
        'on_hand_before' => 'integer',
        'on_hand_after' => 'integer',
        'reserved_before' => 'integer',
        'reserved_after' => 'integer',
        'available_before' => 'integer',
        'available_after' => 'integer',
        'damaged_before' => 'integer',
        'damaged_after' => 'integer',
        'actor_id' => 'integer',
    ];

    /**
     * Get the warehouse associated with the adjustment.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * Get the product associated with the adjustment.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the inventory balance record associated with the adjustment.
     *
     * @return BelongsTo<InventoryBalance, $this>
     */
    public function inventoryBalance(): BelongsTo
    {
        return $this->belongsTo(InventoryBalance::class, 'inventory_balance_id');
    }

    /**
     * Get the user who posted the adjustment.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
