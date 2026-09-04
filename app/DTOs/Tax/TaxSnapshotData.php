<?php

namespace App\DTOs\Tax;

use Carbon\CarbonImmutable;

final readonly class TaxSnapshotData
{
    public function __construct(
        public ?int $taxProfileId,
        public ?string $taxProfileCode,
        public ?string $taxProfileName,
        public string $taxRate,
        public string $taxableAmount,
        public string $taxAmount,
        public string $lineTotal,
        public CarbonImmutable $calculatedAt,
    ) {}

    /**
     * Convert the immutable snapshot to an associative array for storage and serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tax_profile_id' => $this->taxProfileId,
            'tax_profile_code' => $this->taxProfileCode,
            'tax_profile_name' => $this->taxProfileName,
            'tax_rate' => $this->taxRate,
            'taxable_amount' => $this->taxableAmount,
            'tax_amount' => $this->taxAmount,
            'line_total' => $this->lineTotal,
            'calculated_at' => $this->calculatedAt->toIso8601String(),
        ];
    }
}
