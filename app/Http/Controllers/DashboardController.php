<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\InventoryBalance;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Handle the incoming operational dashboard request.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        // Role-based operational redirection / view
        if ($user) {
            if ($user->role === UserRole::SALESMAN) {
                return $this->salesmanDashboard($user);
            }

            if ($user->role === UserRole::DELIVERY_PARTNER) {
                return redirect()->route('delivery.index');
            }

            if ($user->role === UserRole::WAREHOUSE_MANAGER) {
                return redirect()->route('admin.inventory.index');
            }
        }

        // Authoritative operational overview for Admin, Super Admin, and Accountant
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();

        // 1. Order Queue Metrics
        $pendingApprovalOrdersCount = Order::query()
            ->where('status', OrderStatus::SUBMITTED->value)
            ->count();

        $todayOrdersCount = Order::query()
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $todaySalesVolume = Order::query()
            ->whereIn('status', [OrderStatus::APPROVED->value, OrderStatus::COMPLETED->value])
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('grand_total');

        // 2. Customer Master
        $activeCustomersCount = Customer::query()
            ->where('status', 'ACTIVE')
            ->count();

        // 3. Inventory Alerts
        $lowStockCount = InventoryBalance::query()
            ->where('available_quantity', '<=', 10)
            ->count();

        // 4. Payment Verification Queue
        $pendingPaymentsCount = Payment::query()
            ->where('status', PaymentTransactionStatus::PENDING_VERIFICATION->value)
            ->count();

        // 5. Active In-Transit Logistics
        $activeDeliveriesCount = Delivery::query()
            ->whereIn('status', [
                DeliveryStatus::ASSIGNED->value,
                DeliveryStatus::PICKED_UP->value,
                DeliveryStatus::OUT_FOR_DELIVERY->value,
            ])
            ->count();

        // 6. Recent Orders for Operational Feed
        $recentOrders = Order::query()
            ->with(['customer:id,name,code', 'salesman:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get()
            ->map(function (Order $order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer?->name ?? 'Unknown Customer',
                    'salesman_name' => $order->salesman?->name ?? 'Direct / Admin',
                    'status' => $order->status->value,
                    'grand_total' => $order->grand_total,
                    'created_at' => $order->created_at?->toIso8601String(),
                ];
            });

        return Inertia::render('Admin/Dashboard', [
            'metrics' => [
                'pending_approval_orders' => $pendingApprovalOrdersCount,
                'today_orders_count' => $todayOrdersCount,
                'today_sales_volume' => number_format((float) $todaySalesVolume, 2, '.', ''),
                'active_customers_count' => $activeCustomersCount,
                'low_stock_items_count' => $lowStockCount,
                'pending_payments_count' => $pendingPaymentsCount,
                'active_deliveries_count' => $activeDeliveriesCount,
            ],
            'recentOrders' => $recentOrders,
        ]);
    }

    /**
     * Operational overview dashboard scoped strictly to the authenticated Salesman.
     */
    protected function salesmanDashboard(User $user): Response
    {
        // 1. Scoped Salesman Operational Metrics
        $assignedCustomersCount = Customer::query()
            ->where('salesman_id', $user->id)
            ->where('status', CustomerStatus::ACTIVE->value)
            ->count();

        $draftOrdersCount = Order::query()
            ->where('salesman_id', $user->id)
            ->where('status', OrderStatus::DRAFT->value)
            ->count();

        $inFlightOrdersCount = Order::query()
            ->where('salesman_id', $user->id)
            ->whereIn('status', [
                OrderStatus::SUBMITTED->value,
                OrderStatus::PENDING_APPROVAL->value,
                OrderStatus::APPROVED->value,
                OrderStatus::PROCESSING->value,
            ])
            ->count();

        $completedOrdersCount = Order::query()
            ->where('salesman_id', $user->id)
            ->where('status', OrderStatus::COMPLETED->value)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        // 2. Recent Scoped Orders
        $recentOrders = Order::query()
            ->forUser($user)
            ->where('status', '!=', OrderStatus::DRAFT)
            ->with(['customer:id,name,code,phone'])
            ->orderBy('submitted_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(6)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer?->name ?? 'Unknown Customer',
                'customer_code' => $order->customer?->code,
                'status' => $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status,
                'status_label' => $order->status instanceof OrderStatus ? $order->status->label() : (string) $order->status,
                'status_badge_variant' => $order->status instanceof OrderStatus ? $order->status->badgeVariant() : 'info',
                'grand_total' => (string) $order->grand_total,
                'currency' => $order->currency ?? 'USD',
                'submitted_at' => $order->submitted_at?->toIso8601String() ?? $order->created_at->toIso8601String(),
            ]);

        // 3. Assigned Customers Quick List
        $assignedCustomers = Customer::query()
            ->where('salesman_id', $user->id)
            ->where('status', CustomerStatus::ACTIVE->value)
            ->withCount(['orders' => fn ($q) => $q->where('status', '!=', OrderStatus::DRAFT)])
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'contact_name' => $c->contact_name,
                'phone' => $c->phone,
                'orders_count' => $c->orders_count,
            ]);

        // 4. Product Catalog / Categories Quick Access
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('name')
            ->limit(6)
            ->get()
            ->map(fn (Category $cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'code' => $cat->code,
                'products_count' => $cat->products_count,
            ]);

        return Inertia::render('Salesman/Dashboard', [
            'metrics' => [
                'assigned_customers_count' => $assignedCustomersCount,
                'draft_orders_count' => $draftOrdersCount,
                'in_flight_orders_count' => $inFlightOrdersCount,
                'completed_orders_count' => $completedOrdersCount,
            ],
            'recentOrders' => $recentOrders,
            'assignedCustomers' => $assignedCustomers,
            'categories' => $categories,
        ]);
    }
}
