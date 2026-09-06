<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdminInventoryIndexRequest;
use App\Http\Requests\Inventory\AdminInventoryShowRequest;
use App\Models\InventoryBalance;
use App\Services\Auth\PermissionService;
use App\Services\Inventory\InventoryService;
use Inertia\Inertia;
use Inertia\Response;

class AdminInventoryController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService,
        protected InventoryService $inventoryService
    ) {}

    /**
     * Display the read-only administrative physical inventory workspace.
     */
    public function index(AdminInventoryIndexRequest $request): Response
    {
        $actor = $request->user();
        $this->permissionService->authorize($actor, Permission::INVENTORY_VIEW);

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 15);
        $selectedWarehouseId = ! empty($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;
        $selectedCategoryId = ! empty($filters['category_id']) ? (int) $filters['category_id'] : null;

        $balances = $this->inventoryService->list($filters, $perPage, $actor);
        $summaryMetrics = $this->inventoryService->getSummaryMetrics($selectedWarehouseId);
        $summaryCounts = $this->inventoryService->getSummaryCounts($selectedWarehouseId);
        $warehouses = $this->inventoryService->getActiveWarehouses();
        $categories = $this->inventoryService->getCategories();

        return Inertia::render('Admin/Inventory/Index', [
            'balances' => $balances,
            'summaryMetrics' => $summaryMetrics,
            'summaryCounts' => $summaryCounts,
            'warehouses' => $warehouses,
            'categories' => $categories,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'warehouse_id' => $selectedWarehouseId,
                'category_id' => $selectedCategoryId,
                'has_damaged' => ! empty($filters['has_damaged']),
                'has_allocations' => ! empty($filters['has_allocations']),
                'stock_status' => $filters['stock_status'] ?? 'ALL',
                'sort_by' => $filters['sort_by'] ?? 'id',
                'sort_direction' => $filters['sort_direction'] ?? 'asc',
                'per_page' => $perPage,
                'page' => (int) ($filters['page'] ?? 1),
            ],
        ]);
    }

    /**
     * Display the dedicated product stock detail workspace.
     */
    public function show(AdminInventoryShowRequest $request, InventoryBalance $inventoryBalance): Response
    {
        $actor = $request->user();
        $this->permissionService->authorize($actor, Permission::INVENTORY_VIEW);

        $detail = $this->inventoryService->getDetail($inventoryBalance, $actor);

        return Inertia::render('Admin/Inventory/Show', [
            'detail' => $detail,
            'can_adjust' => $this->permissionService->hasPermission($actor, Permission::INVENTORY_ADJUST),
            'can_report_exception' => $this->permissionService->hasPermission($actor, Permission::INVENTORY_EXCEPTION_REPORT) || $this->permissionService->hasPermission($actor, Permission::INVENTORY_ADJUST),
            'adjustment_types' => array_map(fn ($t) => ['value' => $t->value, 'label' => $t->label()], \App\Enums\InventoryAdjustmentType::cases()),
            'adjustment_reasons' => array_map(fn ($r) => ['value' => $r->value, 'label' => $r->label()], \App\Enums\InventoryAdjustmentReason::cases()),
        ]);
    }
}
