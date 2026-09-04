<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case ADMIN = 'ADMIN';
    case ACCOUNTANT = 'ACCOUNTANT';
    case SALESMAN = 'SALESMAN';
    case WAREHOUSE_MANAGER = 'WAREHOUSE_MANAGER';
    case DELIVERY_PARTNER = 'DELIVERY_PARTNER';

    /**
     * Get the human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrator',
            self::ADMIN => 'Administrator',
            self::ACCOUNTANT => 'Accountant',
            self::SALESMAN => 'Sales Representative',
            self::WAREHOUSE_MANAGER => 'Warehouse Manager',
            self::DELIVERY_PARTNER => 'Delivery Partner',
        };
    }

    /**
     * Get the semantic description for the role.
     */
    public function description(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Full system-wide administrative authority and governance.',
            self::ADMIN => 'Operational and business administration across customer, product, and order systems.',
            self::ACCOUNTANT => 'Financial, ledger, payment verification, and credit management operations.',
            self::SALESMAN => 'Sales, field customer portfolio management, and order placement.',
            self::WAREHOUSE_MANAGER => 'Inventory management, stock movements, picking, packing, and fulfillment.',
            self::DELIVERY_PARTNER => 'Dispatch logistics, route delivery execution, and proof of delivery collection.',
        };
    }

    /**
     * Determine whether this role is a privileged administrative or financial role.
     */
    public function isPrivileged(): bool
    {
        return match ($this) {
            self::SUPER_ADMIN, self::ADMIN, self::ACCOUNTANT => true,
            default => false,
        };
    }

    /**
     * Get all backed string values for validation and introspection.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
