<?php

namespace App\Http\Controllers\Salesman;

use App\Enums\AdjustmentStatus;
use App\Enums\CustomerStatus;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Http\Requests\Order\OrderHistoryRequest;
use App\Http\Requests\Order\SaveOrderDraftRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Auth\PermissionService;
use App\Services\Order\OrderService;
use App\Services\Product\ProductImageService;
use App\Services\Product\ProductService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesmanOrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService,
        protected ProductService $productService,
        protected PermissionService $permissionService,
        protected ProductImageService $productImageService,
    ) {}

    /**
     * Display a paginated, filterable history list of submitted orders.
     */
    public function index(OrderHistoryRequest $request): Response
    {
        $actor = $request->user();
        $this->permissionService->authorize($actor, Permission::ORDER_VIEW);

        $query = Order::query()
            ->forUser($actor)
            ->where('status', '!=', OrderStatus::DRAFT)
            ->with(['customer:id,code,name,contact_name,phone,status'])
            ->withCount('items')
            ->orderBy('submitted_at', 'desc')
            ->orderBy('id', 'desc');

        // Multi-column indexed/scoped search
        if ($search = $request->validated('search')) {
            $isPgsql = $query->getConnection()->getDriverName() === 'pgsql';
            $like = $isPgsql ? 'ilike' : 'like';

            $query->where(function (Builder $q) use ($search, $like) {
                $q->where('orders.order_number', $like, "%{$search}%")
                    ->orWhereHas('customer', function (Builder $custQ) use ($search, $like) {
                        $custQ->where('name', $like, "%{$search}%")
                            ->orWhere('code', $like, "%{$search}%");
                    });
            });
        }

        // Status filters
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

        // Date range filters
        if ($dateFrom = $request->validated('date_from')) {
            $query->where('orders.submitted_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        }

        if ($dateTo = $request->validated('date_to')) {
            $query->where('orders.submitted_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        $orders = $query->paginate(15)->withQueryString()->through(fn (Order $order) => [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'idempotency_key' => $order->idempotency_key,
            'customer' => [
                'id' => $order->customer->id,
                'code' => $order->customer->code,
                'name' => $order->customer->name,
                'contact_name' => $order->customer->contact_name,
                'phone' => $order->customer->phone,
            ],
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
            'item_count' => $order->items_count,
            'submitted_at' => $order->submitted_at?->toIso8601String(),
            'created_at' => $order->created_at->toIso8601String(),
        ]);

        return Inertia::render('Salesman/Orders/Index', [
            'orders' => $orders,
            'filters' => [
                'search' => $request->validated('search', ''),
                'status' => $request->validated('status', ''),
                'fulfillment_status' => $request->validated('fulfillment_status', ''),
                'payment_status' => $request->validated('payment_status', ''),
                'delivery_status' => $request->validated('delivery_status', ''),
                'date_from' => $request->validated('date_from', ''),
                'date_to' => $request->validated('date_to', ''),
            ],
            'statusOptions' => array_map(fn (OrderStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ], array_filter(OrderStatus::cases(), fn (OrderStatus $s) => $s !== OrderStatus::DRAFT)),
            'fulfillmentOptions' => array_map(fn (FulfillmentStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ], FulfillmentStatus::cases()),
            'paymentOptions' => array_map(fn (PaymentStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ], PaymentStatus::cases()),
            'deliveryOptions' => array_map(fn (DeliveryStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ], DeliveryStatus::cases()),
        ]);
    }

    /**
     * Display a paginated list of drafts belonging to the salesman.
     */
    public function drafts(Request $request): Response
    {
        $actor = $request->user();
        $this->permissionService->authorize($actor, Permission::ORDER_CREATE);

        $search = $request->query('search');

        $query = Order::query()
            ->where('status', OrderStatus::DRAFT)
            ->when($actor->role === UserRole::SALESMAN, fn ($q) => $q->where('salesman_id', $actor->id))
            ->with(['customer:id,code,name,contact_name,phone,status'])
            ->withCount('items')
            ->orderBy('updated_at', 'desc');

        if ($search) {
            $isPgsql = $query->getConnection()->getDriverName() === 'pgsql';
            $like = $isPgsql ? 'ilike' : 'like';

            $query->whereHas('customer', function ($custQ) use ($search, $like) {
                $custQ->where('name', $like, "%{$search}%")
                    ->orWhere('code', $like, "%{$search}%");
            });
        }

        $drafts = $query->paginate(10)->withQueryString()->through(fn (Order $draft) => [
            'id' => $draft->id,
            'draft_token' => $draft->draft_token,
            'version' => $draft->version,
            'idempotency_key' => $draft->idempotency_key,
            'customer' => [
                'id' => $draft->customer->id,
                'code' => $draft->customer->code,
                'name' => $draft->customer->name,
                'contact_name' => $draft->customer->contact_name,
                'phone' => $draft->customer->phone,
                'status' => $draft->customer->status instanceof CustomerStatus ? $draft->customer->status->value : (string) $draft->customer->status,
                'status_label' => $draft->customer->status instanceof CustomerStatus ? $draft->customer->status->label() : (string) $draft->customer->status,
            ],
            'item_count' => $draft->items_count,
            'subtotal' => (string) $draft->subtotal,
            'tax_total' => (string) $draft->tax_total,
            'grand_total' => (string) $draft->grand_total,
            'notes' => $draft->notes,
            'created_at' => $draft->created_at->toIso8601String(),
            'updated_at' => $draft->updated_at->toIso8601String(),
        ]);

        return Inertia::render('Salesman/Orders/Drafts', [
            'drafts' => $drafts,
            'filters' => [
                'search' => $search ?? '',
            ],
        ]);
    }

    /**
     * Render the Salesman Order Builder workspace.
     */
    public function create(Request $request): Response
    {
        $actor = $request->user();
        $this->permissionService->authorize($actor, Permission::ORDER_CREATE);

        // Retrieve active assigned customers for the salesman
        $customers = Customer::forUser($actor)
            ->active()
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'contact_name' => $c->contact_name,
                'email' => $c->email,
                'phone' => $c->phone,
                'credit_limit' => (float) $c->credit_limit,
                'payment_terms' => $c->payment_terms?->value,
                'payment_terms_label' => $c->payment_terms?->label(),
                'status' => $c->status instanceof CustomerStatus ? $c->status->value : (string) $c->status,
                'status_label' => $c->status instanceof CustomerStatus ? $c->status->label() : (string) $c->status,
                'billing_address' => $c->formatted_billing_address,
                'shipping_address' => $c->formatted_shipping_address,
            ]);

        // Active categories for filtering
        $categories = Category::active()
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'code']);

        // Filter and paginate active products
        $filters = [
            'search' => $request->query('search'),
            'category_id' => $request->query('category_id'),
            'status' => 'ACTIVE',
        ];

        $products = $this->productService->list($filters, 12, $actor);

        $selectedCustomerId = $request->query('customer_id') ? (int) $request->query('customer_id') : null;

        return Inertia::render('Salesman/Orders/Create', [
            'customers' => $customers,
            'selectedCustomerId' => $selectedCustomerId,
            'initialDraft' => null,
            'categories' => $categories,
            'products' => $products,
            'filters' => [
                'search' => $request->query('search', ''),
                'category_id' => $request->query('category_id', ''),
            ],
        ]);
    }

    /**
     * Resume and edit an existing draft order in the Order Builder.
     */
    public function editDraft(Order $order, Request $request): Response
    {
        $actor = $request->user();
        $this->permissionService->authorize($actor, Permission::ORDER_CREATE);

        if (! $order->isDraft()) {
            return redirect()
                ->route('salesman.orders.show', $order->id)
                ->with('error', 'This order has already been submitted and cannot be edited as a draft.');
        }

        if ($actor->role === UserRole::SALESMAN && $order->salesman_id !== $actor->id) {
            throw new AuthorizationException('You are not authorized to view or edit drafts for other salesmen.');
        }

        $order->load(['customer', 'items.product.taxProfile', 'items.product.primaryImage']);

        // Retrieve assigned customers for the salesman (including current customer if on_hold/inactive to display status)
        $customers = Customer::forUser($actor)
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'contact_name' => $c->contact_name,
                'email' => $c->email,
                'phone' => $c->phone,
                'credit_limit' => (float) $c->credit_limit,
                'payment_terms' => $c->payment_terms?->value,
                'payment_terms_label' => $c->payment_terms?->label(),
                'status' => $c->status instanceof CustomerStatus ? $c->status->value : (string) $c->status,
                'status_label' => $c->status instanceof CustomerStatus ? $c->status->label() : (string) $c->status,
                'billing_address' => $c->formatted_billing_address,
                'shipping_address' => $c->formatted_shipping_address,
            ]);

        $categories = Category::active()
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'code']);

        $filters = [
            'search' => $request->query('search'),
            'category_id' => $request->query('category_id'),
            'status' => 'ACTIVE',
        ];

        $products = $this->productService->list($filters, 12, $actor);

        $initialDraft = [
            'id' => $order->id,
            'draft_token' => $order->draft_token,
            'version' => $order->version,
            'idempotency_key' => $order->idempotency_key,
            'customer_id' => $order->customer_id,
            'notes' => $order->notes ?? '',
            'subtotal' => (string) $order->subtotal,
            'tax_total' => (string) $order->tax_total,
            'grand_total' => (string) $order->grand_total,
            'customer_status' => $order->customer->status instanceof CustomerStatus ? $order->customer->status->value : (string) $order->customer->status,
            'customer_is_active' => $order->customer->status === CustomerStatus::ACTIVE,
            'items' => $order->items->map(function (OrderItem $item) use ($actor) {
                $product = $item->product;
                $isProductActive = $product && $product->status === ProductStatus::ACTIVE;

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->ordered_quantity,
                    'unit_price' => (string) $item->unit_price,
                    'is_custom_price' => $product ? (float) $item->unit_price !== (float) $product->default_selling_price : false,
                    'product' => $product ? [
                        'id' => $product->id,
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'unit' => $product->unit,
                        'status' => $product->status instanceof ProductStatus ? $product->status->value : (string) $product->status,
                        'status_label' => $product->status instanceof ProductStatus ? $product->status->label() : (string) $product->status,
                        'is_active' => $isProductActive,
                        'default_selling_price' => (float) $product->default_selling_price,
                        'minimum_allowed_price' => (float) $product->minimum_allowed_price,
                        'mrp' => (float) $product->mrp,
                        'category_id' => $product->category_id,
                        'primary_image_url' => $product->primaryImage ? $this->productImageService->getTemporaryUrl($product->primaryImage) : null,
                        'tax_profile' => $product->taxProfile ? [
                            'id' => $product->taxProfile->id,
                            'name' => $product->taxProfile->name,
                            'code' => $product->taxProfile->code,
                            'rate' => (float) $product->taxProfile->rate,
                            'formatted_rate' => $product->taxProfile->formatted_rate,
                            'is_exempt' => $product->taxProfile->is_exempt,
                        ] : null,
                    ] : null,
                ];
            }),
        ];

        return Inertia::render('Salesman/Orders/Create', [
            'customers' => $customers,
            'selectedCustomerId' => $order->customer_id,
            'initialDraft' => $initialDraft,
            'categories' => $categories,
            'products' => $products,
            'filters' => [
                'search' => $request->query('search', ''),
                'category_id' => $request->query('category_id', ''),
            ],
        ]);
    }

    /**
     * Save an order draft (create new draft or update existing draft).
     */
    public function saveDraft(SaveOrderDraftRequest $request, ?Order $order = null): JsonResponse|RedirectResponse
    {
        $actor = $request->user();
        $dto = $request->toDTO();

        $draft = $this->orderService->saveDraft($actor, $dto, $order, $request->ip());

        if ($request->wantsJson() || $request->ajax() || $request->header('X-Inertia') === null) {
            return response()->json([
                'success' => true,
                'draft' => [
                    'id' => $draft->id,
                    'draft_token' => $draft->draft_token,
                    'version' => $draft->version,
                    'customer_id' => $draft->customer_id,
                    'subtotal' => (string) $draft->subtotal,
                    'tax_total' => (string) $draft->tax_total,
                    'grand_total' => (string) $draft->grand_total,
                ],
                'message' => 'Draft order saved successfully.',
            ]);
        }

        return redirect()
            ->route('salesman.orders.drafts.edit', $draft->id)
            ->with('success', 'Draft order saved successfully.');
    }

    /**
     * Submit an existing draft order.
     */
    public function submitDraft(Request $request, Order $order): RedirectResponse
    {
        $actor = $request->user();
        $idempotencyKey = $request->input('idempotency_key');

        $submittedOrder = $this->orderService->submitDraft($actor, $order, $idempotencyKey, $request->ip());

        return redirect()
            ->route('salesman.orders.show', $submittedOrder->id)
            ->with('success', "Order {$submittedOrder->order_number} has been submitted successfully.");
    }

    /**
     * Discard / delete an unsubmitted draft order.
     */
    public function discardDraft(Order $order, Request $request): RedirectResponse
    {
        $actor = $request->user();
        $this->orderService->discardDraft($actor, $order, $request->ip());

        return redirect()
            ->route('salesman.orders.drafts')
            ->with('success', 'Draft order has been discarded.');
    }

    /**
     * Submit and persist a new order atomically (direct submission).
     */
    public function store(CreateOrderRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $dto = $request->toDTO();

        $order = $this->orderService->createOrder($actor, $dto, $request->ip());

        return redirect()
            ->route('salesman.orders.show', $order->id)
            ->with('success', "Order {$order->order_number} has been placed successfully.");
    }

    /**
     * Display the order confirmation and detail breakdown.
     */
    public function show(Order $order, Request $request): Response
    {
        $actor = $request->user();
        $this->permissionService->authorize($actor, Permission::ORDER_VIEW);

        if ($actor->role === UserRole::SALESMAN && $order->salesman_id !== $actor->id) {
            throw new AuthorizationException('You are not authorized to view orders for other salesmen.');
        }

        $order->load([
            'customer',
            'salesman',
            'creator',
            'approver',
            'canceller',
            'activeAdjustment.requester:id,name',
            'activeAdjustment.items' => fn ($q) => $q->orderBy('id', 'asc'),
            'items' => fn ($q) => $q->orderBy('id', 'asc'),
            'items.allocations' => fn ($q) => $q->orderBy('id', 'asc'),
        ]);

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
                    'cancelled_quantity' => $item->cancelled_quantity,
                    'reserved_quantity' => $item->reserved_quantity,
                    'fulfillable_quantity' => $item->fulfillableQuantity(),
                    'allocated_quantity' => $item->allocatedQuantity(),
                    'unallocated_quantity' => $item->unallocatedQuantity(),
                    'picked_quantity' => $item->picked_quantity,
                    'dispatched_quantity' => $item->dispatched_quantity,
                    'delivered_quantity' => $item->delivered_quantity,
                    'returned_quantity' => $item->returned_quantity,
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
                'active_adjustment' => $order->hasActiveAdjustment() && $order->activeAdjustment ? [
                    'id' => $order->activeAdjustment->id,
                    'adjustment_number' => $order->activeAdjustment->adjustment_number,
                    'status' => $order->activeAdjustment->status->value,
                    'status_label' => $order->activeAdjustment->status->label(),
                    'reason_code' => $order->activeAdjustment->reason_code->value,
                    'reason_label' => $order->activeAdjustment->reason_code->label(),
                    'notes' => $order->activeAdjustment->notes,
                    'requested_by' => $order->activeAdjustment->requester?->name,
                    'requested_by_id' => $order->activeAdjustment->requested_by,
                    'requested_at' => $order->activeAdjustment->requested_at?->toIso8601String(),
                    'can_withdraw' => ($actor->id === $order->activeAdjustment->requested_by || in_array($actor->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true)),
                    'projected_subtotal_reduction' => (string) $order->activeAdjustment->projected_subtotal_reduction,
                    'projected_tax_reduction' => (string) $order->activeAdjustment->projected_tax_reduction,
                    'projected_grand_total_reduction' => (string) $order->activeAdjustment->projected_grand_total_reduction,
                    'items' => $order->activeAdjustment->items->map(fn ($ai) => [
                        'order_item_id' => $ai->order_item_id,
                        'product_name' => $ai->product_name_snapshot,
                        'sku' => $ai->sku_snapshot,
                        'requested_quantity_reduction' => $ai->requested_quantity_reduction,
                        'affected_allocation_quantity' => $ai->affected_allocation_quantity,
                        'is_case_b' => $ai->affected_allocation_quantity > 0,
                        'projected_line_total_reduction' => (string) $ai->projected_line_total_reduction,
                    ]),
                ] : null,
                'timeline' => $this->buildOrderTimeline($order),
                'can' => [
                    'request_adjustment' => $actor->can('requestAdjustment', $order),
                ],
            ],
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

        // 4. Fulfillment Status
        if ($order->status !== OrderStatus::CANCELLED && $order->status !== OrderStatus::REJECTED) {
            $isFulfillmentDelivered = in_array($order->fulfillment_status, [
                FulfillmentStatus::DELIVERED,
                FulfillmentStatus::PARTIALLY_DELIVERED,
            ], true);

            $isFulfillmentStarted = in_array($order->fulfillment_status, [
                FulfillmentStatus::RESERVED,
                FulfillmentStatus::PICKED,
                FulfillmentStatus::PACKED,
                FulfillmentStatus::DISPATCHED,
                FulfillmentStatus::DELIVERED,
                FulfillmentStatus::PARTIALLY_DELIVERED,
            ], true);

            $timeline[] = [
                'id' => 'fulfillment',
                'title' => 'Warehouse Fulfillment',
                'description' => "Current status: {$order->fulfillment_status?->label()}",
                'timestamp' => null,
                'actor_name' => null,
                'status' => $isFulfillmentDelivered ? 'completed' : ($isFulfillmentStarted ? 'current' : 'pending'),
                'badge_label' => $order->fulfillment_status?->label(),
                'badge_variant' => $order->fulfillment_status?->badgeVariant(),
                'icon' => 'fulfillment',
            ];

            // 5. Payment Status
            $isPaid = in_array($order->payment_status, [PaymentStatus::PAID, PaymentStatus::OVERPAID], true);
            $isPartialPaid = $order->payment_status === PaymentStatus::PARTIALLY_PAID;

            $timeline[] = [
                'id' => 'payment',
                'title' => 'Payment Settlement',
                'description' => "Current payment state: {$order->payment_status?->label()}",
                'timestamp' => null,
                'actor_name' => null,
                'status' => $isPaid ? 'completed' : ($isPartialPaid ? 'current' : 'pending'),
                'badge_label' => $order->payment_status?->label(),
                'badge_variant' => $order->payment_status?->badgeVariant(),
                'icon' => 'payment',
            ];

            // 6. Delivery Status
            $isDelivered = $order->delivery_status === DeliveryStatus::DELIVERED;
            $isOutForDelivery = in_array($order->delivery_status, [DeliveryStatus::PICKED_UP, DeliveryStatus::OUT_FOR_DELIVERY], true);

            $timeline[] = [
                'id' => 'delivery',
                'title' => 'Logistics & Delivery',
                'description' => "Current delivery state: {$order->delivery_status?->label()}",
                'timestamp' => null,
                'actor_name' => null,
                'status' => $isDelivered ? 'completed' : ($isOutForDelivery ? 'current' : 'pending'),
                'badge_label' => $order->delivery_status?->label(),
                'badge_variant' => $order->delivery_status?->badgeVariant(),
                'icon' => 'delivery',
            ];

            // 7. Completion
            if ($order->completed_at || $order->status === OrderStatus::COMPLETED) {
                $timeline[] = [
                    'id' => 'completed',
                    'title' => 'Order Completed',
                    'description' => 'Order successfully fulfilled, delivered, and closed.',
                    'timestamp' => $order->completed_at?->toIso8601String(),
                    'actor_name' => null,
                    'status' => 'completed',
                    'badge_label' => 'Completed',
                    'badge_variant' => 'success',
                    'icon' => 'completed',
                ];
            }
        }

        return $timeline;
    }
}
