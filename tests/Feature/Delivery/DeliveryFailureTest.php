<?php

namespace Tests\Feature\Delivery;

use App\Enums\AllocationStatus;
use App\Enums\CustomerStatus;
use App\Enums\DeliveryEventType;
use App\Enums\DeliveryFailureReason;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryEvent;
use App\Models\DeliveryFailure;
use App\Models\DeliveryItem;
use App\Models\InventoryBalance;
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

class DeliveryFailureTest extends TestCase
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

        $this->driver = User::factory()->deliveryPartner()->create(['name' => 'Driver Frank']);
        $this->otherDriver = User::factory()->deliveryPartner()->create(['name' => 'Driver Gina']);
        $this->admin = User::factory()->admin()->create();

        $this->warehouse = Warehouse::create([
            'code' => 'WH-FAIL-01',
            'name' => 'Central Hub',
            'address_line1' => '1200 Logistics Park',
            'city' => 'Metropolis',
            'state' => 'NY',
            'postal_code' => '10001',
            'country_code' => 'USA',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->admin->id,
            'name' => 'Apex Retailers',
            'code' => 'CUST-APEX-01',
            'contact_name' => 'Alex Mercer',
            'phone' => '+15555678901',
            'email' => 'alex@apexretail.com',
            'billing_address_line1' => '700 Broadway',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '700 Broadway',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
        ]);
    }

    private function createDeliveryMission(DeliveryStatus $status = DeliveryStatus::OUT_FOR_DELIVERY): array
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
            'delivery_status' => $status,
            'currency' => 'USD',
            'idempotency_key' => 'idemp-' . Str::uuid(),
            'subtotal' => 200.00,
            'tax_total' => 10.00,
            'grand_total' => 210.00,
        ]);

        $product = Product::create([
            'sku' => 'PROD-FAIL-01',
            'name' => 'Premium Olive Oil 5L',
            'unit' => 'BOTTLE',
            'cost_price' => 15.00,
            'minimum_allowed_price' => 18.00,
            'default_selling_price' => 20.00,
            'mrp' => 25.00,
            'status' => ProductStatus::ACTIVE,
            'created_by' => $this->admin->id,
        ]);

        $balance = InventoryBalance::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'on_hand_quantity' => 100,
            'reserved_quantity' => 10,
            'available_quantity' => 90,
            'damaged_quantity' => 0,
            'version' => 1,
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $product->sku,
            'unit_snapshot' => 'BOTTLE',
            'ordered_quantity' => 10,
            'unit_price' => 20.00,
            'tax_rate_snapshot' => 0.05,
            'taxable_amount' => 200.00,
            'tax_amount' => 10.00,
            'line_total' => 210.00,
        ]);

        $allocation = OrderItemAllocation::create([
            'allocation_number' => 'ALC-' . strtoupper(Str::random(8)),
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'warehouse_code' => $this->warehouse->code,
            'allocated_quantity' => 10,
            'reserved_quantity' => 10,
            'picked_quantity' => 10,
            'dispatched_quantity' => 10,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'status' => AllocationStatus::DISPATCHED,
        ]);

        $delivery = Delivery::create([
            'delivery_number' => 'DEL-' . date('Ymd') . '-0099',
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'status' => $status,
            'delivery_address_line1' => '700 Broadway',
            'delivery_city' => 'Metropolis',
            'delivery_state' => 'NY',
            'delivery_postal_code' => '10001',
            'delivery_country_code' => 'USA',
            'scheduled_date' => Carbon::today(),
            'assigned_at' => Carbon::now()->subHours(3),
            'picked_up_at' => Carbon::now()->subHours(2),
            'out_for_delivery_at' => $status === DeliveryStatus::OUT_FOR_DELIVERY ? Carbon::now()->subHour() : null,
            'created_by' => $this->admin->id,
        ]);

        $item = DeliveryItem::create([
            'delivery_id' => $delivery->id,
            'order_item_id' => $orderItem->id,
            'order_item_allocation_id' => $allocation->id,
            'product_id' => $product->id,
            'deliverable_quantity' => 10,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $product->sku,
        ]);

        return compact('order', 'product', 'balance', 'orderItem', 'allocation', 'delivery', 'item');
    }

    public function test_driver_can_record_delivery_failure_from_out_for_delivery(): void
    {
        $context = $this->createDeliveryMission(DeliveryStatus::OUT_FOR_DELIVERY);
        $delivery = $context['delivery'];

        $payload = [
            'failure_reason' => DeliveryFailureReason::CUSTOMER_UNAVAILABLE->value,
            'driver_notes' => 'Customer store is closed, gate locked, no one answered phone.',
        ];

        $response = $this->actingAs($this->driver)
            ->postJson("/delivery/{$delivery->id}/fail", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'delivery' => [
                    'id' => $delivery->id,
                    'status' => DeliveryStatus::FAILED->value,
                ],
            ]);

        $delivery->refresh();
        $this->assertEquals(DeliveryStatus::FAILED, $delivery->status);
        $this->assertNotNull($delivery->failed_at);

        // Verify Order delivery status
        $this->assertEquals(DeliveryStatus::FAILED, $context['order']->fresh()->delivery_status);

        // Verify structured DeliveryFailure record
        $failure = DeliveryFailure::where('delivery_id', $delivery->id)->first();
        $this->assertNotNull($failure);
        $this->assertEquals(DeliveryFailureReason::CUSTOMER_UNAVAILABLE, $failure->failure_reason);
        $this->assertEquals($payload['driver_notes'], $failure->driver_notes);
        $this->assertEquals($this->driver->id, $failure->driver_id);

        // Verify immutable DeliveryEvent
        $this->assertDatabaseHas('delivery_events', [
            'delivery_id' => $delivery->id,
            'event_type' => DeliveryEventType::FAILED->value,
            'from_status' => DeliveryStatus::OUT_FOR_DELIVERY->value,
            'to_status' => DeliveryStatus::FAILED->value,
            'actor_id' => $this->driver->id,
        ]);

        // Invariant: Inventory is unchanged
        $balance = $context['balance']->fresh();
        $this->assertEquals(100, $balance->on_hand_quantity);
        $this->assertEquals(10, $balance->reserved_quantity);
    }

    public function test_driver_can_record_delivery_failure_from_picked_up(): void
    {
        $context = $this->createDeliveryMission(DeliveryStatus::PICKED_UP);
        $delivery = $context['delivery'];

        $payload = [
            'failure_reason' => DeliveryFailureReason::VEHICLE_BREAKDOWN->value,
            'driver_notes' => 'Flat tire on delivery van, cannot proceed to route.',
        ];

        $response = $this->actingAs($this->driver)
            ->postJson("/delivery/{$delivery->id}/fail", $payload);

        $response->assertStatus(200);

        $delivery->refresh();
        $this->assertEquals(DeliveryStatus::FAILED, $delivery->status);
    }

    public function test_driver_cannot_record_failure_for_already_delivered_mission(): void
    {
        $context = $this->createDeliveryMission(DeliveryStatus::DELIVERED);
        $delivery = $context['delivery'];

        $payload = [
            'failure_reason' => DeliveryFailureReason::CUSTOMER_UNAVAILABLE->value,
            'driver_notes' => 'Attempting to fail a completed delivery.',
        ];

        $response = $this->actingAs($this->driver)
            ->postJson("/delivery/{$delivery->id}/fail", $payload);

        $response->assertStatus(409);
    }

    public function test_anti_idor_other_driver_gets_404_when_recording_failure(): void
    {
        $context = $this->createDeliveryMission(DeliveryStatus::OUT_FOR_DELIVERY);
        $delivery = $context['delivery'];

        $payload = [
            'failure_reason' => DeliveryFailureReason::ADDRESS_NOT_FOUND->value,
            'driver_notes' => 'Unauthorized driver reporting failure.',
        ];

        $response = $this->actingAs($this->otherDriver)
            ->postJson("/delivery/{$delivery->id}/fail", $payload);

        // Fail-Closed Anti-IDOR Convention: 404 Not Found
        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_record_failure(): void
    {
        $context = $this->createDeliveryMission(DeliveryStatus::OUT_FOR_DELIVERY);
        $delivery = $context['delivery'];

        $response = $this->postJson("/delivery/{$delivery->id}/fail", [
            'failure_reason' => DeliveryFailureReason::OTHER->value,
            'driver_notes' => 'Test notes',
        ]);

        $response->assertStatus(401);
    }

    public function test_validation_requires_authoritative_reason_and_notes(): void
    {
        $context = $this->createDeliveryMission(DeliveryStatus::OUT_FOR_DELIVERY);
        $delivery = $context['delivery'];

        // Missing failure_reason and driver_notes
        $response = $this->actingAs($this->driver)
            ->postJson("/delivery/{$delivery->id}/fail", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['failure_reason', 'driver_notes']);

        // Invalid failure_reason
        $response = $this->actingAs($this->driver)
            ->postJson("/delivery/{$delivery->id}/fail", [
                'failure_reason' => 'INVALID_REASON',
                'driver_notes' => 'Valid notes here',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['failure_reason']);

        // Driver notes too short (< 5 chars)
        $response = $this->actingAs($this->driver)
            ->postJson("/delivery/{$delivery->id}/fail", [
                'failure_reason' => DeliveryFailureReason::OTHER->value,
                'driver_notes' => 'abc',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['driver_notes']);
    }

    public function test_idempotent_failure_recording_returns_safely(): void
    {
        $context = $this->createDeliveryMission(DeliveryStatus::OUT_FOR_DELIVERY);
        $delivery = $context['delivery'];

        $payload = [
            'failure_reason' => DeliveryFailureReason::WEATHER_EMERGENCY->value,
            'driver_notes' => 'Severe flood on highway 95, road closed.',
        ];

        // First failure call
        $this->actingAs($this->driver)
            ->postJson("/delivery/{$delivery->id}/fail", $payload)
            ->assertStatus(200);

        // Second failure call (idempotency check)
        $response2 = $this->actingAs($this->driver)
            ->postJson("/delivery/{$delivery->id}/fail", $payload);

        $response2->assertStatus(200);
        $this->assertEquals(1, DeliveryFailure::where('delivery_id', $delivery->id)->count());
    }

    public function test_admin_with_permission_can_record_failure(): void
    {
        $context = $this->createDeliveryMission(DeliveryStatus::OUT_FOR_DELIVERY);
        $delivery = $context['delivery'];

        $payload = [
            'failure_reason' => DeliveryFailureReason::BUSINESS_CLOSED->value,
            'driver_notes' => 'Admin recorded failure on behalf of driver.',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/delivery/{$delivery->id}/fail", $payload);

        $response->assertStatus(200);

        $delivery->refresh();
        $this->assertEquals(DeliveryStatus::FAILED, $delivery->status);
    }
}
