<?php

namespace App\Services\Customer;

use App\DTOs\Customer\CustomerData;
use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\Permission;
use App\Models\Customer;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerService
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Retrieve paginated, searchable, filterable customer list.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Customer::query();

        // 1. Search filter
        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        // 2. Status filter
        if (! empty($filters['status'])) {
            $query->filterByStatus((string) $filters['status']);
        }

        // 3. Sorting (allow-listed fields only)
        $allowedSorts = ['name', 'code', 'contact_name', 'email', 'status', 'credit_limit', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts, true) ? $filters['sort_by'] : 'name';
        $sortOrder = strtolower((string) ($filters['sort_order'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage)->withQueryString();
    }

    /**
     * Retrieve customer by ID or throw exception.
     */
    public function findById(int $id): Customer
    {
        return Customer::findOrFail($id);
    }

    /**
     * Create a new customer master record.
     *
     * @throws AuthorizationException
     */
    public function create(CustomerData $data, User $actor, ?string $ip = null): Customer
    {
        $this->ensureActorIsActiveAndAuthorized($actor, Permission::CUSTOMER_CREATE);

        return DB::transaction(function () use ($data, $actor, $ip) {
            $attributes = $data->toArray();

            // Auto-generate code if not explicitly provided
            if (empty($attributes['code'])) {
                $attributes['code'] = $this->generateNextCustomerCode();
            }

            $customer = Customer::create($attributes);

            Log::info('Customer master record created', [
                'event' => 'audit.customer_event',
                'action' => 'CUSTOMER_CREATED',
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role?->value,
                'customer_id' => $customer->id,
                'customer_code' => $customer->code,
                'customer_name' => $customer->name,
                'ip_address' => $ip,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $customer;
        });
    }

    /**
     * Update an existing customer master record.
     *
     * @throws AuthorizationException
     */
    public function update(Customer $customer, CustomerData $data, User $actor, ?string $ip = null): Customer
    {
        $this->ensureActorIsActiveAndAuthorized($actor, Permission::CUSTOMER_UPDATE);

        return DB::transaction(function () use ($customer, $data, $actor, $ip) {
            /** @var Customer $lockedCustomer */
            $lockedCustomer = Customer::query()
                ->where('id', $customer->id)
                ->lockForUpdate()
                ->firstOrFail();

            $original = $lockedCustomer->only(array_keys($data->toArray()));
            $newValues = $data->toArray();

            $changedFields = [];
            foreach ($newValues as $key => $val) {
                if (($original[$key] ?? null) !== $val) {
                    $changedFields[] = $key;
                }
            }

            $lockedCustomer->fill($newValues);
            $lockedCustomer->save();

            Log::info('Customer master record updated', [
                'event' => 'audit.customer_event',
                'action' => 'CUSTOMER_UPDATED',
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role?->value,
                'customer_id' => $lockedCustomer->id,
                'customer_code' => $lockedCustomer->code,
                'changed_fields' => $changedFields,
                'ip_address' => $ip,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $lockedCustomer;
        });
    }

    /**
     * Transition customer lifecycle status.
     *
     * @throws AuthorizationException
     */
    public function updateStatus(
        Customer $customer,
        CustomerStatus $newStatus,
        User $actor,
        ?string $reason = null,
        ?string $ip = null
    ): Customer {
        $this->ensureActorIsActiveAndAuthorized($actor, Permission::CUSTOMER_UPDATE);

        return DB::transaction(function () use ($customer, $newStatus, $actor, $reason, $ip) {
            /** @var Customer $lockedCustomer */
            $lockedCustomer = Customer::query()
                ->where('id', $customer->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStatus = $lockedCustomer->status;

            if ($previousStatus === $newStatus) {
                return $lockedCustomer;
            }

            $lockedCustomer->status = $newStatus;
            $lockedCustomer->save();

            $action = $newStatus === CustomerStatus::INACTIVE
                ? 'CUSTOMER_DEACTIVATED'
                : 'CUSTOMER_STATUS_CHANGED';

            Log::info("Customer status transitioned to {$newStatus->value}", [
                'event' => 'audit.customer_event',
                'action' => $action,
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role?->value,
                'customer_id' => $lockedCustomer->id,
                'customer_code' => $lockedCustomer->code,
                'previous_status' => $previousStatus?->value ?? (string) $previousStatus,
                'new_status' => $newStatus->value,
                'reason' => $reason,
                'ip_address' => $ip,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $lockedCustomer;
        });
    }

    /**
     * Generate the next deterministic sequential customer code.
     */
    public function generateNextCustomerCode(): string
    {
        $maxNum = 0;
        $codes = Customer::query()->whereNotNull('code')->pluck('code');

        foreach ($codes as $code) {
            if (preg_match('/^CUST-(\d+)$/i', trim($code), $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }

        if ($maxNum === 0) {
            $maxId = (int) Customer::max('id');
            $maxNum = $maxId;
        }

        return sprintf('CUST-%05d', $maxNum + 1);
    }

    /**
     * Ensure actor has an active account status and required permission.
     *
     * @throws AuthorizationException
     */
    protected function ensureActorIsActiveAndAuthorized(User $actor, Permission $permission): void
    {
        $isActive = ($actor->status instanceof AccountStatus)
            ? $actor->status === AccountStatus::ACTIVE
            : $actor->status === AccountStatus::ACTIVE->value;

        if (! $isActive) {
            throw new AuthorizationException('Inactive accounts are not authorized to perform customer operations.');
        }

        $this->permissionService->authorize($actor, $permission);
    }
}
