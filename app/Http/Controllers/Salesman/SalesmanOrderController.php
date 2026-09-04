<?php

namespace App\Http\Controllers\Salesman;

use App\Enums\CustomerStatus;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Auth\PermissionService;
use App\Services\Order\OrderService;
use App\Services\Product\ProductService;
use Illuminate\Auth\Access\AuthorizationException;
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
    ) {}

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
            'categories' => $categories,
            'products' => $products,
            'filters' => [
                'search' => $request->query('search', ''),
                'category_id' => $request->query('category_id', ''),
            ],
        ]);
    }

    /**
     * Submit and persist a new order atomically.
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

        $order->load(['customer', 'salesman', 'creator', 'items']);

        return Inertia::render('Salesman/Orders/Show', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'idempotency_key' => $order->idempotency_key,
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
                'currency' => $order->currency,
                'subtotal' => (string) $order->subtotal,
                'tax_total' => (string) $order->tax_total,
                'adjustment_total' => (string) $order->adjustment_total,
                'grand_total' => (string) $order->grand_total,
                'notes' => $order->notes,
                'submitted_at' => $order->submitted_at?->toIso8601String(),
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
            ],
        ]);
    }
}
