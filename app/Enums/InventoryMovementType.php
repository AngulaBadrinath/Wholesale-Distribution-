<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case RESERVATION = 'RESERVATION';
    case RELEASE = 'RELEASE';
    case DAMAGE_ISOLATION = 'DAMAGE_ISOLATION';
    case DAMAGE_RELEASE = 'DAMAGE_RELEASE';
    case INCREASE_ON_HAND = 'INCREASE_ON_HAND';
    case DECREASE_ON_HAND = 'DECREASE_ON_HAND';
    case INITIAL_STOCK = 'INITIAL_STOCK';
    case DISPATCH = 'DISPATCH';
    case RETURN = 'RETURN';
    case ADJUSTMENT = 'ADJUSTMENT';

    /**
     * Get the human-readable label for the movement type.
     */
    public function label(): string
    {
        return match ($this) {
            self::RESERVATION => 'Stock Reservation',
            self::RELEASE => 'Reservation Release',
            self::DAMAGE_ISOLATION => 'Damage Quarantine',
            self::DAMAGE_RELEASE => 'Damaged Stock Release',
            self::INCREASE_ON_HAND => 'Stock Receipt / Increase',
            self::DECREASE_ON_HAND => 'Stock Write-off / Decrease',
            self::INITIAL_STOCK => 'Initial Baseline Stock',
            self::DISPATCH => 'Order Dispatch',
            self::RETURN => 'Customer Return',
            self::ADJUSTMENT => 'Manual Stock Adjustment',
        };
    }

    /**
     * Badge visual variant for UI presentation.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::RESERVATION => 'info',
            self::RELEASE => 'secondary',
            self::DAMAGE_ISOLATION => 'destructive',
            self::DAMAGE_RELEASE => 'warning',
            self::INCREASE_ON_HAND, self::INITIAL_STOCK, self::RETURN => 'success',
            self::DECREASE_ON_HAND => 'destructive',
            self::DISPATCH => 'primary',
            self::ADJUSTMENT => 'indigo',
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
