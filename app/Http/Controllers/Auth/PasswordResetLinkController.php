<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     */
    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();
        $request->hitRateLimiters();

        $user = User::where('email', $request->normalizedEmail())->first();

        // Only send reset link if the account exists and is in an eligible ACTIVE state
        if ($user && $user->canAuthenticate()) {
            Password::broker()->sendResetLink([
                'email' => $user->email,
            ]);

            Log::info('session.security_event', [
                'action' => 'PASSWORD_RESET_REQUESTED',
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);
        } elseif ($user) {
            // Ineligible account state (e.g. SUSPENDED, DISABLED, INVITED)
            Log::warning('session.security_event', [
                'action' => 'PASSWORD_RESET_REQUESTED',
                'user_id' => $user->id,
                'reason' => 'ineligible_account_status',
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);
        } else {
            // Unknown account
            Log::info('session.security_event', [
                'action' => 'PASSWORD_RESET_REQUESTED',
                'reason' => 'unknown_account',
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        // Always return the exact same generic message to prevent account enumeration
        return back()->with('status', trans('passwords.sent'));
    }
}
