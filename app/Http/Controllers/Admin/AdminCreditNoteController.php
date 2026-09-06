<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CreditNoteStatus;
use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use App\Models\ReturnRequest;
use App\Services\Auth\ResourceScopeService;
use App\Services\Credit\CreditEligibilityService;
use App\Services\Credit\CreditNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminCreditNoteController extends Controller
{
    public function __construct(
        protected CreditNoteService $creditNoteService,
        protected CreditEligibilityService $eligibilityService,
        protected ResourceScopeService $resourceScopeService
    ) {}

    /**
     * Display a paginated listing of credit notes.
     */
    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize('viewAny', CreditNote::class);

        $status = $request->query('status');
        $customerId = $request->query('customer_id');
        $search = $request->query('search');
        $perPage = (int) $request->query('per_page', 20);

        $query = CreditNote::query()
            ->with(['customer', 'order', 'returnRequest', 'issuer'])
            ->orderBy('id', 'desc');

        $query = $this->resourceScopeService->scopeCreditNotes($query, $request->user());

        if ($status && in_array($status, CreditNoteStatus::values(), true)) {
            $query->where('status', $status);
        }

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        if ($search && trim($search) !== '') {
            $query->search(trim($search));
        }

        $creditNotes = $query->paginate($perPage)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($creditNotes);
        }

        return Inertia::render('Admin/Credits/Index', [
            'creditNotes' => $creditNotes,
            'filters' => [
                'status' => $status,
                'customer_id' => $customerId,
                'search' => $search,
            ],
            'statuses' => CreditNoteStatus::options(),
        ]);
    }

    /**
     * Display the specified credit note.
     */
    public function show(Request $request, int $id): Response|JsonResponse
    {
        /** @var CreditNote|null $creditNote */
        $creditNote = CreditNote::with([
            'customer',
            'order.invoice',
            'returnRequest.items',
            'issuer',
            'items.product',
            'items.orderItem',
            'refundRequests.requester',
            'refundRequests.approver',
            'refundTransactions.processor',
        ])->find($id);

        if (! $creditNote) {
            throw new NotFoundHttpException('Credit note not found.');
        }

        // Anti-IDOR: verify resource scope before exposing details
        if (! $this->resourceScopeService->canAccessCreditNote($request->user(), $creditNote)) {
            throw new NotFoundHttpException('Credit note not found.');
        }

        Gate::authorize('view', $creditNote);

        if ($request->wantsJson()) {
            return response()->json($creditNote);
        }

        return Inertia::render('Admin/Credits/Show', [
            'creditNote' => $creditNote,
        ]);
    }

    /**
     * Preview or calculate credit eligibility for a return request.
     */
    public function calculateEligibility(Request $request, int $returnRequestId): JsonResponse
    {
        $returnRequest = ReturnRequest::with(['customer', 'order.invoice', 'items.orderItem'])->find($returnRequestId);

        if (! $returnRequest) {
            throw new NotFoundHttpException('Return request not found.');
        }

        if (! $this->resourceScopeService->canAccessReturn($request->user(), $returnRequest)) {
            throw new NotFoundHttpException('Return request not found.');
        }

        Gate::authorize('create', CreditNote::class);

        $calculation = $this->eligibilityService->calculateReturnEligibility($returnRequest);

        return response()->json($calculation);
    }

    /**
     * Authoritatively issue a credit note from an approved return request.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', CreditNote::class);

        $validated = $request->validate([
            'return_request_id' => ['required', 'integer', 'exists:return_requests,id'],
            'reason' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        $returnRequest = ReturnRequest::find($validated['return_request_id']);

        if (! $returnRequest || ! $this->resourceScopeService->canAccessReturn($request->user(), $returnRequest)) {
            throw new NotFoundHttpException('Return request not found.');
        }

        $creditNote = $this->creditNoteService->generateCreditNote(
            $returnRequest,
            $request->user(),
            $validated
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Credit note {$creditNote->credit_number} generated successfully.",
                'credit_note' => $creditNote,
            ], 201);
        }

        return redirect()->route('admin.credits.show', $creditNote->id)
            ->with('success', "Credit note {$creditNote->credit_number} generated successfully.");
    }
}
