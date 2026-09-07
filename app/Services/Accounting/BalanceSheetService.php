<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\JournalLine;
use Carbon\Carbon;

class BalanceSheetService
{
    /**
     * Generate the Balance Sheet report as of a specified date.
     *
     * @return array<string, mixed>
     */
    public function getBalanceSheet(?string $asOfDate = null): array
    {
        $asOf = $asOfDate ? Carbon::parse($asOfDate)->toDateString() : Carbon::now()->toDateString();

        // 1. Assets (Normal Debit Balance)
        $assetAccounts = Account::where('type', AccountType::ASSET)->orderBy('account_code')->get();
        $currentAssetRows = [];
        $nonCurrentAssetRows = [];
        $totalCurrentAssets = '0.00';
        $totalNonCurrentAssets = '0.00';

        foreach ($assetAccounts as $account) {
            $balance = $this->getCumulativeDebitMinusCredit($account, $asOf);
            $item = [
                'account_id' => $account->id,
                'account_code' => $account->account_code,
                'name' => $account->name,
                'category' => $account->category->value,
                'amount' => $balance,
            ];

            if ($account->category === AccountCategory::NON_CURRENT_ASSET) {
                $nonCurrentAssetRows[] = $item;
                $totalNonCurrentAssets = bcadd($totalNonCurrentAssets, $balance, 2);
            } else {
                $currentAssetRows[] = $item;
                $totalCurrentAssets = bcadd($totalCurrentAssets, $balance, 2);
            }
        }

        $totalAssets = bcadd($totalCurrentAssets, $totalNonCurrentAssets, 2);

        // 2. Liabilities (Normal Credit Balance)
        $liabilityAccounts = Account::where('type', AccountType::LIABILITY)->orderBy('account_code')->get();
        $currentLiabilityRows = [];
        $longTermLiabilityRows = [];
        $totalCurrentLiabilities = '0.00';
        $totalLongTermLiabilities = '0.00';

        foreach ($liabilityAccounts as $account) {
            $balance = $this->getCumulativeCreditMinusDebit($account, $asOf);
            $item = [
                'account_id' => $account->id,
                'account_code' => $account->account_code,
                'name' => $account->name,
                'category' => $account->category->value,
                'amount' => $balance,
            ];

            if ($account->category === AccountCategory::LONG_TERM_LIABILITY) {
                $longTermLiabilityRows[] = $item;
                $totalLongTermLiabilities = bcadd($totalLongTermLiabilities, $balance, 2);
            } else {
                $currentLiabilityRows[] = $item;
                $totalCurrentLiabilities = bcadd($totalCurrentLiabilities, $balance, 2);
            }
        }

        $totalLiabilities = bcadd($totalCurrentLiabilities, $totalLongTermLiabilities, 2);

        // 3. Equity (Normal Credit Balance) + Retained Net Earnings
        $equityAccounts = Account::where('type', AccountType::EQUITY)->orderBy('account_code')->get();
        $equityRows = [];
        $totalBaseEquity = '0.00';

        foreach ($equityAccounts as $account) {
            $balance = $this->getCumulativeCreditMinusDebit($account, $asOf);
            $equityRows[] = [
                'account_id' => $account->id,
                'account_code' => $account->account_code,
                'name' => $account->name,
                'category' => $account->category->value,
                'amount' => $balance,
            ];
            $totalBaseEquity = bcadd($totalBaseEquity, $balance, 2);
        }

        // Compute Net Earnings to date (Total Revenue Credits-Debits minus Total Expense Debits-Credits)
        $cumulativeNetEarnings = $this->getCumulativeNetEarnings($asOf);

        $totalEquity = bcadd($totalBaseEquity, $cumulativeNetEarnings, 2);
        $totalLiabilitiesAndEquity = bcadd($totalLiabilities, $totalEquity, 2);

        $isBalanced = bccomp($totalAssets, $totalLiabilitiesAndEquity, 2) === 0;
        $difference = bcsub($totalAssets, $totalLiabilitiesAndEquity, 2);

        return [
            'as_of_date' => $asOf,
            'assets' => [
                'current_assets' => $currentAssetRows,
                'total_current_assets' => $totalCurrentAssets,
                'non_current_assets' => $nonCurrentAssetRows,
                'total_non_current_assets' => $totalNonCurrentAssets,
                'total_assets' => $totalAssets,
            ],
            'liabilities' => [
                'current_liabilities' => $currentLiabilityRows,
                'total_current_liabilities' => $totalCurrentLiabilities,
                'long_term_liabilities' => $longTermLiabilityRows,
                'total_long_term_liabilities' => $totalLongTermLiabilities,
                'total_liabilities' => $totalLiabilities,
            ],
            'equity' => [
                'accounts' => $equityRows,
                'total_base_equity' => $totalBaseEquity,
                'current_period_earnings' => $cumulativeNetEarnings,
                'total_equity' => $totalEquity,
            ],
            'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
            'is_balanced' => $isBalanced,
            'difference' => $difference,
        ];
    }

    protected function getCumulativeDebitMinusCredit(Account $account, string $asOf): string
    {
        $result = JournalLine::query()
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($asOf) {
                $q->where('status', JournalStatus::POSTED)
                    ->where('accounting_date', '<=', $asOf);
            })
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $debit = $result ? (string) $result->total_debit : '0.00';
        $credit = $result ? (string) $result->total_credit : '0.00';

        return bcsub($debit, $credit, 2);
    }

    protected function getCumulativeCreditMinusDebit(Account $account, string $asOf): string
    {
        $result = JournalLine::query()
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($asOf) {
                $q->where('status', JournalStatus::POSTED)
                    ->where('accounting_date', '<=', $asOf);
            })
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $debit = $result ? (string) $result->total_debit : '0.00';
        $credit = $result ? (string) $result->total_credit : '0.00';

        return bcsub($credit, $debit, 2);
    }

    protected function getCumulativeNetEarnings(string $asOf): string
    {
        // Revenue lines: Credits - Debits
        $revResult = JournalLine::query()
            ->whereHas('account', fn ($q) => $q->where('type', AccountType::REVENUE))
            ->whereHas('journalEntry', function ($q) use ($asOf) {
                $q->where('status', JournalStatus::POSTED)
                    ->where('accounting_date', '<=', $asOf);
            })
            ->selectRaw('COALESCE(SUM(credit), 0) as total_credit, COALESCE(SUM(debit), 0) as total_debit')
            ->first();

        $revCredit = $revResult ? (string) $revResult->total_credit : '0.00';
        $revDebit = $revResult ? (string) $revResult->total_debit : '0.00';
        $netRevenue = bcsub($revCredit, $revDebit, 2);

        // Expense lines: Debits - Credits
        $expResult = JournalLine::query()
            ->whereHas('account', fn ($q) => $q->where('type', AccountType::EXPENSE))
            ->whereHas('journalEntry', function ($q) use ($asOf) {
                $q->where('status', JournalStatus::POSTED)
                    ->where('accounting_date', '<=', $asOf);
            })
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $expDebit = $expResult ? (string) $expResult->total_debit : '0.00';
        $expCredit = $expResult ? (string) $expResult->total_credit : '0.00';
        $netExpenses = bcsub($expDebit, $expCredit, 2);

        return bcsub($netRevenue, $netExpenses, 2);
    }
}
