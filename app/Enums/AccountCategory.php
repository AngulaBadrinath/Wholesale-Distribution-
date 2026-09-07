<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountCategory: string
{
    case CURRENT_ASSET = 'CURRENT_ASSET';
    case NON_CURRENT_ASSET = 'NON_CURRENT_ASSET';
    case CURRENT_LIABILITY = 'CURRENT_LIABILITY';
    case LONG_TERM_LIABILITY = 'LONG_TERM_LIABILITY';
    case EQUITY = 'EQUITY';
    case OPERATING_REVENUE = 'OPERATING_REVENUE';
    case CONTRA_REVENUE = 'CONTRA_REVENUE';
    case COST_OF_GOODS_SOLD = 'COST_OF_GOODS_SOLD';
    case OPERATING_EXPENSE = 'OPERATING_EXPENSE';
    case OTHER_EXPENSE = 'OTHER_EXPENSE';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::CURRENT_ASSET => 'Current Asset',
            self::NON_CURRENT_ASSET => 'Non-Current Asset',
            self::CURRENT_LIABILITY => 'Current Liability',
            self::LONG_TERM_LIABILITY => 'Long-Term Liability',
            self::EQUITY => 'Equity',
            self::OPERATING_REVENUE => 'Operating Revenue',
            self::CONTRA_REVENUE => 'Contra-Revenue',
            self::COST_OF_GOODS_SOLD => 'Cost of Goods Sold',
            self::OPERATING_EXPENSE => 'Operating Expense',
            self::OTHER_EXPENSE => 'Other Expense',
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
