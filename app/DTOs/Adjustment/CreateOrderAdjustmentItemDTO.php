<?php

namespace App\DTOs\Adjustment;

class CreateOrderAdjustmentItemDTO
{
    public function __construct(
        public readonly int $orderItemId,
        public readonly int $reductionQuantity,
    ) {}

    /**
     * Build instance from validated array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            orderItemId: (int) $data['order_item_id'],
            reductionQuantity: (int) ($data['reduction_quantity'] ?? $data['requested_quantity_reduction'] ?? 0),
        );
    }
}
