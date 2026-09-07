<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\ResourceScopeService;

class SupplierPolicy
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ResourceScopeService $resourceScopeService
    ) {}

    /**
     * Determine whether the user can view suppliers.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::PAYABLE_VIEW);
    }

    /**
     * Determine whether the user can view a specific supplier.
     */
    public function view(User $user, Supplier $supplier): bool
    {
        return $this->permissionService->has($user, Permission::PAYABLE_VIEW);
    }

    /**
     * Determine whether the user can create suppliers.
     */
    public function create(User $user): bool
    {
        return $this->permissionService->has($user, Permission::PAYABLE_MANAGE);
    }

    /**
     * Determine whether the user can update a supplier.
     */
    public function update(User $user, Supplier $supplier): bool
    {
        return $this->permissionService->has($user, Permission::PAYABLE_MANAGE);
    }

    /**
     * Determine whether the user can delete a supplier.
     */
    public function delete(User $user, Supplier $supplier): bool
    {
        return false;
    }
}
