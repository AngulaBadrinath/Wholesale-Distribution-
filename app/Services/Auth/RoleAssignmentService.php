<?php

namespace App\Services\Auth;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RoleAssignmentService
{
    public function __construct(
        protected SessionRevocationService $sessionRevocationService
    ) {}

    /**
     * Authoritatively assign a role to a target user.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function assignRole(
        User $actor,
        User $target,
        UserRole $newRole,
        ?string $reason = null,
        ?string $ip = null
    ): User {
        if (! $actor->exists || ! $actor->id) {
            throw new AuthorizationException('Actor must be an authenticated user.');
        }

        return DB::transaction(function () use ($actor, $target, $newRole, $reason, $ip) {
            // Lock target row and re-read from database
            /** @var User $lockedTarget */
            $lockedTarget = User::where('id', $target->id)->lockForUpdate()->firstOrFail();

            // Lock actor row and re-read from database
            /** @var User $lockedActor */
            $lockedActor = User::where('id', $actor->id)->lockForUpdate()->firstOrFail();

            // 1. Actor must be ACTIVE
            if ($lockedActor->status !== AccountStatus::ACTIVE) {
                throw new AuthorizationException('Inactive users cannot perform role assignments.');
            }

            // 2. Actor must have SUPER_ADMIN or ADMIN role
            if (! $lockedActor->isSuperAdmin() && ! $lockedActor->isAdmin()) {
                throw new AuthorizationException('You do not have permission to assign roles.');
            }

            $oldRole = $lockedTarget->role;

            // 3. Last Super Admin guard: cannot demote the last remaining Super Admin
            if ($oldRole === UserRole::SUPER_ADMIN && $newRole !== UserRole::SUPER_ADMIN) {
                // Lock other Super Admin rows in PostgreSQL to prevent concurrent race conditions
                $remainingCount = User::where('role', UserRole::SUPER_ADMIN->value)
                    ->where('id', '!=', $lockedTarget->id)
                    ->lockForUpdate()
                    ->pluck('id')
                    ->count();

                if ($remainingCount < 1) {
                    throw ValidationException::withMessages([
                        'role' => ['The last remaining Super Administrator cannot be demoted.'],
                    ]);
                }
            }

            // 4. Self-role modification is prohibited (prevents self-escalation and evasion)
            if ($lockedActor->id === $lockedTarget->id) {
                throw new AuthorizationException('Users cannot modify their own role.');
            }

            // 5. Only SUPER_ADMIN can modify an existing SUPER_ADMIN
            if ($oldRole === UserRole::SUPER_ADMIN && ! $lockedActor->isSuperAdmin()) {
                throw new AuthorizationException('Only Super Administrators can modify a Super Administrator.');
            }

            // 6. Only SUPER_ADMIN can grant SUPER_ADMIN
            if ($newRole === UserRole::SUPER_ADMIN && ! $lockedActor->isSuperAdmin()) {
                throw new AuthorizationException('Only Super Administrators can grant the Super Administrator role.');
            }

            // 7. No-op check: if old role equals new role, do not transition, audit, or invalidate sessions
            if ($oldRole === $newRole) {
                return $lockedTarget;
            }

            // 8. Update role transactionally, preserving all other attributes
            $lockedTarget->role = $newRole;
            $lockedTarget->save();

            // 9. Invalidate target user's active sessions (actor's session is unaffected)
            $this->sessionRevocationService->revokeUserSessionsForSecurityEvent(
                $lockedTarget,
                'role_changed'
            );

            // 10. Audit event: auth.security_event with action ROLE_ASSIGNED
            Log::info('auth.security_event', [
                'action' => 'ROLE_ASSIGNED',
                'actor_id' => $lockedActor->id,
                'target_user_id' => $lockedTarget->id,
                'previous_role' => $oldRole?->value,
                'new_role' => $newRole->value,
                'reason' => $reason,
                'ip' => $ip ?? request()?->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return $lockedTarget;
        });
    }
}
