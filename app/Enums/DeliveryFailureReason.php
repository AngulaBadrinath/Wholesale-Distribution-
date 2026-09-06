<?php

namespace App\Enums;

enum DeliveryFailureReason: string
{
    case CUSTOMER_UNAVAILABLE = 'CUSTOMER_UNAVAILABLE';
    case ADDRESS_NOT_FOUND = 'ADDRESS_NOT_FOUND';
    case CUSTOMER_REFUSED = 'CUSTOMER_REFUSED';
    case BUSINESS_CLOSED = 'BUSINESS_CLOSED';
    case ACCESS_RESTRICTED = 'ACCESS_RESTRICTED';
    case VEHICLE_BREAKDOWN = 'VEHICLE_BREAKDOWN';
    case WEATHER_EMERGENCY = 'WEATHER_EMERGENCY';
    case OTHER = 'OTHER';

    /**
     * Get the human-readable label for failure reason.
     */
    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER_UNAVAILABLE => 'Customer Unavailable',
            self::ADDRESS_NOT_FOUND => 'Address Not Found / Invalid',
            self::CUSTOMER_REFUSED => 'Customer Refused Delivery',
            self::BUSINESS_CLOSED => 'Business / Store Closed',
            self::ACCESS_RESTRICTED => 'Access Restricted / Gated',
            self::VEHICLE_BREAKDOWN => 'Vehicle Breakdown / Transit Issue',
            self::WEATHER_EMERGENCY => 'Weather Emergency / Road Closure',
            self::OTHER => 'Other Operational Issue',
        };
    }

    /**
     * Get descriptive context for the failure reason.
     */
    public function description(): string
    {
        return match ($this) {
            self::CUSTOMER_UNAVAILABLE => 'No recipient or authorized staff available to receive delivery.',
            self::ADDRESS_NOT_FOUND => 'Destination address cannot be located or road is inaccessible.',
            self::CUSTOMER_REFUSED => 'Customer rejected shipment or requested return without receipt.',
            self::BUSINESS_CLOSED => 'Store or receiving warehouse was closed during delivery window.',
            self::ACCESS_RESTRICTED => 'Security checkpoint, gate, or loading dock denied access.',
            self::VEHICLE_BREAKDOWN => 'Delivery vehicle mechanical failure or accident during transit.',
            self::WEATHER_EMERGENCY => 'Severe weather, flooding, or safety hazards prevented arrival.',
            self::OTHER => 'Custom operational exception documented in driver notes.',
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
