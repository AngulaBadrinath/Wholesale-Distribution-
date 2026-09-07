<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\CustomerStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\Auth\ResourceScopeService;
use App\Services\Receivable\ReceivableAgingService;
use App\Services\Receivable\ReceivableLedgerService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CustomerReportService
{
    public function __construct(
        protected ReceivableAgingService $agingService,
        protected ReceivableLedgerService $ledgerService,
        protected ResourceScopeService $resourceScopeService
    ) {}

    /**
     * Get Customer overview with balance, aging, and purchase metrics.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getCustomerReport(array $filters = [], ?User $user = null, int $perPage = 25): array
    {
        $query = Customer::query()->with('salesman:id,name,email');

        // 1. Scoping
        if ($user) {
            $query = $this->resourceScopeService->scopeCustomers($query, $user);
        }

        // 2. Salesman filter (admin only)
        if (! empty($filters['salesman_id'])) {
            if (! $user || $user->role !== UserRole::SALESMAN) {
                $query->where('salesman_id', (int) $filters['salesman_id']);
            }
        }

        // 3. Customer status filter
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // 4. Search term
        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $query->orderBy('name', 'asc');
        $paginator = $query->paginate($perPage);

        $refDate = ! empty($filters['as_of_date'])
            ? Carbon::parse($filters['as_of_date'])->startOfDay()
            : Carbon::now()->startOfDay();

        $rows = [];
        $totalReceivableSum = '0.00';
        $totalSpendSum = '0.00';

        foreach ($paginator->items() as $customer) {
            /** @var Customer $customer */
            $aging = $this->agingService->getAgingForCustomer($customer, $refDate);
            $totalReceivable = $aging['total_receivable'];

            // Balance state filter if set
            if (! empty($filters['balance_state'])) {
                $balanceState = (string) $filters['balance_state'];
                $hasBalance = bccomp($totalReceivable, '0.00', 2) > 0;
                $inCredit = bccomp($aging['available_credit'], '0.00', 2) > 0;

                if ($balanceState === 'with_balance' && ! $hasBalance) {
                    continue;
                }
                if ($balanceState === 'in_credit' && ! $inCredit) {
                    continue;
                }
                if ($balanceState === 'zero_balance' && ($hasBalance || $inCredit)) {
                    continue;
                }
            }

            // Purchase frequency stats for this customer
            $purchaseStats = $this->getCustomerPurchaseStats($customer->id, $filters);

            $totalReceivableSum = bcadd($totalReceivableSum, $totalReceivable, 2);
            $totalSpendSum = bcadd($totalSpendSum, $purchaseStats['total_spend'], 2);

            $rows[] = [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_code' => $customer->code ?? '',
                'status' => $customer->status instanceof CustomerStatus ? $customer->status->value : (string) $customer->status,
                'salesman_id' => $customer->salesman_id,
                'salesman_name' => $customer->salesman?->name,
                'credit_limit' => (string) ($customer->credit_limit ?? '0.00'),
                'total_receivable' => $totalReceivable,
                'available_credit' => $aging['available_credit'],
                'aging_current' => $aging['current'],
                'aging_1_30' => $aging['days_1_30'],
                'aging_31_60' => $aging['days_31_60'],
                'aging_61_90' => $aging['days_61_90'],
                'aging_91_plus' => $aging['days_91_plus'],
                'total_orders' => $purchaseStats['order_count'],
                'first_order_date' => $purchaseStats['first_order_date'],
                'last_order_date' => $purchaseStats['last_order_date'],
                'order_frequency_days' => $purchaseStats['average_frequency_days'],
                'total_spend' => $purchaseStats['total_spend'],
            ];
        }

        return [
            'data' => $rows,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'summary' => [
                'total_receivables' => $totalReceivableSum,
                'total_spend' => $totalSpendSum,
            ],
            'filters' => [
                'salesman_id' => $filters['salesman_id'] ?? null,
                'status' => $filters['status'] ?? null,
                'search' => $filters['search'] ?? null,
                'balance_state' => $filters['balance_state'] ?? null,
                'as_of_date' => $refDate->toDateString(),
            ],
        ];
    }

    /**
     * Compute purchase statistics for a single customer.
     *
     * @param  array<string, mixed>  $filters
     * @return array{
     *     order_count: int,
     *     first_order_date: ?string,
     *     last_order_date: ?string,
     *     average_frequency_days: ?float,
     *     total_spend: string
     * }
     */
    public function getCustomerPurchaseStats(int $customerId, array $filters = []): array
    {
        $orderQuery = Order::query()
            ->where('customer_id', $customerId)
            ->whereIn('status', [
                OrderStatus::APPROVED->value,
                OrderStatus::COMPLETED->value,
            ]);

        if (! empty($filters['date_from'])) {
            $orderQuery->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }
        if (! empty($filters['date_to'])) {
            $orderQuery->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        $result = $orderQuery->selectRaw('
            COUNT(id) as order_count,
            MIN(created_at) as first_order_date,
            MAX(created_at) as last_order_date,
            COALESCE(SUM(grand_total), 0) as total_spend
        ')->first();

        $count = (int) ($result?->order_count ?? 0);
        $firstDate = $result?->first_order_date ? Carbon::parse($result->first_order_date) : null;
        $lastDate = $result?->last_order_date ? Carbon::parse($result->last_order_date) : null;
        $totalSpend = number_format((float) ($result?->total_spend ?? 0), 2, '.', '');

        $avgFreqDays = null;
        if ($count > 1 && $firstDate && $lastDate) {
            $daysDiff = $firstDate->diffInDays($lastDate);
            $avgFreqDays = round($daysDiff / ($count - 1), 1);
        }

        return [
            'order_count' => $count,
            'first_order_date' => $firstDate?->toDateString(),
            'last_order_date' => $lastDate?->toDateString(),
            'average_frequency_days' => $avgFreqDays,
            'total_spend' => $totalSpend,
        ];
    }
}
