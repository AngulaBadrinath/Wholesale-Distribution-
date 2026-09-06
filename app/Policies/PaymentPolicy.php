<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\ResourceScopeService;

class PaymentPolicy
{
    public function __construct(
        protected PermissionService $permissionService,
        protected ResourceScopeService $resourceScopeService,
    ) {}

    /**
     * Determine whether the user can browse payments.
     */
    public function viewAny(User $user): bool
    {
        return $this->permissionService->has($user, Permission::PAYMENT_VIEW)
            || $this->permissionService->has($user, Permission::PAYMENT_CREATE);
    }

    /**
     * Determine whether the user can view a specific payment record.
     */
    public function view(User $user, Payment $payment): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $this->resourceScopeService->canAccessPayment($user, $payment);
    }

    /**
     * Determine whether the user can record a payment entry.
     */
    public function create(User $user): bool
    {
        return $this->permissionService->has($user, Permission::PAYMENT_CREATE);
    }

    /**
     * Determine whether the user can verify and reconcile a payment.
     * Segregation of Duties / Maker-Checker: The user who recorded the payment cannot verify it,
     * unless they hold the SUPER_ADMIN role.
     */
    public function verify(User $user, Payment $payment): bool
    {
        if (! $this->permissionService->has($user, Permission::PAYMENT_VERIFY)) {
            return false;
        }

        if ((int) $payment->recorded_by === (int) $user->id) {
            return $user->role === UserRole::SUPER_ADMIN;
        }

        return true;
    }

    /**
     * Determine whether the user can reject a pending payment.
     * Segregation of Duties / Maker-Checker: The user who recorded the payment cannot reject it,
     * unless they hold the SUPER_ADMIN role.
     */
    public function reject(User $user, Payment $payment): bool
    {
        if (! $this->permissionService->has($user, Permission::PAYMENT_VERIFY)) {
            return false;
        }

        if ((int) $payment->recorded_by === (int) $user->id) {
            return $user->role === UserRole::SUPER_ADMIN;
        }

        return true;
    }

    /**
     * Determine whether the user can correct and resubmit a rejected payment.
     */
    public function correct(User $user, Payment $payment): bool
    {
        if (! $this->permissionService->has($user, Permission::PAYMENT_CREATE)) {
            return false;
        }

        return $this->resourceScopeService->canAccessPayment($user, $payment);
    }

    /**
     * Determine whether the user can authoritatively reverse a verified payment.
     * Segregation of Duties / Maker-Checker: The user who recorded the payment cannot reverse it,
     * unless they hold the SUPER_ADMIN role.
     */
    public function reverse(User $user, Payment $payment): bool
    {
        if (! $this->permissionService->has($user, Permission::PAYMENT_REVERSE)) {
            return false;
        }

        if ((int) $payment->recorded_by === (int) $user->id) {
            return $user->role === UserRole::SUPER_ADMIN;
        }

        return true;
    }

    /**
     * Determine whether the user can preview or stream payment evidence.
     */
    public function streamEvidence(User $user, Payment $payment): bool
    {
        return $this->view($user, $payment);
    }
}
