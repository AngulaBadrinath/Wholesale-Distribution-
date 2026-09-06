<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\ResourceScopeService;

class OrderAdjustmentPolicy
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ResourceScopeService $resourceScopeService,
    ) {}

    /**
     * Determine whether the user can browse the administrative adjustment queue.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::ORDER_ADJUST_REVIEW)
            || $this->permissionService->has($user, Permission::ORDER_ADJUST_REQUEST);
    }

    /**
     * Determine whether the user can inspect a specific adjustment.
     */
    public function view(User $user, OrderAdjustment $adjustment): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->resourceScopeService->canAccessAdjustment($user, $adjustment);
    }

    /**
     * Determine whether the user can review a specific adjustment in the review workspace.
     */
    public function review(User $user, OrderAdjustment $adjustment, ?Order $order = null): bool
    {
        if (! $this->permissionService->has($user, Permission::ORDER_ADJUST_REVIEW)) {
            return false;
        }

        if ($order !== null && ! $this->resourceScopeService->verifyOrderAdjustmentOwnership($adjustment, $order)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can approve a specific adjustment.
     */
    public function approve(User $user, OrderAdjustment $adjustment, ?Order $order = null): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if (! $this->permissionService->has($user, Permission::ORDER_ADJUST_APPROVE)) {
            return false;
        }

        if ($order !== null && ! $this->resourceScopeService->verifyOrderAdjustmentOwnership($adjustment, $order)) {
            return false;
        }

        // Maker-Checker: Requester cannot approve their own request unless Super Admin emergency override
        if ((int) $adjustment->requested_by === (int) $user->id) {
            return $user->role === UserRole::SUPER_ADMIN;
        }

        return true;
    }

    /**
     * Determine whether the user can reject a specific adjustment.
     */
    public function reject(User $user, OrderAdjustment $adjustment, ?Order $order = null): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if (! $this->permissionService->has($user, Permission::ORDER_ADJUST_APPROVE)) {
            return false;
        }

        if ($order !== null && ! $this->resourceScopeService->verifyOrderAdjustmentOwnership($adjustment, $order)) {
            return false;
        }

        // Maker-Checker: Requester cannot reject their own request unless Super Admin emergency override
        if ((int) $adjustment->requested_by === (int) $user->id) {
            return $user->role === UserRole::SUPER_ADMIN;
        }

        return true;
    }

    /**
     * Determine whether the user can apply a specific approved adjustment.
     */
    public function apply(User $user, OrderAdjustment $adjustment, ?Order $order = null): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if (! $this->permissionService->has($user, Permission::ORDER_ADJUST_APPLY)) {
            return false;
        }

        if ($order !== null && ! $this->resourceScopeService->verifyOrderAdjustmentOwnership($adjustment, $order)) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can reverse a specific applied adjustment.
     */
    public function reverse(User $user, OrderAdjustment $adjustment, ?Order $order = null): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if (! $this->permissionService->has($user, Permission::ORDER_ADJUST_REVERSE)) {
            return false;
        }

        if ($order !== null && ! $this->resourceScopeService->verifyOrderAdjustmentOwnership($adjustment, $order)) {
            return false;
        }

        // Maker-Checker: Requester cannot reverse their own request unless Super Admin emergency override
        if ((int) $adjustment->requested_by === (int) $user->id) {
            return $user->role === UserRole::SUPER_ADMIN;
        }

        return true;
    }
}
