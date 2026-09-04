<?php

namespace App\DTOs\Order;

class CreateOrderDTO
{
    /**
     * @param  array<int, CreateOrderItemDTO>  $items
     */
    public function __construct(
        public readonly int $customerId,
        public readonly string $idempotencyKey,
        public readonly ?string $notes = null,
        public readonly array $items = [],
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
            idempotencyKey: trim((string) ($data['idempotency_key'] ?? '')),
            notes: isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
            items: $items,
        );
    }

    /**
     * Generate a deterministic canonical hash fingerprint of the client command intent.
     * Used for idempotency payload mismatch (409 Conflict) detection.
     */
    public function canonicalFingerprint(): string
    {
        $canonicalItems = array_map(
            fn (CreateOrderItemDTO $item) => [
                'product_id' => $item->productId,
                'quantity' => $item->quantity,
                'unit_price' => $item->unitPrice,
            ],
            $this->items
        );

        // Sort items by product_id ascending for deterministic hash
        usort($canonicalItems, fn ($a, $b) => $a['product_id'] <=> $b['product_id']);

        $canonicalStructure = [
            'customer_id' => $this->customerId,
            'notes' => $this->notes,
            'items' => $canonicalItems,
        ];

        return hash('sha256', (string) json_encode($canonicalStructure));
    }
}
