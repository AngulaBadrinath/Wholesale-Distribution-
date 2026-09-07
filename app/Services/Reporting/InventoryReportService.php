<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\InventoryMovementType;
use App\Enums\UserRole;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class InventoryReportService
{
    /**
     * Determine whether the user is authorized to view unit cost prices and valuation figures.
     */
    public function canViewCostPrice(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return in_array($user->role, [
            UserRole::SUPER_ADMIN,
            UserRole::ADMIN,
            UserRole::ACCOUNTANT,
            UserRole::WAREHOUSE_MANAGER,
        ], true);
    }

    /**
     * Get inventory valuation report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getInventoryValuationReport(array $filters = [], ?User $user = null, int $perPage = 50): array
    {
        $canSeeCost = $this->canViewCostPrice($user);

        $query = InventoryBalance::query()
            ->with(['product.category', 'warehouse'])
            ->join('products', 'inventory_balances.product_id', '=', 'products.id')
            ->select('inventory_balances.*');

        if (! empty($filters['warehouse_id'])) {
            $query->where('inventory_balances.warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('products.category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['stock_status'])) {
            $status = (string) $filters['stock_status'];
            if ($status === 'OUT_OF_STOCK') {
                $query->where('inventory_balances.available_quantity', '<=', 0);
            } elseif ($status === 'LOW_STOCK') {
                $query->where('inventory_balances.available_quantity', '>', 0)
                    ->where('inventory_balances.available_quantity', '<=', 10);
            } elseif ($status === 'IN_STOCK') {
                $query->where('inventory_balances.available_quantity', '>', 10);
            } elseif ($status === 'DAMAGED_ONLY') {
                $query->where('inventory_balances.damaged_quantity', '>', 0);
            }
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%");
            });
        }

        $query->orderBy('products.name', 'asc');
        $paginator = $query->paginate($perPage);

        $totalOnHandQty = 0;
        $totalReservedQty = 0;
        $totalAvailableQty = 0;
        $totalDamagedQty = 0;
        $totalValuationSum = '0.00';

        $rows = collect($paginator->items())->map(function (InventoryBalance $balance) use (
            $canSeeCost,
            &$totalOnHandQty,
            &$totalReservedQty,
            &$totalAvailableQty,
            &$totalDamagedQty,
            &$totalValuationSum
        ) {
            $onHand = (int) $balance->on_hand_quantity;
            $reserved = (int) $balance->reserved_quantity;
            $available = (int) $balance->available_quantity;
            $damaged = (int) $balance->damaged_quantity;
            $costPrice = (string) ($balance->product?->cost_price ?? '0.00');

            $totalOnHandQty += $onHand;
            $totalReservedQty += $reserved;
            $totalAvailableQty += $available;
            $totalDamagedQty += $damaged;

            $onHandValuation = '0.00';
            $availableValuation = '0.00';

            if ($canSeeCost) {
                $onHandValuation = bcmul((string) $onHand, $costPrice, 2);
                $availableValuation = bcmul((string) $available, $costPrice, 2);
                $totalValuationSum = bcadd($totalValuationSum, $onHandValuation, 2);
            }

            return [
                'inventory_balance_id' => $balance->id,
                'product_id' => $balance->product_id,
                'product_name' => $balance->product?->name,
                'product_sku' => $balance->product?->sku,
                'category_name' => $balance->product?->category?->name ?? 'Uncategorized',
                'warehouse_id' => $balance->warehouse_id,
                'warehouse_name' => $balance->warehouse?->name,
                'warehouse_code' => $balance->warehouse?->code,
                'on_hand' => $onHand,
                'reserved' => $reserved,
                'available' => $available,
                'damaged' => $damaged,
                'unit_cost_price' => $canSeeCost ? $costPrice : null,
                'on_hand_valuation' => $canSeeCost ? $onHandValuation : null,
                'available_valuation' => $canSeeCost ? $availableValuation : null,
                'status' => $balance->status ?? ($available <= 0 ? 'OUT_OF_STOCK' : ($available <= 10 ? 'LOW_STOCK' : 'IN_STOCK')),
            ];
        })->toArray();

        return [
            'data' => $rows,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'can_view_cost_price' => $canSeeCost,
            'summary' => [
                'total_on_hand_qty' => $totalOnHandQty,
                'total_reserved_qty' => $totalReservedQty,
                'total_available_qty' => $totalAvailableQty,
                'total_damaged_qty' => $totalDamagedQty,
                'total_valuation' => $canSeeCost ? $totalValuationSum : null,
            ],
            'filters' => [
                'warehouse_id' => $filters['warehouse_id'] ?? null,
                'category_id' => $filters['category_id'] ?? null,
                'stock_status' => $filters['stock_status'] ?? null,
                'search' => $filters['search'] ?? null,
            ],
        ];
    }

    /**
     * Get inventory movement ledger report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getInventoryMovementReport(array $filters = [], ?User $user = null, int $perPage = 50): array
    {
        $query = InventoryMovement::query()
            ->with(['product:id,name,sku', 'warehouse:id,name,code']);

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }

        if (! empty($filters['movement_type'])) {
            $query->where('movement_type', $filters['movement_type']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('movement_number', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
            });
        }

        $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
        $paginator = $query->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(function (InventoryMovement $movement) {
                return [
                    'id' => $movement->id,
                    'movement_number' => $movement->movement_number,
                    'created_at' => $movement->created_at?->toIso8601String(),
                    'warehouse_id' => $movement->warehouse_id,
                    'warehouse_name' => $movement->warehouse?->name,
                    'product_id' => $movement->product_id,
                    'product_name' => $movement->product?->name,
                    'product_sku' => $movement->product?->sku,
                    'movement_type' => $movement->movement_type instanceof InventoryMovementType ? $movement->movement_type->value : (string) $movement->movement_type,
                    'quantity' => (int) $movement->quantity,
                    'from_state' => $movement->from_state?->value,
                    'to_state' => $movement->to_state?->value,
                    'on_hand_before' => (int) $movement->on_hand_before,
                    'on_hand_after' => (int) $movement->on_hand_after,
                    'available_before' => (int) $movement->available_before,
                    'available_after' => (int) $movement->available_after,
                    'reference_type' => $movement->reference_type,
                    'reference_number' => $movement->reference_number,
                    'notes' => $movement->notes,
                ];
            })->toArray(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'filters' => [
                'warehouse_id' => $filters['warehouse_id'] ?? null,
                'product_id' => $filters['product_id'] ?? null,
                'movement_type' => $filters['movement_type'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'search' => $filters['search'] ?? null,
            ],
        ];
    }

    /**
     * Get low stock alerts report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getLowStockAlerts(array $filters = [], ?User $user = null): array
    {
        $canSeeCost = $this->canViewCostPrice($user);

        $query = InventoryBalance::query()
            ->with(['product.category', 'warehouse'])
            ->where('inventory_balances.available_quantity', '<=', 10)
            ->orderBy('inventory_balances.available_quantity', 'asc');

        if (! empty($filters['warehouse_id'])) {
            $query->where('inventory_balances.warehouse_id', (int) $filters['warehouse_id']);
        }

        $records = $query->limit(100)->get();

        return $records->map(function (InventoryBalance $balance) use ($canSeeCost) {
            $available = (int) $balance->available_quantity;
            $onHand = (int) $balance->on_hand_quantity;
            $costPrice = (string) ($balance->product?->cost_price ?? '0.00');

            return [
                'inventory_balance_id' => $balance->id,
                'product_id' => $balance->product_id,
                'product_name' => $balance->product?->name,
                'product_sku' => $balance->product?->sku,
                'warehouse_name' => $balance->warehouse?->name,
                'on_hand' => $onHand,
                'reserved' => (int) $balance->reserved_quantity,
                'available' => $available,
                'unit_cost_price' => $canSeeCost ? $costPrice : null,
                'status' => $available <= 0 ? 'OUT_OF_STOCK' : 'LOW_STOCK',
            ];
        })->toArray();
    }
}
