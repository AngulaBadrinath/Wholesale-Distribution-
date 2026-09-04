<?php

namespace App\Enums;

enum FulfillmentStatus: string
{
    case UNALLOCATED = 'UNALLOCATED';
    case RESERVED = 'RESERVED';
    case PICKED = 'PICKED';
    case PACKED = 'PACKED';
    case DISPATCHED = 'DISPATCHED';
    case DELIVERED = 'DELIVERED';
    case PARTIALLY_DELIVERED = 'PARTIALLY_DELIVERED';
    case RETURNED = 'RETURNED';

    /**
     * Get the human-readable label for fulfillment status.
     */
    public function label(): string
    {
        return match ($this) {
            self::UNALLOCATED => 'Unallocated',
            self::RESERVED => 'Reserved',
            self::PICKED => 'Picked',
            self::PACKED => 'Packed',
            self::DISPATCHED => 'Dispatched',
            self::DELIVERED => 'Delivered',
            self::PARTIALLY_DELIVERED => 'Partially Delivered',
            self::RETURNED => 'Returned',
        };
    }

    /**
     * Badge visual variant for UI presentation.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::UNALLOCATED => 'secondary',
            self::RESERVED => 'info',
            self::PICKED => 'indigo',
            self::PACKED => 'purple',
            self::DISPATCHED => 'primary',
            self::DELIVERED => 'success',
            self::PARTIALLY_DELIVERED => 'warning',
            self::RETURNED => 'destructive',
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
