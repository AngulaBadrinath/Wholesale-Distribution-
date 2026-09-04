<?php

namespace App\Http\Middleware;

use App\Enums\AccountStatus;
use App\Services\Auth\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions  One or more permissions. If multiple, user must possess at least one.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        // 1. Unauthenticated check
        if (! $user) {
            if ($request->expectsJson()) {
                abort(401, 'Unauthenticated.');
            }

            return redirect()->route('login');
        }

        // 2. Active account lifecycle check
        $isActive = ($user->status instanceof AccountStatus)
            ? $user->status === AccountStatus::ACTIVE
            : $user->status === AccountStatus::ACTIVE->value;

        if (! $isActive) {
            abort(403, 'Your account is not active.');
        }

        // 3. Normalize permissions list (supports variadic args or comma-separated)
        $flattenedPermissions = [];
        foreach ($permissions as $perm) {
            foreach (explode(',', $perm) as $single) {
                $trimmed = trim($single);
                if ($trimmed !== '') {
                    $flattenedPermissions[] = $trimmed;
                }
            }
        }

        if (empty($flattenedPermissions)) {
            abort(403, 'No permission specified for this route.');
        }

        // 4. Authoritative evaluation via PermissionService (default deny)
        if (! $this->permissionService->hasAny($user, $flattenedPermissions)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
