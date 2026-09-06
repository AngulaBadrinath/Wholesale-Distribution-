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

class WarehousePickupTest extends TestCase
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

        $this->driver = User::factory()->deliveryPartner()->create(['name' => 'Driver Dave']);
        $this->otherDriver = User::factory()->deliveryPartner()->create(['name' => 'Driver Eve']);
        $this->admin = User::factory()->admin()->create();

        $this->warehouse = Warehouse::create([
            'code' => 'WH-DEL-03',
            'name' => 'East Logistics Hub',
            'address_line1' => '900 Freight Road',
            'city' => 'Metropolis',
            'state' => 'NY',
            'postal_code' => '10001',
            'country_code' => 'USA',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->admin->id,
            'name' => 'Sunrise Market',
            'code' => 'CUST-SUN-01',
            'contact_name' => 'Sara Connor',
            'phone' => '+15553456789',
            'email' => 'sara@sunrise.com',
            'billing_address_line1' => '300 Sunrise Ave',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '300 Sunrise Ave',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
        ]);
    }

    private function createDeliveryWithAllocations(array $deliveryOverrides = []): array
    {
        $order = Order::create([
            'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(Str::random(8)),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->admin->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->admin->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::PICKED,
            'payment_status' => PaymentStatus::UNPAID,
            'delivery_status' => DeliveryStatus::ASSIGNED,
            'currency' => 'USD',
            'idempotency_key' => 'idemp-' . Str::uuid(),
            'subtotal' => 200.00,
            'tax_total' => 10.00,
            'adjustment_total' => 0.00,
            'grand_total' => 210.00,
            'version' => 1,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);

        $product = Product::create([
            'sku' => 'SKU-' . strtoupper(Str::random(6)),
            'name' => 'Juice Box 24pk',
            'unit' => 'BOX',
            'cost_price' => 10.00,
            'minimum_allowed_price' => 15.00,
            'default_selling_price' => 20.00,
            'mrp' => 25.00,
            'status' => ProductStatus::ACTIVE,
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $product->sku,
            'unit_snapshot' => 'BOX',
            'ordered_quantity' => 8,
            'unit_price' => 20.00,
            'tax_rate_snapshot' => 0.05,
            'taxable_amount' => 160.00,
            'tax_amount' => 8.00,
            'line_total' => 168.00,
        ]);

        $allocation = OrderItemAllocation::create([
            'allocation_number' => 'ALC-' . Str::random(8),
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'allocated_quantity' => 8,
            'reserved_quantity' => 8,
            'picked_quantity' => 8,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'status' => AllocationStatus::PICKED,
        ]);

        $delivery = Delivery::create(array_merge([
            'delivery_number' => 'DEL-' . date('Y') . '-' . sprintf('%06d', rand(100000, 999999)),
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'status' => DeliveryStatus::ASSIGNED,
            'delivery_contact_name' => 'Sara Connor',
            'delivery_contact_phone' => '+15553456789',
            'delivery_address_line1' => '300 Sunrise Ave',
            'delivery_city' => 'Metropolis',
            'delivery_state' => 'NY',
            'delivery_postal_code' => '10001',
            'delivery_country_code' => 'USA',
            'scheduled_date' => Carbon::today(),
            'assigned_at' => Carbon::now()->subHour(),
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ], $deliveryOverrides));

        return [$delivery, $order, $allocation];
    }

    public function test_assigned_driver_can_confirm_warehouse_pickup_successfully(): void
    {
        [$delivery, $order, $allocation] = $this->createDeliveryWithAllocations();

        $response = $this->actingAs($this->driver)->postJson(route('delivery.pickup', $delivery), [
            'notes' => 'Picked up from loading bay 4',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Verify delivery status updated
        $delivery->refresh();
        $this->assertSame(DeliveryStatus::PICKED_UP, $delivery->status);
        $this->assertNotNull($delivery->picked_up_at);

        // Verify order status updated
        $order->refresh();
        $this->assertSame(FulfillmentStatus::DISPATCHED, $order->fulfillment_status);
        $this->assertSame(DeliveryStatus::PICKED_UP, $order->delivery_status);

        // Verify allocation updated (dispatched_quantity = picked_quantity)
        $allocation->refresh();
        $this->assertSame(8, $allocation->dispatched_quantity);
        $this->assertSame(AllocationStatus::DISPATCHED, $allocation->status);

        // Verify event logged
        $events = $delivery->events()->where('event_type', DeliveryEventType::PICKED_UP)->get();
        $this->assertCount(1, $events);
        $this->assertSame($this->driver->id, $events->first()->actor_id);
    }

    public function test_admin_can_confirm_pickup_on_behalf_of_driver(): void
    {
        [$delivery, $order, $allocation] = $this->createDeliveryWithAllocations();

        $response = $this->actingAs($this->admin)->postJson(route('delivery.pickup', $delivery));

        $response->assertStatus(200);
        $delivery->refresh();
        $this->assertSame(DeliveryStatus::PICKED_UP, $delivery->status);
    }

    public function test_anti_idor_other_driver_cannot_confirm_pickup(): void
    {
        [$delivery] = $this->createDeliveryWithAllocations();

        $response = $this->actingAs($this->otherDriver)->postJson(route('delivery.pickup', $delivery));

        $response->assertStatus(404);
    }

    public function test_pickup_fails_if_delivery_is_in_invalid_status(): void
    {
        [$delivery] = $this->createDeliveryWithAllocations(['status' => DeliveryStatus::OUT_FOR_DELIVERY]);

        $response = $this->actingAs($this->driver)->postJson(route('delivery.pickup', $delivery));

        $response->assertStatus(409);
    }

    public function test_pickup_is_idempotent_if_already_picked_up(): void
    {
        [$delivery] = $this->createDeliveryWithAllocations([
            'status' => DeliveryStatus::PICKED_UP,
            'picked_up_at' => Carbon::now()->subMinutes(10),
        ]);

        $response = $this->actingAs($this->driver)->postJson(route('delivery.pickup', $delivery));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }
}
