<?php

declare(strict_types=1);

namespace App\Enums;

enum CreditNoteStatus: string
{
    case ISSUED = 'ISSUED';
    case APPLIED = 'APPLIED';
    case PARTIALLY_REFUNDED = 'PARTIALLY_REFUNDED';
    case FULLY_REFUNDED = 'FULLY_REFUNDED';
    case CLOSED = 'CLOSED';

    /**
     * Get the human-readable label for the credit note status.
     */
    public function label(): string
    {
        return match ($this) {
            self::ISSUED => 'Issued',
            self::APPLIED => 'Applied to Invoice/Account',
            self::PARTIALLY_REFUNDED => 'Partially Refunded',
            self::FULLY_REFUNDED => 'Fully Refunded',
            self::CLOSED => 'Closed',
        };
    }

    /**
     * Get the badge style variant for UI presentation.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::ISSUED => 'info',
            self::APPLIED => 'secondary',
            self::PARTIALLY_REFUNDED => 'warning',
            self::FULLY_REFUNDED => 'success',
            self::CLOSED => 'neutral',
        };
    }

    /**
     * Determine whether the credit note has refundable balance available.
     */
    public function isRefundable(): bool
    {
        return in_array($this, [self::ISSUED, self::PARTIALLY_REFUNDED], true);
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
