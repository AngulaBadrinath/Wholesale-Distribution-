<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PayableTransaction;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\ResourceScopeService;

class PayableTransactionPolicy
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ResourceScopeService $resourceScopeService
    ) {}

    /**
     * Determine whether the user can browse accounts payable.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::PAYABLE_VIEW);
    }

    /**
     * Determine whether the user can view a specific payable transaction.
     */
    public function view(User $user, PayableTransaction $transaction): bool
    {
        return $this->permissionService->has($user, Permission::PAYABLE_VIEW);
    }

    /**
     * Creation is system-driven via authoritative business events.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Payable transactions are strictly immutable financial ledger entries.
     */
    public function update(User $user, PayableTransaction $transaction): bool
    {
        return false;
    }

    /**
     * Payable transactions are strictly permanent financial records.
     */
    public function delete(User $user, PayableTransaction $transaction): bool
    {
        return false;
    }
}
