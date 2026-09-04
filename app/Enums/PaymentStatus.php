<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case UNPAID = 'UNPAID';
    case PARTIALLY_PAID = 'PARTIALLY_PAID';
    case PAID = 'PAID';
    case OVERPAID = 'OVERPAID';
    case REFUNDED = 'REFUNDED';

    /**
     * Get the human-readable label for payment status.
     */
    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Unpaid',
            self::PARTIALLY_PAID => 'Partially Paid',
            self::PAID => 'Paid',
            self::OVERPAID => 'Overpaid',
            self::REFUNDED => 'Refunded',
        };
    }

    /**
     * Badge visual variant for UI presentation.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::UNPAID => 'destructive',
            self::PARTIALLY_PAID => 'warning',
            self::PAID => 'success',
            self::OVERPAID => 'indigo',
            self::REFUNDED => 'secondary',
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
