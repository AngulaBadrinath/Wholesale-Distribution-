<?php

namespace Tests\Feature\Delivery;

use App\Enums\AllocationStatus;
use App\Enums\CustomerStatus;
use App\Enums\DeliveryEventType;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class OutForDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $driver;
    private User $otherDriver;
    private User $admin;
    private Warehouse $warehouse;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = User::factory()->deliveryPartner()->create(['name' => 'Driver Dan']);
        $this->otherDriver = User::factory()->deliveryPartner()->create(['name' => 'Driver Diana']);
        $this->admin = User::factory()->admin()->create();

        $this->warehouse = Warehouse::create([
            'code' => 'WH-DEL-04',
            'name' => 'South Distribution Hub',
            'address_line1' => '1000 Commercial Way',
            'city' => 'Metropolis',
            'state' => 'NY',
            'postal_code' => '10001',
            'country_code' => 'USA',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->admin->id,
            'name' => 'Grand Plaza Grocery',
            'code' => 'CUST-GP-01',
            'contact_name' => 'Marcus Aurelius',
            'phone' => '+15554567890',
            'email' => 'marcus@grandplaza.com',
            'billing_address_line1' => '400 Plaza Blvd',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '400 Plaza Blvd',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
        ]);
    }

    private function createDelivery(array $attributes = []): array
    {
        $order = Order::create([
            'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(Str::random(8)),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->admin->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::DISPATCHED,
            'payment_status' => PaymentStatus::UNPAID,
            'delivery_status' => DeliveryStatus::PICKED_UP,
            'currency' => 'USD',
            'idempotency_key' => 'idemp-' . Str::uuid(),
            'subtotal' => 150.00,
            'tax_total' => 7.50,
            'adjustment_total' => 0.00,
            'grand_total' => 157.50,
            'version' => 1,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        $delivery = Delivery::create(array_merge([
            'delivery_number' => 'DEL-' . date('Y') . '-' . sprintf('%06d', rand(100000, 999999)),
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'status' => DeliveryStatus::PICKED_UP,
            'delivery_contact_name' => 'Marcus Aurelius',
            'delivery_contact_phone' => '+15554567890',
            'delivery_address_line1' => '400 Plaza Blvd',
            'delivery_city' => 'Metropolis',
            'delivery_state' => 'NY',
            'delivery_postal_code' => '10001',
            'delivery_country_code' => 'USA',
            'scheduled_date' => Carbon::today(),
            'assigned_at' => Carbon::now()->subHours(2),
            'picked_up_at' => Carbon::now()->subHour(),
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ], $attributes));

        return [$delivery, $order];
    }

    public function test_driver_can_start_delivery_route_successfully(): void
    {
        [$delivery, $order] = $this->createDelivery();

        $response = $this->actingAs($this->driver)->postJson(route('delivery.start-route', $delivery), [
            'notes' => 'Departing warehouse on route 5',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::OUT_FOR_DELIVERY, $delivery->status);
        $this->assertNotNull($delivery->out_for_delivery_at);

        $order->refresh();
        $this->assertSame(DeliveryStatus::OUT_FOR_DELIVERY, $order->delivery_status);

        $events = $delivery->events()->where('event_type', DeliveryEventType::OUT_FOR_DELIVERY)->get();
        $this->assertCount(1, $events);
        $this->assertSame($this->driver->id, $events->first()->actor_id);
    }

    public function test_admin_can_start_route_on_behalf_of_driver(): void
    {
        [$delivery] = $this->createDelivery();

        $response = $this->actingAs($this->admin)->postJson(route('delivery.start-route', $delivery));

        $response->assertStatus(200);
        $delivery->refresh();
        $this->assertSame(DeliveryStatus::OUT_FOR_DELIVERY, $delivery->status);
    }

    public function test_anti_idor_other_driver_cannot_start_route(): void
    {
        [$delivery] = $this->createDelivery();

        $response = $this->actingAs($this->otherDriver)->postJson(route('delivery.start-route', $delivery));

        $response->assertStatus(404);
    }

    public function test_cannot_start_route_if_not_picked_up(): void
    {
        [$delivery] = $this->createDelivery(['status' => DeliveryStatus::ASSIGNED]);

        $response = $this->actingAs($this->driver)->postJson(route('delivery.start-route', $delivery));

        $response->assertStatus(409);
    }

    public function test_start_route_is_idempotent_if_already_out_for_delivery(): void
    {
        [$delivery] = $this->createDelivery([
            'status' => DeliveryStatus::OUT_FOR_DELIVERY,
            'out_for_delivery_at' => Carbon::now()->subMinutes(15),
        ]);

        $response = $this->actingAs($this->driver)->postJson(route('delivery.start-route', $delivery));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }
}
