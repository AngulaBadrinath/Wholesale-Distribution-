<?php

namespace App\Enums;

enum PaymentTransactionStatus: string
{
    case PENDING_VERIFICATION = 'PENDING_VERIFICATION';
    case VERIFIED = 'VERIFIED';
    case REJECTED = 'REJECTED';
    case REVERSED = 'REVERSED';

    /**
     * Get the human-readable label for the transaction status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING_VERIFICATION => 'Pending Verification',
            self::VERIFIED => 'Verified & Confirmed',
            self::REJECTED => 'Rejected',
            self::REVERSED => 'Reversed / Bounced',
        };
    }

    /**
     * Badge visual variant for UI presentation.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::PENDING_VERIFICATION => 'warning',
            self::VERIFIED => 'success',
            self::REJECTED => 'destructive',
            self::REVERSED => 'secondary',
        };
    }

    /**
     * Determine if this is a settled/confirmed payment.
     */
    public function isConfirmed(): bool
    {
        return $this === self::VERIFIED;
    }

    /**
     * Determine if this state is terminal.
     */
    public function isTerminal(): bool
    {
        return $this === self::REVERSED;
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
