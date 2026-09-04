<?php

namespace App\DTOs\Tax;

use App\Enums\TaxProfileStatus;
use App\Services\Tax\TaxCalculationService;

readonly class TaxProfileData
{
    public function __construct(
        public string $name,
        public string $code,
        public string $rate,
        public ?string $description,
        public TaxProfileStatus $status,
    ) {}

    /**
     * Build instance from validated array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $statusValue = $data['status'] ?? TaxProfileStatus::ACTIVE->value;
        $status = $statusValue instanceof TaxProfileStatus
            ? $statusValue
            : (TaxProfileStatus::tryFrom((string) $statusValue) ?? TaxProfileStatus::ACTIVE);

        return new self(
            name: trim((string) ($data['name'] ?? '')),
            code: strtoupper(trim((string) ($data['code'] ?? ''))),
            rate: TaxCalculationService::normalizeRate($data['rate'] ?? '0.0000'),
            description: isset($data['description']) && trim((string) $data['description']) !== '' ? trim((string) $data['description']) : null,
            status: $status,
        );
    }

    /**
     * Convert DTO to array for database persistence.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'rate' => $this->rate,
            'description' => $this->description,
            'status' => $this->status->value,
        ];
    }
}
