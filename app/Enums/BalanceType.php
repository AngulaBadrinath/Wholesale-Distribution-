<?php

declare(strict_types=1);

namespace App\Enums;

enum BalanceType: string
{
    case DEBIT = 'DEBIT';
    case CREDIT = 'CREDIT';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::DEBIT => 'Debit',
            self::CREDIT => 'Credit',
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
