<?php

declare(strict_types=1);

namespace App\Enums;

enum ReconciliationStatus: string
{
    case IN_PROGRESS = 'IN_PROGRESS';
    case RECONCILED = 'RECONCILED';
    case DISCREPANCY = 'DISCREPANCY';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::IN_PROGRESS => 'In Progress',
            self::RECONCILED => 'Reconciled',
            self::DISCREPANCY => 'Discrepancy Detected',
        };
    }

    /**
     * Badge visual variant for UI presentation.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::IN_PROGRESS => 'info',
            self::RECONCILED => 'success',
            self::DISCREPANCY => 'destructive',
        };
    }

    /**
     * Return all string values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
