<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MfaChallengeRequest;
use App\Models\User;
use App\Services\Auth\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MfaChallengeController extends Controller
{
    public function __construct(
        protected TwoFactorAuthenticationService $twoFactorService
    ) {}

    /**
     * Display the MFA challenge view.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $challenge = $request->session()->get('mfa.challenge');

        if (! is_array($challenge) || empty($challenge['user_id']) || ($challenge['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('mfa.challenge');

            return redirect()->route('login')->with('status', 'Your session has expired. Please log in again.');
        }

        $user = User::find($challenge['user_id']);

        if (! $user || ! $user->canAuthenticate()) {
            $request->session()->forget('mfa.challenge');

            return redirect()->route('login')->with('status', 'Authentication is unavailable for this account.');
        }

        $requiresSetup = (bool) ($challenge['requires_setup'] ?? false);
        $qrCodeSvg = null;
        $manualKey = null;

        if ($requiresSetup) {
            if (empty($challenge['pending_secret'])) {
                $challenge['pending_secret'] = $this->twoFactorService->generateSecretKey();
                $request->session()->put('mfa.challenge', $challenge);
            }

            $qrCodeSvg = $this->twoFactorService->generateQrCodeSvg($user, $challenge['pending_secret']);
            $manualKey = $this->twoFactorService->formatSecretForManualEntry($challenge['pending_secret']);
        }

        return Inertia::render('Auth/MfaChallenge', [
            'requires_setup' => $requiresSetup,
            'qr_code_svg' => $qrCodeSvg,
            'manual_key' => $manualKey,
        ]);
    }

    /**
     * Handle verification of the MFA challenge.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(MfaChallengeRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $challenge = $request->session()->get('mfa.challenge');

        if (! is_array($challenge) || empty($challenge['user_id']) || ($challenge['expires_at'] ?? 0) < now()->timestamp) {
            RateLimiter::hit($request->throttleKey(), 60);
            $request->session()->forget('mfa.challenge');

            throw ValidationException::withMessages([
                'code' => 'Your authentication challenge has expired. Please log in again.',
            ]);
        }

        $attempts = (int) ($challenge['attempts'] ?? 0);

        if ($attempts >= 5) {
            $request->session()->forget('mfa.challenge');
            RateLimiter::hit($request->throttleKey(), 60);

            Log::warning('auth.security_event', [
                'action' => 'MFA_CHALLENGE_LOCKED',
                'user_id' => $challenge['user_id'],
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);

            throw ValidationException::withMessages([
                'code' => 'Too many failed verification attempts. Please log in again.',
            ])->status(429);
        }

        $user = User::find($challenge['user_id']);

        if (! $user || ! $user->canAuthenticate()) {
            $request->session()->forget('mfa.challenge');

            throw ValidationException::withMessages([
                'code' => trans('auth.unavailable'),
            ]);
        }

        $recoveryCode = $request->input('recovery_code');

        // Flow 1: Recovery Code Fallback
        if (! empty($recoveryCode)) {
            if ($challenge['requires_setup'] ?? false) {
                $challenge['attempts'] = $attempts + 1;
                $request->session()->put('mfa.challenge', $challenge);

                throw ValidationException::withMessages([
                    'recovery_code' => 'Recovery codes cannot be used during initial enrollment.',
                ]);
            }

            $valid = $this->twoFactorService->verifyAndConsumeRecoveryCode($user, $recoveryCode);

            if (! $valid) {
                RateLimiter::hit($request->throttleKey(), 60);
                $challenge['attempts'] = $attempts + 1;
                $request->session()->put('mfa.challenge', $challenge);

                Log::warning('auth.security_event', [
                    'action' => 'MFA_CHALLENGE_FAILED',
                    'user_id' => $user->id,
                    'type' => 'recovery_code',
                    'ip' => $request->ip(),
                    'timestamp' => now()->toIso8601String(),
                ]);

                throw ValidationException::withMessages([
                    'recovery_code' => 'The provided recovery code is invalid or has already been used.',
                ]);
            }

            RateLimiter::clear($request->throttleKey());
            $request->session()->forget('mfa.challenge');

            Log::info('auth.security_event', [
                'action' => 'MFA_RECOVERY_CODE_USED',
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);

            Auth::loginUsingId($user->id, (bool) ($challenge['remember'] ?? false));
            $request->session()->regenerate();

            return redirect()->intended('/dashboard');
        }

        // Flow 2: 6-Digit TOTP Code
        $code = (string) $request->input('code');
        $secret = ($challenge['requires_setup'] ?? false)
            ? ($challenge['pending_secret'] ?? '')
            : ($user->two_factor_secret ?? '');

        $valid = $this->twoFactorService->verifyCode($secret, $code);

        if (! $valid) {
            RateLimiter::hit($request->throttleKey(), 60);
            $challenge['attempts'] = $attempts + 1;
            $request->session()->put('mfa.challenge', $challenge);

            Log::warning('auth.security_event', [
                'action' => 'MFA_CHALLENGE_FAILED',
                'user_id' => $user->id,
                'type' => 'totp',
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);

            throw ValidationException::withMessages([
                'code' => 'The provided two-factor authentication code is invalid.',
            ]);
        }

        RateLimiter::clear($request->throttleKey());
        $request->session()->forget('mfa.challenge');

        if ($challenge['requires_setup'] ?? false) {
            $user->two_factor_secret = $secret;
            $user->two_factor_confirmed_at = now();
            $user->save();

            $recoveryCodes = $this->twoFactorService->generateRecoveryCodes($user);

            Log::info('auth.security_event', [
                'action' => 'MFA_ENROLLMENT_CONFIRMED',
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);

            Log::info('auth.security_event', [
                'action' => 'MFA_ENABLED',
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);

            $request->session()->flash('recovery_codes', $recoveryCodes);
        }

        Log::info('auth.security_event', [
            'action' => 'MFA_CHALLENGE_SUCCESS',
            'user_id' => $user->id,
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);

        Auth::loginUsingId($user->id, (bool) ($challenge['remember'] ?? false));
        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }
}
