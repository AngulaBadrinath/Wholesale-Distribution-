<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryStockState;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'inventory_movements';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'movement_number',
        'warehouse_id',
        'product_id',
        'inventory_balance_id',
        'movement_type',
        'from_state',
        'to_state',
        'quantity',
        'on_hand_before',
        'on_hand_after',
        'reserved_before',
        'reserved_after',
        'available_before',
        'available_after',
        'damaged_before',
        'damaged_after',
        'reference_type',
        'reference_id',
        'reference_number',
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
        'movement_type' => InventoryMovementType::class,
        'from_state' => InventoryStockState::class,
        'to_state' => InventoryStockState::class,
        'quantity' => 'integer',
        'on_hand_before' => 'integer',
        'on_hand_after' => 'integer',
        'reserved_before' => 'integer',
        'reserved_after' => 'integer',
        'available_before' => 'integer',
        'available_after' => 'integer',
        'damaged_before' => 'integer',
        'damaged_after' => 'integer',
        'reference_id' => 'integer',
        'actor_id' => 'integer',
    ];

    /**
     * The "booted" method of the model.
     * Strict immutability enforcement at Eloquent event layer.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new DomainException('Inventory movement records are strictly immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new DomainException('Inventory movement records are strictly immutable and cannot be deleted.');
        });
    }

    /**
     * Prevent direct updates on existing instances.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new DomainException('Inventory movement records are strictly immutable and cannot be updated.');
    }

    /**
     * Prevent direct deletions on existing instances.
     */
    public function delete(): ?bool
    {
        throw new DomainException('Inventory movement records are strictly immutable and cannot be deleted.');
    }

    /**
     * Get the warehouse associated with this movement.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * Get the product associated with this movement.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the balance row associated with this movement.
     *
     * @return BelongsTo<InventoryBalance, $this>
     */
    public function inventoryBalance(): BelongsTo
    {
        return $this->belongsTo(InventoryBalance::class, 'inventory_balance_id');
    }

    /**
     * Get the user / actor who initiated this movement.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Scope query to a specific balance record.
     */
    public function scopeForBalance(Builder $query, int $balanceId): Builder
    {
        return $query->where('inventory_balance_id', $balanceId);
    }

    /**
     * Scope query to a specific product.
     */
    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope query to a specific warehouse.
     */
    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }
}
