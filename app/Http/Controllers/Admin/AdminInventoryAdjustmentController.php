<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\CreateInventoryAdjustmentRequest;
use App\Services\Inventory\InventoryAdjustmentService;
use Illuminate\Http\RedirectResponse;

class AdminInventoryAdjustmentController extends Controller
{
    public function __construct(
        protected InventoryAdjustmentService $adjustmentService,
    ) {}

    /**
     * Store an authorized direct inventory balance adjustment.
     */
    public function store(CreateInventoryAdjustmentRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $adjustment = $this->adjustmentService->adjustBalance($request->validated(), $actor);

        return redirect()->back()->with('success', "Stock adjustment [{$adjustment->adjustment_number}] completed successfully.");
    }
}
