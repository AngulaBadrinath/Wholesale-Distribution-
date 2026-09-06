<?php

namespace App\Models;

use App\Enums\StockStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'inventory_balances';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'warehouse_id',
        'product_id',
        'bin_location',
        'reorder_point',
        'safety_stock',
        'on_hand_quantity',
        'reserved_quantity',
        'available_quantity',
        'damaged_quantity',
        'is_active',
        'version',
        'last_counted_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'warehouse_id' => 'integer',
        'product_id' => 'integer',
        'reorder_point' => 'integer',
        'safety_stock' => 'integer',
        'on_hand_quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'available_quantity' => 'integer',
        'damaged_quantity' => 'integer',
        'is_active' => 'boolean',
        'version' => 'integer',
        'last_counted_at' => 'datetime',
    ];

    /**
     * Get the warehouse this inventory balance belongs to.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * Get the product this inventory balance belongs to.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Authoritatively compute expected available quantity from baseline components.
     * Invariant: available = on_hand - reserved - damaged
     */
    public function calculateAvailableQuantity(): int
    {
        return max(0, $this->on_hand_quantity - $this->reserved_quantity - $this->damaged_quantity);
    }

    /**
     * Determine the authoritative stock status for this balance row.
     */
    public function getStockStatus(): StockStatus
    {
        if ($this->available_quantity <= 0) {
            return StockStatus::OUT_OF_STOCK;
        }

        if ($this->reorder_point > 0 && $this->available_quantity <= $this->reorder_point) {
            return StockStatus::LOW_STOCK;
        }

        return StockStatus::IN_STOCK;
    }

    /**
     * Check if the balance is currently in stock.
     */
    public function isInStock(): bool
    {
        return $this->getStockStatus() === StockStatus::IN_STOCK;
    }

    /**
     * Check if the balance is currently at low stock.
     */
    public function isLowStock(): bool
    {
        return $this->getStockStatus() === StockStatus::LOW_STOCK;
    }

    /**
     * Check if the balance is currently out of stock.
     */
    public function isOutOfStock(): bool
    {
        return $this->getStockStatus() === StockStatus::OUT_OF_STOCK;
    }

    /**
     * Scope query to a specific warehouse.
     */
    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope query to active inventory balance records.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query to items in stock (available > 0 and not low stock).
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('available_quantity', '>', 0)
            ->where(function (Builder $q) {
                $q->where('reorder_point', '<=', 0)
                    ->orWhereRaw('available_quantity > reorder_point');
            });
    }

    /**
     * Scope query to items with low stock (available > 0 and available <= reorder_point).
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('available_quantity', '>', 0)
            ->where('reorder_point', '>', 0)
            ->whereRaw('available_quantity <= reorder_point');
    }

    /**
     * Scope query to out-of-stock items (available <= 0).
     */
    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('available_quantity', '<=', 0);
    }

    /**
     * Scope query by stock status enum string or value.
     */
    public function scopeFilterByStockStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status) || strtoupper($status) === 'ALL') {
            return $query;
        }

        $resolved = StockStatus::tryFrom(strtoupper(trim($status)));

        return match ($resolved) {
            StockStatus::IN_STOCK => $this->scopeInStock($query),
            StockStatus::LOW_STOCK => $this->scopeLowStock($query),
            StockStatus::OUT_OF_STOCK => $this->scopeOutOfStock($query),
            default => $query,
        };
    }

    /**
     * Scope query by search term across product SKU/name and bin location.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = trim($term);
        $isPgsql = $query->getConnection()->getDriverName() === 'pgsql';
        $like = $isPgsql ? 'ilike' : 'like';

        return $query->where(function (Builder $q) use ($term, $like) {
            $q->where('bin_location', $like, "%{$term}%")
                ->orWhereHas('product', function (Builder $pq) use ($term, $like) {
                    $pq->where('name', $like, "%{$term}%")
                        ->orWhere('sku', $like, "%{$term}%");
                });
        });
    }
}
