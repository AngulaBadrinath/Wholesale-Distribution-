<?php

namespace App\Services\Inventory;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Enums\StockStatus;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Auth\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Retrieve single inventory balance by warehouse and product ID.
     */
    public function findByWarehouseAndProduct(int $warehouseId, int $productId): ?InventoryBalance
    {
        return InventoryBalance::query()
            ->with(['warehouse', 'product'])
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->first();
    }

    /**
     * Retrieve single inventory balance or fail.
     *
     * @throws ValidationException
     */
    public function getBalanceOrFail(int $warehouseId, int $productId): InventoryBalance
    {
        $balance = $this->findByWarehouseAndProduct($warehouseId, $productId);

        if (! $balance) {
            throw ValidationException::withMessages([
                'inventory' => "No physical inventory balance record found for product ID {$productId} at warehouse ID {$warehouseId}.",
            ]);
        }

        return $balance;
    }

    /**
     * Acquire a deterministic pessimistic row lock on a specific balance record.
     * Must be called within an active DB transaction.
     */
    public function lockBalanceForUpdate(int $warehouseId, int $productId): ?InventoryBalance
    {
        /** @var InventoryBalance|null $balance */
        $balance = InventoryBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        return $balance;
    }

    /**
     * Acquire deterministic pessimistic row locks across multiple products in ascending ID sequence.
     * Ordering by primary key ID guarantees absence of cross-table or intra-table locking deadlocks.
     * Must be called within an active DB transaction.
     *
     * @param  array<int, int>  $productIds
     * @return Collection<int, InventoryBalance>
     */
    public function lockBalancesForUpdate(int $warehouseId, array $productIds): Collection
    {
        if (empty($productIds)) {
            return new Collection;
        }

        $uniqueProductIds = array_values(array_unique(array_filter($productIds)));

        return InventoryBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_id', $uniqueProductIds)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Check if a product has sufficient available physical stock at a warehouse.
     */
    public function checkAvailability(int $warehouseId, int $productId, int $requiredQuantity): bool
    {
        if ($requiredQuantity <= 0) {
            return true;
        }

        $balance = InventoryBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->first();

        if (! $balance) {
            return false;
        }

        return $balance->available_quantity >= $requiredQuantity;
    }

    /**
     * Retrieve aggregate stock status counts for operational dashboard widgets.
     *
     * @return array<string, int>
     */
    public function getSummaryCounts(?int $warehouseId = null): array
    {
        $query = InventoryBalance::query();

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        $balances = $query->get(['id', 'on_hand_quantity', 'reserved_quantity', 'damaged_quantity', 'available_quantity', 'reorder_point']);

        $total = $balances->count();
        $inStock = 0;
        $lowStock = 0;
        $outOfStock = 0;

        foreach ($balances as $b) {
            $status = $b->getStockStatus();
            match ($status) {
                StockStatus::IN_STOCK => $inStock++,
                StockStatus::LOW_STOCK => $lowStock++,
                StockStatus::OUT_OF_STOCK => $outOfStock++,
            };
        }

        return [
            'all_items' => $total,
            'in_stock_items' => $inStock,
            'low_stock_items' => $lowStock,
            'out_of_stock_items' => $outOfStock,
        ];
    }

    /**
     * Retrieve paginated, searchable, filterable inventory balance list for Admin/Warehouse workspaces.
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws AuthorizationException
     */
    public function list(array $filters = [], int $perPage = 15, ?User $actor = null): LengthAwarePaginator
    {
        if ($actor !== null) {
            $this->ensureActorCanView($actor);
        }

        $query = InventoryBalance::query()
            ->with([
                'warehouse:id,code,name,is_active,is_default',
                'product:id,sku,name,unit,status,category_id',
                'product.category:id,code,name',
            ]);

        // 1. Warehouse filter
        if (! empty($filters['warehouse_id']) && is_numeric($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        // 2. Stock status filter
        if (! empty($filters['stock_status'])) {
            $query->filterByStockStatus((string) $filters['stock_status']);
        }

        // 3. Search query across product SKU/name and bin_location
        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        // 4. Sort allow-listing
        $allowedSorts = [
            'on_hand_quantity',
            'available_quantity',
            'reserved_quantity',
            'damaged_quantity',
            'reorder_point',
            'safety_stock',
            'bin_location',
            'last_counted_at',
            'created_at',
        ];

        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts, true)
            ? $filters['sort_by']
            : 'id';

        $sortDirection = strtolower((string) ($filters['sort_direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $boundedPerPage = max(1, min(100, $perPage));

        $paginator = $query->orderBy($sortBy, $sortDirection)
            ->paginate($boundedPerPage)
            ->withQueryString();

        $paginator->through(fn (InventoryBalance $balance) => $this->formatBalance($balance));

        return $paginator;
    }

    /**
     * Format an InventoryBalance model into a structured, type-safe presentation array.
     *
     * @return array<string, mixed>
     */
    public function formatBalance(InventoryBalance $balance): array
    {
        $status = $balance->getStockStatus();

        return [
            'id' => $balance->id,
            'warehouse_id' => $balance->warehouse_id,
            'warehouse_code' => $balance->warehouse?->code ?? 'MAIN',
            'warehouse_name' => $balance->warehouse?->name ?? 'Main Distribution Center',
            'product_id' => $balance->product_id,
            'product_name' => $balance->product?->name ?? 'Unknown Product',
            'sku' => $balance->product?->sku ?? 'N/A',
            'unit' => $balance->product?->unit ?? 'UNIT',
            'product_status' => $balance->product?->status instanceof \App\Enums\ProductStatus
                ? $balance->product->status->value
                : (string) ($balance->product?->status ?? 'ACTIVE'),
            'category_name' => $balance->product?->category?->name,
            'bin_location' => $balance->bin_location,
            'reorder_point' => (int) $balance->reorder_point,
            'safety_stock' => (int) $balance->safety_stock,
            'on_hand_quantity' => (int) $balance->on_hand_quantity,
            'reserved_quantity' => (int) $balance->reserved_quantity,
            'available_quantity' => (int) $balance->available_quantity,
            'damaged_quantity' => (int) $balance->damaged_quantity,
            'stock_status' => $status->value,
            'stock_status_label' => $status->label(),
            'stock_status_badge_variant' => $status->badgeVariant(),
            'is_active' => (bool) $balance->is_active,
            'version' => (int) $balance->version,
            'last_counted_at' => $balance->last_counted_at?->toIso8601String(),
            'created_at' => $balance->created_at?->toIso8601String(),
            'updated_at' => $balance->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Retrieve all active warehouses for dropdown filtering.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveWarehouses(): array
    {
        return Warehouse::query()
            ->active()
            ->orderBy('is_default', 'desc')
            ->orderBy('name', 'asc')
            ->get(['id', 'code', 'name', 'is_default', 'is_active'])
            ->map(fn (Warehouse $w) => [
                'id' => $w->id,
                'code' => $w->code,
                'name' => $w->name,
                'is_default' => (bool) $w->is_default,
                'is_active' => (bool) $w->is_active,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Ensure actor has an active account status and the required INVENTORY_VIEW permission.
     *
     * @throws AuthorizationException
     */
    public function ensureActorCanView(User $actor): void
    {
        $isActive = ($actor->status instanceof AccountStatus)
            ? $actor->status === AccountStatus::ACTIVE
            : $actor->status === AccountStatus::ACTIVE->value;

        if (! $isActive) {
            throw new AuthorizationException('Inactive accounts are not authorized to view physical inventory balances.');
        }

        $this->permissionService->authorize($actor, Permission::INVENTORY_VIEW);
    }
}
