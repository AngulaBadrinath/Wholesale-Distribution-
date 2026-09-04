<?php

namespace App\Enums;

enum PaymentTerms: string
{
    case NET_30 = 'NET_30';
    case NET_15 = 'NET_15';
    case NET_60 = 'NET_60';
    case COD = 'COD';
    case DUE_ON_RECEIPT = 'DUE_ON_RECEIPT';

    /**
     * Get the human-readable label for payment terms.
     */
    public function label(): string
    {
        return match ($this) {
            self::NET_30 => 'Net 30 Days',
            self::NET_15 => 'Net 15 Days',
            self::NET_60 => 'Net 60 Days',
            self::COD => 'Cash On Delivery (COD)',
            self::DUE_ON_RECEIPT => 'Due Upon Receipt',
        };
    }

    /**
     * Return the number of grace days allowed before payment is overdue.
     */
    public function gracePeriodDays(): int
    {
        return match ($this) {
            self::NET_30 => 30,
            self::NET_15 => 15,
            self::NET_60 => 60,
            self::COD, self::DUE_ON_RECEIPT => 0,
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
