<?php

namespace App\Enums;

enum ProductStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';

    /**
     * Get the human-readable label for the status.
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
            self::ACTIVE => 'Product is active in the master catalog and available for sales ordering.',
            self::INACTIVE => 'Product is deactivated. New order placement is strictly prohibited. Historical records remain fully preserved.',
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
     * Determine if product is permitted to be added to new orders.
     */
    public function canOrder(): bool
    {
        return $this === self::ACTIVE;
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
