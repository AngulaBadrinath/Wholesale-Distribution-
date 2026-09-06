<?php

namespace App\Enums;

enum PaymentRejectionReason: string
{
    case ILLEGIBLE_EVIDENCE = 'ILLEGIBLE_EVIDENCE';
    case CHEQUE_DATE_INVALID = 'CHEQUE_DATE_INVALID';
    case AMOUNT_MISMATCH = 'AMOUNT_MISMATCH';
    case SIGNATURE_MISSING = 'SIGNATURE_MISSING';
    case INCOMPLETE_DETAILS = 'INCOMPLETE_DETAILS';
    case DUPLICATE_ENTRY = 'DUPLICATE_ENTRY';
    case OTHER = 'OTHER';

    /**
     * Get the human-readable label for the rejection reason.
     */
    public function label(): string
    {
        return match ($this) {
            self::ILLEGIBLE_EVIDENCE => 'Illegible / Blurry Evidence Photo',
            self::CHEQUE_DATE_INVALID => 'Invalid / Post-Dated / Stale Cheque Date',
            self::AMOUNT_MISMATCH => 'Amount Mismatch (Written vs Declared)',
            self::SIGNATURE_MISSING => 'Missing / Invalid Authorized Signature',
            self::INCOMPLETE_DETAILS => 'Incomplete Bank / Issuer Details',
            self::DUPLICATE_ENTRY => 'Duplicate Payment Submission',
            self::OTHER => 'Other Verification Failure',
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
