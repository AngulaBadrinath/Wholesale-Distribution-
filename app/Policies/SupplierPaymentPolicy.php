<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\SupplierPaymentStatus;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\ResourceScopeService;

class SupplierPaymentPolicy
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ResourceScopeService $resourceScopeService
    ) {}

    /**
     * Determine whether the user can view supplier payments.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::PAYABLE_VIEW);
    }

    /**
     * Determine whether the user can view a specific supplier payment.
     */
    public function view(User $user, SupplierPayment $payment): bool
    {
        return $this->permissionService->has($user, Permission::PAYABLE_VIEW);
    }

    /**
     * Determine whether the user can create supplier payments.
     */
    public function create(User $user): bool
    {
        return $this->permissionService->has($user, Permission::PAYABLE_MANAGE);
    }

    /**
     * Determine whether the user can reverse a completed supplier payment.
     */
    public function reverse(User $user, SupplierPayment $payment): bool
    {
        return $this->permissionService->has($user, Permission::PAYABLE_MANAGE)
            && $payment->status === SupplierPaymentStatus::COMPLETED;
    }

    /**
     * Supplier payments are immutable and cannot be updated.
     */
    public function update(User $user, SupplierPayment $payment): bool
    {
        return false;
    }

    /**
     * Supplier payments are permanent records and cannot be deleted.
     */
    public function delete(User $user, SupplierPayment $payment): bool
    {
        return false;
    }
}
