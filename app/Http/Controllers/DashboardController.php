<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\InventoryBalance;
use App\Models\Order;
use App\Models\Payment;
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

        // Role-based operational redirection
        if ($user) {
            if ($user->role === UserRole::SALESMAN) {
                return redirect()->route('salesman.orders.index');
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
}
