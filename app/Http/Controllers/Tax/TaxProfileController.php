<?php

namespace App\Http\Controllers\Tax;

use App\Enums\Permission;
use App\Enums\TaxProfileStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tax\StoreTaxProfileRequest;
use App\Http\Requests\Tax\UpdateTaxProfileRequest;
use App\Models\TaxProfile;
use App\Services\Auth\PermissionService;
use App\Services\Tax\TaxProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxProfileController extends Controller
{
    public function __construct(
        protected TaxProfileService $taxProfileService,
        protected PermissionService $permissionService
    ) {}

    /**
     * Display a listing of tax profiles.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if ($user && ! $this->permissionService->has($user, Permission::PRODUCT_TAX_UPDATE)) {
            abort(403, 'You do not have permission to view or manage tax profiles.');
        }

        $filters = $request->only(['search', 'status', 'sort_by', 'sort_order']);
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));

        $taxProfiles = $this->taxProfileService->list($filters, $perPage, $user);

        return Inertia::render('TaxProfile/Index', [
            'taxProfiles' => $taxProfiles,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', 'ALL'),
                'sort_by' => $request->input('sort_by', 'name'),
                'sort_order' => $request->input('sort_order', 'asc'),
            ],
            'statuses' => collect(TaxProfileStatus::cases())->map(fn (TaxProfileStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'badgeVariant' => $s->badgeVariant(),
            ]),
            'can' => [
                'manage' => $user ? $this->permissionService->has($user, Permission::PRODUCT_TAX_UPDATE) : false,
            ],
        ]);
    }

    /**
     * Show the form for creating a new tax profile.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();
        if ($user && ! $this->permissionService->has($user, Permission::PRODUCT_TAX_UPDATE)) {
            abort(403, 'You do not have permission to create tax profiles.');
        }

        return Inertia::render('TaxProfile/Create', [
            'statuses' => collect(TaxProfileStatus::cases())->map(fn (TaxProfileStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    /**
     * Store a newly created tax profile.
     */
    public function store(StoreTaxProfileRequest $request): RedirectResponse|JsonResponse
    {
        $taxProfile = $this->taxProfileService->create(
            data: $request->toDto(),
            actor: $request->user(),
            ip: $request->ip()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Tax profile created successfully.',
                'tax_profile' => $this->taxProfileService->formatTaxProfile($taxProfile),
            ], 201);
        }

        return redirect()->route('tax-profiles.index')
            ->with('success', "Tax profile '{$taxProfile->name}' created successfully.");
    }

    /**
     * Show the form for editing the specified tax profile.
     */
    public function edit(TaxProfile $taxProfile, Request $request): Response
    {
        $user = $request->user();
        if ($user && ! $this->permissionService->has($user, Permission::PRODUCT_TAX_UPDATE)) {
            abort(403, 'You do not have permission to edit tax profiles.');
        }

        return Inertia::render('TaxProfile/Edit', [
            'taxProfile' => $this->taxProfileService->formatTaxProfile($taxProfile),
            'statuses' => collect(TaxProfileStatus::cases())->map(fn (TaxProfileStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    /**
     * Update the specified tax profile.
     */
    public function update(TaxProfile $taxProfile, UpdateTaxProfileRequest $request): RedirectResponse|JsonResponse
    {
        $updated = $this->taxProfileService->update(
            taxProfile: $taxProfile,
            data: $request->toDto(),
            actor: $request->user(),
            ip: $request->ip()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Tax profile updated successfully.',
                'tax_profile' => $this->taxProfileService->formatTaxProfile($updated),
            ]);
        }

        return redirect()->route('tax-profiles.index')
            ->with('success', "Tax profile '{$updated->name}' updated successfully.");
    }

    /**
     * Remove the specified tax profile from storage.
     */
    public function destroy(TaxProfile $taxProfile, Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        if ($user && ! $this->permissionService->has($user, Permission::PRODUCT_TAX_UPDATE)) {
            abort(403, 'You do not have permission to delete tax profiles.');
        }

        $name = $taxProfile->name;
        $this->taxProfileService->delete(
            taxProfile: $taxProfile,
            actor: $user,
            ip: $request->ip()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => "Tax profile '{$name}' deleted successfully.",
            ]);
        }

        return redirect()->route('tax-profiles.index')
            ->with('success', "Tax profile '{$name}' deleted successfully.");
    }
}
