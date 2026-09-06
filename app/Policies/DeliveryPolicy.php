<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Delivery;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\ResourceScopeService;

class DeliveryPolicy
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ResourceScopeService $resourceScopeService,
    ) {}

    /**
     * Determine whether the user can view any delivery listings.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::DELIVERY_VIEW)
            || $this->permissionService->has($user, Permission::DELIVERY_UPDATE);
    }

    /**
     * Determine whether the user can view the specific delivery.
     * Enforces resource scoping for delivery drivers.
     */
    public function view(User $user, Delivery $delivery): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->resourceScopeService->canAccessDelivery($user, $delivery);
    }

    /**
     * Determine whether the user can confirm warehouse pickup.
     */
    public function pickup(User $user, Delivery $delivery): bool
    {
        if (! $this->permissionService->has($user, Permission::DELIVERY_UPDATE)) {
            return false;
        }

        return $this->resourceScopeService->canAccessDelivery($user, $delivery);
    }

    /**
     * Determine whether the user can start out-for-delivery route.
     */
    public function startRoute(User $user, Delivery $delivery): bool
    {
        if (! $this->permissionService->has($user, Permission::DELIVERY_UPDATE)) {
            return false;
        }

        return $this->resourceScopeService->canAccessDelivery($user, $delivery);
    }

    /**
     * Determine whether the user can complete delivery and submit proof of delivery.
     */
    public function complete(User $user, Delivery $delivery): bool
    {
        if (! $this->permissionService->has($user, Permission::DELIVERY_UPDATE)) {
            return false;
        }

        return $this->resourceScopeService->canAccessDelivery($user, $delivery);
    }

    /**
     * Determine whether the user can record delivery failure.
     */
    public function fail(User $user, Delivery $delivery): bool
    {
        if (! $this->permissionService->has($user, Permission::DELIVERY_UPDATE)) {
            return false;
        }

        return $this->resourceScopeService->canAccessDelivery($user, $delivery);
    }

    /**
     * Determine whether the user can reschedule delivery.
     */
    public function reschedule(User $user, Delivery $delivery): bool
    {
        if (! $this->permissionService->has($user, Permission::DELIVERY_UPDATE)) {
            return false;
        }

        return $this->resourceScopeService->canAccessDelivery($user, $delivery);
    }

    /**
     * Determine whether the user can return undelivered shipment to warehouse custody.
     */
    public function returnToWarehouse(User $user, Delivery $delivery): bool
    {
        if (! $this->permissionService->has($user, Permission::DELIVERY_UPDATE)) {
            return false;
        }

        return $this->resourceScopeService->canAccessDelivery($user, $delivery);
    }

    /**
     * Determine whether the user can assign or reassign deliveries.
     */
    public function assign(User $user): bool
    {
        return $this->permissionService->has($user, Permission::DELIVERY_ASSIGN);
    }
}
