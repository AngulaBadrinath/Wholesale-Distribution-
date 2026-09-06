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

class DeliveryRescheduleReturnTest extends TestCase
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
            'code' => 'WH-RR-01',
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
            'name' => 'Metro Grocery Hub',
            'code' => 'CUST-MG-01',
            'contact_name' => 'Mario Rossi',
            'phone' => '+15555678901',
            'email' => 'mario@metrogrocery.com',
            'billing_address_line1' => '800 5th Avenue',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '800 5th Avenue',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
        ]);
    }

    private function createDeliveryMission(DeliveryStatus $status = DeliveryStatus::FAILED): array
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
            'subtotal' => 300.00,
            'tax_total' => 15.00,
            'grand_total' => 315.00,
        ]);

        $product = Product::create([
            'sku' => 'PROD-RR-01',
            'name' => 'Extra Virgin Olive Oil 5L',
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
            'reserved_quantity' => 15,
            'available_quantity' => 85,
            'damaged_quantity' => 0,
            'version' => 1,
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $product->sku,
            'unit_snapshot' => 'BOTTLE',
            'ordered_quantity' => 15,
            'unit_price' => 20.00,
            'tax_rate_snapshot' => 0.05,
            'taxable_amount' => 300.00,
            'tax_amount' => 15.00,
            'line_total' => 315.00,
        ]);

        $allocation = OrderItemAllocation::create([
            'allocation_number' => 'ALC-' . strtoupper(Str::random(8)),
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'warehouse_code' => $this->warehouse->code,
            'allocated_quantity' => 15,
            'reserved_quantity' => 15,
            'picked_quantity' => 15,
            'dispatched_quantity' => 15,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'status' => AllocationStatus::DISPATCHED,
        ]);

        $delivery = Delivery::create([
            'delivery_number' => 'DEL-' . date('Ymd') . '-0088',
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'status' => $status,
            'delivery_address_line1' => '800 5th Avenue',
            'delivery_city' => 'Metropolis',
            'delivery_state' => 'NY',
            'delivery_postal_code' => '10001',
            'delivery_country_code' => 'USA',
            'scheduled_date' => Carbon::today(),
            'assigned_at' => Carbon::now()->subHours(4),
            'picked_up_at' => Carbon::now()->subHours(3),
            'out_for_delivery_at' => Carbon::now()->subHours(2),
            'failed_at' => $status === DeliveryStatus::FAILED ? Carbon::now()->subHour() : null,
            'created_by' => $this->admin->id,
        ]);

        $item = DeliveryItem::create([
            'delivery_id' => $delivery->id,
            'order_item_id' => $orderItem->id,
            'order_item_allocation_id' => $allocation->id,
            'product_id' => $product->id,
            'deliverable_quantity' => 15,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $product->sku,
        ]);

        if ($status === DeliveryStatus::FAILED) {
            DeliveryFailure::create([
                'delivery_id' => $delivery->id,
                'failure_reason' => DeliveryFailureReason::CUSTOMER_UNAVAILABLE,
                'driver_notes' => 'Customer store was closed during delivery attempt.',
                'driver_id' => $this->driver->id,
                'reported_at' => Carbon::now()->subHour(),
            ]);
        }

        return compact('order', 'product', 'balance', 'orderItem', 'allocation', 'delivery', 'item');
    }

    public function test_driver_can_reschedule_failed_delivery_to_future_date(): void
    {
        $context = $this->createDeliveryMission(DeliveryStatus::FAILED);
        $delivery = $context['delivery'];

        $tomorrow = Carbon::tomorrow()->toDateString();
        $payload = [
            'scheduled_date' => $tomorrow,
            'delivery_window' => 'Morning (09:00 - 13:00)',
            'notes' => 'Customer requested morning delivery tomorrow.',
        ];

        $response = $this->actingAs($this->driver)
            ->postJson("/delivery/{$delivery->id}/reschedule", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'delivery' => [
                    'id' => $delivery->id,
                    'status' => DeliveryStatus::RESCHEDULED->value,
                ],
            ]);

        $delivery->refresh();
        $this->assertEquals(DeliveryStatus::RESCHEDULED, $delivery->status);
        $this->assertEquals($tomorrow, $delivery->scheduled_date->toDateString());
        $this->assertEquals('Morning (09:00 - 13:00)', $delivery->delivery_window);

        // Verify Order status
        $this->assertEquals(DeliveryStatus::RESCHEDULED, $context['order']->fresh()->delivery_status);

        // Verify DeliveryEvent
        $this->assertDatabaseHas('delivery_events', [
            'delivery_id' => $delivery->id,
            'event_type' => DeliveryEventType::RESCHEDULED->value,
            'from_status' => DeliveryStatus::FAILED->value,
            'to_status' => DeliveryStatus::RESCHEDULED->value,
            'actor_id' => $this->driver->id,
        ]);
    }

    public function test_driver_can_reschedule_assigned_delivery(): void
    {
        $context = $this->createDeliveryMission(DeliveryStatus::ASSIGNED);
        $delivery = $context['delivery'];

        $futureDate = Carbon::today()->addDays(2)->toDateString();
        $payload = [
            'scheduled_date' => $futureDate,
        ];

        $response = $this->actingAs($this->driver)
            ->postJson("/delivery/{$delivery->id}/reschedule", $payload);

        $response->assertStatus(200);

        $delivery->refresh();
        $this->assertEquals(DeliveryStatus::RESCHEDULED, $delivery->status);
        $this->assertEquals($futureDate, $delivery->scheduled_date->toDateString());
    }

    public function test_cannot_reschedule_delivered_or_out_for_delivery_mission(): void
    {
        $context = $this->createDeliveryMission(DeliveryStatus::OUT_FOR_DELIVERY);
        $delivery = $context['delivery'];

        $response = $this->actingAs($this->driver)
            ->postJson("/delivery/{$delivery->id}/reschedule", [
                'scheduled_date' => Carbon::tomorrow()->toDateString(),
            ]);

        $response->assertStatus(409);
    }

    public function test_reschedule_validation_requires_valid_future_date(): void
    {
        $context = $this->createDeliveryMission(DeliveryStatus::FAILED);
        $delivery = $context['delivery'];

        // Missing date
        $this->actingAs($this->driver)
            ->postJson("/delivery/{$delivery->id}/reschedule", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_date']);

        // Past date
        $this->actingAs($this->driver)
            ->postJson("/delivery/{$delivery->id}/reschedule", [
                'scheduled_date' => Carbon::yesterday()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_date']);
    }

    public function test_driver_can_return_failed_delivery_to_warehouse(): void
    {
        $context = $this->createDeliveryMission(DeliveryStatus::FAILED);
        $delivery = $context['delivery'];

        $response = $this->actingAs($this->driver)
            ->postJson("/delivery/{$delivery->id}/return-to-warehouse", [
                'notes' => 'Returned 15 units safely to receiving hub.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'delivery' => [
                    'id' => $delivery->id,
                    'status' => DeliveryStatus::RETURNED_TO_WAREHOUSE->value,
                ],
            ]);

        $delivery->refresh();
        $this->assertEquals(DeliveryStatus::RETURNED_TO_WAREHOUSE, $delivery->status);
        $this->assertNotNull($delivery->returned_at);

        // Verify Order status
        $order = $context['order']->fresh();
        $this->assertEquals(DeliveryStatus::RETURNED_TO_WAREHOUSE, $order->delivery_status);
        $this->assertEquals(FulfillmentStatus::RESERVED, $order->fulfillment_status);

        // Verify Allocation reset
        $allocation = $context['allocation']->fresh();
        $this->assertEquals(0, $allocation->dispatched_quantity);
        $this->assertEquals(AllocationStatus::ALLOCATED, $allocation->status);

        // Invariant Model B: Stock remains reserved in warehouse (no double deduction, no double restock)
        $balance = $context['balance']->fresh();
        $this->assertEquals(100, $balance->on_hand_quantity);
        $this->assertEquals(15, $balance->reserved_quantity);

        // Verify failure was resolved
        $failure = DeliveryFailure::where('delivery_id', $delivery->id)->first();
        $this->assertNotNull($failure->resolved_at);
        $this->assertEquals('RETURNED_TO_WAREHOUSE', $failure->resolution_action);
        $this->assertEquals($this->driver->id, $failure->resolved_by);

        // Verify DeliveryEvent
        $this->assertDatabaseHas('delivery_events', [
            'delivery_id' => $delivery->id,
            'event_type' => DeliveryEventType::RETURNED_TO_WAREHOUSE->value,
            'from_status' => DeliveryStatus::FAILED->value,
            'to_status' => DeliveryStatus::RETURNED_TO_WAREHOUSE->value,
            'actor_id' => $this->driver->id,
        ]);
    }

    public function test_driver_can_return_picked_up_delivery_to_warehouse(): void
    {
        $context = $this->createDeliveryMission(DeliveryStatus::PICKED_UP);
        $delivery = $context['delivery'];

        $response = $this->actingAs($this->driver)
            ->postJson("/delivery/{$delivery->id}/return-to-warehouse", [
                'notes' => 'Route cancelled by dispatch supervisor.',
            ]);

        $response->assertStatus(200);

        $delivery->refresh();
        $this->assertEquals(DeliveryStatus::RETURNED_TO_WAREHOUSE, $delivery->status);
    }

    public function test_anti_idor_other_driver_gets_404_on_reschedule_and_return(): void
    {
        $context = $this->createDeliveryMission(DeliveryStatus::FAILED);
        $delivery = $context['delivery'];

        // Reschedule
        $this->actingAs($this->otherDriver)
            ->postJson("/delivery/{$delivery->id}/reschedule", [
                'scheduled_date' => Carbon::tomorrow()->toDateString(),
            ])
            ->assertStatus(404);

        // Return
        $this->actingAs($this->otherDriver)
            ->postJson("/delivery/{$delivery->id}/return-to-warehouse", [
                'notes' => 'Unauthorized return',
            ])
            ->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_reschedule_or_return(): void
    {
        $context = $this->createDeliveryMission(DeliveryStatus::FAILED);
        $delivery = $context['delivery'];

        $this->postJson("/delivery/{$delivery->id}/reschedule", [
            'scheduled_date' => Carbon::tomorrow()->toDateString(),
        ])->assertStatus(401);

        $this->postJson("/delivery/{$delivery->id}/return-to-warehouse", [
            'notes' => 'Unauthorized',
        ])->assertStatus(401);
    }

    public function test_idempotent_return_to_warehouse_call(): void
    {
        $context = $this->createDeliveryMission(DeliveryStatus::FAILED);
        $delivery = $context['delivery'];

        // First call
        $this->actingAs($this->driver)
            ->postJson("/delivery/{$delivery->id}/return-to-warehouse", ['notes' => 'First call'])
            ->assertStatus(200);

        // Second call (idempotency check)
        $response2 = $this->actingAs($this->driver)
            ->postJson("/delivery/{$delivery->id}/return-to-warehouse", ['notes' => 'Second call']);

        $response2->assertStatus(200);
        $delivery->refresh();
        $this->assertEquals(DeliveryStatus::RETURNED_TO_WAREHOUSE, $delivery->status);
    }
}
