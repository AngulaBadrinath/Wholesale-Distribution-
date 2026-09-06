<?php

declare(strict_types=1);

namespace App\Enums;

enum RefundStatus: string
{
    case REQUESTED = 'REQUESTED';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case APPROVED = 'APPROVED';
    case PROCESSING = 'PROCESSING';
    case PROCESSED = 'PROCESSED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';

    /**
     * Get the human-readable label for the refund status.
     */
    public function label(): string
    {
        return match ($this) {
            self::REQUESTED => 'Requested',
            self::UNDER_REVIEW => 'Under Review',
            self::APPROVED => 'Approved',
            self::PROCESSING => 'Processing Settlement',
            self::PROCESSED => 'Processed / Settled',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Get the badge style variant for UI presentation.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::REQUESTED => 'warning',
            self::UNDER_REVIEW => 'info',
            self::APPROVED => 'secondary',
            self::PROCESSING => 'warning',
            self::PROCESSED => 'success',
            self::REJECTED => 'destructive',
            self::CANCELLED => 'neutral',
        };
    }

    /**
     * Determine whether the refund request is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::PROCESSED, self::REJECTED, self::CANCELLED], true);
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
