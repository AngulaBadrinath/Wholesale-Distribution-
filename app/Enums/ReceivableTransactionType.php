<?php

declare(strict_types=1);

namespace App\Enums;

enum ReceivableTransactionType: string
{
    case INVOICE_CHARGE = 'INVOICE_CHARGE';
    case PAYMENT = 'PAYMENT';
    case PAYMENT_REVERSAL = 'PAYMENT_REVERSAL';
    case CREDIT_NOTE = 'CREDIT_NOTE';
    case CREDIT_APPLICATION = 'CREDIT_APPLICATION';
    case ADJUSTMENT_DEBIT = 'ADJUSTMENT_DEBIT';
    case ADJUSTMENT_CREDIT = 'ADJUSTMENT_CREDIT';

    /**
     * Get the human-readable label for the transaction type.
     */
    public function label(): string
    {
        return match ($this) {
            self::INVOICE_CHARGE => 'Invoice Charge',
            self::PAYMENT => 'Payment Received',
            self::PAYMENT_REVERSAL => 'Payment Reversal (Bounced/Dishonored)',
            self::CREDIT_NOTE => 'Credit Note Issued',
            self::CREDIT_APPLICATION => 'Credit Applied to Invoice',
            self::ADJUSTMENT_DEBIT => 'Manual Debit Adjustment',
            self::ADJUSTMENT_CREDIT => 'Manual Credit Adjustment',
        };
    }

    /**
     * Determine whether the transaction increases what the customer owes (Debit).
     */
    public function isDebit(): bool
    {
        return match ($this) {
            self::INVOICE_CHARGE, self::PAYMENT_REVERSAL, self::ADJUSTMENT_DEBIT => true,
            self::PAYMENT, self::CREDIT_NOTE, self::CREDIT_APPLICATION, self::ADJUSTMENT_CREDIT => false,
        };
    }

    /**
     * Determine whether the transaction decreases what the customer owes (Credit).
     */
    public function isCredit(): bool
    {
        return ! $this->isDebit();
    }

    /**
     * Get badge visual variant for UI presentation.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::INVOICE_CHARGE => 'default',
            self::PAYMENT => 'success',
            self::PAYMENT_REVERSAL => 'destructive',
            self::CREDIT_NOTE => 'info',
            self::CREDIT_APPLICATION => 'secondary',
            self::ADJUSTMENT_DEBIT => 'warning',
            self::ADJUSTMENT_CREDIT => 'outline',
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
