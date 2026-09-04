<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Auth\SessionRevocationService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->query('email'),
            'token' => (string) $request->route('token'),
        ]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(ResetPasswordRequest $request, SessionRevocationService $sessionService): RedirectResponse
    {
        $request->ensureIsNotRateLimited();
        $request->hitRateLimiter();

        // Attempt password reset via Laravel Password broker
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $request->clearRateLimiter();

            $user = User::where('email', Str::transliterate(Str::lower((string) $request->input('email'))))->first();

            if ($user) {
                // Mandatory prior session invalidation across all devices
                $sessionService->revokeUserSessionsForSecurityEvent($user, 'password_reset');

                Log::info('session.security_event', [
                    'action' => 'PASSWORD_RESET_COMPLETED',
                    'user_id' => $user->id,
                    'ip' => $request->ip(),
                    'timestamp' => now()->toIso8601String(),
                ]);
            }

            return redirect()->route('login')->with(
                'status',
                trans($status) . ' Please sign in with your new password.'
            );
        }

        Log::warning('session.security_event', [
            'action' => 'PASSWORD_RESET_FAILED',
            'reason' => $status,
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);

        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }
}
