<?php

namespace App\DTOs\Order;

class CreateOrderItemDTO
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
        public readonly ?string $unitPrice = null,
    ) {}

    /**
     * Build instance from validated array data.
     */
    public static function fromArray(array $data): self
    {
        $rawPrice = $data['unit_price'] ?? null;
        $normalizedPrice = null;

        if ($rawPrice !== null && $rawPrice !== '') {
            $normalizedPrice = is_numeric($rawPrice)
                ? number_format((float) $rawPrice, 2, '.', '')
                : (string) $rawPrice;
        }

        return new self(
            productId: (int) $data['product_id'],
            quantity: (int) $data['quantity'],
            unitPrice: $normalizedPrice,
        );
    }

    /**
     * Convert to array representation.
     *
     * @return array{product_id: int, quantity: int, unit_price: ?string}
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
        ];
    }
}
