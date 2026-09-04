<?php

namespace App\Services\Salesman;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\SessionRevocationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SalesmanService
{
    public function __construct(
        protected SessionRevocationService $sessionRevocationService
    ) {}

    /**
     * Retrieve a paginated list of salesman accounts with search and status filtering.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query()
            ->where('role', UserRole::SALESMAN->value)
            ->withCount('assignedCustomers');

        // Text search across name and email
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $isPgsql = $query->getConnection()->getDriverName() === 'pgsql';
            $like = $isPgsql ? 'ilike' : 'like';

            $query->where(function ($q) use ($search, $like) {
                $q->where('name', $like, "%{$search}%")
                    ->orWhere('email', $like, "%{$search}%");
            });
        }

        // Status filter
        if (! empty($filters['status'])) {
            $status = AccountStatus::tryFrom(strtoupper((string) $filters['status']));
            if ($status !== null) {
                $query->where('status', $status->value);
            }
        }

        // Sorting
        $sortField = $filters['sort'] ?? 'name';
        $direction = strtolower($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['name', 'email', 'status', 'created_at', 'assigned_customers_count'];
        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $direction);
        } else {
            $query->orderBy('name', 'asc');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Authoritatively provision a new salesman account.
     * Role is strictly forced to UserRole::SALESMAN on the server.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     */
    public function createSalesman(User $actor, array $data): User
    {
        if (! $actor->exists || ! $actor->id || ! $actor->isActive()) {
            throw new AuthorizationException('Actor must be an active, authenticated user.');
        }

        $initialStatus = isset($data['status'])
            ? AccountStatus::tryFrom(strtoupper((string) $data['status'])) ?? AccountStatus::ACTIVE
            : AccountStatus::ACTIVE;

        // Salesman initial status must be ACTIVE or INVITED
        if (! in_array($initialStatus, [AccountStatus::ACTIVE, AccountStatus::INVITED], true)) {
            $initialStatus = AccountStatus::ACTIVE;
        }

        return DB::transaction(function () use ($actor, $data, $initialStatus) {
            $user = new User();
            $user->name = trim((string) $data['name']);
            $user->email = strtolower(trim((string) $data['email']));
            $user->password = Hash::make((string) $data['password']);
            $user->role = UserRole::SALESMAN;
            $user->status = $initialStatus;
            $user->save();

            Log::info('auth.salesman_event', [
                'action' => 'SALESMAN_CREATED',
                'actor_id' => $actor->id,
                'salesman_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'initial_status' => $user->status->value,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $user;
        });
    }

    /**
     * Update an existing salesman's profile fields (name and email).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function updateSalesman(User $actor, User $salesman, array $data): User
    {
        if (! $actor->exists || ! $actor->id || ! $actor->isActive()) {
            throw new AuthorizationException('Actor must be an active, authenticated user.');
        }

        if ($salesman->role !== UserRole::SALESMAN) {
            throw ValidationException::withMessages([
                'user' => ['Only salesman accounts can be managed through this service.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $salesman, $data) {
            /** @var User $lockedSalesman */
            $lockedSalesman = User::where('id', $salesman->id)->lockForUpdate()->firstOrFail();

            if ($lockedSalesman->role !== UserRole::SALESMAN) {
                throw new AuthorizationException('Target user is not a salesman.');
            }

            $changed = [];

            if (isset($data['name'])) {
                $newName = trim((string) $data['name']);
                if ($lockedSalesman->name !== $newName) {
                    $lockedSalesman->name = $newName;
                    $changed[] = 'name';
                }
            }

            if (isset($data['email'])) {
                $newEmail = strtolower(trim((string) $data['email']));
                if ($lockedSalesman->email !== $newEmail) {
                    $lockedSalesman->email = $newEmail;
                    $changed[] = 'email';
                }
            }

            if (! empty($changed)) {
                $lockedSalesman->save();

                Log::info('auth.salesman_event', [
                    'action' => 'SALESMAN_UPDATED',
                    'actor_id' => $actor->id,
                    'salesman_id' => $lockedSalesman->id,
                    'changed_fields' => $changed,
                    'timestamp' => now()->toIso8601String(),
                ]);
            }

            return $lockedSalesman;
        });
    }

    /**
     * Authoritatively transition a salesman account's lifecycle state.
     * Pessimistic row locking, no-op suppression, and immediate session revocation on suspension/disablement.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function updateStatus(
        User $actor,
        User $salesman,
        AccountStatus $newStatus,
        ?string $reason = null
    ): User {
        if (! $actor->exists || ! $actor->id || ! $actor->isActive()) {
            throw new AuthorizationException('Actor must be an active, authenticated user.');
        }

        if ($actor->id === $salesman->id) {
            throw new AuthorizationException('Administrators cannot suspend or alter the status of their own account.');
        }

        if ($salesman->role !== UserRole::SALESMAN) {
            throw ValidationException::withMessages([
                'user' => ['Only salesman accounts can be managed through this service.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $salesman, $newStatus, $reason) {
            /** @var User $lockedSalesman */
            $lockedSalesman = User::where('id', $salesman->id)->lockForUpdate()->firstOrFail();

            if ($lockedSalesman->role !== UserRole::SALESMAN) {
                throw new AuthorizationException('Target user is not a salesman.');
            }

            $previousStatus = $lockedSalesman->status;

            // No-op check: if status is identical, return cleanly without database write or duplicate audit
            if ($previousStatus === $newStatus) {
                return $lockedSalesman;
            }

            $action = $this->classifyStatusTransitionAction($newStatus);

            $lockedSalesman->status = $newStatus;
            $lockedSalesman->save();

            // Immediate session revocation on suspension or deactivation
            if (in_array($newStatus, [AccountStatus::SUSPENDED, AccountStatus::DISABLED], true)) {
                $this->sessionRevocationService->revokeUserSessionsForSecurityEvent(
                    $lockedSalesman,
                    'account_status_changed'
                );
            }

            Log::info('auth.salesman_event', [
                'action' => $action,
                'actor_id' => $actor->id,
                'salesman_id' => $lockedSalesman->id,
                'previous_status' => $previousStatus instanceof AccountStatus ? $previousStatus->value : (string) $previousStatus,
                'new_status' => $newStatus->value,
                'reason' => $reason,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $lockedSalesman;
        });
    }

    /**
     * Retrieve complete salesman profile data including assigned customer portfolio summary.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function getProfile(User $salesman): array
    {
        if ($salesman->role !== UserRole::SALESMAN) {
            throw ValidationException::withMessages([
                'user' => ['Only salesman accounts can be viewed in this profile.'],
            ]);
        }

        $assignedCustomers = $salesman->assignedCustomers()
            ->select(['id', 'code', 'name', 'status', 'credit_limit', 'payment_terms', 'billing_city', 'billing_state'])
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'status' => $c->status instanceof \App\Enums\CustomerStatus ? $c->status->value : $c->status,
                'status_label' => $c->status instanceof \App\Enums\CustomerStatus ? $c->status->label() : (string) $c->status,
                'credit_limit' => $c->credit_limit,
                'payment_terms' => $c->payment_terms instanceof \App\Enums\PaymentTerms ? $c->payment_terms->value : $c->payment_terms,
                'payment_terms_label' => $c->payment_terms instanceof \App\Enums\PaymentTerms ? $c->payment_terms->label() : (string) $c->payment_terms,
                'city' => $c->billing_city,
                'state' => $c->billing_state,
            ]);

        return [
            'salesman' => [
                'id' => $salesman->id,
                'name' => $salesman->name,
                'email' => $salesman->email,
                'role' => $salesman->role?->value,
                'role_label' => $salesman->role?->label(),
                'status' => $salesman->status instanceof AccountStatus ? $salesman->status->value : $salesman->status,
                'status_label' => $salesman->status instanceof AccountStatus ? $salesman->status->label() : (string) $salesman->status,
                'can_authenticate' => $salesman->canAuthenticate(),
                'can_be_assigned' => $salesman->canBeAssignedAsSalesman(),
                'created_at' => $salesman->created_at?->toIso8601String(),
                'updated_at' => $salesman->updated_at?->toIso8601String(),
                'assigned_customers_count' => $assignedCustomers->count(),
            ],
            'assigned_customers' => $assignedCustomers,
        ];
    }

    /**
     * Classify status transition audit action name.
     */
    protected function classifyStatusTransitionAction(AccountStatus $new): string
    {
        return match ($new) {
            AccountStatus::ACTIVE => 'SALESMAN_ACTIVATED',
            AccountStatus::SUSPENDED => 'SALESMAN_SUSPENDED',
            AccountStatus::DISABLED => 'SALESMAN_DISABLED',
            AccountStatus::INVITED => 'SALESMAN_INVITED',
        };
    }
}
