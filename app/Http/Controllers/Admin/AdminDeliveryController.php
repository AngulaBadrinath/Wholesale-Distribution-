<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeliveryStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Delivery\AdminDeliveryIndexRequest;
use App\Http\Requests\Delivery\AssignDeliveryRequest;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Services\Delivery\DeliveryAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminDeliveryController extends Controller
{
    public function __construct(
        protected DeliveryAssignmentService $assignmentService,
    ) {}

    /**
     * Display the operational deliveries queue and workspace.
     */
    public function index(AdminDeliveryIndexRequest $request): Response
    {
        $tab = $request->validated('tab', 'all');
        $status = $request->validated('status');
        $driverId = $request->validated('driver_id');
        $customerId = $request->validated('customer_id');
        $scheduledDate = $request->validated('scheduled_date');
        $search = $request->validated('search');
        $sortBy = $request->validated('sort_by', 'scheduled_date');
        $sortDirection = $request->validated('sort_direction', 'desc');
        $perPage = (int) $request->validated('per_page', 25);

        // Calculate aggregate tab counts in single query
        $badgeQuery = DB::table('deliveries')
            ->selectRaw("
                COUNT(*) as all_count,
                COUNT(CASE WHEN status = 'PENDING_ASSIGNMENT' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'ASSIGNED' THEN 1 END) as assigned_count,
                COUNT(CASE WHEN status IN ('PICKED_UP', 'OUT_FOR_DELIVERY') THEN 1 END) as active_route_count,
                COUNT(CASE WHEN status = 'DELIVERED' THEN 1 END) as delivered_count,
                COUNT(CASE WHEN status = 'FAILED' THEN 1 END) as failed_count,
                COUNT(CASE WHEN status = 'RESCHEDULED' THEN 1 END) as rescheduled_count,
                COUNT(CASE WHEN status = 'RETURNED_TO_WAREHOUSE' THEN 1 END) as returned_count
            ");

        $badgeRow = $badgeQuery->first();

        $badgeCounts = [
            'all' => (int) ($badgeRow->all_count ?? 0),
            'pending' => (int) ($badgeRow->pending_count ?? 0),
            'assigned' => (int) ($badgeRow->assigned_count ?? 0),
            'active_route' => (int) ($badgeRow->active_route_count ?? 0),
            'delivered' => (int) ($badgeRow->delivered_count ?? 0),
            'failed' => (int) ($badgeRow->failed_count ?? 0),
            'rescheduled' => (int) ($badgeRow->rescheduled_count ?? 0),
            'returned' => (int) ($badgeRow->returned_count ?? 0),
        ];

        // Base query with eager loading
        $query = Delivery::query()
            ->with([
                'order:id,order_number,status,fulfillment_status,grand_total',
                'customer:id,name,customer_code,phone,city,state',
                'driver:id,name,email,phone',
                'items.product:id,name,sku',
            ]);

        // Tab conditions
        match ($tab) {
            'pending' => $query->where('status', DeliveryStatus::PENDING_ASSIGNMENT->value),
            'assigned' => $query->where('status', DeliveryStatus::ASSIGNED->value),
            'active_route' => $query->whereIn('status', [DeliveryStatus::PICKED_UP->value, DeliveryStatus::OUT_FOR_DELIVERY->value]),
            'delivered' => $query->where('status', DeliveryStatus::DELIVERED->value),
            'failed' => $query->where('status', DeliveryStatus::FAILED->value),
            'rescheduled' => $query->where('status', DeliveryStatus::RESCHEDULED->value),
            'returned' => $query->where('status', DeliveryStatus::RETURNED_TO_WAREHOUSE->value),
            default => null,
        };

        // Specific filter overrides
        if ($status && strtoupper($status) !== 'ALL') {
            $query->where('status', $status);
        }

        if ($driverId) {
            $query->where('driver_id', $driverId);
        }

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        if ($scheduledDate) {
            $query->whereDate('scheduled_date', $scheduledDate);
        }

        if (! empty($search)) {
            $query->search($search);
        }

        // Allowlisted sorting
        $sortColumn = match ($sortBy) {
            'delivery_number' => 'deliveries.delivery_number',
            'status' => 'deliveries.status',
            'created_at' => 'deliveries.created_at',
            default => 'deliveries.scheduled_date',
        };

        $deliveries = $query->orderBy($sortColumn, $sortDirection)
            ->orderBy('deliveries.id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // Get available active delivery drivers for assignment dropdown
        $availableDrivers = User::where('role', UserRole::DELIVERY_PARTNER)
            ->where('status', \App\Enums\AccountStatus::ACTIVE)
            ->select('id', 'name', 'email', 'phone')
            ->orderBy('name', 'asc')
            ->get();

        return Inertia::render('Admin/Deliveries/Index', [
            'deliveries' => $deliveries,
            'badgeCounts' => $badgeCounts,
            'availableDrivers' => $availableDrivers,
            'filters' => [
                'tab' => $tab,
                'status' => $status,
                'driver_id' => $driverId ? (int) $driverId : null,
                'customer_id' => $customerId ? (int) $customerId : null,
                'scheduled_date' => $scheduledDate,
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
                'per_page' => $perPage,
            ],
            'capabilities' => [
                'can_assign' => $request->user()->canPermission(Permission::DELIVERY_ASSIGN),
                'can_update' => $request->user()->canPermission(Permission::DELIVERY_UPDATE),
            ],
        ]);
    }

    /**
     * Assign or reassign an order to a delivery partner.
     */
    public function assign(AssignDeliveryRequest $request, ?Order $order = null): JsonResponse|RedirectResponse
    {
        $targetOrder = ($order && $order->exists) ? $order : Order::findOrFail($request->validated('order_id'));
        $driver = User::findOrFail($request->validated('driver_id'));

        $delivery = $this->assignmentService->assignOrder(
            $targetOrder,
            $driver,
            $request->user(),
            $request->validated()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Order {$targetOrder->order_number} successfully assigned to {$driver->name} (Delivery #{$delivery->delivery_number}).",
                'delivery' => $delivery,
            ]);
        }

        return back()->with('success', "Order {$targetOrder->order_number} successfully assigned to {$driver->name}.");
    }
}
