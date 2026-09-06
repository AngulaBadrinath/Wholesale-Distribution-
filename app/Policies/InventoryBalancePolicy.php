<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\InventoryBalance;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\ResourceScopeService;

class InventoryBalancePolicy
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ResourceScopeService $resourceScopeService,
    ) {}

    /**
     * Determine whether the user can browse inventory balances.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::INVENTORY_VIEW);
    }

    /**
     * Determine whether the user can view a specific inventory balance.
     */
    public function view(User $user, InventoryBalance $balance): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->resourceScopeService->canAccessInventoryBalance($user, $balance);
    }

    /**
     * Determine whether the user can perform physical stock adjustments.
     */
    public function adjust(User $user, ?InventoryBalance $balance = null): bool
    {
        return $this->permissionService->has($user, Permission::INVENTORY_ADJUST);
    }

    /**
     * Determine whether the user can report stock exceptions or damaged goods.
     */
    public function reportException(User $user, ?InventoryBalance $balance = null): bool
    {
        return $this->permissionService->has($user, Permission::INVENTORY_EXCEPTION_REPORT)
            || $this->permissionService->has($user, Permission::INVENTORY_ADJUST);
    }
}
