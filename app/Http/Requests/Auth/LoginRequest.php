<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials with throttling and account-state enforcement.
     *
     * @return array<string, mixed>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): array
    {
        $this->ensureIsNotRateLimited();

        $credentials = $this->only('email', 'password');

        if (! Auth::validate($credentials)) {
            RateLimiter::hit($this->throttleKey(), 60);

            Log::warning('Authentication failure: invalid credentials', [
                'email' => $this->input('email'),
                'ip' => $this->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $user = User::where('email', $this->input('email'))->first();

        if (! $user || ! $user->canAuthenticate()) {
            RateLimiter::hit($this->throttleKey(), 60);

            Log::warning('Authentication failure: inactive account state', [
                'user_id' => $user?->id,
                'status' => $user?->status?->value ?? 'unknown',
                'ip' => $this->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => trans('auth.unavailable'),
            ]);
        }

        $hasMfa = $user->hasMfaEnabled();
        $requiresMfa = $user->requiresMfa();

        RateLimiter::clear($this->throttleKey());

        if ($hasMfa || $requiresMfa) {
            $requiresSetup = ! $hasMfa && $requiresMfa;

            $this->session()->put('mfa.challenge', [
                'user_id' => $user->id,
                'remember' => $this->boolean('remember'),
                'requires_setup' => $requiresSetup,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(5)->timestamp,
            ]);

            Log::info('auth.security_event', [
                'action' => 'MFA_CHALLENGE_INITIATED',
                'user_id' => $user->id,
                'requires_setup' => $requiresSetup,
                'ip' => $this->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return [
                'mfa_required' => true,
                'requires_setup' => $requiresSetup,
                'user' => $user,
            ];
        }

        Auth::login($user, $this->boolean('remember'));

        Log::info('User authenticated successfully', [
            'user_id' => $user->id,
            'ip' => $this->ip(),
        ]);

        return [
            'mfa_required' => false,
            'requires_setup' => false,
            'user' => $user,
        ];
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        Log::warning('Authentication throttled: too many attempts', [
            'throttle_key' => $this->throttleKey(),
            'ip' => $this->ip(),
            'available_in_seconds' => $seconds,
        ]);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ])->status(429);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
