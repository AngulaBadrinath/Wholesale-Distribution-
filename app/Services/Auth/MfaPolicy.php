<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\User;

class MfaPolicy
{
    /**
     * Determine whether Multi-Factor Authentication is mandatory for the given user.
     *
     * Per PRD/Security spec and FEAT-AUTH-004:
     * - SUPER_ADMIN: Mandatory
     * - ADMIN: Mandatory in production
     * - ACCOUNTANT: Mandatory in production
     * - Standard roles: Optional/configurable
     */
    public function isMfaRequired(User $user): bool
    {
        if ($user->role instanceof UserRole) {
            return $user->role->isPrivileged();
        }

        return false;
    }

    /**
     * Determine whether the user is permitted to disable Multi-Factor Authentication.
     *
     * Mandatory privileged roles (Super Admin, Admin, Accountant) cannot disable MFA.
     */
    public function canDisableMfa(User $user): bool
    {
        return ! $this->isMfaRequired($user);
    }

    /**
     * Determine whether the user is permitted to enroll in Multi-Factor Authentication.
     *
     * All active accounts are permitted to enroll in MFA.
     */
    public function canEnableMfa(User $user): bool
    {
        return $user->canAuthenticate();
    }
}
