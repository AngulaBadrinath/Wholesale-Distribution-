<?php

declare(strict_types=1);

namespace App\Enums;

enum SupplierBillStatus: string
{
    case DRAFT = 'DRAFT';
    case POSTED = 'POSTED';
    case PARTIALLY_PAID = 'PARTIALLY_PAID';
    case PAID = 'PAID';
    case CANCELLED = 'CANCELLED';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::POSTED => 'Posted (Unpaid)',
            self::PARTIALLY_PAID => 'Partially Paid',
            self::PAID => 'Paid in Full',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Determine whether the bill is in an open/payable state.
     */
    public function isPayable(): bool
    {
        return match ($this) {
            self::POSTED, self::PARTIALLY_PAID => true,
            self::DRAFT, self::PAID, self::CANCELLED => false,
        };
    }

    /**
     * Get badge visual variant.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::POSTED => 'warning',
            self::PARTIALLY_PAID => 'info',
            self::PAID => 'success',
            self::CANCELLED => 'destructive',
        };
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
