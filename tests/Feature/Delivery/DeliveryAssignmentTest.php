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
use App\Models\DeliveryEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Delivery\DeliveryAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $warehouseManager;
    private User $driver;
    private Warehouse $warehouse;
    private Customer $customer;
    private User $salesman;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->warehouseManager = User::factory()->warehouseManager()->create();
        $this->driver = User::factory()->deliveryPartner()->create();
        $this->salesman = User::factory()->salesman()->create();

        $this->warehouse = Warehouse::create([
            'code' => 'WH-DEL-01',
            'name' => 'Logistics Warehouse',
            'address_line1' => '500 Logistics Blvd',
            'city' => 'Metropolis',
            'state' => 'NY',
            'postal_code' => '10001',
            'country_code' => 'USA',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Metro Retail Mart',
            'code' => 'CUST-METRO-01',
            'contact_name' => 'Alice Cooper',
            'phone' => '+15551234567',
            'email' => 'alice@metroretail.com',
            'billing_address_line1' => '123 Main St',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '123 Main St',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
        ]);
    }

    private function createProduct(string $sku, string $name, float $price): Product
    {
        return Product::create([
            'sku' => $sku,
            'name' => $name,
            'unit' => 'BOX',
            'cost_price' => $price * 0.5,
            'minimum_allowed_price' => $price * 0.8,
            'default_selling_price' => $price,
            'mrp' => $price * 1.2,
            'status' => ProductStatus::ACTIVE,
        ]);
    }

    private function createFulfillableOrder(array $orderAttributes = []): array
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(Str::random(8)),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::RESERVED,
            'payment_status' => PaymentStatus::UNPAID,
            'delivery_status' => DeliveryStatus::PENDING_ASSIGNMENT,
            'currency' => 'USD',
            'idempotency_key' => 'idemp-' . Str::uuid(),
            'subtotal' => 300.00,
            'tax_total' => 15.00,
            'adjustment_total' => 0.00,
            'grand_total' => 315.00,
            'version' => 1,
            'submitted_at' => now(),
            'approved_at' => now(),
        ], $orderAttributes));

        $product1 = $this->createProduct('SKU-A-' . Str::random(4), 'Widget A', 20.00);
        $product2 = $this->createProduct('SKU-B-' . Str::random(4), 'Widget B', 20.00);

        $orderItem1 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product1->id,
            'product_name_snapshot' => $product1->name,
            'sku_snapshot' => $product1->sku,
            'unit_snapshot' => 'BOX',
            'ordered_quantity' => 10,
            'unit_price' => 20.00,
            'tax_rate_snapshot' => 0.05,
            'taxable_amount' => 200.00,
            'tax_amount' => 10.00,
            'line_total' => 210.00,
        ]);

        $orderItem2 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'product_name_snapshot' => $product2->name,
            'sku_snapshot' => $product2->sku,
            'unit_snapshot' => 'BOX',
            'ordered_quantity' => 5,
            'unit_price' => 20.00,
            'tax_rate_snapshot' => 0.05,
            'taxable_amount' => 100.00,
            'tax_amount' => 5.00,
            'line_total' => 105.00,
        ]);

        $allocation1 = OrderItemAllocation::create([
            'allocation_number' => 'ALC-' . Str::random(8),
            'order_id' => $order->id,
            'order_item_id' => $orderItem1->id,
            'product_id' => $product1->id,
            'warehouse_id' => $this->warehouse->id,
            'allocated_quantity' => 10,
            'reserved_quantity' => 10,
            'picked_quantity' => 10,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'status' => AllocationStatus::ALLOCATED,
        ]);

        $allocation2 = OrderItemAllocation::create([
            'allocation_number' => 'ALC-' . Str::random(8),
            'order_id' => $order->id,
            'order_item_id' => $orderItem2->id,
            'product_id' => $product2->id,
            'warehouse_id' => $this->warehouse->id,
            'allocated_quantity' => 5,
            'reserved_quantity' => 5,
            'picked_quantity' => 5,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'status' => AllocationStatus::ALLOCATED,
        ]);

        return [$order, [$allocation1, $allocation2]];
    }

    public function test_admin_can_assign_driver_to_eligible_order_successfully(): void
    {
        [$order, $allocations] = $this->createFulfillableOrder();

        $payload = [
            'order_id' => $order->id,
            'driver_id' => $this->driver->id,
            'scheduled_date' => Carbon::tomorrow()->toDateString(),
            'notes' => 'Priority morning delivery',
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.deliveries.assign'), $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $deliveryId = $response->json('delivery.id');
        $this->assertNotNull($deliveryId);

        $delivery = Delivery::with(['items', 'events'])->find($deliveryId);
        $this->assertNotNull($delivery);
        $this->assertSame($order->id, $delivery->order_id);
        $this->assertSame($this->customer->id, $delivery->customer_id);
        $this->assertSame($this->driver->id, $delivery->driver_id);
        $this->assertSame($this->admin->id, $delivery->created_by);
        $this->assertSame(DeliveryStatus::ASSIGNED, $delivery->status);
        $this->assertMatchesRegularExpression('/^DEL-\d{4}-\d{6}$/', $delivery->delivery_number);

        // Address snapshots verified
        $this->assertSame('123 Main St', $delivery->delivery_address_line1);
        $this->assertSame('Metropolis', $delivery->delivery_city);
        $this->assertSame('NY', $delivery->delivery_state);
        $this->assertSame('10001', $delivery->delivery_postal_code);
        $this->assertSame('+15551234567', $delivery->delivery_contact_phone);

        // Items snapshot verified
        $this->assertCount(2, $delivery->items);
        $item1 = $delivery->items->where('order_item_id', $allocations[0]->order_item_id)->first();
        $this->assertNotNull($item1);
        $this->assertEquals(10, $item1->deliverable_quantity);

        // Events logged (CREATED + ASSIGNED)
        $this->assertCount(2, $delivery->events);
        $event = $delivery->events->last();
        $this->assertSame(DeliveryEventType::ASSIGNED, $event->event_type);
        $this->assertSame(DeliveryStatus::ASSIGNED->value, $event->to_status);
        $this->assertSame($this->admin->id, $event->actor_id);

        // Order delivery status updated
        $order->refresh();
        $this->assertSame(DeliveryStatus::ASSIGNED, $order->delivery_status);
    }

    public function test_custom_shipping_address_override_persists(): void
    {
        [$order] = $this->createFulfillableOrder();

        $payload = [
            'order_id' => $order->id,
            'driver_id' => $this->driver->id,
            'scheduled_date' => Carbon::tomorrow()->toDateString(),
            'delivery_address_line1' => '456 Alternate Blvd',
            'delivery_city' => 'Gotham',
            'delivery_state' => 'NJ',
            'delivery_postal_code' => '07001',
            'delivery_contact_name' => 'Site Manager John',
            'delivery_contact_phone' => '+15559876543',
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.deliveries.assign'), $payload);

        $response->assertStatus(200);
        $delivery = Delivery::find($response->json('delivery.id'));
        $this->assertSame('456 Alternate Blvd', $delivery->delivery_address_line1);
        $this->assertSame('Gotham', $delivery->delivery_city);
        $this->assertSame('NJ', $delivery->delivery_state);
        $this->assertSame('07001', $delivery->delivery_postal_code);
        $this->assertSame('Site Manager John', $delivery->delivery_contact_name);
        $this->assertSame('+15559876543', $delivery->delivery_contact_phone);
    }

    public function test_assignment_fails_if_order_is_not_approved(): void
    {
        [$order] = $this->createFulfillableOrder(['status' => OrderStatus::DRAFT]);

        $payload = [
            'order_id' => $order->id,
            'driver_id' => $this->driver->id,
            'scheduled_date' => Carbon::tomorrow()->toDateString(),
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.deliveries.assign'), $payload);
        $response->assertStatus(409);
    }

    public function test_assignment_fails_if_order_has_ineligible_fulfillment_status(): void
    {
        [$order] = $this->createFulfillableOrder(['status' => OrderStatus::COMPLETED]);

        $payload = [
            'order_id' => $order->id,
            'driver_id' => $this->driver->id,
            'scheduled_date' => Carbon::tomorrow()->toDateString(),
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.deliveries.assign'), $payload);
        $response->assertStatus(409);
    }

    public function test_assignment_fails_if_driver_is_inactive(): void
    {
        $inactiveDriver = User::factory()->deliveryPartner()->disabled()->create();
        [$order] = $this->createFulfillableOrder();

        $payload = [
            'order_id' => $order->id,
            'driver_id' => $inactiveDriver->id,
            'scheduled_date' => Carbon::tomorrow()->toDateString(),
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.deliveries.assign'), $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['driver_id']);
    }

    public function test_assignment_fails_if_user_is_not_a_delivery_partner(): void
    {
        $salesman = User::factory()->salesman()->create();
        [$order] = $this->createFulfillableOrder();

        $payload = [
            'order_id' => $order->id,
            'driver_id' => $salesman->id,
            'scheduled_date' => Carbon::tomorrow()->toDateString(),
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.deliveries.assign'), $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['driver_id']);
    }

    public function test_unauthorized_roles_cannot_assign_deliveries(): void
    {
        $salesman = User::factory()->salesman()->create();
        $accountant = User::factory()->accountant()->create();
        [$order] = $this->createFulfillableOrder();

        $payload = [
            'order_id' => $order->id,
            'driver_id' => $this->driver->id,
            'scheduled_date' => Carbon::tomorrow()->toDateString(),
        ];

        $this->actingAs($salesman)
            ->postJson(route('admin.deliveries.assign'), $payload)
            ->assertStatus(403);

        $this->actingAs($accountant)
            ->postJson(route('admin.deliveries.assign'), $payload)
            ->assertStatus(403);

        $this->actingAs($this->driver)
            ->postJson(route('admin.deliveries.assign'), $payload)
            ->assertStatus(403);
    }

    public function test_reassigning_active_assigned_delivery_updates_driver_and_logs_event(): void
    {
        [$order] = $this->createFulfillableOrder();

        $service = app(DeliveryAssignmentService::class);
        $delivery = $service->assignOrder(
            order: $order,
            driver: $this->driver,
            actor: $this->admin,
            data: ['scheduled_date' => Carbon::today()->toDateString()]
        );

        $newDriver = User::factory()->deliveryPartner()->create();

        // Reassign via API
        $payload = [
            'order_id' => $order->id,
            'driver_id' => $newDriver->id,
            'scheduled_date' => Carbon::tomorrow()->toDateString(),
            'notes' => 'Driver shift change',
        ];

        $response = $this->actingAs($this->admin)->postJson(route('admin.deliveries.assign'), $payload);
        $response->assertStatus(200);

        $delivery->refresh();
        $this->assertSame($newDriver->id, $delivery->driver_id);

        $events = $delivery->events()->orderBy('id')->get();
        $this->assertCount(3, $events); // CREATED, ASSIGNED, REASSIGNED
        $this->assertSame(DeliveryEventType::REASSIGNED, $events->last()->event_type);
        $this->assertSame($newDriver->id, $events->last()->metadata['driver_id']);
    }

    public function test_sequential_delivery_numbers_generated_cleanly(): void
    {
        [$order1] = $this->createFulfillableOrder();
        [$order2] = $this->createFulfillableOrder();

        $service = app(DeliveryAssignmentService::class);
        $del1 = $service->assignOrder($order1, $this->driver, $this->admin);
        $del2 = $service->assignOrder($order2, $this->driver, $this->admin);

        $year = Carbon::now()->year;
        $this->assertSame(sprintf('DEL-%04d-000001', $year), $del1->delivery_number);
        $this->assertSame(sprintf('DEL-%04d-000002', $year), $del2->delivery_number);
    }
}
