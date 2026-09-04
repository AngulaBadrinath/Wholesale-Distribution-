<?php

namespace App\Enums;

enum AccountStatus: string
{
    case INVITED = 'INVITED';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case DISABLED = 'DISABLED';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::INVITED => 'Invited',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::DISABLED => 'Disabled',
        };
    }

    /**
     * Get the semantic description for the status.
     */
    public function description(): string
    {
        return match ($this) {
            self::INVITED => 'Account has been provisioned and is awaiting initial authentication and activation.',
            self::ACTIVE => 'Account is fully active and permitted to authenticate and perform role-authorized operations.',
            self::SUSPENDED => 'Account is temporarily suspended. Authentication is immediately blocked. Operational associations remain preserved.',
            self::DISABLED => 'Account is deactivated. Authentication is strictly prohibited. Historical attribution remains fully preserved.',
        };
    }

    /**
     * Get all valid target transition states from the current status.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::INVITED => [self::ACTIVE, self::DISABLED],
            self::ACTIVE => [self::SUSPENDED, self::DISABLED],
            self::SUSPENDED => [self::ACTIVE, self::DISABLED],
            self::DISABLED => [self::ACTIVE, self::SUSPENDED],
        };
    }

    /**
     * Determine whether transitioning to the target status is valid.
     */
    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false; // No-op, not a state transition
        }

        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Determine whether an account in this state is permitted to authenticate.
     */
    public function canAuthenticate(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Get all backed string values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
