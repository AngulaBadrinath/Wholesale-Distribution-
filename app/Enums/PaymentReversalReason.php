<?php

namespace App\Enums;

enum PaymentReversalReason: string
{
    case BOUNCED_CHEQUE = 'BOUNCED_CHEQUE';
    case INSUFFICIENT_FUNDS = 'INSUFFICIENT_FUNDS';
    case STOP_PAYMENT = 'STOP_PAYMENT';
    case DATA_ENTRY_ERROR = 'DATA_ENTRY_ERROR';
    case FRAUDULENT_PAYMENT = 'FRAUDULENT_PAYMENT';
    case ADMIN_CORRECTION = 'ADMIN_CORRECTION';

    /**
     * Get the human-readable label for the reversal reason.
     */
    public function label(): string
    {
        return match ($this) {
            self::BOUNCED_CHEQUE => 'Bounced Cheque / Returned Item',
            self::INSUFFICIENT_FUNDS => 'Non-Sufficient Funds (NSF)',
            self::STOP_PAYMENT => 'Customer Stop Payment Order',
            self::DATA_ENTRY_ERROR => 'Data Entry Error / Duplicate Recording',
            self::FRAUDULENT_PAYMENT => 'Fraudulent / Unauthorized Transaction',
            self::ADMIN_CORRECTION => 'Administrative Correction',
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
