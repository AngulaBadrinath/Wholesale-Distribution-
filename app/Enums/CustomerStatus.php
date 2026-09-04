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
