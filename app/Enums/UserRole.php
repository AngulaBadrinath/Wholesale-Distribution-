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
     * Determine whether this role is a privileged administrative or financial role.
     */
    public function isPrivileged(): bool
    {
        return match ($this) {
            self::SUPER_ADMIN, self::ADMIN, self::ACCOUNTANT => true,
            default => false,
        };
    }
}
