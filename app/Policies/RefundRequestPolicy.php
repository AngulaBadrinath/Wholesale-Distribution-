<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\RefundRequest;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\ResourceScopeService;

class RefundRequestPolicy
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ResourceScopeService $resourceScopeService
    ) {}

    /**
     * Determine whether the user can browse refund requests.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::REFUND_REQUEST)
            || $this->permissionService->has($user, Permission::REFUND_APPROVE)
            || $this->permissionService->has($user, Permission::ACCOUNTING_VIEW);
    }

    /**
     * Determine whether the user can view a specific refund request.
     */
    public function view(User $user, RefundRequest $refundRequest): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->resourceScopeService->canAccessRefundRequest($user, $refundRequest);
    }

    /**
     * Determine whether the user can create a refund request.
     */
    public function create(User $user): bool
    {
        return $this->permissionService->has($user, Permission::REFUND_REQUEST);
    }

    /**
     * Determine whether the user can review a refund request.
     */
    public function review(User $user, RefundRequest $refundRequest): bool
    {
        return $this->permissionService->has($user, Permission::REFUND_APPROVE);
    }

    /**
     * Determine whether the user can approve a refund request.
     * Segregation of Duties / Maker-Checker: The user who created the refund request cannot approve it,
     * unless they hold the SUPER_ADMIN role for emergency administrative resolution.
     */
    public function approve(User $user, RefundRequest $refundRequest): bool
    {
        if (! $this->permissionService->has($user, Permission::REFUND_APPROVE)) {
            return false;
        }

        if ((int) $refundRequest->requested_by === (int) $user->id) {
            return $user->role === UserRole::SUPER_ADMIN;
        }

        return true;
    }

    /**
     * Determine whether the user can reject a refund request.
     * Segregation of Duties / Maker-Checker: The user who created the refund request cannot reject it,
     * unless they hold the SUPER_ADMIN role.
     */
    public function reject(User $user, RefundRequest $refundRequest): bool
    {
        if (! $this->permissionService->has($user, Permission::REFUND_APPROVE)) {
            return false;
        }

        if ((int) $refundRequest->requested_by === (int) $user->id) {
            return $user->role === UserRole::SUPER_ADMIN;
        }

        return true;
    }

    /**
     * Determine whether the user can cancel a refund request.
     */
    public function cancel(User $user, RefundRequest $refundRequest): bool
    {
        if ((int) $refundRequest->requested_by === (int) $user->id) {
            return true;
        }

        return $user->role === UserRole::SUPER_ADMIN || $user->role === UserRole::ADMIN;
    }

    /**
     * Determine whether the user can authoritatively process / disburse a refund.
     */
    public function process(User $user, RefundRequest $refundRequest): bool
    {
        return $this->permissionService->has($user, Permission::REFUND_APPROVE);
    }

    /**
     * Refund requests are permanent financial audit records.
     */
    public function delete(User $user, RefundRequest $refundRequest): bool
    {
        return false;
    }
}
