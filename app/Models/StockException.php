<?php

namespace App\Models;

use App\Enums\InventoryStockState;
use App\Enums\StockExceptionSeverity;
use App\Enums\StockExceptionStatus;
use App\Enums\StockExceptionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockException extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'stock_exceptions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'exception_number',
        'warehouse_id',
        'product_id',
        'inventory_balance_id',
        'order_id',
        'order_item_allocation_id',
        'exception_type',
        'severity',
        'source_stock_state',
        'quantity',
        'status',
        'description',
        'reported_by',
        'resolved_by',
        'resolution_notes',
        'resolved_at',
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
        'order_id' => 'integer',
        'order_item_allocation_id' => 'integer',
        'exception_type' => StockExceptionType::class,
        'severity' => StockExceptionSeverity::class,
        'source_stock_state' => InventoryStockState::class,
        'status' => StockExceptionStatus::class,
        'quantity' => 'integer',
        'reported_by' => 'integer',
        'resolved_by' => 'integer',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the warehouse where this exception was reported.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * Get the product associated with this exception.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the inventory balance row associated with this exception.
     *
     * @return BelongsTo<InventoryBalance, $this>
     */
    public function inventoryBalance(): BelongsTo
    {
        return $this->belongsTo(InventoryBalance::class, 'inventory_balance_id');
    }

    /**
     * Get the order associated with this exception, if any.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Get the order item allocation associated with this exception, if any.
     *
     * @return BelongsTo<OrderItemAllocation, $this>
     */
    public function orderItemAllocation(): BelongsTo
    {
        return $this->belongsTo(OrderItemAllocation::class, 'order_item_allocation_id');
    }

    /**
     * Get the user who reported this exception.
     *
     * @return BelongsTo<User, $this>
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * Get the user who resolved this exception.
     *
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope query to pending exceptions.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', StockExceptionStatus::PENDING_REVIEW);
    }

    /**
     * Scope query for a specific warehouse.
     */
    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }
}
