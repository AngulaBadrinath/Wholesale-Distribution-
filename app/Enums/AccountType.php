<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountType: string
{
    case ASSET = 'ASSET';
    case LIABILITY = 'LIABILITY';
    case EQUITY = 'EQUITY';
    case REVENUE = 'REVENUE';
    case EXPENSE = 'EXPENSE';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::ASSET => 'Asset',
            self::LIABILITY => 'Liability',
            self::EQUITY => 'Equity',
            self::REVENUE => 'Revenue',
            self::EXPENSE => 'Expense',
        };
    }

    /**
     * Get the normal balance direction for this account type.
     */
    public function normalBalance(): BalanceType
    {
        return match ($this) {
            self::ASSET, self::EXPENSE => BalanceType::DEBIT,
            self::LIABILITY, self::EQUITY, self::REVENUE => BalanceType::CREDIT,
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
