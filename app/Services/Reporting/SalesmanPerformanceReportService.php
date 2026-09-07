<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SalesmanPerformanceReportService
{
    /**
     * Get salesman performance league table / report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSalesmanPerformanceReport(array $filters = [], ?User $user = null): array
    {
        $salesmenQuery = User::query()->where('role', UserRole::SALESMAN);

        // Scoping: If user is a salesman, restrict exclusively to self
        if ($user && $user->role === UserRole::SALESMAN) {
            $salesmenQuery->where('id', $user->id);
        } elseif (! empty($filters['salesman_id'])) {
            $salesmenQuery->where('id', (int) $filters['salesman_id']);
        }

        $salesmen = $salesmenQuery->orderBy('name', 'asc')->get();

        $dateFrom = ! empty($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : null;
        $dateTo = ! empty($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : null;

        $rows = [];
        $totalGrossSum = '0.00';
        $totalNetSum = '0.00';
        $totalOrdersSum = 0;

        foreach ($salesmen as $salesman) {
            /** @var User $salesman */
            $orderBaseQuery = Order::query()->where('salesman_id', $salesman->id);

            if ($dateFrom) {
                $orderBaseQuery->where('created_at', '>=', $dateFrom);
            }
            if ($dateTo) {
                $orderBaseQuery->where('created_at', '<=', $dateTo);
            }

            // 1. Order counts across statuses
            $submittedCount = (clone $orderBaseQuery)->where('status', '!=', OrderStatus::DRAFT->value)->count();
            $approvedCompletedCount = (clone $orderBaseQuery)->whereIn('status', [
                OrderStatus::APPROVED->value,
                OrderStatus::COMPLETED->value,
            ])->count();
            $cancelledCount = (clone $orderBaseQuery)->where('status', OrderStatus::CANCELLED->value)->count();

            // 2. Fulfillment breakdowns
            $fulfilledCount = (clone $orderBaseQuery)->where('fulfillment_status', FulfillmentStatus::DELIVERED->value)->count();
            $partiallyFulfilledCount = (clone $orderBaseQuery)->where('fulfillment_status', FulfillmentStatus::PARTIALLY_DELIVERED->value)->count();

            // 3. Financial volume (approved/completed orders)
            $financials = (clone $orderBaseQuery)
                ->whereIn('status', [OrderStatus::APPROVED->value, OrderStatus::COMPLETED->value])
                ->selectRaw('
                    COALESCE(SUM(subtotal), 0) as gross_sales,
                    COALESCE(SUM(tax_total), 0) as tax_total,
                    COALESCE(SUM(adjustment_total), 0) as discount_total,
                    COALESCE(SUM(grand_total), 0) as net_sales
                ')->first();

            $grossSales = number_format((float) ($financials?->gross_sales ?? 0), 2, '.', '');
            $taxTotal = number_format((float) ($financials?->tax_total ?? 0), 2, '.', '');
            $discountTotal = number_format((float) ($financials?->discount_total ?? 0), 2, '.', '');
            $netSales = number_format((float) ($financials?->net_sales ?? 0), 2, '.', '');

            $aov = $approvedCompletedCount > 0
                ? number_format((float) bcdiv($netSales, (string) $approvedCompletedCount, 4), 2, '.', '')
                : '0.00';

            // 4. Customer Activity
            $assignedCustomersCount = Customer::where('salesman_id', $salesman->id)->count();
            $activeOrderingCustomersCount = (clone $orderBaseQuery)
                ->whereIn('status', [OrderStatus::APPROVED->value, OrderStatus::COMPLETED->value])
                ->distinct('customer_id')
                ->count('customer_id');

            // 5. Price overrides on salesman's orders
            $orderIds = (clone $orderBaseQuery)->pluck('id');
            $priceOverrideCount = OrderItem::whereIn('order_id', $orderIds)
                ->where('is_price_overridden', true)
                ->count();

            $totalGrossSum = bcadd($totalGrossSum, $grossSales, 2);
            $totalNetSum = bcadd($totalNetSum, $netSales, 2);
            $totalOrdersSum += $approvedCompletedCount;

            $rows[] = [
                'salesman_id' => $salesman->id,
                'salesman_name' => $salesman->name,
                'salesman_email' => $salesman->email,
                'assigned_customers_count' => $assignedCustomersCount,
                'active_customers_count' => $activeOrderingCustomersCount,
                'orders_submitted' => $submittedCount,
                'orders_approved' => $approvedCompletedCount,
                'orders_cancelled' => $cancelledCount,
                'orders_fulfilled' => $fulfilledCount,
                'orders_partially_fulfilled' => $partiallyFulfilledCount,
                'gross_sales' => $grossSales,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'net_sales' => $netSales,
                'average_order_value' => $aov,
                'price_overrides_count' => $priceOverrideCount,
                'commission_status' => 'Unconfigured in V1 (Policy / Contract TBD)',
            ];
        }

        return [
            'data' => $rows,
            'summary' => [
                'total_salesmen' => count($rows),
                'total_orders' => $totalOrdersSum,
                'total_gross_sales' => $totalGrossSum,
                'total_net_sales' => $totalNetSum,
            ],
            'filters' => [
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
                'salesman_id' => $filters['salesman_id'] ?? null,
            ],
        ];
    }
}
