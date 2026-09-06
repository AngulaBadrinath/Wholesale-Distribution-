<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Services\Return\ReturnInspectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminReturnInspectionController extends Controller
{
    public function __construct(
        protected ReturnInspectionService $inspectionService,
    ) {}

    /**
     * Record physical warehouse inspection.
     */
    public function store(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        $validated = $request->validate([
            'inspection_notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.received_quantity' => ['required', 'integer', 'min:0'],
            'items.*.item_notes' => ['nullable', 'string', 'max:500'],
            'evidence_photos' => ['nullable', 'array', 'max:5'],
            'evidence_photos.*' => ['file', 'mimes:jpeg,jpg,png', 'max:5120'],
        ]);

        $evidenceFiles = $request->file('evidence_photos', []);
        if (! is_array($evidenceFiles)) {
            $evidenceFiles = [$evidenceFiles];
        }

        $this->inspectionService->recordInspection($returnRequest, $validated, $request->user(), $evidenceFiles);

        return redirect()->route('admin.returns.show', $returnRequest->id)
            ->with('success', "Warehouse physical inspection for Return #{$returnRequest->return_number} has been recorded.");
    }
}
