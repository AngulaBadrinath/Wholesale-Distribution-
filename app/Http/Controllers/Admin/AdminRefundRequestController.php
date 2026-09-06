<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use App\Models\RefundRequest;
use App\Services\Auth\ResourceScopeService;
use App\Services\Refund\RefundWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminRefundRequestController extends Controller
{
    public function __construct(
        protected RefundWorkflowService $refundWorkflowService,
        protected ResourceScopeService $resourceScopeService
    ) {}

    /**
     * Display a listing of refund requests.
     */
    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', RefundRequest::class);

        $status = $request->query('status');
        $customerId = $request->query('customer_id');
        $creditNoteId = $request->query('credit_note_id');
        $search = $request->query('search');
        $perPage = (int) $request->query('per_page', 20);

        $query = RefundRequest::query()
            ->with(['creditNote', 'customer', 'requester', 'approver'])
            ->orderBy('id', 'desc');

        $query = $this->resourceScopeService->scopeRefundRequests($query, $request->user());

        if ($status && in_array($status, RefundStatus::values(), true)) {
            $query->where('status', $status);
        }

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        if ($creditNoteId) {
            $query->where('credit_note_id', $creditNoteId);
        }

        if ($search && trim($search) !== '') {
            $query->search(trim($search));
        }

        $refundRequests = $query->paginate($perPage)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($refundRequests);
        }

        return Inertia::render('Admin/Refunds/Index', [
            'refundRequests' => $refundRequests,
            'filters' => [
                'status' => $status,
                'customer_id' => $customerId,
                'credit_note_id' => $creditNoteId,
                'search' => $search,
            ],
            'statuses' => RefundStatus::options(),
        ]);
    }

    /**
     * Display the specified refund request.
     */
    public function show(Request $request, int $id): Response|JsonResponse
    {
        /** @var RefundRequest|null $refundRequest */
        $refundRequest = RefundRequest::with([
            'creditNote.items',
            'creditNote.order',
            'customer',
            'requester',
            'reviewer',
            'approver',
            'rejector',
            'canceller',
            'events.actor',
            'transaction.processor',
        ])->find($id);

        if (! $refundRequest) {
            throw new NotFoundHttpException('Refund request not found.');
        }

        if (! $this->resourceScopeService->canAccessRefundRequest($request->user(), $refundRequest)) {
            throw new NotFoundHttpException('Refund request not found.');
        }

        Gate::authorize('view', $refundRequest);

        if ($request->wantsJson()) {
            return response()->json($refundRequest);
        }

        return Inertia::render('Admin/Refunds/Show', [
            'refundRequest' => $refundRequest,
        ]);
    }

    /**
     * Store a newly created refund request.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', RefundRequest::class);

        $validated = $request->validate([
            'credit_note_id' => ['required', 'integer', 'exists:credit_notes,id'],
            'requested_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'in:CASH,CHEQUE,MONEY_ORDER'],
            'reason' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        $creditNote = CreditNote::find($validated['credit_note_id']);

        if (! $creditNote || ! $this->resourceScopeService->canAccessCreditNote($request->user(), $creditNote)) {
            throw new NotFoundHttpException('Credit note not found.');
        }

        $refundRequest = $this->refundWorkflowService->createRefundRequest(
            $creditNote,
            $request->user(),
            $validated
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Refund request {$refundRequest->refund_number} created successfully.",
                'refund_request' => $refundRequest,
            ], 201);
        }

        return redirect()->route('admin.refunds.show', $refundRequest->id)
            ->with('success', "Refund request {$refundRequest->refund_number} created successfully.");
    }

    /**
     * Transition a refund request to UNDER_REVIEW.
     */
    public function review(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $refundRequest = RefundRequest::find($id);

        if (! $refundRequest || ! $this->resourceScopeService->canAccessRefundRequest($request->user(), $refundRequest)) {
            throw new NotFoundHttpException('Refund request not found.');
        }

        Gate::authorize('review', $refundRequest);

        $updated = $this->refundWorkflowService->reviewRefund($refundRequest, $request->user(), $request->all());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Refund request {$updated->refund_number} is now under review.",
                'refund_request' => $updated,
            ]);
        }

        return back()->with('success', "Refund request {$updated->refund_number} is now under review.");
    }

    /**
     * Authoritatively approve a refund request.
     */
    public function approve(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $refundRequest = RefundRequest::find($id);

        if (! $refundRequest || ! $this->resourceScopeService->canAccessRefundRequest($request->user(), $refundRequest)) {
            throw new NotFoundHttpException('Refund request not found.');
        }

        Gate::authorize('approve', $refundRequest);

        $updated = $this->refundWorkflowService->approveRefund($refundRequest, $request->user(), $request->all());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Refund request {$updated->refund_number} approved successfully.",
                'refund_request' => $updated,
            ]);
        }

        return back()->with('success', "Refund request {$updated->refund_number} approved successfully.");
    }

    /**
     * Formally reject a refund request.
     */
    public function reject(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $refundRequest = RefundRequest::find($id);

        if (! $refundRequest || ! $this->resourceScopeService->canAccessRefundRequest($request->user(), $refundRequest)) {
            throw new NotFoundHttpException('Refund request not found.');
        }

        Gate::authorize('reject', $refundRequest);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $updated = $this->refundWorkflowService->rejectRefund($refundRequest, $request->user(), $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Refund request {$updated->refund_number} has been rejected.",
                'refund_request' => $updated,
            ]);
        }

        return back()->with('success', "Refund request {$updated->refund_number} has been rejected.");
    }

    /**
     * Cancel an active refund request.
     */
    public function cancel(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $refundRequest = RefundRequest::find($id);

        if (! $refundRequest || ! $this->resourceScopeService->canAccessRefundRequest($request->user(), $refundRequest)) {
            throw new NotFoundHttpException('Refund request not found.');
        }

        Gate::authorize('cancel', $refundRequest);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $updated = $this->refundWorkflowService->cancelRefund($refundRequest, $request->user(), $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Refund request {$updated->refund_number} has been cancelled.",
                'refund_request' => $updated,
            ]);
        }

        return back()->with('success', "Refund request {$updated->refund_number} has been cancelled.");
    }

    /**
     * Authoritatively process disbursement for an approved refund request.
     */
    public function process(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $refundRequest = RefundRequest::find($id);

        if (! $refundRequest || ! $this->resourceScopeService->canAccessRefundRequest($request->user(), $refundRequest)) {
            throw new NotFoundHttpException('Refund request not found.');
        }

        Gate::authorize('process', $refundRequest);

        $validated = $request->validate([
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        $transaction = $this->refundWorkflowService->processRefund(
            $refundRequest,
            $request->user(),
            $validated
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Refund transaction {$transaction->transaction_number} processed successfully.",
                'transaction' => $transaction,
            ], 201);
        }

        return redirect()->route('admin.refunds.show', $refundRequest->id)
            ->with('success', "Refund transaction {$transaction->transaction_number} processed successfully.");
    }
}
