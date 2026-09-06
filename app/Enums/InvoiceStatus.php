<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case ISSUED = 'ISSUED';
    case PAID = 'PAID';
    case VOID = 'VOID';

    /**
     * Get the human-readable label for invoice status.
     */
    public function label(): string
    {
        return match ($this) {
            self::ISSUED => 'Issued',
            self::PAID => 'Paid',
            self::VOID => 'Void',
        };
    }

    /**
     * Badge visual variant for UI presentation.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::ISSUED => 'default',
            self::PAID => 'success',
            self::VOID => 'destructive',
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
