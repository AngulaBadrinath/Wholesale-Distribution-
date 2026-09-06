<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\User;
use App\Services\Auth\PermissionService;

class OrderAdjustmentPolicy
{
    public function __construct(
        protected PermissionService $permissionService,
    ) {}

    /**
     * Determine whether the user can browse the administrative adjustment queue.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::ORDER_ADJUST_REVIEW);
    }

    /**
     * Determine whether the user can inspect a specific adjustment.
     */
    public function view(User $user, OrderAdjustment $adjustment): bool
    {
        return $this->permissionService->has($user, Permission::ORDER_ADJUST_REVIEW);
    }

    /**
     * Determine whether the user can review a specific adjustment in the review workspace.
     */
    public function review(User $user, OrderAdjustment $adjustment, ?Order $order = null): bool
    {
        if (! $this->permissionService->has($user, Permission::ORDER_ADJUST_REVIEW)) {
            return false;
        }

        if ($order !== null && (int) $adjustment->order_id !== (int) $order->id) {
            return false;
        }

        return true;
    }
}
