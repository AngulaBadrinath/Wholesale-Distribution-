<?php

declare(strict_types=1);

namespace App\Enums;

enum PayableTransactionType: string
{
    case SUPPLIER_BILL = 'SUPPLIER_BILL';
    case SUPPLIER_PAYMENT = 'SUPPLIER_PAYMENT';
    case PAYMENT_REVERSAL = 'PAYMENT_REVERSAL';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::SUPPLIER_BILL => 'Supplier Bill (Liability Created)',
            self::SUPPLIER_PAYMENT => 'Supplier Payment (Liability Reduced)',
            self::PAYMENT_REVERSAL => 'Payment Reversal (Liability Restored)',
        };
    }

    /**
     * Determine whether the transaction increases supplier liability (Credit in AP).
     */
    public function isLiabilityIncrease(): bool
    {
        return match ($this) {
            self::SUPPLIER_BILL, self::PAYMENT_REVERSAL => true,
            self::SUPPLIER_PAYMENT => false,
        };
    }

    /**
     * Determine whether the transaction decreases supplier liability (Debit in AP).
     */
    public function isLiabilityDecrease(): bool
    {
        return ! $this->isLiabilityIncrease();
    }

    /**
     * Get badge visual variant for UI presentation.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::SUPPLIER_BILL => 'default',
            self::SUPPLIER_PAYMENT => 'success',
            self::PAYMENT_REVERSAL => 'destructive',
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
