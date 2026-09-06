<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryItem>
 */
class DeliveryItemFactory extends Factory
{
    protected $model = DeliveryItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 20);

        return [
            'delivery_id' => Delivery::factory(),
            'order_item_id' => OrderItem::factory(),
            'order_item_allocation_id' => OrderItemAllocation::factory(),
            'product_id' => Product::factory(),
            'product_name_snapshot' => fake()->words(3, true),
            'sku_snapshot' => 'SKU-' . fake()->unique()->numerify('#####'),
            'deliverable_quantity' => $qty,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
        ];
    }
}
