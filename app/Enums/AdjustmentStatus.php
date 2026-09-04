<?php

namespace App\Enums;

enum AdjustmentStatus: string
{
    case NONE = 'NONE';
    case REQUESTED = 'REQUESTED';
    case APPLIED = 'APPLIED';
    case REVERSED = 'REVERSED';

    /**
     * Get the human-readable label for adjustment status.
     */
    public function label(): string
    {
        return match ($this) {
            self::NONE => 'None',
            self::REQUESTED => 'Requested',
            self::APPLIED => 'Applied',
            self::REVERSED => 'Reversed',
        };
    }

    /**
     * Badge visual variant for UI presentation.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::NONE => 'secondary',
            self::REQUESTED => 'warning',
            self::APPLIED => 'info',
            self::REVERSED => 'destructive',
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
