<?php

namespace App\Enums;

enum StockExceptionStatus: string
{
    case PENDING_REVIEW = 'PENDING_REVIEW';
    case RESOLVED = 'RESOLVED';
    case DISMISSED = 'DISMISSED';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'Pending Review',
            self::RESOLVED => 'Resolved',
            self::DISMISSED => 'Dismissed',
        };
    }

    /**
     * Badge visual variant.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'warning',
            self::RESOLVED => 'success',
            self::DISMISSED => 'secondary',
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
