<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Enums\StockExceptionSeverity;
use App\Enums\StockExceptionStatus;
use App\Enums\StockExceptionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdminStockExceptionIndexRequest;
use App\Http\Requests\Inventory\DismissStockExceptionRequest;
use App\Http\Requests\Inventory\ReportStockExceptionRequest;
use App\Http\Requests\Inventory\ResolveStockExceptionRequest;
use App\Models\StockException;
use App\Services\Auth\PermissionService;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\StockExceptionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminStockExceptionController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService,
        protected StockExceptionService $exceptionService,
        protected InventoryService $inventoryService,
    ) {}

    /**
     * Display the warehouse stock exceptions queue.
     */
    public function index(AdminStockExceptionIndexRequest $request): Response
    {
        $actor = $request->user();
        $this->permissionService->authorize($actor, Permission::INVENTORY_VIEW);

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 15);
        $exceptions = $this->exceptionService->listExceptions($filters, $perPage, $actor);
        $warehouses = $this->inventoryService->getActiveWarehouses();

        return Inertia::render('Admin/Inventory/Exceptions', [
            'exceptions' => $exceptions,
            'warehouses' => $warehouses,
            'exception_types' => array_map(fn ($t) => ['value' => $t->value, 'label' => $t->label()], StockExceptionType::cases()),
            'severities' => array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], StockExceptionSeverity::cases()),
            'statuses' => array_map(fn ($st) => ['value' => $st->value, 'label' => $st->label()], StockExceptionStatus::cases()),
            'filters' => [
                'warehouse_id' => ! empty($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null,
                'status' => $filters['status'] ?? 'ALL',
                'severity' => $filters['severity'] ?? 'ALL',
                'exception_type' => $filters['exception_type'] ?? 'ALL',
                'search' => $filters['search'] ?? '',
                'per_page' => $perPage,
                'page' => (int) ($filters['page'] ?? 1),
            ],
            'can_adjust' => $this->permissionService->hasPermission($actor, Permission::INVENTORY_ADJUST),
            'can_report' => $this->permissionService->hasPermission($actor, Permission::INVENTORY_EXCEPTION_REPORT) || $this->permissionService->hasPermission($actor, Permission::INVENTORY_ADJUST),
        ]);
    }

    /**
     * Report and quarantine damaged or compromised physical stock.
     */
    public function store(ReportStockExceptionRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $exception = $this->exceptionService->reportException($request->validated(), $actor);

        return redirect()->back()->with('success', "Stock exception [{$exception->exception_number}] reported and {$exception->quantity} unit(s) quarantined.");
    }

    /**
     * Authoritatively resolve a reported stock exception.
     */
    public function resolve(StockException $stockException, ResolveStockExceptionRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $resolved = $this->exceptionService->resolveException($stockException, $actor, $request->validated('resolution_notes'));

        return redirect()->back()->with('success', "Stock exception [{$resolved->exception_number}] has been authoritatively resolved.");
    }

    /**
     * Authoritatively dismiss a reported stock exception.
     */
    public function dismiss(StockException $stockException, DismissStockExceptionRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $dismissed = $this->exceptionService->dismissException(
            $stockException,
            $actor,
            $request->validated('dismissal_reason'),
            (bool) $request->validated('revert_quarantine', false)
        );

        return redirect()->back()->with('success', "Stock exception [{$dismissed->exception_number}] has been dismissed.");
    }
}
