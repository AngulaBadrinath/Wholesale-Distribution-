<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Category;
use App\Models\User;
use App\Services\Auth\PermissionService;

class CategoryPolicy
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Determine whether the user can view category directories.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::PRODUCT_VIEW);
    }

    /**
     * Determine whether the user can view the category details.
     */
    public function view(User $user, ?Category $category = null): bool
    {
        return $this->permissionService->has($user, Permission::PRODUCT_VIEW);
    }

    /**
     * Determine whether the user can create categories.
     */
    public function create(User $user): bool
    {
        return $this->permissionService->has($user, Permission::PRODUCT_CREATE);
    }

    /**
     * Determine whether the user can update the category.
     */
    public function update(User $user, ?Category $category = null): bool
    {
        return $this->permissionService->has($user, Permission::PRODUCT_UPDATE);
    }

    /**
     * Determine whether the user can delete the category.
     * Note: Deletion is permitted only if the category has zero products and zero children.
     */
    public function delete(User $user, ?Category $category = null): bool
    {
        return $this->permissionService->has($user, Permission::PRODUCT_UPDATE);
    }
}
