<?php

namespace App\DTOs\Pricing;

use App\Enums\PriceOverrideDirection;
use Carbon\CarbonImmutable;

final readonly class PriceOverrideDecision
{
    /**
     * @param  array<string, mixed>  $authorizationContext  Decision-time authorization context metadata
     */
    public function __construct(
        public bool $isOverride,
        public PriceOverrideDirection $direction,
        public string $unitPrice,
        public string $minimumAllowedPrice,
        public string $defaultSellingPrice,
        public string $mrp,
        public string $varianceAmount,
        public ?string $reason = null,
        public ?int $authorizedById = null,
        public ?string $authorizedByEmail = null,
        public ?CarbonImmutable $authorizedAt = null,
        public array $authorizationContext = []
    ) {}

    /**
     * Convert the decision DTO to an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_override' => $this->isOverride,
            'direction' => $this->direction->value,
            'direction_label' => $this->direction->label(),
            'unit_price' => $this->unitPrice,
            'minimum_allowed_price' => $this->minimumAllowedPrice,
            'default_selling_price' => $this->defaultSellingPrice,
            'mrp' => $this->mrp,
            'variance_amount' => $this->varianceAmount,
            'reason' => $this->reason,
            'authorized_by_id' => $this->authorizedById,
            'authorized_by_email' => $this->authorizedByEmail,
            'authorized_at' => $this->authorizedAt?->toIso8601String(),
            'authorization_context' => $this->authorizationContext,
        ];
    }
}
