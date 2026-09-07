<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\JournalLine;
use Carbon\Carbon;

class ProfitAndLossService
{
    /**
     * Generate the Profit & Loss (Income Statement) report.
     *
     * @return array<string, mixed>
     */
    public function getProfitAndLoss(?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->toDateString() : Carbon::now()->startOfYear()->toDateString();
        $end = $endDate ? Carbon::parse($endDate)->toDateString() : Carbon::now()->toDateString();

        // 1. Revenue Accounts
        $revenueAccounts = Account::where('type', AccountType::REVENUE)
            ->orderBy('account_code')
            ->get();

        $operatingRevenueRows = [];
        $contraRevenueRows = [];
        $totalOperatingRevenue = '0.00';
        $totalContraRevenue = '0.00';

        foreach ($revenueAccounts as $account) {
            $balance = $this->getPeriodAccountBalance($account, $start, $end);

            if ($account->category === AccountCategory::CONTRA_REVENUE) {
                // Contra-revenue has normal debit balance (increases with debit)
                $contraBalance = $this->getPeriodAccountDebitMinusCredit($account, $start, $end);
                $totalContraRevenue = bcadd($totalContraRevenue, $contraBalance, 2);
                $contraRevenueRows[] = [
                    'account_id' => $account->id,
                    'account_code' => $account->account_code,
                    'name' => $account->name,
                    'amount' => $contraBalance,
                ];
            } else {
                // Regular revenue has normal credit balance (increases with credit)
                $revBalance = $this->getPeriodAccountCreditMinusDebit($account, $start, $end);
                $totalOperatingRevenue = bcadd($totalOperatingRevenue, $revBalance, 2);
                $operatingRevenueRows[] = [
                    'account_id' => $account->id,
                    'account_code' => $account->account_code,
                    'name' => $account->name,
                    'amount' => $revBalance,
                ];
            }
        }

        $netRevenue = bcsub($totalOperatingRevenue, $totalContraRevenue, 2);

        // 2. Cost of Goods Sold (COGS)
        $cogsAccounts = Account::where('type', AccountType::EXPENSE)
            ->where('category', AccountCategory::COST_OF_GOODS_SOLD)
            ->orderBy('account_code')
            ->get();

        $cogsRows = [];
        $totalCogs = '0.00';

        foreach ($cogsAccounts as $account) {
            $cogsBalance = $this->getPeriodAccountDebitMinusCredit($account, $start, $end);
            $totalCogs = bcadd($totalCogs, $cogsBalance, 2);
            $cogsRows[] = [
                'account_id' => $account->id,
                'account_code' => $account->account_code,
                'name' => $account->name,
                'amount' => $cogsBalance,
            ];
        }

        // 3. Gross Profit = Net Revenue - Total COGS
        $grossProfit = bcsub($netRevenue, $totalCogs, 2);

        // 4. Operating Expenses (Non-COGS)
        $expenseAccounts = Account::where('type', AccountType::EXPENSE)
            ->where('category', '<>', AccountCategory::COST_OF_GOODS_SOLD)
            ->orderBy('account_code')
            ->get();

        $expenseRows = [];
        $totalOperatingExpenses = '0.00';

        foreach ($expenseAccounts as $account) {
            $expBalance = $this->getPeriodAccountDebitMinusCredit($account, $start, $end);
            $totalOperatingExpenses = bcadd($totalOperatingExpenses, $expBalance, 2);
            $expenseRows[] = [
                'account_id' => $account->id,
                'account_code' => $account->account_code,
                'name' => $account->name,
                'category' => $account->category->value,
                'amount' => $expBalance,
            ];
        }

        // 5. Net Income / Operating Profit = Gross Profit - Total Expenses
        $netIncome = bcsub($grossProfit, $totalOperatingExpenses, 2);

        return [
            'start_date' => $start,
            'end_date' => $end,
            'operating_revenue' => [
                'rows' => $operatingRevenueRows,
                'total' => $totalOperatingRevenue,
            ],
            'contra_revenue' => [
                'rows' => $contraRevenueRows,
                'total' => $totalContraRevenue,
            ],
            'net_revenue' => $netRevenue,
            'cost_of_goods_sold' => [
                'rows' => $cogsRows,
                'total' => $totalCogs,
            ],
            'gross_profit' => $grossProfit,
            'operating_expenses' => [
                'rows' => $expenseRows,
                'total' => $totalOperatingExpenses,
            ],
            'total_operating_expenses' => $totalOperatingExpenses,
            'net_income' => $netIncome,
        ];
    }

    protected function getPeriodAccountBalance(Account $account, string $start, string $end): string
    {
        $result = JournalLine::query()
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($start, $end) {
                $q->where('status', JournalStatus::POSTED)
                    ->whereBetween('accounting_date', [$start, $end]);
            })
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $debit = $result ? (string) $result->total_debit : '0.00';
        $credit = $result ? (string) $result->total_credit : '0.00';

        return bcsub($credit, $debit, 2);
    }

    protected function getPeriodAccountCreditMinusDebit(Account $account, string $start, string $end): string
    {
        $result = JournalLine::query()
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($start, $end) {
                $q->where('status', JournalStatus::POSTED)
                    ->whereBetween('accounting_date', [$start, $end]);
            })
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $debit = $result ? (string) $result->total_debit : '0.00';
        $credit = $result ? (string) $result->total_credit : '0.00';

        return bcsub($credit, $debit, 2);
    }

    protected function getPeriodAccountDebitMinusCredit(Account $account, string $start, string $end): string
    {
        $result = JournalLine::query()
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($start, $end) {
                $q->where('status', JournalStatus::POSTED)
                    ->whereBetween('accounting_date', [$start, $end]);
            })
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $debit = $result ? (string) $result->total_debit : '0.00';
        $credit = $result ? (string) $result->total_credit : '0.00';

        return bcsub($debit, $credit, 2);
    }
}
