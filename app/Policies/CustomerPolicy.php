<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\User;
use App\Services\Auth\PermissionService;

class CustomerPolicy
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Determine whether the user can view any customers.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::CUSTOMER_VIEW);
    }

    /**
     * Determine whether the user can view the customer.
     * Salesmen may only access customer records within their assigned portfolio.
     */
    public function view(User $user, Customer $customer): bool
    {
        if (! $this->permissionService->has($user, Permission::CUSTOMER_VIEW)) {
            return false;
        }

        if ($user->role === \App\Enums\UserRole::SALESMAN) {
            return $customer->salesman_id === $user->id;
        }

        return true;
    }

    /**
     * Determine whether the user can create customers.
     */
    public function create(User $user): bool
    {
        return $this->permissionService->has($user, Permission::CUSTOMER_CREATE);
    }

    /**
     * Determine whether the user can update the customer.
     */
    public function update(User $user, Customer $customer): bool
    {
        return $this->permissionService->has($user, Permission::CUSTOMER_UPDATE);
    }

    /**
     * Determine whether the user can assign or reassign the customer's sales representative.
     */
    public function assign(User $user, Customer $customer): bool
    {
        return $this->permissionService->has($user, Permission::CUSTOMER_UPDATE);
    }

    /**
     * Determine whether the user can delete the customer.
     * Deletions are prohibited to preserve referential transaction integrity.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return false;
    }
}
