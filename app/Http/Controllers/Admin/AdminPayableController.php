<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SupplierBillStatus;
use App\Enums\SupplierPaymentMethod;
use App\Enums\SupplierStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateSupplierBillRequest;
use App\Http\Requests\Admin\CreateSupplierPaymentRequest;
use App\Http\Requests\Admin\CreateSupplierRequest;
use App\Http\Requests\Admin\ReverseSupplierPaymentRequest;
use App\Models\PayableTransaction;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Services\Auth\ResourceScopeService;
use App\Services\Payable\PayableLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminPayableController extends Controller
{
    public function __construct(
        protected PayableLedgerService $ledgerService,
        protected ResourceScopeService $resourceScopeService
    ) {}

    /**
     * Display accounts payable overview and supplier list.
     */
    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', PayableTransaction::class);

        $search = $request->query('search');
        $status = $request->query('status');

        $query = Supplier::query();

        if ($search) {
            $query->search($search);
        }

        if ($status && in_array($status, SupplierStatus::values(), true)) {
            $query->where('status', $status);
        }

        // Subquery for outstanding balance and active bills count
        $suppliers = $query->withCount([
            'bills as active_bills_count' => function ($bq) {
                $bq->whereIn('status', [SupplierBillStatus::POSTED->value, SupplierBillStatus::PARTIALLY_PAID->value]);
            },
        ])
            ->orderBy('name', 'asc')
            ->paginate((int) $request->query('per_page', 20))
            ->withQueryString();

        // Calculate outstanding balance for each supplier in the page
        $suppliers->getCollection()->transform(function (Supplier $supplier) {
            $supplier->outstanding_balance = $this->ledgerService->getSupplierOutstandingBalance($supplier);

            return $supplier;
        });

        // Summary cards aggregation
        $totalApOutstanding = DB::table('payable_transactions')
            ->selectRaw('COALESCE(SUM(credit_amount), 0) - COALESCE(SUM(debit_amount), 0) AS balance')
            ->value('balance');

        $summary = [
            'total_ap_outstanding' => number_format((float) ($totalApOutstanding ?? 0.00), 2, '.', ''),
            'total_suppliers' => Supplier::count(),
            'active_bills_count' => SupplierBill::whereIn('status', [
                SupplierBillStatus::POSTED->value,
                SupplierBillStatus::PARTIALLY_PAID->value,
            ])->count(),
            'total_paid_bills' => SupplierBill::where('status', SupplierBillStatus::PAID->value)->count(),
        ];

        $payload = [
            'suppliers' => $suppliers,
            'summary' => $summary,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statuses' => SupplierStatus::values(),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Admin/Payables/Index', $payload);
    }

    /**
     * Display detailed payable sub-ledger, bills, and payments for a specific supplier.
     */
    public function show(Request $request, int $id): Response|JsonResponse
    {
        Gate::authorize('viewAny', PayableTransaction::class);

        /** @var Supplier|null $supplier */
        $supplier = Supplier::find($id);

        if (! $supplier) {
            throw new NotFoundHttpException('Supplier accounts payable record not found.');
        }

        // Anti-IDOR check
        if (! $this->resourceScopeService->canAccessSupplierPayables($request->user())) {
            throw new NotFoundHttpException('Supplier accounts payable record not found.');
        }

        $outstandingBalance = $this->ledgerService->getSupplierOutstandingBalance($supplier);

        // Load bills with pagination
        $bills = SupplierBill::where('supplier_id', $supplier->id)
            ->orderBy('bill_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate((int) $request->query('bills_per_page', 10), ['*'], 'bills_page')
            ->withQueryString();

        // Load payments with pagination
        $payments = SupplierPayment::where('supplier_id', $supplier->id)
            ->with(['supplierBill', 'creator', 'reverser'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate((int) $request->query('payments_per_page', 10), ['*'], 'payments_page')
            ->withQueryString();

        // Load transactions (sub-ledger) with pagination
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $txQuery = PayableTransaction::where('supplier_id', $supplier->id)
            ->with(['supplierBill', 'supplierPayment', 'creator'])
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc');

        if ($startDate) {
            $txQuery->where('transaction_date', '>=', $startDate);
        }

        if ($endDate) {
            $txQuery->where('transaction_date', '<=', $endDate);
        }

        $transactions = $txQuery->paginate((int) $request->query('txns_per_page', 25), ['*'], 'txns_page')
            ->withQueryString();

        // Open bills for payment modal
        $openBills = SupplierBill::where('supplier_id', $supplier->id)
            ->whereIn('status', [SupplierBillStatus::POSTED->value, SupplierBillStatus::PARTIALLY_PAID->value])
            ->orderBy('due_date', 'asc')
            ->get(['id', 'bill_number', 'supplier_invoice_number', 'due_date', 'total_amount', 'amount_paid', 'amount_due']);

        $payload = [
            'supplier' => $supplier,
            'outstanding_balance' => $outstandingBalance,
            'bills' => $bills,
            'payments' => $payments,
            'transactions' => $transactions,
            'open_bills' => $openBills,
            'payment_methods' => SupplierPaymentMethod::values(),
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Admin/Payables/Show', $payload);
    }

    /**
     * Store a new supplier master record.
     */
    public function storeSupplier(CreateSupplierRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Supplier::class);

        $supplier = $this->ledgerService->createSupplier($request->validated(), $request->user());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Supplier created successfully.', 'supplier' => $supplier], 201);
        }

        return redirect()->route('admin.payables.show', $supplier->id)
            ->with('success', "Supplier {$supplier->name} ({$supplier->supplier_code}) created successfully.");
    }

    /**
     * Store a new supplier bill and optionally post it to AP.
     */
    public function storeBill(CreateSupplierBillRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', SupplierBill::class);

        $supplier = Supplier::findOrFail((int) $request->validated('supplier_id'));

        $bill = $this->ledgerService->createBill($supplier, $request->validated(), $request->user());

        // Post immediately if specified (default true for standard flow)
        if ($request->boolean('post_immediately', true)) {
            $this->ledgerService->recordSupplierBill($bill, $request->user());
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Supplier bill created and posted successfully.', 'bill' => $bill], 201);
        }

        return redirect()->back()
            ->with('success', "Supplier bill {$bill->bill_number} recorded and posted to Accounts Payable.");
    }

    /**
     * Post a draft supplier bill to Accounts Payable.
     */
    public function postBill(Request $request, int $billId): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', SupplierBill::class);

        $bill = SupplierBill::findOrFail($billId);

        $transaction = $this->ledgerService->recordSupplierBill($bill, $request->user());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Supplier bill posted successfully.', 'transaction' => $transaction]);
        }

        return redirect()->back()
            ->with('success', "Supplier bill {$bill->bill_number} posted to Accounts Payable.");
    }

    /**
     * Record a supplier payment.
     */
    public function storePayment(CreateSupplierPaymentRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', SupplierPayment::class);

        $supplier = Supplier::findOrFail((int) $request->validated('supplier_id'));

        $payment = $this->ledgerService->recordSupplierPayment($supplier, $request->validated(), $request->user());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Supplier payment recorded successfully.', 'payment' => $payment], 201);
        }

        return redirect()->back()
            ->with('success', "Supplier payment {$payment->payment_number} for \${$payment->amount} recorded successfully.");
    }

    /**
     * Reverse a completed supplier payment.
     */
    public function reversePayment(ReverseSupplierPaymentRequest $request, int $paymentId): RedirectResponse|JsonResponse
    {
        $payment = SupplierPayment::findOrFail($paymentId);

        Gate::authorize('reverse', $payment);

        $transaction = $this->ledgerService->recordPaymentReversal(
            $payment,
            (string) $request->validated('reversal_reason'),
            $request->user()
        );

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Supplier payment reversed successfully.', 'transaction' => $transaction]);
        }

        return redirect()->back()
            ->with('success', "Supplier payment {$payment->payment_number} reversed. Reversal entry {$transaction->transaction_number} posted.");
    }
}
