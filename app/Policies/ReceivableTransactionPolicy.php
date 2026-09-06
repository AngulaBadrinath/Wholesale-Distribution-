<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ReceivableTransaction;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\ResourceScopeService;

class ReceivableTransactionPolicy
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ResourceScopeService $resourceScopeService
    ) {}

    /**
     * Determine whether the user can browse accounts receivable.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::RECEIVABLE_VIEW);
    }

    /**
     * Determine whether the user can view a specific receivable transaction.
     */
    public function view(User $user, ReceivableTransaction $transaction): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->resourceScopeService->canAccessCustomerReceivables($user, $transaction->customer_id);
    }

    /**
     * Creation is system-driven via authoritative business events.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Receivable transactions are strictly immutable financial ledger entries.
     */
    public function update(User $user, ReceivableTransaction $transaction): bool
    {
        return false;
    }

    /**
     * Receivable transactions are strictly permanent financial records.
     */
    public function delete(User $user, ReceivableTransaction $transaction): bool
    {
        return false;
    }
}
