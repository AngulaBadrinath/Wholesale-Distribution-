<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\ReceivableTransaction;
use App\Services\Auth\ResourceScopeService;
use App\Services\Receivable\CustomerStatementService;
use App\Services\Receivable\ReceivableAgingService;
use App\Services\Receivable\ReceivableLedgerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminReceivableController extends Controller
{
    public function __construct(
        protected ReceivableLedgerService $ledgerService,
        protected ReceivableAgingService $agingService,
        protected CustomerStatementService $statementService,
        protected ResourceScopeService $resourceScopeService
    ) {}

    /**
     * Display accounts receivable aging overview and customer ledger summary.
     */
    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', ReceivableTransaction::class);

        $search = $request->query('search');
        $referenceDateStr = $request->query('reference_date');
        $referenceDate = $referenceDateStr ? Carbon::parse($referenceDateStr) : Carbon::now();

        $agingReport = $this->agingService->getAgingReport(
            referenceDate: $referenceDate,
            scopedUser: $request->user(),
            searchTerm: $search
        );

        if ($request->wantsJson()) {
            return response()->json($agingReport);
        }

        return Inertia::render('Admin/Receivables/Index', [
            'agingReport' => $agingReport,
            'filters' => [
                'search' => $search,
                'reference_date' => $referenceDate->toDateString(),
            ],
        ]);
    }

    /**
     * Display detailed receivable ledger and aging for a specific customer.
     */
    public function show(Request $request, int $id): Response|JsonResponse
    {
        Gate::authorize('viewAny', ReceivableTransaction::class);

        /** @var Customer|null $customer */
        $customer = Customer::with(['salesman'])->find($id);

        if (! $customer) {
            throw new NotFoundHttpException('Customer accounts receivable record not found.');
        }

        // Anti-IDOR: Check salesman / role scoping
        if (! $this->resourceScopeService->canAccessCustomerReceivables($request->user(), $customer)) {
            throw new NotFoundHttpException('Customer accounts receivable record not found.');
        }

        $referenceDateStr = $request->query('reference_date');
        $referenceDate = $referenceDateStr ? Carbon::parse($referenceDateStr) : Carbon::now();

        $aging = $this->agingService->getAgingForCustomer($customer, $referenceDate);
        $financialSummary = $this->ledgerService->getCustomerFinancialSummary($customer);
        $receivableBalance = $financialSummary['net_receivable'];
        $pendingPayments = $financialSummary['pending_payments'];
        $operationalOutstanding = $financialSummary['operational_outstanding'];
        $availableCredit = $financialSummary['available_credit'];

        $transactions = ReceivableTransaction::query()
            ->where('customer_id', $customer->id)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('posting_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate((int) $request->query('per_page', 25))
            ->withQueryString();

        $payload = [
            'customer' => $customer,
            'aging' => $aging,
            'financial_summary' => $financialSummary,
            'receivable_balance' => $receivableBalance,
            'pending_payments' => $pendingPayments,
            'operational_outstanding' => $operationalOutstanding,
            'available_credit' => $availableCredit,
            'transactions' => $transactions,
            'filters' => [
                'reference_date' => $referenceDate->toDateString(),
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Admin/Receivables/Show', $payload);
    }

    /**
     * Generate and display chronological customer statement.
     */
    public function statement(Request $request, int $id): Response|JsonResponse
    {
        Gate::authorize('viewAny', ReceivableTransaction::class);

        /** @var Customer|null $customer */
        $customer = Customer::find($id);

        if (! $customer) {
            throw new NotFoundHttpException('Customer not found for statement generation.');
        }

        // Anti-IDOR: Check salesman / role scoping
        if (! $this->resourceScopeService->canAccessCustomerReceivables($request->user(), $customer)) {
            throw new NotFoundHttpException('Customer not found for statement generation.');
        }

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $statement = $this->statementService->generateStatement($customer, $startDate, $endDate);

        if ($request->wantsJson()) {
            return response()->json($statement);
        }

        return Inertia::render('Admin/Receivables/Statement', [
            'statement' => $statement,
            'filters' => [
                'start_date' => $statement['statement_period']['start_date'],
                'end_date' => $statement['statement_period']['end_date'],
            ],
        ]);
    }
}
