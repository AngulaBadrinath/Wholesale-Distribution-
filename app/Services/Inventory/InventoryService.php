<?php

namespace App\Services\Inventory;

use App\Enums\AccountStatus;
use App\Enums\AllocationStatus;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\StockStatus;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
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
     * Authoritatively reserve physical inventory for an order inside an active DB transaction.
     * Pessimistic row locking is acquired in deterministic ascending ID order on inventory_balances.
     *
     * @return array<int, InventoryBalance> Map of product_id => updated InventoryBalance
     *
     * @throws InsufficientStockException
     * @throws ValidationException
     */
    public function reserveStockForOrder(Order $order, User $actor, ?int $warehouseId = null): array
    {
        $warehouse = $warehouseId
            ? Warehouse::findOrFail($warehouseId)
            : (Warehouse::getDefault() ?: Warehouse::firstOrFail());

        // Extract order items that require fulfillment
        $items = $order->items()->orderBy('id', 'asc')->get();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'order' => 'Order contains no items to reserve.',
            ]);
        }

        // Calculate fulfillable demand per product ID
        $productDemand = [];
        $productSkus = [];
        foreach ($items as $item) {
            $fulfillable = $item->fulfillableQuantity();
            if ($fulfillable > 0) {
                $productDemand[$item->product_id] = ($productDemand[$item->product_id] ?? 0) + $fulfillable;
                $productSkus[$item->product_id] = $item->sku_snapshot;
            }
        }

        if (empty($productDemand)) {
            return [];
        }

        $productIds = array_keys($productDemand);
        sort($productIds, SORT_NUMERIC);

        // Deterministically acquire pessimistic row locks in ascending ID order
        $balances = $this->lockBalancesForUpdate($warehouse->id, $productIds);
        $balancesByProduct = $balances->keyBy('product_id');

        // Phase 1: Authoritative Pre-validation across all lines
        foreach ($productDemand as $productId => $requiredQuantity) {
            /** @var InventoryBalance|null $balance */
            $balance = $balancesByProduct->get($productId);
            $sku = $productSkus[$productId] ?? "PROD-{$productId}";

            if (! $balance) {
                throw new InsufficientStockException(
                    productId: $productId,
                    sku: $sku,
                    requestedQuantity: $requiredQuantity,
                    availableQuantity: 0,
                    warehouseId: $warehouse->id,
                    message: "No inventory balance found for SKU [{$sku}] at warehouse [{$warehouse->code}]. Available: 0, Requested: {$requiredQuantity}."
                );
            }

            if ($balance->available_quantity < $requiredQuantity) {
                throw new InsufficientStockException(
                    productId: $productId,
                    sku: $sku,
                    requestedQuantity: $requiredQuantity,
                    availableQuantity: $balance->available_quantity,
                    warehouseId: $warehouse->id,
                    message: "Insufficient physical stock for SKU [{$sku}] at warehouse [{$warehouse->code}]. Available: {$balance->available_quantity}, Requested: {$requiredQuantity}."
                );
            }
        }

        // Phase 2: Authoritative Atomic Mutation
        $updatedBalances = [];
        foreach ($productDemand as $productId => $requiredQuantity) {
            /** @var InventoryBalance $balance */
            $balance = $balancesByProduct->get($productId);

            $balance->reserved_quantity += $requiredQuantity;
            $balance->available_quantity = $balance->calculateAvailableQuantity();
            $balance->version += 1;
            $balance->save();

            $updatedBalances[$productId] = $balance;
        }

        return $updatedBalances;
    }

    /**
     * Authoritatively release physical inventory reservation for an order inside an active DB transaction.
     * Pessimistic row locking is acquired in deterministic ascending ID order on inventory_balances.
     *
     * @param  array<int, int>|null  $itemQuantities  Optional map of order_item_id => quantityToRelease
     * @return array<int, InventoryBalance> Map of product_id => updated InventoryBalance
     */
    public function releaseStockForOrder(Order $order, User $actor, ?int $warehouseId = null, ?array $itemQuantities = null): array
    {
        $warehouse = $warehouseId
            ? Warehouse::findOrFail($warehouseId)
            : (Warehouse::getDefault() ?: Warehouse::firstOrFail());

        $items = $order->items()->orderBy('id', 'asc')->get();
        if ($items->isEmpty()) {
            return [];
        }

        $productReleases = [];
        foreach ($items as $item) {
            $releaseQty = $itemQuantities !== null
                ? ($itemQuantities[$item->id] ?? 0)
                : $item->reserved_quantity;

            if ($releaseQty > 0) {
                $productReleases[$item->product_id] = ($productReleases[$item->product_id] ?? 0) + $releaseQty;
            }
        }

        if (empty($productReleases)) {
            return [];
        }

        $productIds = array_keys($productReleases);
        sort($productIds, SORT_NUMERIC);

        $balances = $this->lockBalancesForUpdate($warehouse->id, $productIds);
        $balancesByProduct = $balances->keyBy('product_id');

        $updatedBalances = [];
        foreach ($productReleases as $productId => $qtyToRelease) {
            /** @var InventoryBalance|null $balance */
            $balance = $balancesByProduct->get($productId);
            if (! $balance) {
                continue;
            }

            $actualRelease = min($balance->reserved_quantity, $qtyToRelease);
            if ($actualRelease <= 0) {
                continue;
            }

            $balance->reserved_quantity = max(0, $balance->reserved_quantity - $actualRelease);
            $balance->available_quantity = $balance->calculateAvailableQuantity();
            $balance->version += 1;
            $balance->save();

            $updatedBalances[$productId] = $balance;
        }

        // Update item reserved quantities
        foreach ($items as $item) {
            $releaseQty = $itemQuantities !== null
                ? ($itemQuantities[$item->id] ?? 0)
                : $item->reserved_quantity;

            if ($releaseQty > 0) {
                $item->reserved_quantity = max(0, $item->reserved_quantity - $releaseQty);
                $item->save();
            }
        }

        return $updatedBalances;
    }


    /**
     * Retrieve aggregate stock status counts for operational dashboard widgets.
     *
     * @return array<string, int>
     */
    public function getSummaryCounts(?int $warehouseId = null): array
    {
        $metrics = $this->getSummaryMetrics($warehouseId);

        return [
            'all_items' => $metrics['all_items'],
            'in_stock_items' => $metrics['in_stock_items'],
            'low_stock_items' => $metrics['low_stock_items'],
            'out_of_stock_items' => $metrics['out_of_stock_items'],
        ];
    }

    /**
     * Retrieve comprehensive multi-metric KPI summary for operational inventory dashboards.
     * Computes both SKU counts and exact unit sums in a single-trip aggregate query.
     *
     * @return array<string, int>
     */
    public function getSummaryMetrics(?int $warehouseId = null): array
    {
        $query = InventoryBalance::query();

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        $balances = $query->get([
            'id',
            'product_id',
            'on_hand_quantity',
            'reserved_quantity',
            'damaged_quantity',
            'available_quantity',
            'reorder_point',
        ]);

        $totalSkus = $balances->count();
        $totalOnHand = 0;
        $totalReserved = 0;
        $totalAvailable = 0;
        $totalDamaged = 0;
        $inStock = 0;
        $lowStock = 0;
        $outOfStock = 0;

        foreach ($balances as $b) {
            $totalOnHand += (int) $b->on_hand_quantity;
            $totalReserved += (int) $b->reserved_quantity;
            $totalAvailable += (int) $b->available_quantity;
            $totalDamaged += (int) $b->damaged_quantity;

            $status = $b->getStockStatus();
            match ($status) {
                StockStatus::IN_STOCK => $inStock++,
                StockStatus::LOW_STOCK => $lowStock++,
                StockStatus::OUT_OF_STOCK => $outOfStock++,
            };
        }

        // Active commercial allocations across warehouse or system
        $allocQuery = OrderItemAllocation::query()
            ->whereIn('status', [
                AllocationStatus::ALLOCATED,
                AllocationStatus::RESERVED,
                AllocationStatus::PICKED,
                AllocationStatus::PACKED,
                AllocationStatus::DISPATCHED,
            ]);

        $totalAllocatedUnits = (int) $allocQuery->sum('allocated_quantity');

        return [
            'total_skus' => $totalSkus,
            'total_on_hand_units' => $totalOnHand,
            'total_reserved_units' => $totalReserved,
            'total_available_units' => $totalAvailable,
            'total_allocated_units' => $totalAllocatedUnits,
            'total_damaged_units' => $totalDamaged,
            'in_stock_skus' => $inStock,
            'low_stock_skus' => $lowStock,
            'out_of_stock_skus' => $outOfStock,
            'all_items' => $totalSkus,
            'in_stock_items' => $inStock,
            'low_stock_items' => $lowStock,
            'out_of_stock_items' => $outOfStock,
        ];
    }

    /**
     * Retrieve paginated, searchable, filterable inventory balance list for Admin/Warehouse workspaces.
     * Includes single-trip subqueries for commercial allocations and demand to prevent N+1 queries.
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

        $activeAllocationStatuses = [
            AllocationStatus::ALLOCATED->value,
            AllocationStatus::RESERVED->value,
            AllocationStatus::PICKED->value,
            AllocationStatus::PACKED->value,
            AllocationStatus::DISPATCHED->value,
        ];

        $openOrderStatuses = [
            OrderStatus::APPROVED->value,
            OrderStatus::PROCESSING->value,
        ];

        $query = InventoryBalance::query()
            ->select('inventory_balances.*')
            ->selectSub(
                OrderItemAllocation::query()
                    ->selectRaw('COALESCE(SUM(allocated_quantity), 0)')
                    ->whereColumn('order_item_allocations.product_id', 'inventory_balances.product_id')
                    ->whereIn('status', $activeAllocationStatuses),
                'commercial_allocated_quantity'
            )
            ->selectSub(
                OrderItem::query()
                    ->selectRaw('COALESCE(SUM(CASE WHEN (ordered_quantity - cancelled_quantity) - reserved_quantity > 0 THEN (ordered_quantity - cancelled_quantity) - reserved_quantity ELSE 0 END), 0)')
                    ->whereColumn('order_items.product_id', 'inventory_balances.product_id')
                    ->whereHas('order', fn ($q) => $q->whereIn('status', $openOrderStatuses)),
                'commercial_unallocated_demand'
            )
            ->with([
                'warehouse:id,code,name,is_active,is_default',
                'product:id,sku,name,unit,status,category_id',
                'product.category:id,code,name',
            ]);

        // 1. Warehouse filter
        if (! empty($filters['warehouse_id']) && is_numeric($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        // 2. Category filter
        if (! empty($filters['category_id']) && is_numeric($filters['category_id'])) {
            $query->whereHas('product', fn ($q) => $q->where('category_id', (int) $filters['category_id']));
        }

        // 3. Stock status filter
        if (! empty($filters['stock_status'])) {
            $query->filterByStockStatus((string) $filters['stock_status']);
        }

        // 4. Damaged stock toggle
        if (! empty($filters['has_damaged'])) {
            $query->where('damaged_quantity', '>', 0);
        }

        // 5. Active allocations toggle
        if (! empty($filters['has_allocations'])) {
            $query->whereExists(function ($sub) use ($activeAllocationStatuses) {
                $sub->select(DB::raw(1))
                    ->from('order_item_allocations')
                    ->whereColumn('order_item_allocations.product_id', 'inventory_balances.product_id')
                    ->whereIn('status', $activeAllocationStatuses);
            });
        }

        // 6. Search query across product SKU/name and bin_location
        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        // 7. Sort allow-listing
        $allowedSorts = [
            'id',
            'on_hand_quantity',
            'available_quantity',
            'reserved_quantity',
            'damaged_quantity',
            'commercial_allocated_quantity',
            'commercial_unallocated_demand',
            'reorder_point',
            'safety_stock',
            'bin_location',
            'last_counted_at',
            'created_at',
            'sku',
            'product_name',
        ];

        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts, true)
            ? $filters['sort_by']
            : 'id';

        $sortDirection = strtolower((string) ($filters['sort_direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($sortBy === 'sku') {
            $query->join('products as p_sort', 'p_sort.id', '=', 'inventory_balances.product_id')
                ->orderBy('p_sort.sku', $sortDirection);
        } elseif ($sortBy === 'product_name') {
            $query->join('products as p_sort', 'p_sort.id', '=', 'inventory_balances.product_id')
                ->orderBy('p_sort.name', $sortDirection);
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        $boundedPerPage = max(1, min(100, $perPage));

        $paginator = $query->paginate($boundedPerPage)->withQueryString();

        $paginator->through(fn (InventoryBalance $balance) => $this->formatBalance($balance));

        return $paginator;
    }

    /**
     * Retrieve complete product stock detail workspace data including physical breakdown,
     * active commercial order allocations, and net commercial coverage.
     *
     * @return array<string, mixed>
     *
     * @throws AuthorizationException
     */
    public function getDetail(InventoryBalance $balance, ?User $actor = null): array
    {
        if ($actor !== null) {
            $this->ensureActorCanView($actor);
        }

        $balance->loadMissing([
            'warehouse:id,code,name,is_active,is_default',
            'product:id,sku,name,unit,status,category_id',
            'product.category:id,code,name',
        ]);

        $activeAllocationStatuses = [
            AllocationStatus::ALLOCATED,
            AllocationStatus::RESERVED,
            AllocationStatus::PICKED,
            AllocationStatus::PACKED,
            AllocationStatus::DISPATCHED,
        ];

        // Active commercial allocations for this SKU
        $allocations = OrderItemAllocation::query()
            ->with([
                'order:id,order_number,customer_id,status,fulfillment_status',
                'order.customer:id,name,code',
                'allocatedBy:id,name',
            ])
            ->where('product_id', $balance->product_id)
            ->whereIn('status', $activeAllocationStatuses)
            ->orderBy('id', 'desc')
            ->get();

        $activeAllocationsFormatted = $allocations->map(fn (OrderItemAllocation $a) => [
            'id' => $a->id,
            'allocation_number' => $a->allocation_number,
            'order_id' => $a->order_id,
            'order_number' => $a->order?->order_number ?? "ORD-{$a->order_id}",
            'customer_name' => $a->order?->customer?->name ?? 'Unknown Customer',
            'customer_code' => $a->order?->customer?->code ?? 'N/A',
            'order_status' => $a->order?->status instanceof OrderStatus
                ? $a->order->status->value
                : (string) ($a->order?->status ?? 'APPROVED'),
            'order_status_label' => $a->order?->status instanceof OrderStatus
                ? $a->order->status->label()
                : (string) ($a->order?->status ?? 'Approved'),
            'allocated_quantity' => (int) $a->allocated_quantity,
            'reserved_quantity' => (int) $a->reserved_quantity,
            'picked_quantity' => (int) $a->picked_quantity,
            'dispatched_quantity' => (int) $a->dispatched_quantity,
            'delivered_quantity' => (int) $a->delivered_quantity,
            'status' => $a->status instanceof AllocationStatus ? $a->status->value : (string) $a->status,
            'status_label' => $a->status instanceof AllocationStatus ? $a->status->label() : (string) $a->status,
            'status_badge_variant' => $a->status instanceof AllocationStatus ? $a->status->badgeVariant() : 'outline',
            'allocated_by_name' => $a->allocatedBy?->name ?? 'System',
            'allocated_at' => $a->allocated_at?->toIso8601String() ?? $a->created_at?->toIso8601String(),
        ])->values()->toArray();

        // Total commercial allocated quantity
        $commercialAllocatedQty = (int) $allocations->sum('allocated_quantity');

        // Open unallocated demand for approved/processing orders
        $unallocatedDemand = (int) OrderItem::query()
            ->where('product_id', $balance->product_id)
            ->whereHas('order', fn ($q) => $q->whereIn('status', [OrderStatus::APPROVED, OrderStatus::PROCESSING]))
            ->get()
            ->sum(fn (OrderItem $item) => max(0, $item->fulfillableQuantity() - $item->reserved_quantity));

        // Net Commercial Coverage = Physical Available - Open Unallocated Demand
        $availableQty = (int) $balance->available_quantity;
        $netCommercialCoverage = $availableQty - $unallocatedDemand;

        // Stock proportions for visual chart (sum to on_hand or 1 if on_hand is 0)
        $onHand = max(0, (int) $balance->on_hand_quantity);
        $reserved = max(0, (int) $balance->reserved_quantity);
        $damaged = max(0, (int) $balance->damaged_quantity);

        $availablePercent = $onHand > 0 ? round(($availableQty / $onHand) * 100, 1) : 0;
        $reservedPercent = $onHand > 0 ? round(($reserved / $onHand) * 100, 1) : 0;
        $damagedPercent = $onHand > 0 ? round(($damaged / $onHand) * 100, 1) : 0;

        return [
            'balance' => $this->formatBalance($balance),
            'commercial_summary' => [
                'allocated_quantity' => $commercialAllocatedQty,
                'unallocated_demand' => $unallocatedDemand,
                'net_coverage' => $netCommercialCoverage,
                'is_surplus' => $netCommercialCoverage >= 0,
                'coverage_status' => $netCommercialCoverage >= 0 ? 'SURPLUS' : 'DEFICIT',
            ],
            'composition_proportions' => [
                'on_hand_total' => $onHand,
                'available_percent' => $availablePercent,
                'reserved_percent' => $reservedPercent,
                'damaged_percent' => $damagedPercent,
            ],
            'active_allocations' => $activeAllocationsFormatted,
        ];
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
            'category_id' => $balance->product?->category_id,
            'category_name' => $balance->product?->category?->name,
            'bin_location' => $balance->bin_location,
            'reorder_point' => (int) $balance->reorder_point,
            'safety_stock' => (int) $balance->safety_stock,
            'on_hand_quantity' => (int) $balance->on_hand_quantity,
            'reserved_quantity' => (int) $balance->reserved_quantity,
            'available_quantity' => (int) $balance->available_quantity,
            'damaged_quantity' => (int) $balance->damaged_quantity,
            'commercial_allocated_quantity' => isset($balance->commercial_allocated_quantity)
                ? (int) $balance->commercial_allocated_quantity
                : 0,
            'commercial_unallocated_demand' => isset($balance->commercial_unallocated_demand)
                ? (int) $balance->commercial_unallocated_demand
                : 0,
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
     * Retrieve all active product categories for filter dropdown.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCategories(): array
    {
        return Category::query()
            ->orderBy('name', 'asc')
            ->get(['id', 'code', 'name'])
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
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
