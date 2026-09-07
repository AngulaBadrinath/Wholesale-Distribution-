<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\BalanceType;
use App\Enums\JournalEntryType;
use App\Enums\JournalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\CreateAccountRequest;
use App\Http\Requests\Accounting\CreateManualJournalRequest;
use App\Http\Requests\Accounting\CreateReconciliationRequest;
use App\Http\Requests\Accounting\ReconciliationAdjustmentRequest;
use App\Http\Requests\Accounting\ReverseJournalRequest;
use App\Http\Requests\Accounting\UpdateAccountRequest;
use App\Models\Account;
use App\Models\CashReconciliation;
use App\Models\JournalEntry;
use App\Services\Accounting\AccountService;
use App\Services\Accounting\BalanceSheetService;
use App\Services\Accounting\CashReconciliationService;
use App\Services\Accounting\GeneralLedgerService;
use App\Services\Accounting\JournalMappingService;
use App\Services\Accounting\JournalReversalService;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\ProfitAndLossService;
use App\Services\Accounting\TrialBalanceService;
use App\Services\Auth\ResourceScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminAccountingController extends Controller
{
    public function __construct(
        protected AccountService $accountService,
        protected JournalService $journalService,
        protected JournalMappingService $mappingService,
        protected JournalReversalService $reversalService,
        protected GeneralLedgerService $glService,
        protected TrialBalanceService $trialBalanceService,
        protected ProfitAndLossService $pnlService,
        protected BalanceSheetService $balanceSheetService,
        protected CashReconciliationService $reconciliationService,
        protected ResourceScopeService $resourceScopeService
    ) {}

    /**
     * Display the Accounting Hub / Overview.
     */
    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', JournalEntry::class);

        // Compute high-level financial summary
        $pnl = $this->pnlService->getProfitAndLoss();
        $bs = $this->balanceSheetService->getBalanceSheet();
        $tb = $this->trialBalanceService->getTrialBalance();

        $recentJournals = JournalEntry::with(['lines.account', 'creator'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $payload = [
            'summary' => [
                'total_assets' => $bs['assets']['total_assets'],
                'total_liabilities' => $bs['liabilities']['total_liabilities'],
                'total_equity' => $bs['equity']['total_equity'],
                'net_revenue' => $pnl['net_revenue'],
                'gross_profit' => $pnl['gross_profit'],
                'net_income' => $pnl['net_income'],
                'is_tb_balanced' => $tb['is_balanced'],
                'is_bs_balanced' => $bs['is_balanced'],
                'total_journals_count' => JournalEntry::where('status', JournalStatus::POSTED)->count(),
            ],
            'recent_journals' => $recentJournals,
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Admin/Accounting/Index', $payload);
    }

    /**
     * Display the Chart of Accounts list and hierarchy.
     */
    public function accounts(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', Account::class);

        $accounts = Account::with(['parent', 'children'])
            ->orderBy('account_code')
            ->get();

        $hierarchy = $this->accountService->getHierarchy();

        $payload = [
            'accounts' => $accounts,
            'hierarchy' => $hierarchy,
            'account_types' => AccountType::values(),
            'account_categories' => AccountCategory::values(),
            'balance_types' => BalanceType::values(),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Admin/Accounting/Accounts', $payload);
    }

    /**
     * Store a newly created account.
     */
    public function storeAccount(CreateAccountRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Account::class);

        $account = $this->accountService->createAccount($request->validated(), $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Account {$account->account_code} created successfully.",
                'account' => $account,
            ], 201);
        }

        return redirect()->route('admin.accounting.accounts')
            ->with('success', "Account {$account->account_code} ({$account->name}) created successfully.");
    }

    /**
     * Update an existing account.
     */
    public function updateAccount(UpdateAccountRequest $request, Account $account): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $account);

        $updated = $this->accountService->updateAccount($account, $request->validated(), $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Account {$updated->account_code} updated successfully.",
                'account' => $updated,
            ]);
        }

        return redirect()->route('admin.accounting.accounts')
            ->with('success', "Account {$updated->account_code} updated successfully.");
    }

    /**
     * Delete an account from the Chart of Accounts.
     */
    public function deleteAccount(Request $request, Account $account): RedirectResponse|JsonResponse
    {
        Gate::authorize('delete', $account);

        $this->accountService->deleteAccount($account);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Account deleted successfully.',
            ]);
        }

        return redirect()->route('admin.accounting.accounts')
            ->with('success', 'Account deleted successfully.');
    }

    /**
     * Display journal entries list with filtering.
     */
    public function journals(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', JournalEntry::class);

        $query = JournalEntry::with(['lines.account', 'creator', 'poster', 'reversalJournal', 'reversedJournal']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('journal_number', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%")
                    ->orWhere('source_number', 'ILIKE', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            if (in_array($status, JournalStatus::values(), true)) {
                $query->where('status', $status);
            }
        }

        if ($entryType = $request->query('entry_type')) {
            if (in_array($entryType, JournalEntryType::values(), true)) {
                $query->where('entry_type', $entryType);
            }
        }

        if ($startDate = $request->query('start_date')) {
            $query->where('accounting_date', '>=', $startDate);
        }

        if ($endDate = $request->query('end_date')) {
            $query->where('accounting_date', '<=', $endDate);
        }

        $journals = $query->orderBy('accounting_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate((int) $request->query('per_page', 20))
            ->withQueryString();

        $accounts = Account::where('is_active', true)->orderBy('account_code')->get();

        $payload = [
            'journals' => $journals,
            'accounts' => $accounts,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'entry_type' => $entryType,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'statuses' => JournalStatus::values(),
            'entry_types' => JournalEntryType::values(),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Admin/Accounting/Journals', $payload);
    }

    /**
     * Display a specific journal entry with lines.
     */
    public function showJournal(Request $request, int $id): Response|JsonResponse
    {
        /** @var JournalEntry|null $journal */
        $journal = JournalEntry::with(['lines.account', 'creator', 'poster', 'reversalJournal', 'reversedJournal'])
            ->find($id);

        if (! $journal) {
            throw new NotFoundHttpException('Journal entry not found.');
        }

        Gate::authorize('view', $journal);

        $payload = [
            'journal' => $journal,
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Admin/Accounting/ShowJournal', $payload);
    }

    /**
     * Store a manual journal entry.
     */
    public function storeManualJournal(CreateManualJournalRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', JournalEntry::class);

        $validated = $request->validated();
        $header = [
            'entry_type' => JournalEntryType::MANUAL,
            'description' => $validated['description'],
            'accounting_date' => $validated['accounting_date'],
            'notes' => $validated['notes'] ?? null,
            'idempotency_key' => $validated['idempotency_key'] ?? null,
        ];

        $journal = $this->journalService->createAndPostJournal($header, $validated['lines'], $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Manual journal {$journal->journal_number} posted successfully.",
                'journal' => $journal,
            ], 201);
        }

        return redirect()->route('admin.accounting.journals')
            ->with('success', "Manual journal {$journal->journal_number} posted successfully.");
    }

    /**
     * Execute a controlled reversal of a posted journal entry.
     */
    public function reverseJournal(ReverseJournalRequest $request, JournalEntry $journal): RedirectResponse|JsonResponse
    {
        Gate::authorize('reverse', $journal);

        $reversal = $this->reversalService->reverseJournal($journal, $request->validated('reason'), $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Journal {$journal->journal_number} reversed successfully by {$reversal->journal_number}.",
                'reversal_journal' => $reversal,
            ]);
        }

        return redirect()->route('admin.accounting.journals')
            ->with('success', "Journal {$journal->journal_number} reversed successfully via {$reversal->journal_number}.");
    }

    /**
     * Display General Ledger statement.
     */
    public function generalLedger(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', JournalEntry::class);

        $accounts = Account::where('is_active', true)->orderBy('account_code')->get();

        $selectedAccountId = (int) $request->query('account_id', $accounts->first()?->id ?? 0);
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $ledgerData = null;
        if ($selectedAccountId > 0) {
            $ledgerData = $this->glService->getAccountLedger($selectedAccountId, $startDate, $endDate);
        }

        $payload = [
            'accounts' => $accounts,
            'selected_account_id' => $selectedAccountId,
            'ledger' => $ledgerData,
            'filters' => [
                'account_id' => $selectedAccountId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Admin/Accounting/GeneralLedger', $payload);
    }

    /**
     * Display Trial Balance report.
     */
    public function trialBalance(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', JournalEntry::class);

        $asOfDate = $request->query('as_of_date');
        $startDate = $request->query('start_date');

        $report = $this->trialBalanceService->getTrialBalance($asOfDate, $startDate);

        $payload = [
            'report' => $report,
            'filters' => [
                'as_of_date' => $asOfDate,
                'start_date' => $startDate,
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Admin/Accounting/TrialBalance', $payload);
    }

    /**
     * Display Profit & Loss (Income Statement) report.
     */
    public function profitLoss(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', JournalEntry::class);

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $report = $this->pnlService->getProfitAndLoss($startDate, $endDate);

        $payload = [
            'report' => $report,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Admin/Accounting/ProfitLoss', $payload);
    }

    /**
     * Display Balance Sheet report.
     */
    public function balanceSheet(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', JournalEntry::class);

        $asOfDate = $request->query('as_of_date');

        $report = $this->balanceSheetService->getBalanceSheet($asOfDate);

        $payload = [
            'report' => $report,
            'filters' => [
                'as_of_date' => $asOfDate,
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Admin/Accounting/BalanceSheet', $payload);
    }

    /**
     * Display Cash & Bank Reconciliation workbench.
     */
    public function reconciliation(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', JournalEntry::class);

        $reconcilableAccounts = Account::where('is_reconcilable', true)->where('is_active', true)->orderBy('account_code')->get();

        $selectedAccountId = (int) $request->query('account_id', $reconcilableAccounts->first()?->id ?? 0);
        $asOfDate = $request->query('as_of_date');

        $summary = null;
        if ($selectedAccountId > 0) {
            $summary = $this->reconciliationService->getReconciliationSummary($selectedAccountId, $asOfDate);
        }

        $recentReconciliations = CashReconciliation::with(['account', 'creator', 'reconciler'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $payload = [
            'accounts' => $reconcilableAccounts,
            'selected_account_id' => $selectedAccountId,
            'summary' => $summary,
            'recent_reconciliations' => $recentReconciliations,
            'filters' => [
                'account_id' => $selectedAccountId,
                'as_of_date' => $asOfDate,
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Admin/Accounting/Reconciliation', $payload);
    }

    /**
     * Create a new cash reconciliation session.
     */
    public function storeReconciliation(CreateReconciliationRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', JournalEntry::class);

        $validated = $request->validated();
        $account = Account::findOrFail($validated['account_id']);

        $rec = $this->reconciliationService->createReconciliationSession($account, $validated, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Reconciliation session {$rec->reconciliation_number} started successfully.",
                'reconciliation' => $rec,
            ], 201);
        }

        return redirect()->route('admin.accounting.reconciliation', ['account_id' => $account->id])
            ->with('success', "Reconciliation session {$rec->reconciliation_number} started.");
    }

    /**
     * Finalize a cash reconciliation session.
     */
    public function finalizeReconciliation(Request $request, CashReconciliation $reconciliation): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', JournalEntry::class);

        $finalized = $this->reconciliationService->finalizeReconciliation($reconciliation, $request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Reconciliation {$finalized->reconciliation_number} finalized in status [{$finalized->status->value}].",
                'reconciliation' => $finalized,
            ]);
        }

        return redirect()->route('admin.accounting.reconciliation', ['account_id' => $reconciliation->account_id])
            ->with('success', "Reconciliation {$finalized->reconciliation_number} finalized.");
    }

    /**
     * Record a reconciliation adjustment journal.
     */
    public function storeAdjustment(ReconciliationAdjustmentRequest $request, CashReconciliation $reconciliation): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', JournalEntry::class);

        $journal = $this->reconciliationService->recordAdjustmentJournal(
            $reconciliation,
            (string) $request->validated('amount'),
            (string) $request->validated('reason'),
            $request->user()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Adjustment journal {$journal->journal_number} posted successfully.",
                'journal' => $journal,
            ], 201);
        }

        return redirect()->route('admin.accounting.reconciliation', ['account_id' => $reconciliation->account_id])
            ->with('success', "Adjustment journal {$journal->journal_number} posted successfully.");
    }

    /**
     * Sync unposted historical business events to General Ledger.
     */
    public function syncEvents(Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', JournalEntry::class);

        $result = $this->mappingService->syncUnpostedHistoricalEvents($request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Historical business events synchronized to General Ledger.',
                'synced' => $result,
            ]);
        }

        return redirect()->route('admin.accounting.index')
            ->with('success', 'Historical business events synchronized to General Ledger.');
    }
}
