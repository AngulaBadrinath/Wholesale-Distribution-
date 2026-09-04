<?php

namespace App\Http\Controllers\Salesman;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Salesman\StoreSalesmanRequest;
use App\Http\Requests\Salesman\UpdateSalesmanRequest;
use App\Http\Requests\Salesman\UpdateSalesmanStatusRequest;
use App\Models\User;
use App\Services\Salesman\SalesmanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesmanController extends Controller
{
    public function __construct(
        protected SalesmanService $salesmanService
    ) {}

    /**
     * Display a paginated listing of salesman accounts.
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();

        if (! $actor->canPermission(Permission::USER_VIEW)) {
            abort(403, 'You do not have permission to view salesmen.');
        }

        $filters = $request->only(['search', 'status', 'sort', 'direction']);
        $paginator = $this->salesmanService->paginate($filters);

        $salesmen = $paginator->through(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status instanceof AccountStatus ? $user->status->value : $user->status,
            'status_label' => $user->status instanceof AccountStatus ? $user->status->label() : (string) $user->status,
            'can_authenticate' => $user->canAuthenticate(),
            'can_be_assigned' => $user->canBeAssignedAsSalesman(),
            'assigned_customers_count' => $user->assigned_customers_count ?? 0,
            'created_at' => $user->created_at?->toIso8601String(),
        ]);

        $statuses = collect(AccountStatus::cases())->map(fn (AccountStatus $s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'description' => $s->description(),
        ]);

        return Inertia::render('Salesman/Index', [
            'salesmen' => $salesmen,
            'filters' => $filters,
            'statuses' => $statuses,
        ]);
    }

    /**
     * Show the form for provisioning a new salesman account.
     */
    public function create(Request $request): Response
    {
        $actor = $request->user();

        if (! $actor->canPermission(Permission::USER_CREATE)) {
            abort(403, 'You do not have permission to provision salesman accounts.');
        }

        $statuses = collect([AccountStatus::ACTIVE, AccountStatus::INVITED])->map(fn (AccountStatus $s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'description' => $s->description(),
        ]);

        return Inertia::render('Salesman/Create', [
            'statuses' => $statuses,
        ]);
    }

    /**
     * Store a newly provisioned salesman account in storage.
     */
    public function store(StoreSalesmanRequest $request): RedirectResponse
    {
        $actor = $request->user();

        $salesman = $this->salesmanService->createSalesman($actor, $request->validated());

        return redirect()->route('salesmen.show', $salesman->id)
            ->with('status', "Salesman account for {$salesman->name} was provisioned successfully.");
    }

    /**
     * Display the specified salesman profile and customer portfolio summary.
     */
    public function show(Request $request, User $salesman): Response
    {
        $actor = $request->user();

        if (! $actor->canPermission(Permission::USER_VIEW)) {
            abort(403, 'You do not have permission to view this salesman.');
        }

        if ($salesman->role !== UserRole::SALESMAN) {
            abort(404, 'Salesman account not found.');
        }

        $profileData = $this->salesmanService->getProfile($salesman);

        $statuses = collect(AccountStatus::cases())->map(fn (AccountStatus $s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'description' => $s->description(),
            'can_transition' => $salesman->status instanceof AccountStatus ? $salesman->status->canTransitionTo($s) : false,
        ]);

        return Inertia::render('Salesman/Show', [
            'salesman' => $profileData['salesman'],
            'assigned_customers' => $profileData['assigned_customers'],
            'statuses' => $statuses,
            'canEdit' => $actor->canPermission(Permission::USER_UPDATE),
            'canSuspend' => $actor->canPermission(Permission::USER_SUSPEND) && $actor->id !== $salesman->id,
        ]);
    }

    /**
     * Show the form for editing the specified salesman profile.
     */
    public function edit(Request $request, User $salesman): Response
    {
        $actor = $request->user();

        if (! $actor->canPermission(Permission::USER_UPDATE)) {
            abort(403, 'You do not have permission to edit this salesman.');
        }

        if ($salesman->role !== UserRole::SALESMAN) {
            abort(404, 'Salesman account not found.');
        }

        return Inertia::render('Salesman/Edit', [
            'salesman' => [
                'id' => $salesman->id,
                'name' => $salesman->name,
                'email' => $salesman->email,
                'status' => $salesman->status instanceof AccountStatus ? $salesman->status->value : $salesman->status,
                'status_label' => $salesman->status instanceof AccountStatus ? $salesman->status->label() : (string) $salesman->status,
            ],
        ]);
    }

    /**
     * Update the specified salesman profile in storage.
     */
    public function update(UpdateSalesmanRequest $request, User $salesman): RedirectResponse
    {
        $actor = $request->user();

        if ($salesman->role !== UserRole::SALESMAN) {
            abort(404, 'Salesman account not found.');
        }

        $this->salesmanService->updateSalesman($actor, $salesman, $request->validated());

        return redirect()->route('salesmen.show', $salesman->id)
            ->with('status', 'Salesman profile updated successfully.');
    }

    /**
     * Transition the specified salesman account's lifecycle state.
     */
    public function updateStatus(UpdateSalesmanStatusRequest $request, User $salesman): RedirectResponse
    {
        $actor = $request->user();

        if ($salesman->role !== UserRole::SALESMAN) {
            abort(404, 'Salesman account not found.');
        }

        if ($actor->id === $salesman->id) {
            abort(403, 'Administrators cannot alter the lifecycle state of their own account.');
        }

        $newStatus = AccountStatus::from($request->validated('status'));
        $reason = $request->validated('reason');

        $this->salesmanService->updateStatus($actor, $salesman, $newStatus, $reason);

        return redirect()->back()
            ->with('status', "Salesman status transitioned to {$newStatus->label()} successfully.");
    }
}
