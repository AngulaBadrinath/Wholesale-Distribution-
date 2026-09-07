<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Account;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\ResourceScopeService;

class AccountPolicy
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ResourceScopeService $resourceScopeService
    ) {}

    /**
     * Determine whether the user can browse Chart of Accounts.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::ACCOUNTING_VIEW);
    }

    /**
     * Determine whether the user can view a specific account.
     */
    public function view(User $user, Account $account): bool
    {
        return $this->permissionService->has($user, Permission::ACCOUNTING_VIEW);
    }

    /**
     * Determine whether the user can create an account.
     */
    public function create(User $user): bool
    {
        return $this->permissionService->has($user, Permission::ACCOUNTING_POST);
    }

    /**
     * Determine whether the user can update an account.
     */
    public function update(User $user, Account $account): bool
    {
        return $this->permissionService->has($user, Permission::ACCOUNTING_POST);
    }

    /**
     * Determine whether the user can delete an account.
     */
    public function delete(User $user, Account $account): bool
    {
        if ($account->is_system) {
            return false;
        }

        return $this->permissionService->has($user, Permission::ACCOUNTING_POST);
    }
}
