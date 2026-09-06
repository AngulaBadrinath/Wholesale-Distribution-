<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Services\Return\ReturnWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminReturnWorkflowController extends Controller
{
    public function __construct(
        protected ReturnWorkflowService $workflowService,
    ) {}

    /**
     * Authoritative approval of return request with item-by-item stock disposition.
     */
    public function approve(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.accepted_good_quantity' => ['required', 'integer', 'min:0'],
            'items.*.accepted_damaged_quantity' => ['required', 'integer', 'min:0'],
            'items.*.rejected_quantity' => ['required', 'integer', 'min:0'],
        ]);

        $this->workflowService->approveReturn($returnRequest, $validated, $request->user());

        return redirect()->route('admin.returns.show', $returnRequest->id)
            ->with('success', "Return #{$returnRequest->return_number} has been approved and inventory disposition executed.");
    }

    /**
     * Authoritative rejection of return request.
     */
    public function reject(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $this->workflowService->rejectReturn($returnRequest, $validated, $request->user());

        return redirect()->route('admin.returns.show', $returnRequest->id)
            ->with('success', "Return #{$returnRequest->return_number} has been rejected.");
    }

    /**
     * Requester cancellation of return request.
     */
    public function cancel(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        $this->workflowService->cancelReturn($returnRequest, $request->user());

        return redirect()->route('admin.returns.show', $returnRequest->id)
            ->with('success', "Return #{$returnRequest->return_number} has been cancelled.");
    }
}
