<?php

namespace App\Enums;

enum AdjustmentReasonCode: string
{
    case CUSTOMER_REQUEST = 'CUSTOMER_REQUEST';
    case WAREHOUSE_DAMAGE = 'WAREHOUSE_DAMAGE';
    case STOCKOUT_DEFECT = 'STOCKOUT_DEFECT';
    case PRICING_DISPUTE = 'PRICING_DISPUTE';
    case OTHER = 'OTHER';

    /**
     * Human-readable label for adjustment reason.
     */
    public function label(): string
    {
        return match ($this) {
            self::CUSTOMER_REQUEST => 'Customer Request / Cancellation',
            self::WAREHOUSE_DAMAGE => 'Warehouse Damaged Stock',
            self::STOCKOUT_DEFECT => 'Stockout or Quality Defect',
            self::PRICING_DISPUTE => 'Pricing Dispute / Commercial Correction',
            self::OTHER => 'Other Operational Reason',
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
