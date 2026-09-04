<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AccountStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
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
}
