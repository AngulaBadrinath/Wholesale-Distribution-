<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Auth\ResourceScopeService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SalesReportService
{
    public function __construct(
        protected ResourceScopeService $resourceScopeService
    ) {}

    /**
     * Build base query for sales orders based on date filters and role scoping.
     * Excludes DRAFT, REJECTED, and CANCELLED orders from standard sales by default.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getBaseSalesQuery(array $filters = [], ?User $user = null): Builder
    {
        $query = Order::query();

        // 1. Role / Resource Scoping
        if ($user) {
            $query = $this->resourceScopeService->scopeOrders($query, $user);
        }

        // 2. Status filtering (default: approved or completed)
        if (! empty($filters['status'])) {
            $query->where('orders.status', $filters['status']);
        } else {
            $query->whereIn('orders.status', [
                OrderStatus::APPROVED->value,
                OrderStatus::COMPLETED->value,
            ]);
        }

        // 3. Payment status filtering
        if (! empty($filters['payment_status'])) {
            $query->where('orders.payment_status', $filters['payment_status']);
        }

        // 4. Fulfillment status filtering
        if (! empty($filters['fulfillment_status'])) {
            $query->where('orders.fulfillment_status', $filters['fulfillment_status']);
        }

        // 5. Customer filter
        if (! empty($filters['customer_id'])) {
            $query->where('orders.customer_id', (int) $filters['customer_id']);
        }

        // 6. Salesman filter (only applied if user is not a restricted salesman)
        if (! empty($filters['salesman_id'])) {
            if (! $user || $user->role !== UserRole::SALESMAN) {
                $query->where('orders.salesman_id', (int) $filters['salesman_id']);
            }
        }

        // 7. Date range filtering (inclusive)
        if (! empty($filters['date_from'])) {
            $dateFrom = Carbon::parse($filters['date_from'])->startOfDay();
            $query->where('orders.created_at', '>=', $dateFrom);
        }

        if (! empty($filters['date_to'])) {
            $dateTo = Carbon::parse($filters['date_to'])->endOfDay();
            $query->where('orders.created_at', '<=', $dateTo);
        }

        return $query;
    }

    /**
     * Compute overall sales summary KPIs.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSalesSummary(array $filters = [], ?User $user = null): array
    {
        $query = $this->getBaseSalesQuery($filters, $user);

        $result = (clone $query)->selectRaw('
            COUNT(orders.id) as total_orders,
            COALESCE(SUM(orders.subtotal), 0) as total_subtotal,
            COALESCE(SUM(orders.tax_total), 0) as total_tax,
            COALESCE(SUM(orders.adjustment_total), 0) as total_discounts,
            COALESCE(SUM(orders.grand_total), 0) as total_net_sales
        ')->first();

        $totalOrders = (int) ($result?->total_orders ?? 0);
        $totalSubtotal = number_format((float) ($result?->total_subtotal ?? 0), 2, '.', '');
        $totalTax = number_format((float) ($result?->total_tax ?? 0), 2, '.', '');
        $totalDiscounts = number_format((float) ($result?->total_discounts ?? 0), 2, '.', '');
        $totalNetSales = number_format((float) ($result?->total_net_sales ?? 0), 2, '.', '');

        $aov = $totalOrders > 0
            ? number_format((float) bcdiv($totalNetSales, (string) $totalOrders, 4), 2, '.', '')
            : '0.00';

        // Total units sold across filtered orders
        $orderIdsQuery = (clone $query)->select('orders.id');
        $totalUnits = (int) OrderItem::whereIn('order_items.order_id', $orderIdsQuery)->sum('order_items.ordered_quantity');

        return [
            'total_orders' => $totalOrders,
            'gross_sales' => $totalSubtotal,
            'tax_total' => $totalTax,
            'discount_total' => $totalDiscounts,
            'net_sales' => $totalNetSales,
            'average_order_value' => $aov,
            'total_units_sold' => $totalUnits,
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'status' => $filters['status'] ?? null,
                'payment_status' => $filters['payment_status'] ?? null,
                'customer_id' => $filters['customer_id'] ?? null,
                'salesman_id' => $filters['salesman_id'] ?? null,
            ],
        ];
    }

    /**
     * Get daily sales time-series breakdown.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getDailySalesBreakdown(array $filters = [], ?User $user = null): array
    {
        $query = $this->getBaseSalesQuery($filters, $user);

        $records = $query->selectRaw('
            DATE(orders.created_at) as sale_date,
            COUNT(orders.id) as order_count,
            COALESCE(SUM(orders.subtotal), 0) as gross_sales,
            COALESCE(SUM(orders.tax_total), 0) as tax_total,
            COALESCE(SUM(orders.adjustment_total), 0) as discount_total,
            COALESCE(SUM(orders.grand_total), 0) as net_sales
        ')
        ->groupBy(DB::raw('DATE(orders.created_at)'))
        ->orderBy('sale_date', 'asc')
        ->get();

        return $records->map(function ($row) {
            $orderCount = (int) $row->order_count;
            $netSales = number_format((float) $row->net_sales, 2, '.', '');
            $aov = $orderCount > 0
                ? number_format((float) bcdiv($netSales, (string) $orderCount, 4), 2, '.', '')
                : '0.00';

            return [
                'date' => (string) $row->sale_date,
                'order_count' => $orderCount,
                'gross_sales' => number_format((float) $row->gross_sales, 2, '.', ''),
                'tax_total' => number_format((float) $row->tax_total, 2, '.', ''),
                'discount_total' => number_format((float) $row->discount_total, 2, '.', ''),
                'net_sales' => $netSales,
                'average_order_value' => $aov,
            ];
        })->toArray();
    }

    /**
     * Get sales aggregated by customer.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getSalesByCustomer(array $filters = [], ?User $user = null, int $limit = 50): array
    {
        $query = $this->getBaseSalesQuery($filters, $user);

        $records = $query->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->selectRaw('
                customers.id as customer_id,
                customers.name as customer_name,
                customers.code as customer_code,
                COUNT(orders.id) as order_count,
                COALESCE(SUM(orders.subtotal), 0) as gross_sales,
                COALESCE(SUM(orders.tax_total), 0) as tax_total,
                COALESCE(SUM(orders.adjustment_total), 0) as discount_total,
                COALESCE(SUM(orders.grand_total), 0) as net_sales
            ')
            ->groupBy('customers.id', 'customers.name', 'customers.code')
            ->orderBy('net_sales', 'desc')
            ->limit($limit)
            ->get();

        return $records->map(function ($row) {
            $orderCount = (int) $row->order_count;
            $netSales = number_format((float) $row->net_sales, 2, '.', '');
            $aov = $orderCount > 0
                ? number_format((float) bcdiv($netSales, (string) $orderCount, 4), 2, '.', '')
                : '0.00';

            return [
                'customer_id' => (int) $row->customer_id,
                'customer_name' => (string) $row->customer_name,
                'customer_code' => (string) ($row->customer_code ?? ''),
                'order_count' => $orderCount,
                'gross_sales' => number_format((float) $row->gross_sales, 2, '.', ''),
                'tax_total' => number_format((float) $row->tax_total, 2, '.', ''),
                'discount_total' => number_format((float) $row->discount_total, 2, '.', ''),
                'net_sales' => $netSales,
                'average_order_value' => $aov,
            ];
        })->toArray();
    }

    /**
     * Get sales aggregated by product.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function getSalesByProduct(array $filters = [], ?User $user = null, int $limit = 50): array
    {
        $baseOrderQuery = $this->getBaseSalesQuery($filters, $user);

        $orderIds = $baseOrderQuery->pluck('orders.id');

        if ($orderIds->isEmpty()) {
            return [];
        }

        $records = OrderItem::query()
            ->whereIn('order_items.order_id', $orderIds)
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->selectRaw('
                products.id as product_id,
                products.name as product_name,
                products.sku as product_sku,
                COUNT(DISTINCT order_items.order_id) as order_count,
                COALESCE(SUM(order_items.ordered_quantity), 0) as total_quantity_sold,
                COALESCE(SUM(order_items.taxable_amount), 0) as gross_sales,
                COALESCE(SUM(order_items.tax_amount), 0) as tax_total,
                COALESCE(SUM(order_items.line_total), 0) as net_sales
            ')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderBy('net_sales', 'desc')
            ->limit($limit)
            ->get();

        return $records->map(function ($row) {
            $qty = (int) $row->total_quantity_sold;
            $netSales = number_format((float) $row->net_sales, 2, '.', '');
            $avgPrice = $qty > 0
                ? number_format((float) bcdiv($netSales, (string) $qty, 4), 2, '.', '')
                : '0.00';

            return [
                'product_id' => (int) $row->product_id,
                'product_name' => (string) $row->product_name,
                'product_sku' => (string) $row->product_sku,
                'order_count' => (int) $row->order_count,
                'total_quantity_sold' => $qty,
                'gross_sales' => number_format((float) $row->gross_sales, 2, '.', ''),
                'tax_total' => number_format((float) $row->tax_total, 2, '.', ''),
                'net_sales' => $netSales,
                'average_unit_realization' => $avgPrice,
            ];
        })->toArray();
    }

    /**
     * Get paginated contributing order records for drill-down.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getContributingOrders(array $filters = [], ?User $user = null, int $perPage = 25): array
    {
        $query = $this->getBaseSalesQuery($filters, $user)
            ->with(['customer:id,name,code', 'salesman:id,name,email'])
            ->orderBy('orders.created_at', 'desc');

        $paginator = $query->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(function (Order $order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_id' => $order->customer_id,
                    'customer_name' => $order->customer?->name,
                    'customer_code' => $order->customer?->code,
                    'salesman_id' => $order->salesman_id,
                    'salesman_name' => $order->salesman?->name,
                    'status' => $order->status->value,
                    'fulfillment_status' => $order->fulfillment_status->value,
                    'payment_status' => $order->payment_status->value,
                    'subtotal' => (string) $order->subtotal,
                    'tax_total' => (string) $order->tax_total,
                    'adjustment_total' => (string) $order->adjustment_total,
                    'grand_total' => (string) $order->grand_total,
                    'created_at' => $order->created_at?->toIso8601String(),
                ];
            })->toArray(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
