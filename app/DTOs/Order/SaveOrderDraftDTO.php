<?php

namespace App\DTOs\Order;

class SaveOrderDraftDTO
{
    /**
     * @param  array<int, CreateOrderItemDTO>  $items
     */
    public function __construct(
        public readonly int $customerId,
        public readonly ?string $notes = null,
        public readonly array $items = [],
        public readonly ?int $expectedVersion = null,
        public readonly ?string $idempotencyKey = null,
    ) {}

    /**
     * Build instance from validated array data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $items = array_map(
            fn (array $item) => CreateOrderItemDTO::fromArray($item),
            $data['items'] ?? []
        );

        return new self(
            customerId: (int) $data['customer_id'],
            notes: isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
            items: $items,
            expectedVersion: isset($data['expected_version']) ? (int) $data['expected_version'] : null,
            idempotencyKey: isset($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : null,
        );
    }
}
