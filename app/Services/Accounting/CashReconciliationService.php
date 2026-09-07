<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\JournalEntryType;
use App\Enums\JournalStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTransactionStatus;
use App\Enums\ReconciliationStatus;
use App\Enums\SupplierPaymentMethod;
use App\Enums\SupplierPaymentStatus;
use App\Models\Account;
use App\Models\CashReconciliation;
use App\Models\CashReconciliationItem;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payment;
use App\Models\SupplierPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashReconciliationService
{
    public function __construct(
        protected CashReconciliationNumberGenerator $numberGenerator,
        protected JournalService $journalService,
        protected AccountService $accountService
    ) {}

    /**
     * Get real-time reconciliation overview between GL Cash Balance and Operational Verified Receipts.
     *
     * @return array<string, mixed>
     */
    public function getReconciliationSummary(Account|int $accountInput, ?string $asOfDate = null): array
    {
        $account = $accountInput instanceof Account ? $accountInput : Account::findOrFail((int) $accountInput);
        $asOf = $asOfDate ? Carbon::parse($asOfDate)->toDateString() : Carbon::now()->toDateString();

        // 1. General Ledger Balance as of date
        $glResult = JournalLine::query()
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($asOf) {
                $q->where(function ($sub) {
                    $sub->where('status', JournalStatus::POSTED)
                        ->orWhere('status', JournalStatus::POSTED->value);
                })->whereDate('accounting_date', '<=', $asOf);
            })
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $glDebit = $glResult ? (string) $glResult->total_debit : '0.00';
        $glCredit = $glResult ? (string) $glResult->total_credit : '0.00';
        $glBalance = bcsub($glDebit, $glCredit, 2);

        // 2. Operational Receipts matching this account type
        $methods = match ($account->account_code) {
            '1010' => [PaymentMethod::CASH, PaymentMethod::CASH->value],
            '1020' => [PaymentMethod::CHEQUE, PaymentMethod::CHEQUE->value, PaymentMethod::MONEY_ORDER, PaymentMethod::MONEY_ORDER->value],
            default => [],
        };

        $operationalReceipts = '0.00';
        if (! empty($methods)) {
            $verifiedCustomerPayments = Payment::query()
                ->whereIn('payment_method', $methods)
                ->where(function ($q) {
                    $q->where('status', PaymentTransactionStatus::VERIFIED)
                        ->orWhere('status', PaymentTransactionStatus::VERIFIED->value);
                })
                ->whereDate('payment_date', '<=', $asOf)
                ->sum('amount');

            $operationalReceipts = number_format((float) $verifiedCustomerPayments, 2, '.', '');
        }

        // 3. Operational Supplier Disbursements
        $supplierDisbursements = '0.00';
        if ($account->account_code === '1030' || $account->account_code === '1010') {
            $spMethod = $account->account_code === '1010' ? SupplierPaymentMethod::CASH : SupplierPaymentMethod::BANK_TRANSFER;
            $spSum = SupplierPayment::query()
                ->where(function ($q) use ($spMethod) {
                    $q->where('payment_method', $spMethod)
                        ->orWhere('payment_method', $spMethod->value);
                })
                ->where(function ($q) {
                    $q->where('status', SupplierPaymentStatus::COMPLETED)
                        ->orWhere('status', SupplierPaymentStatus::COMPLETED->value);
                })
                ->whereDate('payment_date', '<=', $asOf)
                ->sum('amount');
            $supplierDisbursements = number_format((float) $spSum, 2, '.', '');
        }

        $operationalNet = bcsub($operationalReceipts, $supplierDisbursements, 2);
        $difference = bcsub($glBalance, $operationalNet, 2);
        $isReconciled = bccomp($difference, '0.00', 2) === 0;

        return [
            'account' => [
                'id' => $account->id,
                'account_code' => $account->account_code,
                'name' => $account->name,
            ],
            'as_of_date' => $asOf,
            'gl_balance' => $glBalance,
            'operational_receipts' => $operationalReceipts,
            'operational_disbursements' => $supplierDisbursements,
            'operational_net' => $operationalNet,
            'difference' => $difference,
            'is_reconciled' => $isReconciled,
        ];
    }

    /**
     * Start a formal reconciliation session.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createReconciliationSession(Account|int $accountInput, array $data, ?User $actor = null): CashReconciliation
    {
        $account = $accountInput instanceof Account ? $accountInput : Account::findOrFail((int) $accountInput);

        $statementDate = ! empty($data['statement_date']) ? Carbon::parse($data['statement_date'])->toDateString() : Carbon::now()->toDateString();
        $endingBalance = isset($data['ending_balance']) ? number_format((float) $data['ending_balance'], 2, '.', '') : '0.00';
        $startingBalance = isset($data['starting_balance']) ? number_format((float) $data['starting_balance'], 2, '.', '') : '0.00';

        $summary = $this->getReconciliationSummary($account, $statementDate);
        $ledgerBalance = $summary['gl_balance'];
        $difference = bcsub($endingBalance, $ledgerBalance, 2);

        $recNumber = $this->numberGenerator->generate();

        return DB::transaction(function () use ($account, $recNumber, $statementDate, $startingBalance, $endingBalance, $ledgerBalance, $difference, $data, $actor) {
            /** @var CashReconciliation $reconciliation */
            $reconciliation = CashReconciliation::create([
                'reconciliation_number' => $recNumber,
                'account_id' => $account->id,
                'statement_date' => $statementDate,
                'starting_balance' => $startingBalance,
                'ending_balance' => $endingBalance,
                'ledger_balance' => $ledgerBalance,
                'difference' => $difference,
                'status' => bccomp($difference, '0.00', 2) === 0 ? ReconciliationStatus::RECONCILED : ReconciliationStatus::IN_PROGRESS,
                'notes' => ! empty($data['notes']) ? trim((string) $data['notes']) : null,
                'created_by' => $actor?->id,
            ]);

            // Populate uncleared items from posted journal lines
            $journalLines = JournalLine::query()
                ->where('account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($statementDate) {
                    $q->where('status', JournalStatus::POSTED)
                        ->where('accounting_date', '<=', $statementDate);
                })
                ->with('journalEntry')
                ->get();

            foreach ($journalLines as $line) {
                $isDebit = bccomp((string) $line->debit, '0.00', 2) > 0;
                $amount = $isDebit ? (string) $line->debit : (string) $line->credit;
                $type = $isDebit ? 'RECEIPT' : 'DISBURSEMENT';

                CashReconciliationItem::create([
                    'cash_reconciliation_id' => $reconciliation->id,
                    'journal_entry_id' => $line->journal_entry_id,
                    'item_date' => $line->journalEntry?->accounting_date ?? $statementDate,
                    'amount' => $amount,
                    'type' => $type,
                    'is_cleared' => true,
                    'cleared_at' => Carbon::now(),
                    'reference_number' => $line->journalEntry?->source_number ?: $line->journalEntry?->journal_number,
                    'notes' => $line->description,
                ]);
            }

            return $reconciliation->fresh(['account', 'items', 'creator']);
        });
    }

    /**
     * Finalize reconciliation session.
     *
     * @throws ValidationException
     */
    public function finalizeReconciliation(CashReconciliation $reconciliation, ?User $actor = null): CashReconciliation
    {
        if ($reconciliation->status === ReconciliationStatus::RECONCILED) {
            return $reconciliation;
        }

        $clearedDebits = CashReconciliationItem::where('cash_reconciliation_id', $reconciliation->id)
            ->where('is_cleared', true)
            ->where('type', 'RECEIPT')
            ->sum('amount');

        $clearedCredits = CashReconciliationItem::where('cash_reconciliation_id', $reconciliation->id)
            ->where('is_cleared', true)
            ->where('type', 'DISBURSEMENT')
            ->sum('amount');

        $clearedNet = bcsub((string) $clearedDebits, (string) $clearedCredits, 2);
        $difference = bcsub((string) $reconciliation->ending_balance, $clearedNet, 2);

        $reconciliation->difference = $difference;
        $reconciliation->status = bccomp($difference, '0.00', 2) === 0 ? ReconciliationStatus::RECONCILED : ReconciliationStatus::DISCREPANCY;
        $reconciliation->reconciled_by = $actor?->id;
        $reconciliation->reconciled_at = Carbon::now();
        $reconciliation->save();

        return $reconciliation->fresh(['account', 'items', 'reconciler']);
    }

    /**
     * Record a reconciliation adjustment journal (e.g. Bank Fee / Discrepancy write-off).
     *
     * @throws ValidationException
     */
    public function recordAdjustmentJournal(CashReconciliation $reconciliation, string $amount, string $reason, ?User $actor = null): JournalEntry
    {
        $cleanAmount = number_format((float) $amount, 2, '.', '');
        if (bccomp($cleanAmount, '0.00', 2) <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Adjustment amount must be greater than zero.',
            ]);
        }

        $cashAccount = $reconciliation->account;
        $feeAccount = $this->accountService->resolveAccount('5040'); // Bank Fees & Processing

        // Debit Bank Fee Expense (5040), Credit Cash Account
        $lines = [
            [
                'account_id' => $feeAccount->id,
                'debit' => $cleanAmount,
                'credit' => '0.00',
                'description' => "Reconciliation adjustment: {$reason}",
            ],
            [
                'account_id' => $cashAccount->id,
                'debit' => '0.00',
                'credit' => $cleanAmount,
                'description' => "Cash reconciliation #{$reconciliation->reconciliation_number} adjustment",
            ],
        ];

        $header = [
            'entry_type' => JournalEntryType::MANUAL,
            'source_type' => 'cash_reconciliation',
            'source_id' => $reconciliation->id,
            'source_number' => $reconciliation->reconciliation_number,
            'source_event' => 'RECONCILIATION_ADJUSTMENT',
            'posting_date' => Carbon::now()->toDateString(),
            'accounting_date' => Carbon::now()->toDateString(),
            'description' => "Reconciliation #{$reconciliation->reconciliation_number} adjustment: {$reason}",
        ];

        return $this->journalService->createAndPostJournal($header, $lines, $actor);
    }
}
