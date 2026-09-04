<?php

namespace App\DTOs\Tax;

final readonly class TaxCalculationResult
{
    public function __construct(
        public string $taxableAmount,
        public string $taxRate,
        public string $taxAmount,
        public string $lineTotal,
        public TaxSnapshotData $snapshot,
    ) {}

    /**
     * Convert calculation result to associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'taxable_amount' => $this->taxableAmount,
            'tax_rate' => $this->taxRate,
            'tax_amount' => $this->taxAmount,
            'line_total' => $this->lineTotal,
            'snapshot' => $this->snapshot->toArray(),
        ];
    }
}
