<?php

namespace App\Enums;

enum InventoryAdjustmentType: string
{
    case INCREASE_ON_HAND = 'INCREASE_ON_HAND';
    case DECREASE_ON_HAND = 'DECREASE_ON_HAND';
    case TRANSFER_TO_DAMAGED = 'TRANSFER_TO_DAMAGED';
    case DAMAGE_DISPOSAL = 'DAMAGE_DISPOSAL';

    /**
     * Get the human-readable label for the adjustment type.
     */
    public function label(): string
    {
        return match ($this) {
            self::INCREASE_ON_HAND => 'Increase Available Stock',
            self::DECREASE_ON_HAND => 'Decrease Available Stock (Write-off)',
            self::TRANSFER_TO_DAMAGED => 'Transfer Available to Damaged',
            self::DAMAGE_DISPOSAL => 'Dispose Damaged Stock',
        };
    }

    /**
     * Badge visual variant for UI presentation.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::INCREASE_ON_HAND => 'success',
            self::DECREASE_ON_HAND => 'destructive',
            self::TRANSFER_TO_DAMAGED => 'warning',
            self::DAMAGE_DISPOSAL => 'secondary',
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
