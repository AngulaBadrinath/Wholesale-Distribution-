<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case PENDING_ASSIGNMENT = 'PENDING_ASSIGNMENT';
    case ASSIGNED = 'ASSIGNED';
    case ACCEPTED = 'ACCEPTED';
    case PICKED_UP = 'PICKED_UP';
    case OUT_FOR_DELIVERY = 'OUT_FOR_DELIVERY';
    case DELIVERED = 'DELIVERED';
    case FAILED = 'FAILED';
    case RESCHEDULED = 'RESCHEDULED';
    case RETURNED_TO_WAREHOUSE = 'RETURNED_TO_WAREHOUSE';

    /**
     * Get the human-readable label for delivery status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING_ASSIGNMENT => 'Pending Assignment',
            self::ASSIGNED => 'Assigned',
            self::ACCEPTED => 'Accepted',
            self::PICKED_UP => 'Picked Up',
            self::OUT_FOR_DELIVERY => 'Out for Delivery',
            self::DELIVERED => 'Delivered',
            self::FAILED => 'Failed',
            self::RESCHEDULED => 'Rescheduled',
            self::RETURNED_TO_WAREHOUSE => 'Returned to Warehouse',
        };
    }

    /**
     * Badge visual variant for UI presentation.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::PENDING_ASSIGNMENT => 'secondary',
            self::ASSIGNED => 'info',
            self::ACCEPTED => 'indigo',
            self::PICKED_UP => 'purple',
            self::OUT_FOR_DELIVERY => 'warning',
            self::DELIVERED => 'success',
            self::FAILED => 'destructive',
            self::RESCHEDULED => 'warning',
            self::RETURNED_TO_WAREHOUSE => 'outline',
        };
    }

    /**
     * Determine whether the delivery is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::DELIVERED => true,
            default => false,
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
