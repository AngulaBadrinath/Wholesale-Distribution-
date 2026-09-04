<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ForgotPasswordRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * Get normalized email address.
     */
    public function normalizedEmail(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('email')));
    }

    /**
     * Get the rate limiting throttle key for the client IP address.
     */
    public function throttleKeyIp(): string
    {
        return 'forgot-password-ip:' . $this->ip();
    }

    /**
     * Get the rate limiting throttle key for the normalized email and IP combination.
     */
    public function throttleKeyEmail(): string
    {
        return 'forgot-password-email:' . $this->normalizedEmail() . '|' . $this->ip();
    }

    /**
     * Ensure the forgot password request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        // Check IP rate limit (5 attempts per minute)
        if (RateLimiter::tooManyAttempts($this->throttleKeyIp(), 5)) {
            $seconds = RateLimiter::availableIn($this->throttleKeyIp());

            Log::warning('Forgot password throttled: too many requests per IP', [
                'ip' => $this->ip(),
                'available_in_seconds' => $seconds,
            ]);

            throw ValidationException::withMessages([
                'email' => trans('passwords.throttled'),
            ])->status(429);
        }

        // Check Email + IP rate limit (3 attempts per minute)
        if (RateLimiter::tooManyAttempts($this->throttleKeyEmail(), 3)) {
            $seconds = RateLimiter::availableIn($this->throttleKeyEmail());

            Log::warning('Forgot password throttled: too many requests per email/IP combination', [
                'ip' => $this->ip(),
                'available_in_seconds' => $seconds,
            ]);

            throw ValidationException::withMessages([
                'email' => trans('passwords.throttled'),
            ])->status(429);
        }
    }

    /**
     * Record a hit against both rate limiters.
     */
    public function hitRateLimiters(): void
    {
        RateLimiter::hit($this->throttleKeyIp(), 60);
        RateLimiter::hit($this->throttleKeyEmail(), 60);
    }
}
