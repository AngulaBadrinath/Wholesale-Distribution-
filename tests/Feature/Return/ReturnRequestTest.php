<?php

namespace Tests\Feature\Return;

use App\Enums\AllocationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentTerms;
use App\Enums\ReturnReasonCode;
use App\Enums\ReturnStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Return\ReturnRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ReturnRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman;
    protected User $otherSalesman;
    protected Customer $customer;
    protected Warehouse $warehouse;
    protected TaxProfile $taxProfile;
    protected Product $productA;
    protected Product $productB;
    protected Order $deliveredOrder;
    protected OrderItem $itemA;
    protected OrderItem $itemB;
    protected ReturnRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => 'ACTIVE',
        ]);

        $this->salesman = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => 'ACTIVE',
        ]);

        $this->otherSalesman = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => 'ACTIVE',
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'WH-MAIN',
            'name' => 'Main Distribution Warehouse',
            'address_line1' => '100 Logistics Blvd',
            'city' => 'Atlanta',
            'state' => 'GA',
            'postal_code' => '30301',
            'country' => 'US',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'code' => 'CUST-001',
            'name' => 'Acme Supermarket',
            'contact_name' => 'Alice Smith',
            'email' => 'alice@acmesuper.com',
            'phone' => '+1 555-0100',
            'billing_address_line1' => '123 Market St',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30303',
            'billing_country' => 'US',
            'shipping_address_line1' => '123 Market St',
            'shipping_city' => 'Atlanta',
            'shipping_state' => 'GA',
            'shipping_postal_code' => '30303',
            'shipping_country' => 'US',
            'tax_id' => 'TAX-12345',
            'payment_terms' => PaymentTerms::NET_30,
            'salesman_id' => $this->salesman->id,
            'status' => 'ACTIVE',
        ]);

        $this->taxProfile = TaxProfile::create([
            'code' => 'STD_GST',
            'name' => 'Standard GST 10%',
            'rate' => 0.1000,
            'is_active' => true,
        ]);

        $this->productA = Product::create([
            'sku' => 'SKU-RET-A',
            'name' => 'Organic Orange Juice 1L',
            'unit' => 'BOTTLE',
            'cost_price' => 5.00,
            'minimum_allowed_price' => 8.00,
            'default_selling_price' => 10.00,
            'mrp' => 12.00,
            'status' => 'ACTIVE',
            'tax_profile_id' => $this->taxProfile->id,
        ]);

        $this->productB = Product::create([
            'sku' => 'SKU-RET-B',
            'name' => 'Almond Milk 1L',
            'unit' => 'CARTON',
            'cost_price' => 8.00,
            'minimum_allowed_price' => 12.00,
            'default_selling_price' => 15.00,
            'mrp' => 18.00,
            'status' => 'ACTIVE',
            'tax_profile_id' => $this->taxProfile->id,
        ]);

        $this->deliveredOrder = Order::create([
            'order_number' => 'ORD-2026-900001',
            'idempotency_key' => 'IDEMP-ORD-2026-900001',
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'status' => OrderStatus::COMPLETED,
            'subtotal' => 250.00,
            'tax_amount' => 25.00,
            'total_amount' => 275.00,
            'created_by' => $this->salesman->id,
            'ordered_at' => now()->subDays(2),
        ]);

        $this->itemA = OrderItem::create([
            'order_id' => $this->deliveredOrder->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => 10,
            'dispatched_quantity' => 10,
            'delivered_quantity' => 10,
            'returned_quantity' => 0,
            'unit_price' => 10.00,
            'tax_profile_id' => $this->taxProfile->id,
            'tax_profile_code_snapshot' => $this->taxProfile->code,
            'tax_profile_name_snapshot' => $this->taxProfile->name,
            'tax_rate_snapshot' => 0.1000,
            'taxable_amount' => 100.00,
            'tax_amount' => 10.00,
            'line_total' => 110.00,
        ]);

        $this->itemB = OrderItem::create([
            'order_id' => $this->deliveredOrder->id,
            'product_id' => $this->productB->id,
            'product_name_snapshot' => $this->productB->name,
            'sku_snapshot' => $this->productB->sku,
            'unit_snapshot' => $this->productB->unit,
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => 10,
            'dispatched_quantity' => 10,
            'delivered_quantity' => 10,
            'returned_quantity' => 0,
            'unit_price' => 15.00,
            'tax_profile_id' => $this->taxProfile->id,
            'tax_profile_code_snapshot' => $this->taxProfile->code,
            'tax_profile_name_snapshot' => $this->taxProfile->name,
            'tax_rate_snapshot' => 0.1000,
            'taxable_amount' => 150.00,
            'tax_amount' => 15.00,
            'line_total' => 165.00,
        ]);

        OrderItemAllocation::create([
            'allocation_number' => 'ALC-001',
            'order_id' => $this->deliveredOrder->id,
            'order_item_id' => $this->itemA->id,
            'product_id' => $this->productA->id,
            'allocated_quantity' => 10,
            'reserved_quantity' => 0,
            'picked_quantity' => 10,
            'dispatched_quantity' => 10,
            'delivered_quantity' => 10,
            'returned_quantity' => 0,
            'status' => AllocationStatus::DELIVERED,
            'warehouse_code' => $this->warehouse->code,
            'allocated_by' => $this->admin->id,
            'allocated_at' => now()->subDays(2),
        ]);

        OrderItemAllocation::create([
            'allocation_number' => 'ALC-002',
            'order_id' => $this->deliveredOrder->id,
            'order_item_id' => $this->itemB->id,
            'product_id' => $this->productB->id,
            'allocated_quantity' => 10,
            'reserved_quantity' => 0,
            'picked_quantity' => 10,
            'dispatched_quantity' => 10,
            'delivered_quantity' => 10,
            'returned_quantity' => 0,
            'status' => AllocationStatus::DELIVERED,
            'warehouse_code' => $this->warehouse->code,
            'allocated_by' => $this->admin->id,
            'allocated_at' => now()->subDays(2),
        ]);

        $this->service = app(ReturnRequestService::class);
    }

    public function test_can_create_valid_return_request_for_delivered_order(): void
    {
        $payload = [
            'order_id' => $this->deliveredOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'notes' => 'Customer received damaged bottles.',
            'items' => [
                [
                    'order_item_id' => $this->itemA->id,
                    'requested_quantity' => 4,
                    'reason_code' => ReturnReasonCode::DAMAGED_IN_TRANSIT->value,
                    'item_notes' => 'Leaking caps',
                ],
                [
                    'order_item_id' => $this->itemB->id,
                    'requested_quantity' => 2,
                    'reason_code' => ReturnReasonCode::EXCESS_STOCK->value,
                    'item_notes' => 'Excess units',
                ],
            ],
        ];

        $return = $this->service->createRequest($payload, $this->admin);

        $this->assertNotNull($return->id);
        $this->assertStringStartsWith('RET-', $return->return_number);
        $this->assertEquals(ReturnStatus::REQUESTED, $return->status);
        $this->assertEquals($this->customer->id, $return->customer_id);
        $this->assertEquals($this->deliveredOrder->id, $return->order_id);
        $this->assertCount(2, $return->items);

        // Subtotal: (4 * 10) + (2 * 15) = 40 + 30 = 70.00
        // Tax: 70 * 0.10 = 7.00
        // Total: 77.00
        $this->assertEquals('70.00', (string) $return->estimated_refund_subtotal);
        $this->assertEquals('7.00', (string) $return->estimated_refund_tax);
        $this->assertEquals('77.00', (string) $return->estimated_refund_total);

        // Event logged
        $this->assertDatabaseHas('return_request_events', [
            'return_request_id' => $return->id,
            'event_type' => 'REQUESTED',
            'actor_id' => $this->admin->id,
        ]);
    }

    public function test_rejects_return_request_when_quantity_exceeds_returnable(): void
    {
        $payload = [
            'order_id' => $this->deliveredOrder->id,
            'items' => [
                [
                    'order_item_id' => $this->itemA->id,
                    'requested_quantity' => 15, // Delivered is only 10
                    'reason_code' => ReturnReasonCode::DEFECTIVE->value,
                ],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->service->createRequest($payload, $this->admin);
    }

    public function test_rejects_return_request_for_undelivered_order(): void
    {
        $pendingOrder = Order::create([
            'order_number' => 'ORD-2026-900002',
            'idempotency_key' => 'IDEMP-ORD-2026-900002',
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'status' => OrderStatus::SUBMITTED,
            'subtotal' => 100.00,
            'tax_amount' => 10.00,
            'total_amount' => 110.00,
            'created_by' => $this->salesman->id,
        ]);

        $pendingItem = OrderItem::create([
            'order_id' => $pendingOrder->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'unit_price' => 10.00,
            'tax_rate_snapshot' => 0.1000,
            'taxable_amount' => 100.00,
            'tax_amount' => 10.00,
            'line_total' => 110.00,
        ]);

        $payload = [
            'order_id' => $pendingOrder->id,
            'items' => [
                [
                    'order_item_id' => $pendingItem->id,
                    'requested_quantity' => 1,
                ],
            ],
        ];

        $this->expectException(ValidationException::class);
        $this->service->createRequest($payload, $this->admin);
    }

    public function test_salesman_cannot_request_return_for_unassigned_customer_order(): void
    {
        $payload = [
            'order_id' => $this->deliveredOrder->id,
            'items' => [
                [
                    'order_item_id' => $this->itemA->id,
                    'requested_quantity' => 2,
                ],
            ],
        ];

        $this->expectException(NotFoundHttpException::class);
        $this->service->createRequest($payload, $this->otherSalesman);
    }

    public function test_open_returns_reduce_available_returnable_quantity_on_subsequent_requests(): void
    {
        // First request: 7 units
        $this->service->createRequest([
            'order_id' => $this->deliveredOrder->id,
            'items' => [
                [
                    'order_item_id' => $this->itemA->id,
                    'requested_quantity' => 7,
                ],
            ],
        ], $this->admin);

        // Second request for 4 units should fail (only 3 returnable: 10 - 7 = 3)
        $this->expectException(ValidationException::class);
        $this->service->createRequest([
            'order_id' => $this->deliveredOrder->id,
            'items' => [
                [
                    'order_item_id' => $this->itemA->id,
                    'requested_quantity' => 4,
                ],
            ],
        ], $this->admin);
    }

    public function test_http_endpoint_creates_return_successfully(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.returns.store'), [
            'order_id' => $this->deliveredOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'notes' => 'Test HTTP Return Request',
            'items' => [
                [
                    'order_item_id' => $this->itemA->id,
                    'requested_quantity' => 3,
                    'reason_code' => ReturnReasonCode::QUALITY_ISSUE->value,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('return_requests', [
            'order_id' => $this->deliveredOrder->id,
            'customer_id' => $this->customer->id,
            'status' => ReturnStatus::REQUESTED->value,
        ]);
    }
}
