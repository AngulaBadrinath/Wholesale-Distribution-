<?php

namespace App\Enums;

enum InventoryStockState: string
{
    case AVAILABLE = 'AVAILABLE';
    case RESERVED = 'RESERVED';
    case DAMAGED = 'DAMAGED';
    case DISPATCHED = 'DISPATCHED';
    case EXTERNAL = 'EXTERNAL';
    case NONE = 'NONE';

    /**
     * Get the human-readable label for the stock state.
     */
    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Available Stock',
            self::RESERVED => 'Reserved Stock',
            self::DAMAGED => 'Damaged / Quarantine',
            self::DISPATCHED => 'Dispatched Stock',
            self::EXTERNAL => 'External / Supplier',
            self::NONE => 'None',
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
