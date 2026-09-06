<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdminInventoryIndexRequest;
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

        $balances = $this->inventoryService->list($filters, $perPage, $actor);
        $summaryCounts = $this->inventoryService->getSummaryCounts($selectedWarehouseId);
        $warehouses = $this->inventoryService->getActiveWarehouses();

        return Inertia::render('Admin/Inventory/Index', [
            'balances' => $balances,
            'summaryCounts' => $summaryCounts,
            'warehouses' => $warehouses,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'warehouse_id' => $selectedWarehouseId,
                'stock_status' => $filters['stock_status'] ?? 'ALL',
                'sort_by' => $filters['sort_by'] ?? 'id',
                'sort_direction' => $filters['sort_direction'] ?? 'asc',
                'per_page' => $perPage,
                'page' => (int) ($filters['page'] ?? 1),
            ],
        ]);
    }
}
