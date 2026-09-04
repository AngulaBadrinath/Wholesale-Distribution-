<?php

namespace App\Enums;

enum PriceOverrideDirection: string
{
    case NONE = 'NONE';
    case BELOW_MINIMUM = 'BELOW_MINIMUM';
    case ABOVE_MRP = 'ABOVE_MRP';

    /**
     * Get the human-readable label for this override direction.
     */
    public function label(): string
    {
        return match ($this) {
            self::NONE => 'Standard Pricing (No Override)',
            self::BELOW_MINIMUM => 'Below Minimum Allowed Price',
            self::ABOVE_MRP => 'Above Maximum Retail Price (MRP)',
        };
    }

    /**
     * Determine if this case represents an actual price boundary override.
     */
    public function isOverride(): bool
    {
        return $this !== self::NONE;
    }
}
