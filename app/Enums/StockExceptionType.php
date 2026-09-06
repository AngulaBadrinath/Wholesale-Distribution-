<?php

namespace App\Enums;

enum StockExceptionType: string
{
    case DAMAGE = 'DAMAGE';
    case LEAKAGE = 'LEAKAGE';
    case EXPIRATION = 'EXPIRATION';
    case MISSING_COUNT = 'MISSING_COUNT';
    case CONTAMINATION = 'CONTAMINATION';
    case PACKAGING_DEFECT = 'PACKAGING_DEFECT';
    case OTHER = 'OTHER';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::DAMAGE => 'Physical Damage',
            self::LEAKAGE => 'Fluid Leakage / Spillage',
            self::EXPIRATION => 'Past Expiration Date',
            self::MISSING_COUNT => 'Physical Count Discrepancy',
            self::CONTAMINATION => 'Contamination / Spoilage',
            self::PACKAGING_DEFECT => 'Packaging / Seal Defect',
            self::OTHER => 'Other Operational Exception',
        };
    }

    /**
     * Badge visual variant.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::DAMAGE, self::CONTAMINATION => 'destructive',
            self::LEAKAGE, self::EXPIRATION => 'warning',
            self::MISSING_COUNT, self::PACKAGING_DEFECT => 'indigo',
            self::OTHER => 'secondary',
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
