<?php

namespace App\DTOs\Product;

use App\Enums\ProductStatus;

readonly class ProductData
{
    public function __construct(
        public string $sku,
        public string $name,
        public ?string $description,
        public ?int $category_id,
        public string $unit,
        public ProductStatus $status,
        public float $cost_price,
        public float $minimum_allowed_price,
        public float $default_selling_price,
        public float $mrp,
        public ?int $tax_profile_id = null,
    ) {}

    /**
     * Build instance from validated array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $statusValue = $data['status'] ?? ProductStatus::ACTIVE->value;
        $status = $statusValue instanceof ProductStatus
            ? $statusValue
            : (ProductStatus::tryFrom((string) $statusValue) ?? ProductStatus::ACTIVE);

        return new self(
            sku: strtoupper(trim((string) ($data['sku'] ?? ''))),
            name: trim((string) ($data['name'] ?? '')),
            description: isset($data['description']) && trim((string) $data['description']) !== '' ? trim((string) $data['description']) : null,
            category_id: isset($data['category_id']) && $data['category_id'] !== '' && $data['category_id'] !== null ? (int) $data['category_id'] : null,
            unit: isset($data['unit']) && trim((string) $data['unit']) !== '' ? trim((string) $data['unit']) : 'UNIT',
            status: $status,
            cost_price: (float) ($data['cost_price'] ?? 0.00),
            minimum_allowed_price: (float) ($data['minimum_allowed_price'] ?? 0.00),
            default_selling_price: (float) ($data['default_selling_price'] ?? 0.00),
            mrp: (float) ($data['mrp'] ?? 0.00),
            tax_profile_id: isset($data['tax_profile_id']) && $data['tax_profile_id'] !== '' && $data['tax_profile_id'] !== null ? (int) $data['tax_profile_id'] : null,
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
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'unit' => $this->unit,
            'status' => $this->status->value,
            'cost_price' => $this->cost_price,
            'minimum_allowed_price' => $this->minimum_allowed_price,
            'default_selling_price' => $this->default_selling_price,
            'mrp' => $this->mrp,
            'tax_profile_id' => $this->tax_profile_id,
        ];
    }
}
