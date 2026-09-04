<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Services\Auth\MfaPolicy;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'status', 'role', 'two_factor_secret', 'two_factor_confirmed_at'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => AccountStatus::class,
            'role' => UserRole::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Determine whether the account is currently permitted to authenticate.
     */
    public function canAuthenticate(): bool
    {
        return $this->status instanceof AccountStatus && $this->status->canAuthenticate();
    }

    /**
     * Check if account is active.
     */
    public function isActive(): bool
    {
        return $this->status === AccountStatus::ACTIVE;
    }

    /**
     * Check if account is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === AccountStatus::SUSPENDED;
    }

    /**
     * Check if account is disabled.
     */
    public function isDisabled(): bool
    {
        return $this->status === AccountStatus::DISABLED;
    }

    /**
     * Check if account is in invited state.
     */
    public function isInvited(): bool
    {
        return $this->status === AccountStatus::INVITED;
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new \App\Notifications\Auth\ResetPasswordNotification($token));
    }

    /**
     * Get the two factor recovery codes for the user.
     */
    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(TwoFactorRecoveryCode::class);
    }

    /**
     * Determine whether the user has confirmed Multi-Factor Authentication.
     */
    public function hasMfaEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && ! empty($this->two_factor_secret);
    }

    /**
     * Determine whether Multi-Factor Authentication is required for the user by policy.
     */
    public function requiresMfa(): bool
    {
        return app(MfaPolicy::class)->isMfaRequired($this);
    }

    /**
     * Determine whether the user possesses a privileged administrative or financial role.
     */
    public function isPrivileged(): bool
    {
        return $this->role instanceof UserRole && $this->role->isPrivileged();
    }
}
