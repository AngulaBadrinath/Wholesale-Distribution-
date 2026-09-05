<?php

namespace App\Models;

use App\Enums\AllocationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemAllocation extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'order_item_allocations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'allocation_number',
        'order_id',
        'order_item_id',
        'product_id',
        'allocated_quantity',
        'reserved_quantity',
        'picked_quantity',
        'dispatched_quantity',
        'delivered_quantity',
        'returned_quantity',
        'status',
        'warehouse_code',
        'notes',
        'allocated_by',
        'allocated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => AllocationStatus::class,
        'allocated_quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'picked_quantity' => 'integer',
        'dispatched_quantity' => 'integer',
        'delivered_quantity' => 'integer',
        'returned_quantity' => 'integer',
        'allocated_at' => 'datetime',
        'order_id' => 'integer',
        'order_item_id' => 'integer',
        'product_id' => 'integer',
        'allocated_by' => 'integer',
    ];

    /**
     * Get the order that this allocation belongs to.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Get the order line item that this allocation is bound to.
     *
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /**
     * Get the product being allocated.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the staff user who created or authorized this allocation.
     *
     * @return BelongsTo<User, $this>
     */
    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    /**
     * Scope query to active allocations.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AllocationStatus::ALLOCATED,
            AllocationStatus::RESERVED,
            AllocationStatus::PICKED,
            AllocationStatus::PACKED,
            AllocationStatus::DISPATCHED,
        ]);
    }

    /**
     * Calculate remaining unpicked quantity on this allocation.
     */
    public function unpickedQuantity(): int
    {
        return max(0, $this->allocated_quantity - $this->picked_quantity);
    }

    /**
     * Determine if this allocation can be released (freed back to unallocated quantity).
     * Only unpicked allocations in ALLOCATED or RESERVED status can be released.
     */
    public function isReleasable(): bool
    {
        return in_array($this->status, [AllocationStatus::ALLOCATED, AllocationStatus::RESERVED], true)
            && $this->picked_quantity === 0;
    }

    /**
     * Determine if this allocation can be cancelled.
     * Allocations can only be cancelled prior to physical picking.
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, [AllocationStatus::ALLOCATED, AllocationStatus::RESERVED], true)
            && $this->picked_quantity === 0;
    }
}
