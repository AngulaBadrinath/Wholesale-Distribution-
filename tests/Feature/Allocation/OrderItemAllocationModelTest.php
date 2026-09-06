<?php

namespace Tests\Feature\Allocation;

use App\Enums\AccountStatus;
use App\Enums\AllocationStatus;
use App\Enums\CustomerStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\TaxProfileStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Services\Allocation\OrderAllocationService;
use App\Services\Order\OrderWorkflowService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class OrderItemAllocationModelTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman;
    protected Customer $customer;
    protected Product $productA;
    protected Product $productB;
    protected TaxProfile $taxProfile;
    protected Category $category;
    protected OrderAllocationService $allocationService;
    protected OrderWorkflowService $workflowService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->allocationService = app(OrderAllocationService::class);
        $this->workflowService = app(OrderWorkflowService::class);

        $this->admin = User::factory()->create([
            'name' => 'Allocation Admin',
            'email' => 'admin@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'name' => 'Sam Salesman',
            'email' => 'sam@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Metro Retailers',
            'code' => 'CUST-METRO-01',
            'contact_name' => 'Jane Metro',
            'phone' => '+1-555-4321',
            'email' => 'metro@wholesale.test',
            'billing_address_line1' => '200 Market Street',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10002',
            'billing_country' => 'USA',
            'shipping_address_line1' => '200 Market Street',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10002',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
            'credit_limit' => 10000.00,
        ]);

        $this->category = Category::create([
            'name' => 'Pantry Goods',
            'code' => 'PANTRY',
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'Standard VAT',
            'code' => 'VAT-STD',
            'rate' => 0.10,
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->productA = Product::create([
            'name' => 'Flour 25kg Sack',
            'sku' => 'SKU-FLOUR-01',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'cost_price' => 10.00,
            'minimum_allowed_price' => 15.00,
            'default_selling_price' => 20.00,
            'mrp' => 25.00,
            'status' => ProductStatus::ACTIVE,
            'unit' => 'BAG',
        ]);

        $this->productB = Product::create([
            'name' => 'Sugar 50kg Sack',
            'sku' => 'SKU-SUGAR-02',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'cost_price' => 25.00,
            'minimum_allowed_price' => 35.00,
            'default_selling_price' => 45.00,
            'mrp' => 55.00,
            'status' => ProductStatus::ACTIVE,
            'unit' => 'BAG',
        ]);

        InventoryBalance::whereIn('product_id', [$this->productA->id, $this->productB->id])
            ->update([
                'on_hand_quantity' => 100,
                'available_quantity' => 100,
            ]);
    }

    protected function createOrderWithItems(
        OrderStatus $status = OrderStatus::APPROVED,
        FulfillmentStatus $fulfillmentStatus = FulfillmentStatus::RESERVED,
        int $qtyA = 10,
        int $canA = 2,
        int $qtyB = 15,
        int $canB = 0
    ): Order {
        $order = Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'idempotency_key' => 'idemp-' . uniqid(),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'approved_by' => $status === OrderStatus::APPROVED ? $this->admin->id : null,
            'status' => $status,
            'fulfillment_status' => $fulfillmentStatus,
            'payment_status' => PaymentStatus::UNPAID,
            'currency' => 'USD',
            'subtotal' => 835.00,
            'tax_total' => 83.50,
            'grand_total' => 918.50,
            'submitted_at' => Carbon::now()->subHours(3),
            'approved_at' => $status === OrderStatus::APPROVED ? Carbon::now()->subHour() : null,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => $qtyA,
            'cancelled_quantity' => $canA,
            'reserved_quantity' => $status === OrderStatus::APPROVED ? ($qtyA - $canA) : 0,
            'unit_price' => 20.00,
            'tax_rate_snapshot' => 0.10,
            'taxable_amount' => ($qtyA - $canA) * 20.00,
            'tax_amount' => ($qtyA - $canA) * 2.00,
            'line_total' => ($qtyA - $canA) * 22.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productB->id,
            'product_name_snapshot' => $this->productB->name,
            'sku_snapshot' => $this->productB->sku,
            'unit_snapshot' => $this->productB->unit,
            'ordered_quantity' => $qtyB,
            'cancelled_quantity' => $canB,
            'reserved_quantity' => $status === OrderStatus::APPROVED ? ($qtyB - $canB) : 0,
            'unit_price' => 45.00,
            'tax_rate_snapshot' => 0.10,
            'taxable_amount' => ($qtyB - $canB) * 45.00,
            'tax_amount' => ($qtyB - $canB) * 4.50,
            'line_total' => ($qtyB - $canB) * 49.50,
        ]);

        return $order->fresh(['items']);
    }

    /**
     * 1. Schema & Migration Tests
     */
    public function test_schema_migration_creates_allocations_table_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('order_item_allocations'));

        $expectedColumns = [
            'id', 'order_id', 'order_item_id', 'product_id', 'allocation_number',
            'allocated_quantity', 'reserved_quantity', 'picked_quantity', 'dispatched_quantity',
            'delivered_quantity', 'returned_quantity', 'status', 'warehouse_code',
            'notes', 'allocated_by', 'allocated_at', 'created_at', 'updated_at',
        ];

        foreach ($expectedColumns as $col) {
            $this->assertTrue(Schema::hasColumn('order_item_allocations', $col), "Missing column: {$col}");
        }
    }

    public function test_model_relationships_and_casts(): void
    {
        $order = $this->createOrderWithItems();
        $item = $order->items->first();

        $allocation = OrderItemAllocation::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'allocation_number' => 'ALC-TEST-001',
            'allocated_quantity' => 5,
            'reserved_quantity' => 5,
            'status' => AllocationStatus::RESERVED,
            'warehouse_code' => 'MAIN',
            'allocated_by' => $this->admin->id,
            'allocated_at' => now(),
        ]);

        $this->assertInstanceOf(Order::class, $allocation->order);
        $this->assertEquals($order->id, $allocation->order->id);

        $this->assertInstanceOf(OrderItem::class, $allocation->orderItem);
        $this->assertEquals($item->id, $allocation->orderItem->id);

        $this->assertInstanceOf(Product::class, $allocation->product);
        $this->assertEquals($item->product_id, $allocation->product->id);

        $this->assertInstanceOf(User::class, $allocation->allocatedBy);
        $this->assertEquals($this->admin->id, $allocation->allocatedBy->id);

        $this->assertInstanceOf(AllocationStatus::class, $allocation->status);
        $this->assertInstanceOf(Carbon::class, $allocation->allocated_at);

        // Order and OrderItem hasMany relationships
        $this->assertCount(1, $item->allocations);
        $this->assertCount(1, $order->allocations);
    }

    /**
     * 2. Quantity Conservation Law & Invariant Tests
     */
    public function test_quantity_conservation_law_fulfillable_quantity(): void
    {
        $order = $this->createOrderWithItems(OrderStatus::APPROVED, FulfillmentStatus::RESERVED, 10, 3);
        $item = $order->items->first();

        // ordered = cancelled + fulfillable
        $this->assertEquals(10, $item->ordered_quantity);
        $this->assertEquals(3, $item->cancelled_quantity);
        $this->assertEquals(7, $item->fulfillableQuantity());
        $this->assertEquals($item->ordered_quantity, $item->cancelled_quantity + $item->fulfillableQuantity());
    }

    public function test_partial_allocation_calculates_allocated_and_unallocated_quantities(): void
    {
        $order = $this->createOrderWithItems(OrderStatus::APPROVED, FulfillmentStatus::RESERVED, 10, 2);
        $item = $order->items->first();

        // fulfillable = 8
        $this->assertEquals(8, $item->fulfillableQuantity());
        $this->assertEquals(0, $item->allocatedQuantity());
        $this->assertEquals(8, $item->unallocatedQuantity());

        // Allocate 6 units partially
        $allocation = $this->allocationService->allocateItemQuantity($item, 6, $this->admin, 'MAIN', 'First partial allocation');

        $item->refresh();
        $this->assertEquals(6, $item->allocatedQuantity());
        $this->assertEquals(2, $item->unallocatedQuantity());
        $this->assertTrue($item->canAllocate(2));
        $this->assertFalse($item->canAllocate(3));

        // Allocate remaining 2 units
        $allocation2 = $this->allocationService->allocateItemQuantity($item, 2, $this->admin, 'MAIN', 'Second partial allocation');

        $item->refresh();
        $this->assertEquals(8, $item->allocatedQuantity());
        $this->assertEquals(0, $item->unallocatedQuantity());
        $this->assertFalse($item->canAllocate(1));
    }

    /**
     * 3. Over-Allocation Prevention Tests
     */
    public function test_over_allocation_beyond_fulfillable_fails_validation(): void
    {
        $order = $this->createOrderWithItems(OrderStatus::APPROVED, FulfillmentStatus::RESERVED, 10, 2);
        $item = $order->items->first(); // fulfillable = 8

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('remain unallocated');

        $this->allocationService->allocateItemQuantity($item, 9, $this->admin);
    }

    public function test_sum_of_multiple_allocations_exceeding_fulfillable_fails_validation(): void
    {
        $order = $this->createOrderWithItems(OrderStatus::APPROVED, FulfillmentStatus::RESERVED, 10, 2);
        $item = $order->items->first(); // fulfillable = 8

        $this->allocationService->allocateItemQuantity($item, 5, $this->admin);

        $item->refresh();
        $this->assertEquals(3, $item->unallocatedQuantity());

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('remain unallocated');

        // Trying to allocate 4 when only 3 remain unallocated
        $this->allocationService->allocateItemQuantity($item, 4, $this->admin);
    }

    public function test_zero_or_negative_allocation_quantity_fails_validation(): void
    {
        $order = $this->createOrderWithItems();
        $item = $order->items->first();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Allocation quantity must be between 1 and 999,999');

        $this->allocationService->allocateItemQuantity($item, 0, $this->admin);
    }

    /**
     * 4. Database Row-Local CHECK Constraints
     */
    public function test_database_check_constraints_enforce_row_local_invariants(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL check constraint tests run only on pgsql.');
        }

        $order = $this->createOrderWithItems();
        $item = $order->items->first();

        // 1. allocated_quantity must be > 0
        try {
            DB::table('order_item_allocations')->insert([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'allocation_number' => 'ALC-CHK-01',
                'allocated_quantity' => 0,
                'reserved_quantity' => 0,
                'status' => 'ALLOCATED',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('DB constraint order_item_allocations_allocated_quantity_check did not trigger.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('order_item_allocations_allocated_quantity_check', $e->getMessage());
        }

        // 2. reserved_quantity <= allocated_quantity
        try {
            DB::table('order_item_allocations')->insert([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'allocation_number' => 'ALC-CHK-02',
                'allocated_quantity' => 5,
                'reserved_quantity' => 6,
                'status' => 'ALLOCATED',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('DB constraint order_item_allocations_reserved_quantity_check did not trigger.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('order_item_allocations_reserved_quantity_check', $e->getMessage());
        }

        // 3. returned_quantity <= delivered_quantity
        try {
            DB::table('order_item_allocations')->insert([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'allocation_number' => 'ALC-CHK-03',
                'allocated_quantity' => 5,
                'delivered_quantity' => 2,
                'returned_quantity' => 3,
                'status' => 'ALLOCATED',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('DB constraint order_item_allocations_returned_quantity_check did not trigger.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('order_item_allocations_returned_quantity_check', $e->getMessage());
        }
    }

    /**
     * 5. Order Approval Integration & Exact-Once Baseline Allocation
     */
    public function test_approving_order_creates_canonical_baseline_allocations_atomically(): void
    {
        // Start with a SUBMITTED order
        $order = $this->createOrderWithItems(OrderStatus::SUBMITTED, FulfillmentStatus::UNALLOCATED, 10, 2, 15, 0);

        // Verify pre-conditions
        $this->assertEquals(0, OrderItemAllocation::where('order_id', $order->id)->count());

        // Perform authoritative approval
        $approvedOrder = $this->workflowService->approveOrder($order, $this->admin);

        // Verify order-level state
        $this->assertEquals(OrderStatus::APPROVED, $approvedOrder->status);
        $this->assertEquals(FulfillmentStatus::RESERVED, $approvedOrder->fulfillment_status);
        $this->assertEquals($this->admin->id, $approvedOrder->approved_by);

        // Verify allocations created for each item
        $allocations = OrderItemAllocation::where('order_id', $order->id)->get();
        $this->assertCount(2, $allocations);

        $itemA = $order->items->first();
        $alcA = $allocations->where('order_item_id', $itemA->id)->first();
        $this->assertNotNull($alcA);
        $this->assertEquals("ALC-{$order->order_number}-{$itemA->id}-01", $alcA->allocation_number);
        $this->assertEquals(8, $alcA->allocated_quantity);
        $this->assertEquals(8, $alcA->reserved_quantity);
        $this->assertEquals(AllocationStatus::ALLOCATED, $alcA->status);
        $this->assertEquals('MAIN', $alcA->warehouse_code);

        // Verify order_items.reserved_quantity is NOT doubled
        $itemA->refresh();
        $this->assertEquals(8, $itemA->reserved_quantity);
        $this->assertEquals(8, $itemA->allocatedQuantity());
        $this->assertEquals(0, $itemA->unallocatedQuantity());

        $itemB = $order->items->last();
        $alcB = $allocations->where('order_item_id', $itemB->id)->first();
        $this->assertNotNull($alcB);
        $this->assertEquals(15, $alcB->allocated_quantity);
        $this->assertEquals(15, $alcB->reserved_quantity);
    }

    public function test_approval_allocation_creation_is_idempotent_on_retry(): void
    {
        $order = $this->createOrderWithItems(OrderStatus::APPROVED, FulfillmentStatus::RESERVED, 10, 0, 5, 0);

        // Create baseline allocations
        $created = $this->allocationService->createInitialAllocationsForOrder($order, $this->admin);
        $this->assertCount(2, $created);

        // Calling again should return existing allocations without duplicate inserts
        $secondCall = $this->allocationService->createInitialAllocationsForOrder($order, $this->admin);
        $this->assertCount(2, $secondCall);

        $totalAllocations = OrderItemAllocation::where('order_id', $order->id)->count();
        $this->assertEquals(2, $totalAllocations);
    }

    /**
     * 6. Order Lifecycle State Restrictions
     */
    public function test_cannot_create_allocations_for_draft_orders(): void
    {
        $order = $this->createOrderWithItems(OrderStatus::DRAFT, FulfillmentStatus::UNALLOCATED);
        $item = $order->items->first();

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('cannot receive allocations');

        $this->allocationService->allocateItemQuantity($item, 5, $this->admin);
    }

    public function test_cannot_create_allocations_for_submitted_orders(): void
    {
        $order = $this->createOrderWithItems(OrderStatus::SUBMITTED, FulfillmentStatus::UNALLOCATED);
        $item = $order->items->first();

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('cannot receive allocations');

        $this->allocationService->allocateItemQuantity($item, 5, $this->admin);
    }

    public function test_cannot_create_allocations_for_rejected_orders(): void
    {
        $order = $this->createOrderWithItems(OrderStatus::REJECTED, FulfillmentStatus::UNALLOCATED);
        $item = $order->items->first();

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('cannot receive allocations');

        $this->allocationService->allocateItemQuantity($item, 5, $this->admin);
    }

    public function test_cannot_create_allocations_for_cancelled_orders(): void
    {
        $order = $this->createOrderWithItems(OrderStatus::CANCELLED, FulfillmentStatus::UNALLOCATED);
        $item = $order->items->first();

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('cannot receive allocations');

        $this->allocationService->allocateItemQuantity($item, 5, $this->admin);
    }

    public function test_cannot_create_allocations_for_completed_orders(): void
    {
        $order = $this->createOrderWithItems(OrderStatus::COMPLETED, FulfillmentStatus::DELIVERED);
        $item = $order->items->first();

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('cannot receive allocations');

        $this->allocationService->allocateItemQuantity($item, 5, $this->admin);
    }

    /**
     * 7. Financial & Historical Immutability
     */
    public function test_financial_snapshots_and_order_totals_are_immutable_during_allocation(): void
    {
        $order = $this->createOrderWithItems(OrderStatus::APPROVED, FulfillmentStatus::RESERVED, 10, 2);
        $item = $order->items->first();

        $originalSubtotal = (string) $order->subtotal;
        $originalTaxTotal = (string) $order->tax_total;
        $originalGrandTotal = (string) $order->grand_total;
        $originalUnitPrice = (string) $item->unit_price;
        $originalOrderedQty = $item->ordered_quantity;
        $originalCancelledQty = $item->cancelled_quantity;

        // Perform partial allocation
        $this->allocationService->allocateItemQuantity($item, 4, $this->admin);

        $order->refresh();
        $item->refresh();

        $this->assertEquals($originalSubtotal, (string) $order->subtotal);
        $this->assertEquals($originalTaxTotal, (string) $order->tax_total);
        $this->assertEquals($originalGrandTotal, (string) $order->grand_total);
        $this->assertEquals($originalUnitPrice, (string) $item->unit_price);
        $this->assertEquals($originalOrderedQty, $item->ordered_quantity);
        $this->assertEquals($originalCancelledQty, $item->cancelled_quantity);
    }

    public function test_historical_product_snapshots_preserved_even_if_catalog_product_is_deactivated(): void
    {
        $order = $this->createOrderWithItems(OrderStatus::APPROVED, FulfillmentStatus::RESERVED);
        $item = $order->items->first();

        // Deactivate catalog product
        $this->productA->update(['status' => ProductStatus::INACTIVE]);

        $this->allocationService->allocateItemQuantity($item, 3, $this->admin);

        $item->refresh();
        $this->assertEquals('SKU-FLOUR-01', $item->sku_snapshot);
        $this->assertEquals('Flour 25kg Sack', $item->product_name_snapshot);
        $this->assertEquals('BAG', $item->unit_snapshot);
    }

    /**
     * 8. Backfill Compatibility for Existing Approved Orders
     */
    public function test_backfill_approved_orders_creates_missing_allocations_safely(): void
    {
        // Create an approved order that mimics an order approved before FEAT-ALLOC-001
        $order = $this->createOrderWithItems(OrderStatus::APPROVED, FulfillmentStatus::RESERVED, 12, 2, 8, 0);

        $this->assertEquals(0, OrderItemAllocation::where('order_id', $order->id)->count());

        // Run backfill
        $backfilled = $this->allocationService->backfillApprovedOrderAllocations();
        $this->assertGreaterThanOrEqual(2, $backfilled->count());

        $allocations = OrderItemAllocation::where('order_id', $order->id)->get();
        $this->assertCount(2, $allocations);

        $itemA = $order->items->first();
        $alcA = $allocations->where('order_item_id', $itemA->id)->first();
        $this->assertNotNull($alcA);
        $this->assertEquals(10, $alcA->allocated_quantity);
        $this->assertEquals(10, $alcA->reserved_quantity);

        // Running backfill again produces 0 new allocations (idempotent)
        $secondBackfilled = $this->allocationService->backfillApprovedOrderAllocations();
        $this->assertEquals(0, $secondBackfilled->count());
    }

    /**
     * 9. Admin Order Detail Projection & Inertia Payload
     */
    public function test_admin_order_detail_projects_allocation_data_and_summary(): void
    {
        $order = $this->createOrderWithItems(OrderStatus::APPROVED, FulfillmentStatus::RESERVED, 10, 2, 10, 0);
        $this->allocationService->createInitialAllocationsForOrder($order, $this->admin);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.show', $order));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Orders/Show')
            ->has('orderData.items', 2)
            ->has('orderData.items.0.allocations', 1)
            ->where('orderData.items.0.allocated_quantity', 8)
            ->where('orderData.items.0.unallocated_quantity', 0)
            ->where('orderData.allocation_summary.total_allocated_units', 18)
            ->where('orderData.allocation_summary.total_fulfillable_units', 18)
            ->where('orderData.allocation_summary.total_unallocated_units', 0)
            ->where('orderData.allocation_summary.has_allocations', true)
        );
    }
}
