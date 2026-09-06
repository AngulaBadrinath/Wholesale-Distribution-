<?php

namespace App\Enums;

enum StockExceptionSeverity: string
{
    case LOW = 'LOW';
    case MEDIUM = 'MEDIUM';
    case HIGH = 'HIGH';
    case CRITICAL = 'CRITICAL';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Low Severity',
            self::MEDIUM => 'Medium Severity',
            self::HIGH => 'High Severity',
            self::CRITICAL => 'Critical Severity',
        };
    }

    /**
     * Badge visual variant.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::LOW => 'secondary',
            self::MEDIUM => 'info',
            self::HIGH => 'warning',
            self::CRITICAL => 'destructive',
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
