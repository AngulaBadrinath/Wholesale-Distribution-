<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\StockException;
use App\Models\User;
use App\Services\Auth\PermissionService;

class StockExceptionPolicy
{
    public function __construct(
        protected PermissionService $permissionService,
    ) {}

    /**
     * Determine whether the user can browse stock exception records.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::INVENTORY_VIEW);
    }

    /**
     * Determine whether the user can view a specific stock exception.
     */
    public function view(User $user, StockException $exception): bool
    {
        return $this->permissionService->has($user, Permission::INVENTORY_VIEW);
    }

    /**
     * Determine whether the user can report a stock exception.
     */
    public function create(User $user): bool
    {
        return $this->permissionService->has($user, Permission::INVENTORY_EXCEPTION_REPORT)
            || $this->permissionService->has($user, Permission::INVENTORY_ADJUST);
    }

    /**
     * Determine whether the user can resolve a stock exception.
     */
    public function resolve(User $user, StockException $exception): bool
    {
        return $this->permissionService->has($user, Permission::INVENTORY_ADJUST);
    }

    /**
     * Determine whether the user can dismiss a stock exception.
     */
    public function dismiss(User $user, StockException $exception): bool
    {
        return $this->permissionService->has($user, Permission::INVENTORY_ADJUST);
    }
}
