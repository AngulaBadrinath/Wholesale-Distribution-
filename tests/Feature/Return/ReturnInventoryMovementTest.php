<?php

namespace Tests\Feature\Return;

use App\Enums\AllocationStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStockState;
use App\Enums\OrderStatus;
use App\Enums\PaymentTerms;
use App\Enums\ReturnReasonCode;
use App\Enums\ReturnStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Return\ReturnInspectionService;
use App\Services\Return\ReturnRequestService;
use App\Services\Return\ReturnWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnInventoryMovementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminRequester;
    protected User $adminApprover;
    protected User $warehouseManager;
    protected User $salesman;
    protected Customer $customer;
    protected Warehouse $warehouse;
    protected TaxProfile $taxProfile;
    protected Product $productA;
    protected Product $productB;
    protected Order $deliveredOrder;
    protected OrderItem $itemA;
    protected OrderItem $itemB;
    protected OrderItemAllocation $allocA;
    protected OrderItemAllocation $allocB;
    protected InventoryBalance $balanceA;
    protected InventoryBalance $balanceB;
    protected ReturnWorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRequester = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => 'ACTIVE',
        ]);

        $this->adminApprover = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => 'ACTIVE',
        ]);

        $this->warehouseManager = User::factory()->create([
            'role' => UserRole::WAREHOUSE_MANAGER,
            'status' => 'ACTIVE',
        ]);

        $this->salesman = User::factory()->create([
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
            'sku' => 'SKU-MOV-A',
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
            'sku' => 'SKU-MOV-B',
            'name' => 'Almond Milk 1L',
            'unit' => 'CARTON',
            'cost_price' => 8.00,
            'minimum_allowed_price' => 12.00,
            'default_selling_price' => 15.00,
            'mrp' => 18.00,
            'status' => 'ACTIVE',
            'tax_profile_id' => $this->taxProfile->id,
        ]);

        // Baseline Inventory before return: Product A has 50 on-hand (50 available), Product B has 20 on-hand (20 available)
        $this->balanceA = InventoryBalance::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $this->productA->id],
            [
                'on_hand_quantity' => 50,
                'reserved_quantity' => 0,
                'available_quantity' => 50,
                'damaged_quantity' => 0,
                'is_active' => true,
            ]
        );

        $this->balanceB = InventoryBalance::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $this->productB->id],
            [
                'on_hand_quantity' => 20,
                'reserved_quantity' => 0,
                'available_quantity' => 20,
                'damaged_quantity' => 0,
                'is_active' => true,
            ]
        );

        $this->deliveredOrder = Order::create([
            'order_number' => 'ORD-2026-900005',
            'idempotency_key' => 'IDEMP-ORD-2026-900005',
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

        $this->allocA = OrderItemAllocation::create([
            'allocation_number' => 'ALC-005-A',
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
            'allocated_by' => $this->adminRequester->id,
            'allocated_at' => now()->subDays(2),
        ]);

        $this->allocB = OrderItemAllocation::create([
            'allocation_number' => 'ALC-005-B',
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
            'allocated_by' => $this->adminRequester->id,
            'allocated_at' => now()->subDays(2),
        ]);

        $this->service = app(ReturnWorkflowService::class);
    }

    public function test_accepted_good_stock_disposition_restores_available_inventory_and_logs_movement(): void
    {
        // 1. Create Return Request (4 units of Item A)
        $reqService = app(ReturnRequestService::class);
        $return = $reqService->createRequest([
            'order_id' => $this->deliveredOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'order_item_id' => $this->itemA->id,
                    'requested_quantity' => 4,
                    'reason_code' => ReturnReasonCode::EXCESS_STOCK->value,
                ],
            ],
        ], $this->adminRequester);

        // 2. Inspect (Received: 4 units)
        $inspService = app(ReturnInspectionService::class);
        $itemA_req = $return->items->firstWhere('order_item_id', $this->itemA->id);
        $inspService->recordInspection($return, [
            'items' => [
                [
                    'item_id' => $itemA_req->id,
                    'received_quantity' => 4,
                ],
            ],
        ], $this->warehouseManager);

        // 3. Approve with 4 ACCEPTED_GOOD
        $this->service->approveReturn($return->fresh(), [
            'items' => [
                [
                    'item_id' => $itemA_req->id,
                    'accepted_good_quantity' => 4,
                    'accepted_damaged_quantity' => 0,
                    'rejected_quantity' => 0,
                ],
            ],
        ], $this->adminApprover);

        // Verify Balance A updated: On-hand: 50 -> 54, Available: 50 -> 54
        $balance = $this->balanceA->fresh();
        $this->assertEquals(54, $balance->on_hand_quantity);
        $this->assertEquals(54, $balance->available_quantity);
        $this->assertEquals(0, $balance->reserved_quantity);
        $this->assertEquals(0, $balance->damaged_quantity);

        // Verify InventoryMovement logged
        $this->assertDatabaseHas('inventory_movements', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->productA->id,
            'movement_type' => InventoryMovementType::RETURN->value,
            'from_state' => InventoryStockState::EXTERNAL->value,
            'to_state' => InventoryStockState::AVAILABLE->value,
            'quantity' => 4,
            'on_hand_before' => 50,
            'on_hand_after' => 54,
            'available_before' => 50,
            'available_after' => 54,
            'reference_type' => ReturnRequest::class,
            'reference_id' => $return->id,
        ]);

        // Verify Allocation & Order Item returned_quantity synchronized
        $this->assertEquals(4, $this->itemA->fresh()->returned_quantity);
        $this->assertEquals(4, $this->allocA->fresh()->returned_quantity);
        $this->assertEquals(10, $this->itemA->fresh()->ordered_quantity); // Invariant: baseline ordered_quantity unchanged
    }

    public function test_accepted_damaged_stock_disposition_increases_damaged_inventory_and_logs_movement(): void
    {
        // 1. Create Return Request (3 units of Item B)
        $reqService = app(ReturnRequestService::class);
        $return = $reqService->createRequest([
            'order_id' => $this->deliveredOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'order_item_id' => $this->itemB->id,
                    'requested_quantity' => 3,
                    'reason_code' => ReturnReasonCode::DAMAGED_IN_TRANSIT->value,
                ],
            ],
        ], $this->adminRequester);

        // 2. Inspect (Received: 3 units)
        $inspService = app(ReturnInspectionService::class);
        $itemB_req = $return->items->firstWhere('order_item_id', $this->itemB->id);
        $inspService->recordInspection($return, [
            'items' => [
                [
                    'item_id' => $itemB_req->id,
                    'received_quantity' => 3,
                ],
            ],
        ], $this->warehouseManager);

        // 3. Approve with 3 ACCEPTED_DAMAGED
        $this->service->approveReturn($return->fresh(), [
            'items' => [
                [
                    'item_id' => $itemB_req->id,
                    'accepted_good_quantity' => 0,
                    'accepted_damaged_quantity' => 3,
                    'rejected_quantity' => 0,
                ],
            ],
        ], $this->adminApprover);

        // Verify Balance B updated: On-hand: 20 -> 23, Damaged: 0 -> 3, Available: 20 (unchanged)
        $balance = $this->balanceB->fresh();
        $this->assertEquals(23, $balance->on_hand_quantity);
        $this->assertEquals(3, $balance->damaged_quantity);
        $this->assertEquals(20, $balance->available_quantity);
        $this->assertEquals(0, $balance->reserved_quantity);

        // Verify Check Constraint Invariants: reserved + damaged <= on_hand && available = on_hand - reserved - damaged
        $this->assertTrue($balance->reserved_quantity + $balance->damaged_quantity <= $balance->on_hand_quantity);
        $this->assertEquals($balance->on_hand_quantity - $balance->reserved_quantity - $balance->damaged_quantity, $balance->available_quantity);

        // Verify InventoryMovement logged
        $this->assertDatabaseHas('inventory_movements', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->productB->id,
            'movement_type' => InventoryMovementType::RETURN->value,
            'from_state' => InventoryStockState::EXTERNAL->value,
            'to_state' => InventoryStockState::DAMAGED->value,
            'quantity' => 3,
            'on_hand_before' => 20,
            'on_hand_after' => 23,
            'damaged_before' => 0,
            'damaged_after' => 3,
            'available_before' => 20,
            'available_after' => 20,
            'reference_type' => ReturnRequest::class,
            'reference_id' => $return->id,
        ]);

        // Verify Allocation & Order Item returned_quantity synchronized
        $this->assertEquals(3, $this->itemB->fresh()->returned_quantity);
        $this->assertEquals(3, $this->allocB->fresh()->returned_quantity);
    }
}
