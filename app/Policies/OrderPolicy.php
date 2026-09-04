<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Auth\PermissionService;

class OrderPolicy
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Determine whether the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::ORDER_VIEW);
    }

    /**
     * Determine whether the user can view the specific order.
     * Enforces resource scoping for salesmen.
     */
    public function view(User $user, Order $order): bool
    {
        if (! $this->permissionService->has($user, Permission::ORDER_VIEW)) {
            return false;
        }

        if ($user->role === UserRole::SALESMAN) {
            return $order->salesman_id === $user->id;
        }

        return true;
    }

    /**
     * Determine whether the user can create new orders.
     */
    public function create(User $user): bool
    {
        return $this->permissionService->has($user, Permission::ORDER_CREATE);
    }

    /**
     * Determine whether the user can submit drafted orders.
     */
    public function submit(User $user): bool
    {
        return $this->permissionService->has($user, Permission::ORDER_SUBMIT);
    }
}
