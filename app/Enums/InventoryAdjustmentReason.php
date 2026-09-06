<?php

namespace App\Enums;

enum InventoryAdjustmentReason: string
{
    case CYCLE_COUNT_DISCREPANCY = 'CYCLE_COUNT_DISCREPANCY';
    case DAMAGED_WRITE_OFF = 'DAMAGED_WRITE_OFF';
    case SUPPLIER_SHORTAGE = 'SUPPLIER_SHORTAGE';
    case EXPIRATION_DISPOSAL = 'EXPIRATION_DISPOSAL';
    case RETURN_INSPECTION = 'RETURN_INSPECTION';
    case FOUND_STOCK = 'FOUND_STOCK';
    case MANUAL_CORRECTION = 'MANUAL_CORRECTION';

    /**
     * Human-readable label for adjustment reason.
     */
    public function label(): string
    {
        return match ($this) {
            self::CYCLE_COUNT_DISCREPANCY => 'Cycle Count Discrepancy',
            self::DAMAGED_WRITE_OFF => 'Damaged Stock Write-off',
            self::SUPPLIER_SHORTAGE => 'Supplier Shortage / Inbound Error',
            self::EXPIRATION_DISPOSAL => 'Expired Stock Disposal',
            self::RETURN_INSPECTION => 'Return Inspection Adjustment',
            self::FOUND_STOCK => 'Found / Surplus Stock',
            self::MANUAL_CORRECTION => 'Manual Admin Correction',
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
