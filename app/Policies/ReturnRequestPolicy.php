<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\ResourceScopeService;

class ReturnRequestPolicy
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ResourceScopeService $resourceScopeService,
    ) {}

    /**
     * Determine whether the user can browse return requests.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::RETURN_REVIEW)
            || $this->permissionService->has($user, Permission::RETURN_REQUEST)
            || $this->permissionService->has($user, Permission::RETURN_APPROVE);
    }

    /**
     * Determine whether the user can view a specific return request.
     */
    public function view(User $user, ReturnRequest $returnRequest): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->resourceScopeService->canAccessReturn($user, $returnRequest);
    }

    /**
     * Determine whether the user can create a return request.
     */
    public function create(User $user): bool
    {
        return $this->permissionService->has($user, Permission::RETURN_REQUEST);
    }

    /**
     * Determine whether the user can perform physical warehouse inspection on a return.
     */
    public function inspect(User $user, ReturnRequest $returnRequest): bool
    {
        return $this->permissionService->has($user, Permission::RETURN_REVIEW);
    }

    /**
     * Determine whether the user can approve a return request.
     * Segregation of Duties / Maker-Checker: The user who created the return request cannot approve it,
     * unless they hold the SUPER_ADMIN role for emergency administrative resolution.
     */
    public function approve(User $user, ReturnRequest $returnRequest): bool
    {
        if (! $this->permissionService->has($user, Permission::RETURN_APPROVE)) {
            return false;
        }

        if ((int) $returnRequest->created_by === (int) $user->id) {
            return $user->role === UserRole::SUPER_ADMIN;
        }

        return true;
    }

    /**
     * Determine whether the user can reject a return request.
     * Segregation of Duties / Maker-Checker: The user who created the return request cannot reject it,
     * unless they hold the SUPER_ADMIN role.
     */
    public function reject(User $user, ReturnRequest $returnRequest): bool
    {
        if (! $this->permissionService->has($user, Permission::RETURN_APPROVE)) {
            return false;
        }

        if ((int) $returnRequest->created_by === (int) $user->id) {
            return $user->role === UserRole::SUPER_ADMIN;
        }

        return true;
    }

    /**
     * Determine whether the user can cancel a return request.
     */
    public function cancel(User $user, ReturnRequest $returnRequest): bool
    {
        if ((int) $returnRequest->created_by === (int) $user->id) {
            return $this->permissionService->has($user, Permission::RETURN_REQUEST)
                || $this->permissionService->has($user, Permission::RETURN_APPROVE);
        }

        return $this->permissionService->has($user, Permission::RETURN_APPROVE);
    }
}
