<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\CustomerStatus;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_number' => 'DEL-' . date('Y') . '-' . sprintf('%06d', fake()->unique()->numberBetween(1, 999999)),
            'order_id' => function () {
                $customer = Customer::first() ?? Customer::create([
                    'salesman_id' => User::factory()->salesman()->create()->id,
                    'name' => 'Acme Corporation',
                    'code' => 'CUST-' . strtoupper(Str::random(6)),
                    'contact_name' => fake()->name(),
                    'phone' => '+1-555-0100',
                    'email' => fake()->unique()->safeEmail(),
                    'billing_address_line1' => '100 Main St',
                    'billing_city' => 'Metropolis',
                    'billing_state' => 'NY',
                    'billing_postal_code' => '10001',
                    'billing_country' => 'USA',
                    'shipping_address_line1' => '100 Main St',
                    'shipping_city' => 'Metropolis',
                    'shipping_state' => 'NY',
                    'shipping_postal_code' => '10001',
                    'shipping_country' => 'USA',
                    'status' => CustomerStatus::ACTIVE,
                ]);

                $warehouse = Warehouse::first() ?? Warehouse::create([
                    'code' => 'WH-' . strtoupper(Str::random(4)),
                    'name' => 'Main Warehouse',
                    'address_line1' => '100 Logistics Blvd',
                    'city' => 'Metropolis',
                    'state' => 'NY',
                    'postal_code' => '10001',
                    'country_code' => 'USA',
                    'is_active' => true,
                    'is_default' => true,
                ]);

                $salesman = User::factory()->salesman()->create();

                return Order::create([
                    'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(Str::random(8)),
                    'customer_id' => $customer->id,
                    'salesman_id' => $salesman->id,
                    'warehouse_id' => $warehouse->id,
                    'created_by' => $salesman->id,
                    'status' => OrderStatus::APPROVED,
                    'fulfillment_status' => FulfillmentStatus::RESERVED,
                    'payment_status' => PaymentStatus::UNPAID,
                    'delivery_status' => DeliveryStatus::ASSIGNED,
                    'currency' => 'USD',
                    'idempotency_key' => 'idemp-' . Str::uuid(),
                    'subtotal' => 100.00,
                    'tax_total' => 10.00,
                    'adjustment_total' => 0.00,
                    'grand_total' => 110.00,
                    'version' => 1,
                    'submitted_at' => now(),
                    'approved_at' => now(),
                ])->id;
            },
            'customer_id' => function (array $attributes) {
                if (! empty($attributes['order_id'])) {
                    $order = Order::find($attributes['order_id']);
                    if ($order) {
                        return $order->customer_id;
                    }
                }

                return Customer::first()?->id ?? Customer::create([
                    'salesman_id' => User::factory()->salesman()->create()->id,
                    'name' => 'Acme Corporation',
                    'code' => 'CUST-' . strtoupper(Str::random(6)),
                    'contact_name' => fake()->name(),
                    'phone' => '+1-555-0100',
                    'email' => fake()->unique()->safeEmail(),
                    'billing_address_line1' => '100 Main St',
                    'billing_city' => 'Metropolis',
                    'billing_state' => 'NY',
                    'billing_postal_code' => '10001',
                    'billing_country' => 'USA',
                    'shipping_address_line1' => '100 Main St',
                    'shipping_city' => 'Metropolis',
                    'shipping_state' => 'NY',
                    'shipping_postal_code' => '10001',
                    'shipping_country' => 'USA',
                    'status' => CustomerStatus::ACTIVE,
                ])->id;
            },
            'driver_id' => fn () => User::factory()->deliveryPartner()->create()->id,
            'status' => DeliveryStatus::ASSIGNED,
            'delivery_contact_name' => fake()->name(),
            'delivery_contact_phone' => fake()->phoneNumber(),
            'delivery_address_line1' => fake()->streetAddress(),
            'delivery_address_line2' => fake()->secondaryAddress(),
            'delivery_city' => fake()->city(),
            'delivery_state' => fake()->stateAbbr(),
            'delivery_postal_code' => fake()->postcode(),
            'delivery_country_code' => 'USA',
            'scheduled_date' => Carbon::today(),
            'delivery_window' => '09:00 - 12:00',
            'driver_instructions' => 'Deliver to back loading dock.',
            'assigned_at' => Carbon::now(),
            'version' => 1,
            'created_by' => fn () => User::factory()->admin()->create()->id,
            'updated_by' => fn () => User::factory()->admin()->create()->id,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryStatus::PENDING_ASSIGNMENT,
            'driver_id' => null,
            'assigned_at' => null,
        ]);
    }

    public function pickedUp(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryStatus::PICKED_UP,
            'picked_up_at' => Carbon::now(),
        ]);
    }

    public function outForDelivery(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryStatus::OUT_FOR_DELIVERY,
            'picked_up_at' => Carbon::now()->subHour(),
            'out_for_delivery_at' => Carbon::now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryStatus::DELIVERED,
            'picked_up_at' => Carbon::now()->subHours(2),
            'out_for_delivery_at' => Carbon::now()->subHour(),
            'delivered_at' => Carbon::now(),
            'recipient_name' => fake()->name(),
            'pod_notes' => 'Received in good condition.',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => DeliveryStatus::FAILED,
            'picked_up_at' => Carbon::now()->subHours(2),
            'out_for_delivery_at' => Carbon::now()->subHour(),
            'failed_at' => Carbon::now(),
        ]);
    }
}
