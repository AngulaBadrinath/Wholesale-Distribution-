<?php

namespace App\Enums;

enum ReturnReasonCode: string
{
    case DEFECTIVE = 'DEFECTIVE';
    case DAMAGED_IN_TRANSIT = 'DAMAGED_IN_TRANSIT';
    case WRONG_ITEM = 'WRONG_ITEM';
    case CUSTOMER_ORDERED_WRONG = 'CUSTOMER_ORDERED_WRONG';
    case EXCESS_STOCK = 'EXCESS_STOCK';
    case EXPIRED = 'EXPIRED';
    case QUALITY_ISSUE = 'QUALITY_ISSUE';
    case OTHER = 'OTHER';

    /**
     * Human-readable label for return reason.
     */
    public function label(): string
    {
        return match ($this) {
            self::DEFECTIVE => 'Defective Product',
            self::DAMAGED_IN_TRANSIT => 'Damaged in Transit',
            self::WRONG_ITEM => 'Wrong Item Delivered',
            self::CUSTOMER_ORDERED_WRONG => 'Customer Ordered Wrong',
            self::EXCESS_STOCK => 'Excess Stock / Overstocked',
            self::EXPIRED => 'Expired / Near Expiry',
            self::QUALITY_ISSUE => 'Quality Issue / Substandard',
            self::OTHER => 'Other Reason',
        };
    }

    /**
     * Get all enum string values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
