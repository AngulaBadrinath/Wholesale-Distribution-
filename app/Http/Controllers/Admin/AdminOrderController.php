<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Enums\AdjustmentStatus;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\AdminOrderQueueRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminOrderController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService,
    ) {}

    /**
     * Display the operational order queues workspace for administrators.
     */
    public function index(AdminOrderQueueRequest $request): Response
    {
        $actor = $request->user();
        $this->permissionService->authorize($actor, Permission::ORDER_VIEW);

        // Salesmen are strictly restricted to the salesman portal (/salesman/orders)
        if ($actor->role === UserRole::SALESMAN) {
            throw new AuthorizationException('Salesmen must access orders via their salesman portal.');
        }

        $now = Carbon::now();
        $agingCutoff = $now->copy()->subHours(24)->toDateTimeString();

        // 1. Single SQL Aggregation Query for accurate queue badge counts
        $countRow = DB::table('orders')
            ->selectRaw("
                COUNT(CASE WHEN status IN ('SUBMITTED', 'PENDING_APPROVAL') THEN 1 END) as new_count,
                COUNT(CASE WHEN status != 'DRAFT' AND (delivery_status = 'FAILED' OR adjustment_status = 'REQUESTED' OR (status = 'SUBMITTED' AND submitted_at < ?)) THEN 1 END) as attention_count,
                COUNT(CASE WHEN status = 'APPROVED' AND fulfillment_status IN ('UNALLOCATED', 'RESERVED', 'PICKED', 'PACKED') AND status NOT IN ('COMPLETED', 'CANCELLED', 'REJECTED') THEN 1 END) as processing_count,
                COUNT(CASE WHEN status NOT IN ('DRAFT', 'COMPLETED', 'CANCELLED', 'REJECTED') AND (delivery_status IN ('ASSIGNED', 'ACCEPTED', 'PICKED_UP', 'OUT_FOR_DELIVERY') OR fulfillment_status = 'DISPATCHED') THEN 1 END) as delivery_count,
                COUNT(CASE WHEN adjustment_status IN ('REQUESTED', 'APPLIED') AND status != 'DRAFT' THEN 1 END) as adjustments_count,
                COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) as completed_count,
                COUNT(CASE WHEN status IN ('CANCELLED', 'REJECTED') THEN 1 END) as cancelled_count,
                COUNT(CASE WHEN status != 'DRAFT' THEN 1 END) as all_count
            ", [$agingCutoff])
            ->first();

        $counts = [
            'new' => (int) ($countRow->new_count ?? 0),
            'attention' => (int) ($countRow->attention_count ?? 0),
            'processing' => (int) ($countRow->processing_count ?? 0),
            'delivery' => (int) ($countRow->delivery_count ?? 0),
            'adjustments' => (int) ($countRow->adjustments_count ?? 0),
            'completed' => (int) ($countRow->completed_count ?? 0),
            'cancelled' => (int) ($countRow->cancelled_count ?? 0),
            'all' => (int) ($countRow->all_count ?? 0),
        ];

        // 2. Determine active operational queue
        $activeQueue = $request->validated('queue') ?: 'new';

        // 3. Construct base query (Drafts are strictly excluded from all admin queues)
        $query = Order::query()->where('orders.status', '!=', OrderStatus::DRAFT);

        // Apply active queue criteria
        match ($activeQueue) {
            'new' => $query->whereIn('orders.status', [OrderStatus::SUBMITTED, OrderStatus::PENDING_APPROVAL]),
            'attention' => $query->where(function (Builder $sub) use ($agingCutoff) {
                $sub->where('orders.delivery_status', DeliveryStatus::FAILED)
                    ->orWhere('orders.adjustment_status', AdjustmentStatus::REQUESTED)
                    ->orWhere(function (Builder $inner) use ($agingCutoff) {
                        $inner->where('orders.status', OrderStatus::SUBMITTED)
                            ->where('orders.submitted_at', '<', $agingCutoff);
                    });
            }),
            'processing' => $query->where('orders.status', OrderStatus::APPROVED)
                ->whereIn('orders.fulfillment_status', [
                    FulfillmentStatus::UNALLOCATED,
                    FulfillmentStatus::RESERVED,
                    FulfillmentStatus::PICKED,
                    FulfillmentStatus::PACKED,
                ]),
            'delivery' => $query->where(function (Builder $sub) {
                $sub->whereIn('orders.delivery_status', [
                    DeliveryStatus::ASSIGNED,
                    DeliveryStatus::ACCEPTED,
                    DeliveryStatus::PICKED_UP,
                    DeliveryStatus::OUT_FOR_DELIVERY,
                ])->orWhere('orders.fulfillment_status', FulfillmentStatus::DISPATCHED);
            })->whereNotIn('orders.status', [
                OrderStatus::COMPLETED,
                OrderStatus::CANCELLED,
                OrderStatus::REJECTED,
            ]),
            'adjustments' => $query->whereIn('orders.adjustment_status', [
                AdjustmentStatus::REQUESTED,
                AdjustmentStatus::APPLIED,
            ]),
            'completed' => $query->where('orders.status', OrderStatus::COMPLETED),
            'cancelled' => $query->whereIn('orders.status', [OrderStatus::CANCELLED, OrderStatus::REJECTED]),
            'all' => null, // No additional queue constraint beyond status != DRAFT
            default => $query->whereIn('orders.status', [OrderStatus::SUBMITTED, OrderStatus::PENDING_APPROVAL]),
        };

        // Multi-column search
        if ($search = $request->validated('search')) {
            $isPgsql = $query->getConnection()->getDriverName() === 'pgsql';
            $like = $isPgsql ? 'ilike' : 'like';

            $query->where(function (Builder $q) use ($search, $like) {
                $q->where('orders.order_number', $like, "%{$search}%")
                    ->orWhereHas('customer', function (Builder $custQ) use ($search, $like) {
                        $custQ->where('name', $like, "%{$search}%")
                            ->orWhere('code', $like, "%{$search}%");
                    })
                    ->orWhereHas('salesman', function (Builder $salesQ) use ($search, $like) {
                        $salesQ->where('name', $like, "%{$search}%");
                    });
            });
        }

        // Secondary filters
        if ($status = $request->validated('status')) {
            if (strtoupper($status) !== 'ALL') {
                $query->where('orders.status', $status);
            }
        }

        if ($fulfillmentStatus = $request->validated('fulfillment_status')) {
            if (strtoupper($fulfillmentStatus) !== 'ALL') {
                $query->where('orders.fulfillment_status', $fulfillmentStatus);
            }
        }

        if ($paymentStatus = $request->validated('payment_status')) {
            if (strtoupper($paymentStatus) !== 'ALL') {
                $query->where('orders.payment_status', $paymentStatus);
            }
        }

        if ($deliveryStatus = $request->validated('delivery_status')) {
            if (strtoupper($deliveryStatus) !== 'ALL') {
                $query->where('orders.delivery_status', $deliveryStatus);
            }
        }

        if ($adjustmentStatus = $request->validated('adjustment_status')) {
            if (strtoupper($adjustmentStatus) !== 'ALL') {
                $query->where('orders.adjustment_status', $adjustmentStatus);
            }
        }

        if ($salesmanId = $request->validated('salesman_id')) {
            if (strtoupper((string) $salesmanId) !== 'ALL') {
                $query->where('orders.salesman_id', (int) $salesmanId);
            }
        }

        if ($customerId = $request->validated('customer_id')) {
            if (strtoupper((string) $customerId) !== 'ALL') {
                $query->where('orders.customer_id', (int) $customerId);
            }
        }

        // Date range filters
        if ($dateFrom = $request->validated('date_from')) {
            $query->where('orders.submitted_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        if ($dateTo = $request->validated('date_to')) {
            $query->where('orders.submitted_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        // Sorting
        $sortBy = $request->validated('sort_by');
        $sortDirection = strtolower($request->validated('sort_direction', '')) === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'customer_name') {
            $query->join('customers', 'customers.id', '=', 'orders.customer_id')
                ->select('orders.*')
                ->orderBy('customers.name', $sortDirection)
                ->orderBy('orders.id', $sortDirection);
        } elseif ($sortBy === 'order_number') {
            $query->orderBy('orders.order_number', $sortDirection)->orderBy('orders.id', $sortDirection);
        } elseif ($sortBy === 'grand_total') {
            $query->orderBy('orders.grand_total', $sortDirection)->orderBy('orders.id', $sortDirection);
        } elseif ($sortBy === 'status') {
            $query->orderBy('orders.status', $sortDirection)->orderBy('orders.id', $sortDirection);
        } elseif ($sortBy === 'submitted_at') {
            $query->orderBy('orders.submitted_at', $sortDirection)->orderBy('orders.id', $sortDirection);
        } else {
            // Default queue-aware ordering
            if (in_array($activeQueue, ['new', 'attention'], true)) {
                $query->orderBy('orders.submitted_at', 'asc')->orderBy('orders.id', 'asc');
            } else {
                $query->orderBy('orders.submitted_at', 'desc')->orderBy('orders.id', 'desc');
            }
        }

        // Bounded pagination with query-string preservation
        $perPage = (int) ($request->validated('per_page') ?: 25);
        $paginated = $query->with([
            'customer:id,code,name,status,phone',
            'salesman:id,name,email',
        ])
            ->withCount('items')
            ->paginate($perPage)
            ->withQueryString();

        // Transform lightweight items
        $orders = $paginated->through(function (Order $order) use ($agingCutoff) {
            $attentionFlags = [];
            if ($order->delivery_status === DeliveryStatus::FAILED) {
                $attentionFlags[] = 'delivery_exception';
            }
            if ($order->adjustment_status === AdjustmentStatus::REQUESTED) {
                $attentionFlags[] = 'adjustment_pending';
            }
            if ($order->status === OrderStatus::SUBMITTED && $order->submitted_at && $order->submitted_at->lt($agingCutoff)) {
                $attentionFlags[] = 'aging_submission';
            }

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer' => $order->customer ? [
                    'id' => $order->customer->id,
                    'code' => $order->customer->code,
                    'name' => $order->customer->name,
                    'status' => $order->customer->status?->value ?? (string) $order->customer->status,
                    'phone' => $order->customer->phone,
                ] : null,
                'salesman' => $order->salesman ? [
                    'id' => $order->salesman->id,
                    'name' => $order->salesman->name,
                    'email' => $order->salesman->email,
                ] : null,
                'status' => $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status,
                'status_label' => $order->status instanceof OrderStatus ? $order->status->label() : (string) $order->status,
                'status_badge_variant' => $order->status instanceof OrderStatus ? $order->status->badgeVariant() : 'info',
                'fulfillment_status' => $order->fulfillment_status?->value,
                'fulfillment_status_label' => $order->fulfillment_status?->label(),
                'fulfillment_badge_variant' => $order->fulfillment_status?->badgeVariant(),
                'payment_status' => $order->payment_status?->value,
                'payment_status_label' => $order->payment_status?->label(),
                'payment_badge_variant' => $order->payment_status?->badgeVariant(),
                'delivery_status' => $order->delivery_status?->value,
                'delivery_status_label' => $order->delivery_status?->label(),
                'delivery_badge_variant' => $order->delivery_status?->badgeVariant(),
                'adjustment_status' => $order->adjustment_status?->value,
                'adjustment_status_label' => $order->adjustment_status?->label(),
                'adjustment_badge_variant' => $order->adjustment_status?->badgeVariant(),
                'item_count' => $order->items_count,
                'currency' => $order->currency ?? 'USD',
                'grand_total' => (string) $order->grand_total,
                'submitted_at' => $order->submitted_at?->toIso8601String(),
                'submitted_at_formatted' => $order->submitted_at ? $order->submitted_at->format('M d, Y H:i') : null,
                'submitted_at_relative' => $order->submitted_at ? $order->submitted_at->diffForHumans() : null,
                'created_at' => $order->created_at->toIso8601String(),
                'attention_flags' => $attentionFlags,
                'notes' => $order->notes,
            ];
        });

        $eligibleSalesmen = User::where('role', UserRole::SALESMAN)
            ->where('status', AccountStatus::ACTIVE)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'counts' => $counts,
            'filters' => [
                'queue' => $activeQueue,
                'search' => $request->validated('search', ''),
                'status' => $request->validated('status', 'ALL'),
                'fulfillment_status' => $request->validated('fulfillment_status', 'ALL'),
                'payment_status' => $request->validated('payment_status', 'ALL'),
                'delivery_status' => $request->validated('delivery_status', 'ALL'),
                'adjustment_status' => $request->validated('adjustment_status', 'ALL'),
                'salesman_id' => $request->validated('salesman_id', 'ALL'),
                'customer_id' => $request->validated('customer_id', 'ALL'),
                'date_from' => $request->validated('date_from', ''),
                'date_to' => $request->validated('date_to', ''),
                'sort_by' => $sortBy ?? '',
                'sort_direction' => $sortDirection,
                'per_page' => $perPage,
            ],
            'eligibleSalesmen' => $eligibleSalesmen,
            'orderStatuses' => array_map(fn (OrderStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ], OrderStatus::cases()),
            'fulfillmentStatuses' => array_map(fn (FulfillmentStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ], FulfillmentStatus::cases()),
            'paymentStatuses' => array_map(fn (PaymentStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ], PaymentStatus::cases()),
            'deliveryStatuses' => array_map(fn (DeliveryStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ], DeliveryStatus::cases()),
            'adjustmentStatuses' => array_map(fn (AdjustmentStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ], AdjustmentStatus::cases()),
            'can' => [
                'view' => true,
                'approve' => $this->permissionService->has($actor, Permission::ORDER_APPROVE),
                'reject' => $this->permissionService->has($actor, Permission::ORDER_REJECT),
                'cancel' => $this->permissionService->has($actor, Permission::ORDER_CANCEL),
            ],
        ]);
    }

    /**
     * Display the order confirmation and detail breakdown for administrative inspection.
     */
    public function show(Order $order, Request $request): Response
    {
        $actor = $request->user();
        $this->permissionService->authorize($actor, Permission::ORDER_VIEW);

        if ($actor->role === UserRole::SALESMAN) {
            throw new AuthorizationException('Salesmen must access orders via their salesman portal.');
        }

        $order->load(['customer', 'salesman', 'creator', 'approver', 'canceller', 'items']);

        return Inertia::render('Salesman/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'idempotency_key' => $order->idempotency_key,
                'draft_token' => $order->draft_token,
                'status' => $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status,
                'status_label' => $order->status instanceof OrderStatus ? $order->status->label() : (string) $order->status,
                'status_badge_variant' => $order->status instanceof OrderStatus ? $order->status->badgeVariant() : 'info',
                'fulfillment_status' => $order->fulfillment_status?->value,
                'fulfillment_status_label' => $order->fulfillment_status?->label(),
                'fulfillment_badge_variant' => $order->fulfillment_status?->badgeVariant(),
                'payment_status' => $order->payment_status?->value,
                'payment_status_label' => $order->payment_status?->label(),
                'payment_badge_variant' => $order->payment_status?->badgeVariant(),
                'delivery_status' => $order->delivery_status?->value,
                'delivery_status_label' => $order->delivery_status?->label(),
                'delivery_badge_variant' => $order->delivery_status?->badgeVariant(),
                'adjustment_status' => $order->adjustment_status?->value,
                'adjustment_status_label' => $order->adjustment_status?->label(),
                'adjustment_badge_variant' => $order->adjustment_status?->badgeVariant(),
                'currency' => $order->currency,
                'subtotal' => (string) $order->subtotal,
                'tax_total' => (string) $order->tax_total,
                'adjustment_total' => (string) $order->adjustment_total,
                'grand_total' => (string) $order->grand_total,
                'notes' => $order->notes,
                'submitted_at' => $order->submitted_at?->toIso8601String(),
                'approved_at' => $order->approved_at?->toIso8601String(),
                'approver' => $order->approver ? [
                    'id' => $order->approver->id,
                    'name' => $order->approver->name,
                ] : null,
                'cancelled_at' => $order->cancelled_at?->toIso8601String(),
                'canceller' => $order->canceller ? [
                    'id' => $order->canceller->id,
                    'name' => $order->canceller->name,
                ] : null,
                'cancellation_reason' => $order->cancellation_reason,
                'completed_at' => $order->completed_at?->toIso8601String(),
                'created_at' => $order->created_at->toIso8601String(),
                'customer' => [
                    'id' => $order->customer->id,
                    'code' => $order->customer->code,
                    'name' => $order->customer->name,
                    'contact_name' => $order->customer->contact_name,
                    'email' => $order->customer->email,
                    'phone' => $order->customer->phone,
                    'billing_address' => $order->customer->formatted_billing_address,
                    'shipping_address' => $order->customer->formatted_shipping_address,
                    'payment_terms' => $order->customer->payment_terms?->label(),
                ],
                'salesman' => [
                    'id' => $order->salesman->id,
                    'name' => $order->salesman->name,
                    'email' => $order->salesman->email,
                ],
                'items' => $order->items->map(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name_snapshot,
                    'sku' => $item->sku_snapshot,
                    'unit' => $item->unit_snapshot,
                    'ordered_quantity' => $item->ordered_quantity,
                    'fulfillable_quantity' => $item->fulfillableQuantity(),
                    'unit_price' => (string) $item->unit_price,
                    'is_price_overridden' => $item->is_price_overridden,
                    'tax_profile_code' => $item->tax_profile_code_snapshot,
                    'tax_profile_name' => $item->tax_profile_name_snapshot,
                    'tax_rate' => (string) $item->tax_rate_snapshot,
                    'formatted_tax_rate' => rtrim(rtrim((string) $item->tax_rate_snapshot, '0'), '.').'%',
                    'taxable_amount' => (string) $item->taxable_amount,
                    'tax_amount' => (string) $item->tax_amount,
                    'line_total' => (string) $item->line_total,
                ]),
                'timeline' => $this->buildOrderTimeline($order),
            ],
            'backUrl' => '/admin/orders',
            'backLabel' => 'Back to Order Queue',
        ]);
    }

    /**
     * Build an authentic, verifiable multi-state timeline based strictly on persisted order data.
     * Zero synthetic or fabricated transition timestamps are generated.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildOrderTimeline(Order $order): array
    {
        $timeline = [];

        // 1. Order Initialized / Created
        $timeline[] = [
            'id' => 'created',
            'title' => 'Order Created',
            'description' => 'Order was initialized and drafted in the system.',
            'timestamp' => $order->created_at->toIso8601String(),
            'actor_name' => $order->creator?->name ?? 'System',
            'status' => 'completed',
            'badge_label' => 'Created',
            'badge_variant' => 'secondary',
            'icon' => 'created',
        ];

        // 2. Order Submitted
        if ($order->submitted_at) {
            $timeline[] = [
                'id' => 'submitted',
                'title' => 'Order Submitted',
                'description' => "Order committed with idempotency token {$order->idempotency_key}.",
                'timestamp' => $order->submitted_at->toIso8601String(),
                'actor_name' => $order->salesman?->name ?? $order->creator?->name,
                'status' => 'completed',
                'badge_label' => 'Submitted',
                'badge_variant' => 'info',
                'icon' => 'submitted',
            ];
        }

        // 3. Administrative Review & Approval / Rejection
        if ($order->status === OrderStatus::CANCELLED || $order->status === OrderStatus::REJECTED) {
            $timeline[] = [
                'id' => 'cancelled',
                'title' => $order->status === OrderStatus::REJECTED ? 'Order Rejected' : 'Order Cancelled',
                'description' => $order->cancellation_reason ?: 'Order was cancelled prior to completion.',
                'timestamp' => $order->cancelled_at?->toIso8601String() ?? $order->updated_at->toIso8601String(),
                'actor_name' => $order->canceller?->name,
                'status' => 'cancelled',
                'badge_label' => $order->status->label(),
                'badge_variant' => 'destructive',
                'icon' => 'cancelled',
            ];
        } elseif ($order->approved_at) {
            $timeline[] = [
                'id' => 'approved',
                'title' => 'Order Approved',
                'description' => 'Order approved for warehouse allocation and fulfillment processing.',
                'timestamp' => $order->approved_at->toIso8601String(),
                'actor_name' => $order->approver?->name,
                'status' => 'completed',
                'badge_label' => 'Approved',
                'badge_variant' => 'primary',
                'icon' => 'approved',
            ];
        } elseif ($order->status === OrderStatus::SUBMITTED || $order->status === OrderStatus::PENDING_APPROVAL) {
            $timeline[] = [
                'id' => 'approval_pending',
                'title' => 'Administrative Review',
                'description' => 'Awaiting administrative verification and inventory allocation.',
                'timestamp' => null,
                'actor_name' => null,
                'status' => 'current',
                'badge_label' => 'Pending Review',
                'badge_variant' => 'warning',
                'icon' => 'processing',
            ];
        }

        return $timeline;
    }
}
