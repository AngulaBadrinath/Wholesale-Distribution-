<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => AccountStatus::ACTIVE,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the account is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AccountStatus::SUSPENDED,
        ]);
    }

    /**
     * Indicate that the account is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AccountStatus::DISABLED,
        ]);
    }

    /**
     * Indicate that the account is invited.
     */
    public function invited(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AccountStatus::INVITED,
        ]);
    }

    /**
     * Assign a specific role to the user.
     */
    public function role(\App\Enums\UserRole $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
        ]);
    }

    /**
     * Assign Super Admin role.
     */
    public function superAdmin(): static
    {
        return $this->role(\App\Enums\UserRole::SUPER_ADMIN);
    }

    /**
     * Assign Admin role.
     */
    public function admin(): static
    {
        return $this->role(\App\Enums\UserRole::ADMIN);
    }

    /**
     * Assign Accountant role.
     */
    public function accountant(): static
    {
        return $this->role(\App\Enums\UserRole::ACCOUNTANT);
    }

    /**
     * Indicate that the user has configured and confirmed MFA.
     */
    public function withMfa(?string $secret = null): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => $secret ?? (new \PragmaRX\Google2FA\Google2FA())->generateSecretKey(32),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
