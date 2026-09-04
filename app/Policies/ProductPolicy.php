<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Product;
use App\Models\User;
use App\Services\Auth\PermissionService;

class ProductPolicy
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Determine whether the user can view any products.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::PRODUCT_VIEW);
    }

    /**
     * Determine whether the user can view the product.
     */
    public function view(User $user, ?Product $product = null): bool
    {
        return $this->permissionService->has($user, Permission::PRODUCT_VIEW);
    }

    /**
     * Determine whether the user can create products.
     */
    public function create(User $user): bool
    {
        return $this->permissionService->has($user, Permission::PRODUCT_CREATE);
    }

    /**
     * Determine whether the user can update the product metadata.
     */
    public function update(User $user, ?Product $product = null): bool
    {
        return $this->permissionService->has($user, Permission::PRODUCT_UPDATE);
    }

    /**
     * Determine whether the user can update product commercial prices.
     */
    public function updatePrice(User $user, ?Product $product = null): bool
    {
        return $this->permissionService->has($user, Permission::PRODUCT_PRICE_UPDATE);
    }

    /**
     * Determine whether the user can update product tax configuration.
     */
    public function updateTax(User $user, ?Product $product = null): bool
    {
        return $this->permissionService->has($user, Permission::PRODUCT_TAX_UPDATE);
    }

    /**
     * Determine whether the user can delete the product.
     * Deletions are prohibited to preserve historical referential transaction integrity.
     */
    public function delete(User $user, ?Product $product = null): bool
    {
        return false;
    }
}
