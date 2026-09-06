<?php

namespace Tests\Feature\Delivery;

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
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class DeliveryModelTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomer(): Customer
    {
        $salesman = User::factory()->salesman()->create();

        return Customer::create([
            'salesman_id' => $salesman->id,
            'name' => 'Metro Supermarket',
            'code' => 'CUST-' . strtoupper(Str::random(6)),
            'contact_name' => 'Jane Smith',
            'phone' => '+1-555-0199',
            'email' => 'jane@example.com',
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
    }

    private function createWarehouse(): Warehouse
    {
        return Warehouse::create([
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
    }

    private function createOrder(Customer $customer, Warehouse $warehouse): Order
    {
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
        ]);
    }

    private function createProduct(): Product
    {
        return Product::create([
            'sku' => 'SKU-' . strtoupper(Str::random(6)),
            'name' => 'Test Product',
            'unit' => 'BOX',
            'cost_price' => 10.00,
            'minimum_allowed_price' => 15.00,
            'default_selling_price' => 20.00,
            'mrp' => 25.00,
            'status' => ProductStatus::ACTIVE,
        ]);
    }

    public function test_delivery_model_instantiation_and_casts(): void
    {
        $driver = User::factory()->deliveryPartner()->create();
        $customer = $this->createCustomer();
        $warehouse = $this->createWarehouse();
        $order = $this->createOrder($customer, $warehouse);
        $admin = User::factory()->admin()->create();

        $delivery = Delivery::create([
            'delivery_number' => 'DEL-2026-000001',
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'status' => DeliveryStatus::ASSIGNED,
            'delivery_contact_name' => 'Jane Smith',
            'delivery_contact_phone' => '+15551234567',
            'delivery_address_line1' => '100 Main St',
            'delivery_city' => 'Metropolis',
            'delivery_state' => 'NY',
            'delivery_postal_code' => '10001',
            'delivery_country_code' => 'USA',
            'scheduled_date' => Carbon::parse('2026-09-10'),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->assertInstanceOf(Delivery::class, $delivery);
        $this->assertInstanceOf(DeliveryStatus::class, $delivery->status);
        $this->assertSame(DeliveryStatus::ASSIGNED, $delivery->status);
        $this->assertInstanceOf(Carbon::class, $delivery->scheduled_date);
        $this->assertSame('2026-09-10', $delivery->scheduled_date->toDateString());
        $this->assertSame(1, $delivery->version);
    }

    public function test_delivery_relationships_resolve_accurately(): void
    {
        $customer = $this->createCustomer();
        $warehouse = $this->createWarehouse();
        $order = $this->createOrder($customer, $warehouse);
        $driver = User::factory()->deliveryPartner()->create();
        $creator = User::factory()->admin()->create();

        $delivery = Delivery::create([
            'delivery_number' => 'DEL-2026-000002',
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'status' => DeliveryStatus::ASSIGNED,
            'delivery_contact_name' => 'Jane Smith',
            'delivery_contact_phone' => '+15551234567',
            'delivery_address_line1' => '100 Main St',
            'delivery_city' => 'Metropolis',
            'delivery_state' => 'NY',
            'delivery_postal_code' => '10001',
            'delivery_country_code' => 'USA',
            'scheduled_date' => Carbon::today(),
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);

        $product = $this->createProduct();
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $product->sku,
            'unit_snapshot' => 'BOX',
            'ordered_quantity' => 10,
            'unit_price' => 20.00,
            'tax_rate_snapshot' => 0.05,
            'taxable_amount' => 200.00,
            'tax_amount' => 10.00,
            'line_total' => 210.00,
        ]);

        $allocation = OrderItemAllocation::create([
            'allocation_number' => 'ALC-' . Str::random(8),
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'allocated_quantity' => 10,
            'reserved_quantity' => 10,
            'picked_quantity' => 10,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'status' => \App\Enums\AllocationStatus::ALLOCATED,
        ]);

        $item = DeliveryItem::create([
            'delivery_id' => $delivery->id,
            'order_item_id' => $orderItem->id,
            'order_item_allocation_id' => $allocation->id,
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $product->sku,
            'deliverable_quantity' => 10,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
        ]);

        $event = DeliveryEvent::create([
            'delivery_id' => $delivery->id,
            'event_type' => DeliveryEventType::CREATED,
            'from_status' => null,
            'to_status' => DeliveryStatus::ASSIGNED->value,
            'actor_id' => $creator->id,
            'created_at' => Carbon::now(),
        ]);

        $this->assertTrue($delivery->order->is($order));
        $this->assertTrue($delivery->customer->is($customer));
        $this->assertTrue($delivery->driver->is($driver));
        $this->assertTrue($delivery->creator->is($creator));
        $this->assertCount(1, $delivery->items);
        $this->assertTrue($delivery->items->first()->is($item));
        $this->assertCount(1, $delivery->events);
        $this->assertTrue($delivery->events->first()->is($event));

        $this->assertTrue($order->deliveries->contains($delivery));
        $this->assertTrue($order->latestDelivery->is($delivery));
        $this->assertTrue($customer->deliveries->contains($delivery));
        $this->assertTrue($driver->assignedDeliveries->contains($delivery));
    }

    public function test_delivery_event_immutability_enforced(): void
    {
        $driver = User::factory()->deliveryPartner()->create();
        $customer = $this->createCustomer();
        $warehouse = $this->createWarehouse();
        $order = $this->createOrder($customer, $warehouse);
        $admin = User::factory()->admin()->create();

        $delivery = Delivery::create([
            'delivery_number' => 'DEL-2026-000003',
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'status' => DeliveryStatus::ASSIGNED,
            'delivery_address_line1' => '100 Main St',
            'delivery_city' => 'Metropolis',
            'delivery_state' => 'NY',
            'delivery_postal_code' => '10001',
            'delivery_country_code' => 'USA',
            'scheduled_date' => Carbon::today(),
            'created_by' => $admin->id,
        ]);

        $event = DeliveryEvent::create([
            'delivery_id' => $delivery->id,
            'event_type' => DeliveryEventType::ASSIGNED,
            'from_status' => DeliveryStatus::PENDING_ASSIGNMENT->value,
            'to_status' => DeliveryStatus::ASSIGNED->value,
            'actor_id' => $admin->id,
            'created_at' => Carbon::now(),
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Delivery events are immutable');

        $event->notes = 'Attempting illegal alteration';
        $event->save();
    }

    public function test_delivery_event_deletion_forbidden(): void
    {
        $driver = User::factory()->deliveryPartner()->create();
        $customer = $this->createCustomer();
        $warehouse = $this->createWarehouse();
        $order = $this->createOrder($customer, $warehouse);
        $admin = User::factory()->admin()->create();

        $delivery = Delivery::create([
            'delivery_number' => 'DEL-2026-000004',
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'status' => DeliveryStatus::ASSIGNED,
            'delivery_address_line1' => '100 Main St',
            'delivery_city' => 'Metropolis',
            'delivery_state' => 'NY',
            'delivery_postal_code' => '10001',
            'delivery_country_code' => 'USA',
            'scheduled_date' => Carbon::today(),
            'created_by' => $admin->id,
        ]);

        $event = DeliveryEvent::create([
            'delivery_id' => $delivery->id,
            'event_type' => DeliveryEventType::ASSIGNED,
            'from_status' => DeliveryStatus::PENDING_ASSIGNMENT->value,
            'to_status' => DeliveryStatus::ASSIGNED->value,
            'actor_id' => $admin->id,
            'created_at' => Carbon::now(),
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Delivery events are immutable');

        $event->delete();
    }

    public function test_delivery_scopes_filter_records_correctly(): void
    {
        $driverA = User::factory()->deliveryPartner()->create();
        $driverB = User::factory()->deliveryPartner()->create();
        $customer = $this->createCustomer();
        $warehouse = $this->createWarehouse();
        $order = $this->createOrder($customer, $warehouse);
        $admin = User::factory()->admin()->create();

        $todayDeliveryA = Delivery::create([
            'delivery_number' => 'DEL-2026-000005',
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'driver_id' => $driverA->id,
            'status' => DeliveryStatus::ASSIGNED,
            'delivery_address_line1' => '100 Main St',
            'delivery_city' => 'Metropolis',
            'delivery_state' => 'NY',
            'delivery_postal_code' => '10001',
            'scheduled_date' => Carbon::today(),
            'created_by' => $admin->id,
        ]);

        $pastDeliveryA = Delivery::create([
            'delivery_number' => 'DEL-2026-000006',
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'driver_id' => $driverA->id,
            'status' => DeliveryStatus::DELIVERED,
            'delivery_address_line1' => '100 Main St',
            'delivery_city' => 'Metropolis',
            'delivery_state' => 'NY',
            'delivery_postal_code' => '10001',
            'scheduled_date' => Carbon::yesterday(),
            'created_by' => $admin->id,
        ]);

        $todayDeliveryB = Delivery::create([
            'delivery_number' => 'DEL-2026-000007',
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'driver_id' => $driverB->id,
            'status' => DeliveryStatus::PICKED_UP,
            'delivery_address_line1' => '100 Main St',
            'delivery_city' => 'Metropolis',
            'delivery_state' => 'NY',
            'delivery_postal_code' => '10001',
            'scheduled_date' => Carbon::today(),
            'created_by' => $admin->id,
        ]);

        // forDriver scope
        $driverADeliveries = Delivery::forDriver($driverA)->get();
        $this->assertCount(2, $driverADeliveries);
        $this->assertTrue($driverADeliveries->contains($todayDeliveryA));
        $this->assertTrue($driverADeliveries->contains($pastDeliveryA));
        $this->assertFalse($driverADeliveries->contains($todayDeliveryB));

        // today scope
        $todayDeliveries = Delivery::today()->get();
        $this->assertCount(2, $todayDeliveries);
        $this->assertTrue($todayDeliveries->contains($todayDeliveryA));
        $this->assertTrue($todayDeliveries->contains($todayDeliveryB));
        $this->assertFalse($todayDeliveries->contains($pastDeliveryA));

        // forStatus scope
        $pickedUp = Delivery::forStatus(DeliveryStatus::PICKED_UP)->get();
        $this->assertCount(1, $pickedUp);
        $this->assertTrue($pickedUp->first()->is($todayDeliveryB));
    }
}
