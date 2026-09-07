<?php

declare(strict_types=1);

namespace App\Enums;

enum SupplierPaymentMethod: string
{
    case BANK_TRANSFER = 'BANK_TRANSFER';
    case CHEQUE = 'CHEQUE';
    case CASH = 'CASH';
    case MONEY_ORDER = 'MONEY_ORDER';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::BANK_TRANSFER => 'Bank Transfer / NEFT / RTGS',
            self::CHEQUE => 'Cheque',
            self::CASH => 'Cash',
            self::MONEY_ORDER => 'Money Order',
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
