<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\SupplierBillStatus;
use App\Models\SupplierBill;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\ResourceScopeService;

class SupplierBillPolicy
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ResourceScopeService $resourceScopeService
    ) {}

    /**
     * Determine whether the user can view supplier bills.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::PAYABLE_VIEW);
    }

    /**
     * Determine whether the user can view a specific supplier bill.
     */
    public function view(User $user, SupplierBill $bill): bool
    {
        return $this->permissionService->has($user, Permission::PAYABLE_VIEW);
    }

    /**
     * Determine whether the user can create supplier bills.
     */
    public function create(User $user): bool
    {
        return $this->permissionService->has($user, Permission::PAYABLE_MANAGE);
    }

    /**
     * Determine whether the user can update a supplier bill.
     */
    public function update(User $user, SupplierBill $bill): bool
    {
        return $this->permissionService->has($user, Permission::PAYABLE_MANAGE)
            && $bill->status === SupplierBillStatus::DRAFT;
    }

    /**
     * Determine whether the user can post a draft supplier bill.
     */
    public function post(User $user, SupplierBill $bill): bool
    {
        return $this->permissionService->has($user, Permission::PAYABLE_MANAGE)
            && $bill->status === SupplierBillStatus::DRAFT;
    }

    /**
     * Determine whether the user can delete a draft supplier bill.
     */
    public function delete(User $user, SupplierBill $bill): bool
    {
        return $this->permissionService->has($user, Permission::PAYABLE_MANAGE)
            && $bill->status === SupplierBillStatus::DRAFT;
    }
}
