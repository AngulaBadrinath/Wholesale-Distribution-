<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CreditNote;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\ResourceScopeService;

class CreditNotePolicy
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ResourceScopeService $resourceScopeService
    ) {}

    /**
     * Determine whether the user can browse credit notes.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::CREDIT_CREATE)
            || $this->permissionService->has($user, Permission::ACCOUNTING_VIEW)
            || $this->permissionService->has($user, Permission::CUSTOMER_VIEW);
    }

    /**
     * Determine whether the user can view a specific credit note.
     */
    public function view(User $user, CreditNote $creditNote): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->resourceScopeService->canAccessCreditNote($user, $creditNote);
    }

    /**
     * Determine whether the user can generate credit notes.
     */
    public function create(User $user): bool
    {
        return $this->permissionService->has($user, Permission::CREDIT_CREATE);
    }

    /**
     * Credit notes are immutable financial records.
     */
    public function update(User $user, CreditNote $creditNote): bool
    {
        return false;
    }

    /**
     * Credit notes are permanent financial records.
     */
    public function delete(User $user, CreditNote $creditNote): bool
    {
        return false;
    }
}
