<?php

namespace Tests\Feature\Delivery;

use App\Enums\AllocationStatus;
use App\Enums\CustomerStatus;
use App\Enums\DeliveryEventType;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryCompletionTest extends TestCase
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

        Storage::fake('local');

        $this->driver = User::factory()->deliveryPartner()->create(['name' => 'Driver Frank']);
        $this->otherDriver = User::factory()->deliveryPartner()->create(['name' => 'Driver Gina']);
        $this->admin = User::factory()->admin()->create();

        $this->warehouse = Warehouse::create([
            'code' => 'WH-DEL-05',
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
            'name' => 'Riverside Foods',
            'code' => 'CUST-RS-01',
            'contact_name' => 'Rachel Green',
            'phone' => '+15555678901',
            'email' => 'rachel@riverside.com',
            'billing_address_line1' => '500 Riverside Drive',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '500 Riverside Drive',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
        ]);
    }

    private function createDeliveryMission(int $quantity = 10): array
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
            'delivery_status' => DeliveryStatus::OUT_FOR_DELIVERY,
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
            'name' => 'Organic Milk 12pk',
            'unit' => 'CASE',
            'cost_price' => 10.00,
            'minimum_allowed_price' => 15.00,
            'default_selling_price' => 20.00,
            'mrp' => 25.00,
            'status' => ProductStatus::ACTIVE,
        ]);

        $balance = InventoryBalance::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $product->id],
            [
                'on_hand_quantity' => 50,
                'reserved_quantity' => $quantity,
                'available_quantity' => 40,
                'damaged_quantity' => 0,
                'is_active' => true,
                'version' => 1,
            ]
        );

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $product->sku,
            'unit_snapshot' => 'CASE',
            'ordered_quantity' => $quantity,
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
            'warehouse_code' => $this->warehouse->code,
            'allocated_quantity' => $quantity,
            'reserved_quantity' => $quantity,
            'picked_quantity' => $quantity,
            'dispatched_quantity' => $quantity,
            'delivered_quantity' => 0,
            'status' => AllocationStatus::DISPATCHED,
        ]);

        $delivery = Delivery::create([
            'delivery_number' => 'DEL-' . date('Y') . '-' . sprintf('%06d', rand(100000, 999999)),
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $this->driver->id,
            'status' => DeliveryStatus::OUT_FOR_DELIVERY,
            'delivery_contact_name' => 'Rachel Green',
            'delivery_contact_phone' => '+15555678901',
            'delivery_address_line1' => '500 Riverside Drive',
            'delivery_city' => 'Metropolis',
            'delivery_state' => 'NY',
            'delivery_postal_code' => '10001',
            'delivery_country_code' => 'USA',
            'scheduled_date' => Carbon::today(),
            'assigned_at' => Carbon::now()->subHours(3),
            'picked_up_at' => Carbon::now()->subHours(2),
            'out_for_delivery_at' => Carbon::now()->subHour(),
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $deliveryItem = DeliveryItem::create([
            'delivery_id' => $delivery->id,
            'order_item_id' => $orderItem->id,
            'order_item_allocation_id' => $allocation->id,
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $product->sku,
            'deliverable_quantity' => $quantity,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
        ]);

        return [$delivery, $order, $allocation, $balance, $deliveryItem];
    }

    public function test_driver_can_complete_delivery_with_physical_inventory_relief(): void
    {
        [$delivery, $order, $allocation, $balance, $deliveryItem] = $this->createDeliveryMission(10);

        $payload = [
            'recipient_name' => 'Rachel Green (Store Manager)',
            'pod_notes' => 'Delivered to dry storage bay 1',
        ];

        $response = $this->actingAs($this->driver)->postJson(route('delivery.complete', $delivery), $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // 1. Delivery state verified
        $delivery->refresh();
        $this->assertSame(DeliveryStatus::DELIVERED, $delivery->status);
        $this->assertSame('Rachel Green (Store Manager)', $delivery->recipient_name);
        $this->assertSame('Delivered to dry storage bay 1', $delivery->pod_notes);
        $this->assertNotNull($delivery->delivered_at);

        // 2. Order state verified
        $order->refresh();
        $this->assertSame(FulfillmentStatus::DELIVERED, $order->fulfillment_status);
        $this->assertSame(DeliveryStatus::DELIVERED, $order->delivery_status);

        // 3. Allocation and Delivery Item state verified
        $allocation->refresh();
        $this->assertSame(10, $allocation->delivered_quantity);
        $this->assertSame(AllocationStatus::DELIVERED, $allocation->status);

        $deliveryItem->refresh();
        $this->assertSame(10, $deliveryItem->delivered_quantity);

        // 4. Physical Inventory Balance verified (on_hand -= 10, reserved -= 10)
        $balance->refresh();
        $this->assertSame(40, $balance->on_hand_quantity); // 50 - 10
        $this->assertSame(0, $balance->reserved_quantity);  // 10 - 10
        $this->assertSame(40, $balance->available_quantity); // 40 - 0

        // 5. Inventory Movement written
        $movement = InventoryMovement::where('reference_id', $delivery->id)->first();
        $this->assertNotNull($movement);
        $this->assertSame(InventoryMovementType::DISPATCH, $movement->movement_type);
        $this->assertSame(10, $movement->quantity);
        $this->assertSame(50, $movement->on_hand_before);
        $this->assertSame(40, $movement->on_hand_after);

        // 6. Delivery Event written
        $events = $delivery->events()->where('event_type', DeliveryEventType::DELIVERED)->get();
        $this->assertCount(1, $events);
        $this->assertSame($this->driver->id, $events->first()->actor_id);
    }

    public function test_complete_delivery_with_jpeg_pod_evidence(): void
    {
        [$delivery] = $this->createDeliveryMission(5);

        // Fake JPEG with magic bytes \xFF\xD8\xFF
        $jpegContent = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00" . str_repeat('A', 500);
        $file = UploadedFile::fake()->createWithContent('receipt_pod.jpg', $jpegContent);

        $payload = [
            'recipient_name' => 'Store Receiver Bob',
            'pod_evidence' => $file,
        ];

        $response = $this->actingAs($this->driver)->postJson(route('delivery.complete', $delivery), $payload);

        $response->assertStatus(200);
        $delivery->refresh();
        $this->assertSame(DeliveryStatus::DELIVERED, $delivery->status);
        $this->assertNotNull($delivery->pod_evidence_path);
        $this->assertTrue(Storage::disk('local')->exists($delivery->pod_evidence_path));
    }

    public function test_completion_fails_if_recipient_name_is_missing(): void
    {
        [$delivery] = $this->createDeliveryMission(5);

        $response = $this->actingAs($this->driver)->postJson(route('delivery.complete', $delivery), [
            'recipient_name' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recipient_name']);
    }

    public function test_completion_fails_if_delivery_is_not_out_for_delivery(): void
    {
        [$delivery] = $this->createDeliveryMission(5);
        $delivery->status = DeliveryStatus::ASSIGNED;
        $delivery->save();

        $response = $this->actingAs($this->driver)->postJson(route('delivery.complete', $delivery), [
            'recipient_name' => 'Valid Name',
        ]);

        $response->assertStatus(409);
    }

    public function test_completion_is_idempotent_and_does_not_double_deduct_inventory(): void
    {
        [$delivery, $order, $allocation, $balance] = $this->createDeliveryMission(10);

        // First completion
        $this->actingAs($this->driver)->postJson(route('delivery.complete', $delivery), [
            'recipient_name' => 'First Completion',
        ])->assertStatus(200);

        $balance->refresh();
        $this->assertSame(40, $balance->on_hand_quantity);

        // Second duplicate call
        $response = $this->actingAs($this->driver)->postJson(route('delivery.complete', $delivery), [
            'recipient_name' => 'First Completion',
        ]);

        $response->assertStatus(200);

        // Verify inventory is NOT double-deducted
        $balance->refresh();
        $this->assertSame(40, $balance->on_hand_quantity);

        // Verify only 1 dispatch movement was recorded
        $movementsCount = InventoryMovement::where('reference_id', $delivery->id)->count();
        $this->assertSame(1, $movementsCount);
    }

    public function test_anti_idor_other_driver_cannot_complete_delivery(): void
    {
        [$delivery] = $this->createDeliveryMission(5);

        $response = $this->actingAs($this->otherDriver)->postJson(route('delivery.complete', $delivery), [
            'recipient_name' => 'Intruder',
        ]);

        $response->assertStatus(404);
    }
}
