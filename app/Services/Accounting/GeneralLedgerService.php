<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\BalanceType;
use App\Enums\JournalStatus;
use App\Models\Account;
use App\Models\JournalLine;
use Carbon\Carbon;

class GeneralLedgerService
{
    /**
     * Retrieve the detailed General Ledger account statement.
     *
     * @return array<string, mixed>
     */
    public function getAccountLedger(Account|int $accountInput, ?string $startDate = null, ?string $endDate = null): array
    {
        $account = $accountInput instanceof Account ? $accountInput : Account::findOrFail((int) $accountInput);

        $start = $startDate ? Carbon::parse($startDate)->toDateString() : null;
        $end = $endDate ? Carbon::parse($endDate)->toDateString() : Carbon::now()->toDateString();

        $isDebitNormal = $account->normal_balance === BalanceType::DEBIT;

        // 1. Calculate Opening Balance prior to start_date
        $openingBalance = '0.00';

        if ($start) {
            $priorLines = JournalLine::query()
                ->where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($start) {
                    $q->where('status', JournalStatus::POSTED)
                        ->where('accounting_date', '<', $start);
                })
                ->selectRaw('COALESCE(SUM(debit), 0) as total_debits, COALESCE(SUM(credit), 0) as total_credits')
                ->first();

            $priorDebits = $priorLines ? (string) $priorLines->total_debits : '0.00';
            $priorCredits = $priorLines ? (string) $priorLines->total_credits : '0.00';

            $openingBalance = $isDebitNormal
                ? bcsub($priorDebits, $priorCredits, 2)
                : bcsub($priorCredits, $priorDebits, 2);
        }

        // 2. Query Period Journal Lines
        $query = JournalLine::query()
            ->with(['journalEntry'])
            ->where('journal_lines.account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($start, $end) {
                $q->where('status', JournalStatus::POSTED);
                if ($start) {
                    $q->where('accounting_date', '>=', $start);
                }
                if ($end) {
                    $q->where('accounting_date', '<=', $end);
                }
            })
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->orderBy('journal_entries.accounting_date', 'asc')
            ->orderBy('journal_entries.id', 'asc')
            ->orderBy('journal_lines.line_number', 'asc')
            ->select('journal_lines.*');

        $lines = $query->get();

        // 3. Compute Chronological Running Balance
        $runningBalance = $openingBalance;
        $totalPeriodDebits = '0.00';
        $totalPeriodCredits = '0.00';
        $transactionRows = [];

        foreach ($lines as $line) {
            $debit = number_format((float) $line->debit, 2, '.', '');
            $credit = number_format((float) $line->credit, 2, '.', '');

            $totalPeriodDebits = bcadd($totalPeriodDebits, $debit, 2);
            $totalPeriodCredits = bcadd($totalPeriodCredits, $credit, 2);

            if ($isDebitNormal) {
                $runningBalance = bcsub(bcadd($runningBalance, $debit, 2), $credit, 2);
            } else {
                $runningBalance = bcsub(bcadd($runningBalance, $credit, 2), $debit, 2);
            }

            $entry = $line->journalEntry;

            $transactionRows[] = [
                'id' => $line->id,
                'journal_entry_id' => $line->journal_entry_id,
                'journal_number' => $entry?->journal_number,
                'entry_type' => $entry?->entry_type?->value,
                'accounting_date' => $entry?->accounting_date ? Carbon::parse($entry->accounting_date)->toDateString() : null,
                'posting_date' => $entry?->posting_date ? Carbon::parse($entry->posting_date)->toDateString() : null,
                'source_type' => $entry?->source_type,
                'source_id' => $entry?->source_id,
                'source_number' => $entry?->source_number,
                'source_event' => $entry?->source_event,
                'description' => $line->description ?: $entry?->description,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => $runningBalance,
            ];
        }

        $closingBalance = $runningBalance;

        return [
            'account' => [
                'id' => $account->id,
                'account_code' => $account->account_code,
                'name' => $account->name,
                'type' => $account->type->value,
                'category' => $account->category->value,
                'normal_balance' => $account->normal_balance->value,
                'is_reconcilable' => $account->is_reconcilable,
            ],
            'start_date' => $start,
            'end_date' => $end,
            'opening_balance' => $openingBalance,
            'period_debits' => $totalPeriodDebits,
            'period_credits' => $totalPeriodCredits,
            'closing_balance' => $closingBalance,
            'transactions' => $transactionRows,
        ];
    }
}
