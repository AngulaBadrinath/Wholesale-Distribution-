<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case REQUESTED = 'REQUESTED';
    case UNDER_REVIEW = 'UNDER_REVIEW';
    case INSPECTED = 'INSPECTED';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';

    /**
     * Get human readable status label.
     */
    public function label(): string
    {
        return match ($this) {
            self::REQUESTED => 'Requested',
            self::UNDER_REVIEW => 'Under Review',
            self::INSPECTED => 'Inspected',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * UI badge color variant.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::REQUESTED => 'warning',
            self::UNDER_REVIEW => 'info',
            self::INSPECTED => 'purple',
            self::APPROVED => 'success',
            self::REJECTED => 'destructive',
            self::CANCELLED => 'secondary',
        };
    }

    /**
     * Whether the return request is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::APPROVED, self::REJECTED, self::CANCELLED => true,
            default => false,
        };
    }

    /**
     * Whether the return request is eligible for physical inspection.
     */
    public function canInspect(): bool
    {
        return match ($this) {
            self::REQUESTED, self::UNDER_REVIEW => true,
            default => false,
        };
    }

    /**
     * Whether the return request is eligible for approval or rejection.
     */
    public function canApprove(): bool
    {
        return $this === self::INSPECTED;
    }

    /**
     * Whether the return request can be cancelled by the requester.
     */
    public function canCancel(): bool
    {
        return $this === self::REQUESTED;
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
