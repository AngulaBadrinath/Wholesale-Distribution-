<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\JournalLine;
use Carbon\Carbon;

class TrialBalanceService
{
    /**
     * Generate the Trial Balance report.
     *
     * @return array<string, mixed>
     */
    public function getTrialBalance(?string $asOfDate = null, ?string $startDate = null): array
    {
        $asOf = $asOfDate ? Carbon::parse($asOfDate)->toDateString() : Carbon::now()->toDateString();
        $start = $startDate ? Carbon::parse($startDate)->toDateString() : null;

        $accounts = Account::orderBy('account_code')->get();

        $accountRows = [];
        $totalDebits = '0.00';
        $totalCredits = '0.00';
        $totalNetDebits = '0.00';
        $totalNetCredits = '0.00';

        foreach ($accounts as $account) {
            $query = JournalLine::query()
                ->where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($start, $asOf) {
                    $q->where('status', JournalStatus::POSTED)
                        ->where('accounting_date', '<=', $asOf);
                    if ($start) {
                        $q->where('accounting_date', '>=', $start);
                    }
                });

            $result = $query->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')->first();

            $debit = $result ? number_format((float) $result->total_debit, 2, '.', '') : '0.00';
            $credit = $result ? number_format((float) $result->total_credit, 2, '.', '') : '0.00';

            // Only include accounts that have transaction activity or are active
            $hasActivity = bccomp($debit, '0.00', 2) > 0 || bccomp($credit, '0.00', 2) > 0;

            if ($hasActivity || $account->is_active) {
                $diff = bcsub($debit, $credit, 2);
                $netDebit = bccomp($diff, '0.00', 2) > 0 ? $diff : '0.00';
                $netCredit = bccomp($diff, '0.00', 2) < 0 ? number_format(abs((float) $diff), 2, '.', '') : '0.00';

                $totalDebits = bcadd($totalDebits, $debit, 2);
                $totalCredits = bcadd($totalCredits, $credit, 2);
                $totalNetDebits = bcadd($totalNetDebits, $netDebit, 2);
                $totalNetCredits = bcadd($totalNetCredits, $netCredit, 2);

                $accountRows[] = [
                    'account_id' => $account->id,
                    'account_code' => $account->account_code,
                    'name' => $account->name,
                    'type' => $account->type->value,
                    'category' => $account->category->value,
                    'normal_balance' => $account->normal_balance->value,
                    'debit' => $debit,
                    'credit' => $credit,
                    'net_debit' => $netDebit,
                    'net_credit' => $netCredit,
                ];
            }
        }

        $isBalanced = bccomp($totalDebits, $totalCredits, 2) === 0;
        $difference = number_format(abs((float) bcsub($totalDebits, $totalCredits, 2)), 2, '.', '');

        return [
            'as_of_date' => $asOf,
            'start_date' => $start,
            'is_balanced' => $isBalanced,
            'difference' => $difference,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'total_net_debits' => $totalNetDebits,
            'total_net_credits' => $totalNetCredits,
            'accounts' => $accountRows,
        ];
    }
}
