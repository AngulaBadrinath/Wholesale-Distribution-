<?php

namespace App\Enums;

enum OrderAdjustmentStatus: string
{
    case SUBMITTED = 'SUBMITTED';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case APPLIED = 'APPLIED';
    case CANCELLED = 'CANCELLED';
    case REVERSED = 'REVERSED';

    /**
     * Human-readable label for adjustment status.
     */
    public function label(): string
    {
        return match ($this) {
            self::SUBMITTED => 'Submitted',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::APPLIED => 'Applied',
            self::CANCELLED => 'Cancelled',
            self::REVERSED => 'Reversed',
        };
    }

    /**
     * Badge visual variant for UI presentation.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::SUBMITTED => 'warning',
            self::APPROVED => 'primary',
            self::REJECTED => 'destructive',
            self::APPLIED => 'success',
            self::CANCELLED => 'secondary',
            self::REVERSED => 'destructive',
        };
    }

    /**
     * Determine if this adjustment status is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::REJECTED, self::CANCELLED, self::REVERSED], true);
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
