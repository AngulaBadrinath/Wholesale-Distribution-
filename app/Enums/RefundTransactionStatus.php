<?php

declare(strict_types=1);

namespace App\Enums;

enum RefundTransactionStatus: string
{
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';

    /**
     * Get the human-readable label for the transaction status.
     */
    public function label(): string
    {
        return match ($this) {
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
        };
    }

    /**
     * Get the badge style variant for UI presentation.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::COMPLETED => 'success',
            self::FAILED => 'destructive',
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
