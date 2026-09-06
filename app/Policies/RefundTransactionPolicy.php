<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\RefundTransaction;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\ResourceScopeService;

class RefundTransactionPolicy
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ResourceScopeService $resourceScopeService
    ) {}

    /**
     * Determine whether the user can browse refund transactions.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::REFUND_APPROVE)
            || $this->permissionService->has($user, Permission::ACCOUNTING_VIEW);
    }

    /**
     * Determine whether the user can view a specific refund transaction.
     */
    public function view(User $user, RefundTransaction $transaction): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->resourceScopeService->canAccessRefundTransaction($user, $transaction);
    }

    /**
     * Direct creation is blocked; transactions are created via the authoritative workflow service.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Refund transactions are immutable financial history.
     */
    public function update(User $user, RefundTransaction $transaction): bool
    {
        return false;
    }

    /**
     * Refund transactions are permanent records and cannot be deleted.
     */
    public function delete(User $user, RefundTransaction $transaction): bool
    {
        return false;
    }
}
