<?php

namespace App\Enums;

enum DeliveryEventType: string
{
    case CREATED = 'CREATED';
    case ASSIGNED = 'ASSIGNED';
    case REASSIGNED = 'REASSIGNED';
    case UNASSIGNED = 'UNASSIGNED';
    case PICKED_UP = 'PICKED_UP';
    case OUT_FOR_DELIVERY = 'OUT_FOR_DELIVERY';
    case DELIVERED = 'DELIVERED';
    case FAILED = 'FAILED';
    case RESCHEDULED = 'RESCHEDULED';
    case RETURNED_TO_WAREHOUSE = 'RETURNED_TO_WAREHOUSE';

    /**
     * Get the human-readable label for delivery event type.
     */
    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Delivery Created',
            self::ASSIGNED => 'Driver Assigned',
            self::REASSIGNED => 'Driver Reassigned',
            self::UNASSIGNED => 'Driver Unassigned',
            self::PICKED_UP => 'Warehouse Pickup Confirmed',
            self::OUT_FOR_DELIVERY => 'Out for Delivery',
            self::DELIVERED => 'Delivery Completed',
            self::FAILED => 'Delivery Failed',
            self::RESCHEDULED => 'Delivery Rescheduled',
            self::RETURNED_TO_WAREHOUSE => 'Returned to Warehouse',
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
