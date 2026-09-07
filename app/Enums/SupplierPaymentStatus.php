<?php

declare(strict_types=1);

namespace App\Enums;

enum SupplierPaymentStatus: string
{
    case COMPLETED = 'COMPLETED';
    case REVERSED = 'REVERSED';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::COMPLETED => 'Completed',
            self::REVERSED => 'Reversed',
        };
    }

    /**
     * Get badge visual variant.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::COMPLETED => 'success',
            self::REVERSED => 'destructive',
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
