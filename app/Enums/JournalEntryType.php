<?php

declare(strict_types=1);

namespace App\Enums;

enum JournalEntryType: string
{
    case SYSTEM = 'SYSTEM';
    case MANUAL = 'MANUAL';
    case REVERSAL = 'REVERSAL';
    case CLOSING = 'CLOSING';

    /**
     * Get the human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::SYSTEM => 'System Automated',
            self::MANUAL => 'Manual Entry',
            self::REVERSAL => 'Controlled Reversal',
            self::CLOSING => 'Period Closing',
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
