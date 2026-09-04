<?php

namespace App\DTOs\Category;

use App\Enums\CategoryStatus;

readonly class CategoryData
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $description = null,
        public ?int $parent_id = null,
        public int $sort_order = 0,
        public CategoryStatus $status = CategoryStatus::ACTIVE
    ) {}

    /**
     * Create DTO from array of validated request inputs.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $status = $data['status'] ?? CategoryStatus::ACTIVE;
        if (is_string($status)) {
            $status = CategoryStatus::from($status);
        }

        return new self(
            code: strtoupper(trim((string) ($data['code'] ?? ''))),
            name: trim((string) ($data['name'] ?? '')),
            description: isset($data['description']) && trim((string) $data['description']) !== ''
                ? trim((string) $data['description'])
                : null,
            parent_id: isset($data['parent_id']) && (int) $data['parent_id'] > 0
                ? (int) $data['parent_id']
                : null,
            sort_order: isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            status: $status
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
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'sort_order' => $this->sort_order,
            'status' => $this->status->value,
        ];
    }
}
