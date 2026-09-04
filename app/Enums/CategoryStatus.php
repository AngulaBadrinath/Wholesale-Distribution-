<?php

namespace App\Enums;

enum CategoryStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';

    /**
     * Get the human-readable label for the category status.
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
        };
    }

    /**
     * Get the semantic operational description for the status.
     */
    public function description(): string
    {
        return match ($this) {
            self::ACTIVE => 'Category is active and available for product classification and catalog discovery.',
            self::INACTIVE => 'Category is deactivated. New product assignments are prohibited. Existing product associations remain preserved.',
        };
    }

    /**
     * Determine if products can be newly assigned to a category with this status.
     */
    public function canAssignProducts(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Get valid target transition states from the current status.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::ACTIVE => [self::INACTIVE],
            self::INACTIVE => [self::ACTIVE],
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
     * Badge visual variant for UI presentation.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'secondary',
        };
    }

    /**
     * Return all enum string values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
