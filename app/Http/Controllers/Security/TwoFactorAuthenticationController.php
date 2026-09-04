<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\MfaPolicy;
use App\Services\Auth\SessionRevocationService;
use App\Services\Auth\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorAuthenticationController extends Controller
{
    public function __construct(
        protected TwoFactorAuthenticationService $twoFactorService,
        protected MfaPolicy $mfaPolicy,
        protected SessionRevocationService $sessionRevocationService
    ) {}

    /**
     * Display the MFA security settings page.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Security/MFA/Index', [
            'enabled' => $user->hasMfaEnabled(),
            'mandatory' => $user->requiresMfa(),
            'can_disable' => $this->mfaPolicy->canDisableMfa($user),
            'recovery_codes_count' => $this->twoFactorService->getUnconsumedRecoveryCodesCount($user),
            'setup_data' => session('mfa_setup_data'),
            'recovery_codes' => session('recovery_codes'),
            'status' => session('status'),
        ]);
    }

    /**
     * Initiate MFA enrollment with step-up password confirmation.
     * Generates a pending secret and local SVG QR code (session only, not yet saved to user).
     */
    public function enable(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $secret = $this->twoFactorService->generateSecretKey();
        $request->session()->put('mfa.pending_secret', $secret);

        $qrCodeSvg = $this->twoFactorService->generateQrCodeSvg($user, $secret);
        $manualKey = $this->twoFactorService->formatSecretForManualEntry($secret);

        Log::info('auth.security_event', [
            'action' => 'MFA_ENROLLMENT_STARTED',
            'user_id' => $user->id,
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);

        return redirect()->route('mfa.index')->with('mfa_setup_data', [
            'qr_code_svg' => $qrCodeSvg,
            'manual_key' => $manualKey,
        ]);
    }

    /**
     * Confirm MFA enrollment by verifying a 6-digit TOTP code.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $throttleKey = 'mfa-confirm:' . Str::transliterate($request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'code' => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ])->status(429);
        }

        $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $pendingSecret = (string) $request->session()->get('mfa.pending_secret');

        if (empty($pendingSecret)) {
            throw ValidationException::withMessages([
                'code' => 'MFA setup session has expired. Please start setup again.',
            ]);
        }

        $code = (string) $request->input('code');

        if (! $this->twoFactorService->verifyCode($pendingSecret, $code)) {
            RateLimiter::hit($throttleKey, 60);

            Log::warning('auth.security_event', [
                'action' => 'MFA_ENROLLMENT_FAILED',
                'user_id' => $request->user()->id,
                'ip' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);

            throw ValidationException::withMessages([
                'code' => 'The provided two-factor authentication code is invalid.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->forget('mfa.pending_secret');

        /** @var User $user */
        $user = $request->user();
        $user->two_factor_secret = $pendingSecret;
        $user->two_factor_confirmed_at = now();
        $user->save();

        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes($user);

        // Revoke other active sessions for security while preserving current session
        $this->sessionRevocationService->revokeUserSessionsForSecurityEvent(
            $user,
            'mfa_enabled',
            $request->session()->getId()
        );

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

        return redirect()->route('mfa.index')
            ->with('recovery_codes', $recoveryCodes)
            ->with('status', 'Two-factor authentication has been enabled successfully.');
    }

    /**
     * Disable MFA with step-up password confirmation.
     * Privileged users where MFA is mandatory CANNOT disable MFA (enforced server-side).
     */
    public function disable(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! $this->mfaPolicy->canDisableMfa($user)) {
            abort(403, 'Multi-Factor Authentication is mandatory for your role and cannot be disabled.');
        }

        $user->two_factor_secret = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        DB::table('two_factor_recovery_codes')
            ->where('user_id', $user->id)
            ->delete();

        // Revoke other sessions while preserving current session
        $this->sessionRevocationService->revokeUserSessionsForSecurityEvent(
            $user,
            'mfa_disabled',
            $request->session()->getId()
        );

        Log::info('auth.security_event', [
            'action' => 'MFA_DISABLED',
            'user_id' => $user->id,
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);

        return redirect()->route('mfa.index')
            ->with('status', 'Two-factor authentication has been disabled.');
    }

    /**
     * Regenerate recovery codes with step-up password confirmation.
     */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! $user->hasMfaEnabled()) {
            abort(400, 'Two-factor authentication must be enabled to regenerate recovery codes.');
        }

        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes($user);

        // Revoke other sessions on credential rotation
        $this->sessionRevocationService->revokeUserSessionsForSecurityEvent(
            $user,
            'mfa_recovery_codes_regenerated',
            $request->session()->getId()
        );

        Log::info('auth.security_event', [
            'action' => 'MFA_RECOVERY_CODES_REGENERATED',
            'user_id' => $user->id,
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);

        return redirect()->route('mfa.index')
            ->with('recovery_codes', $recoveryCodes)
            ->with('status', 'New recovery codes have been generated. Old codes are now invalid.');
    }
}
