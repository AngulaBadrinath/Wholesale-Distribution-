<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case ACTIVE = 'ACTIVE';
    case ON_HOLD = 'ON_HOLD';
    case INACTIVE = 'INACTIVE';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::ON_HOLD => 'On Hold',
            self::INACTIVE => 'Inactive',
        };
    }

    /**
     * Get the semantic operational description for the status.
     */
    public function description(): string
    {
        return match ($this) {
            self::ACTIVE => 'Customer account is in good standing and authorized to place new wholesale orders.',
            self::ON_HOLD => 'Customer account is temporarily restricted from placing new orders pending credit, compliance, or administrative review.',
            self::INACTIVE => 'Customer account is deactivated. New order placement is strictly prohibited. Historical records remain fully preserved.',
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
            self::ACTIVE => [self::ON_HOLD, self::INACTIVE],
            self::ON_HOLD => [self::ACTIVE, self::INACTIVE],
            self::INACTIVE => [self::ACTIVE, self::ON_HOLD],
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
     * Determine if customer is permitted to participate in commercial transactions / ordering.
     */
    public function canPlaceOrders(): bool
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
            self::ON_HOLD => 'warning',
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
