<?php

namespace App\Enums;

enum OrderStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case APPROVED = 'APPROVED';
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
    case REJECTED = 'REJECTED';

    /**
     * Get the human-readable label for the order status.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::PENDING_APPROVAL => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::PROCESSING => 'Processing',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::REJECTED => 'Rejected',
        };
    }

    /**
     * Get the operational description for the status.
     */
    public function description(): string
    {
        return match ($this) {
            self::DRAFT => 'Order is being prepared and has not been formally submitted.',
            self::SUBMITTED => 'Order has been placed and is waiting for operational processing.',
            self::PENDING_APPROVAL => 'Order requires administrative review or credit approval before processing.',
            self::APPROVED => 'Order is approved for warehouse allocation and fulfillment.',
            self::PROCESSING => 'Order is actively being picked, packed, or fulfilled in the warehouse.',
            self::COMPLETED => 'Order has been successfully fulfilled, delivered, and closed.',
            self::CANCELLED => 'Order has been cancelled prior to delivery.',
            self::REJECTED => 'Order has been rejected by administration with a documented reason.',
        };
    }

    /**
     * Determine whether the order state is terminal.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED, self::REJECTED], true);
    }

    /**
     * Badge visual variant for UI presentation.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::SUBMITTED => 'info',
            self::PENDING_APPROVAL => 'warning',
            self::APPROVED => 'primary',
            self::PROCESSING => 'indigo',
            self::COMPLETED => 'success',
            self::CANCELLED => 'destructive',
            self::REJECTED => 'destructive',
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
