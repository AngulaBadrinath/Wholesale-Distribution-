<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\ResourceScopeService;

class JournalEntryPolicy
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ResourceScopeService $resourceScopeService
    ) {}

    /**
     * Determine whether the user can browse journal entries.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::ACCOUNTING_VIEW);
    }

    /**
     * Determine whether the user can view a specific journal entry.
     */
    public function view(User $user, JournalEntry $journal): bool
    {
        return $this->permissionService->has($user, Permission::ACCOUNTING_VIEW);
    }

    /**
     * Determine whether the user can create manual journal entries.
     */
    public function create(User $user): bool
    {
        return $this->permissionService->has($user, Permission::ACCOUNTING_POST);
    }

    /**
     * Determine whether the user can reverse a journal entry.
     */
    public function reverse(User $user, JournalEntry $journal): bool
    {
        return $this->permissionService->has($user, Permission::ACCOUNTING_REVERSE);
    }

    /**
     * Posted journals cannot be directly updated in place.
     */
    public function update(User $user, JournalEntry $journal): bool
    {
        return false;
    }

    /**
     * Posted journals cannot be deleted.
     */
    public function delete(User $user, JournalEntry $journal): bool
    {
        return false;
    }
}
