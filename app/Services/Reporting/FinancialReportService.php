<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\Permission;
use App\Models\User;
use App\Services\Accounting\BalanceSheetService;
use App\Services\Accounting\CashReconciliationService;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\Accounting\ProfitAndLossService;
use App\Services\Accounting\TrialBalanceService;
use App\Services\Auth\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;

class FinancialReportService
{
    public function __construct(
        protected TrialBalanceService $trialBalanceService,
        protected ProfitAndLossService $profitAndLossService,
        protected BalanceSheetService $balanceSheetService,
        protected GeneralLedgerService $generalLedgerService,
        protected CashReconciliationService $cashReconciliationService,
        protected PermissionService $permissionService
    ) {}

    /**
     * Authorize that the user possesses accounting reporting permission.
     *
     * @throws AuthorizationException
     */
    public function authorizeAccess(User $user): void
    {
        $this->permissionService->authorize($user, Permission::ACCOUNTING_VIEW);
    }

    /**
     * Get Trial Balance directly from authoritative accounting service.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getTrialBalance(array $filters = [], ?User $user = null): array
    {
        if ($user) {
            $this->authorizeAccess($user);
        }

        $asOfDate = ! empty($filters['as_of_date']) ? (string) $filters['as_of_date'] : null;
        $startDate = ! empty($filters['start_date']) ? (string) $filters['start_date'] : null;

        return $this->trialBalanceService->getTrialBalance($asOfDate, $startDate);
    }

    /**
     * Get Profit & Loss directly from authoritative accounting service.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getProfitAndLoss(array $filters = [], ?User $user = null): array
    {
        if ($user) {
            $this->authorizeAccess($user);
        }

        $startDate = ! empty($filters['start_date']) ? (string) $filters['start_date'] : null;
        $endDate = ! empty($filters['end_date']) ? (string) $filters['end_date'] : null;

        return $this->profitAndLossService->getProfitAndLoss($startDate, $endDate);
    }

    /**
     * Get Balance Sheet directly from authoritative accounting service.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getBalanceSheet(array $filters = [], ?User $user = null): array
    {
        if ($user) {
            $this->authorizeAccess($user);
        }

        $asOfDate = ! empty($filters['as_of_date']) ? (string) $filters['as_of_date'] : null;

        return $this->balanceSheetService->getBalanceSheet($asOfDate);
    }

    /**
     * Get unified executive Financial Summary dashboard data.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getFinancialSummary(array $filters = [], ?User $user = null): array
    {
        if ($user) {
            $this->authorizeAccess($user);
        }

        $pnl = $this->getProfitAndLoss($filters, $user);
        $bs = $this->getBalanceSheet($filters, $user);
        $tb = $this->getTrialBalance($filters, $user);

        return [
            'profit_and_loss' => [
                'period_start' => $pnl['start_date'],
                'period_end' => $pnl['end_date'],
                'net_sales_revenue' => $pnl['net_revenue'],
                'cogs' => $pnl['cost_of_goods_sold']['total'],
                'gross_profit' => $pnl['gross_profit'],
                'operating_expenses' => $pnl['total_operating_expenses'],
                'net_income' => $pnl['net_income'],
            ],
            'balance_sheet' => [
                'as_of_date' => $bs['as_of_date'],
                'total_assets' => $bs['assets']['total_assets'],
                'total_liabilities' => $bs['liabilities']['total_liabilities'],
                'total_equity' => $bs['equity']['total_equity'],
                'is_balanced' => $bs['is_balanced'],
                'difference' => $bs['difference'],
            ],
            'trial_balance' => [
                'as_of_date' => $tb['as_of_date'],
                'total_debits' => $tb['total_debits'],
                'total_credits' => $tb['total_credits'],
                'is_balanced' => $tb['is_balanced'],
                'difference' => $tb['difference'],
            ],
        ];
    }
}
