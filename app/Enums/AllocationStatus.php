<?php

namespace App\Enums;

enum AllocationStatus: string
{
    case ALLOCATED = 'ALLOCATED';
    case RESERVED = 'RESERVED';
    case PICKED = 'PICKED';
    case PACKED = 'PACKED';
    case DISPATCHED = 'DISPATCHED';
    case DELIVERED = 'DELIVERED';
    case PARTIALLY_DELIVERED = 'PARTIALLY_DELIVERED';
    case CANCELLED = 'CANCELLED';
    case RELEASED = 'RELEASED';

    /**
     * Human-readable label for the allocation status.
     */
    public function label(): string
    {
        return match ($this) {
            self::ALLOCATED => 'Allocated',
            self::RESERVED => 'Reserved',
            self::PICKED => 'Picked',
            self::PACKED => 'Packed',
            self::DISPATCHED => 'Dispatched',
            self::DELIVERED => 'Delivered',
            self::PARTIALLY_DELIVERED => 'Partially Delivered',
            self::CANCELLED => 'Cancelled',
            self::RELEASED => 'Released',
        };
    }

    /**
     * Badge visual variant for UI presentation.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::ALLOCATED, self::RESERVED => 'info',
            self::PICKED => 'indigo',
            self::PACKED => 'purple',
            self::DISPATCHED => 'primary',
            self::DELIVERED => 'success',
            self::PARTIALLY_DELIVERED => 'warning',
            self::CANCELLED => 'destructive',
            self::RELEASED => 'secondary',
        };
    }

    /**
     * Determine if this allocation is in an active fulfillment state.
     */
    public function isActive(): bool
    {
        return in_array($this, [
            self::ALLOCATED,
            self::RESERVED,
            self::PICKED,
            self::PACKED,
            self::DISPATCHED,
        ], true);
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
