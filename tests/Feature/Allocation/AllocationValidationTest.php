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
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Services\Allocation\OrderAllocationService;
use App\Services\Allocation\OrderAllocationValidationService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class AllocationValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman;
    protected Customer $customer;
    protected Product $product;
    protected TaxProfile $taxProfile;
    protected Category $category;
    protected OrderAllocationService $allocationService;
    protected OrderAllocationValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = app(OrderAllocationValidationService::class);
        $this->allocationService = app(OrderAllocationService::class);

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
            'name' => 'Apex Retail',
            'code' => 'CUST-APEX-01',
            'contact_name' => 'John Apex',
            'phone' => '+1-555-0199',
            'email' => 'apex@wholesale.test',
            'billing_address_line1' => '100 Main Street',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '100 Main Street',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
            'credit_limit' => 50000.00,
        ]);

        $this->category = Category::create([
            'name' => 'Dry Goods',
            'code' => 'DRY-GOODS',
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'Standard Rate',
            'code' => 'STD-RATE',
            'rate' => 0.10,
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->product = Product::create([
            'name' => 'Wheat Grain 50kg',
            'sku' => 'SKU-WHEAT-50',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'cost_price' => 15.00,
            'minimum_allowed_price' => 20.00,
            'default_selling_price' => 25.00,
            'mrp' => 30.00,
            'status' => ProductStatus::ACTIVE,
            'unit' => 'BAG',
        ]);
    }

    protected function createApprovedOrderWithItem(
        int $orderedQty = 10,
        int $cancelledQty = 2
    ): Order {
        $fulfillable = max(0, $orderedQty - $cancelledQty);
        $order = Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'idempotency_key' => 'idemp-' . uniqid(),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'approved_by' => $this->admin->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::RESERVED,
            'payment_status' => PaymentStatus::UNPAID,
            'currency' => 'USD',
            'subtotal' => $fulfillable * 25.00,
            'tax_total' => $fulfillable * 2.50,
            'grand_total' => $fulfillable * 27.50,
            'submitted_at' => Carbon::now()->subHours(2),
            'approved_at' => Carbon::now()->subHour(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
            'unit_snapshot' => $this->product->unit,
            'ordered_quantity' => $orderedQty,
            'cancelled_quantity' => $cancelledQty,
            'reserved_quantity' => 0, // start unallocated for fine-grained tests
            'unit_price' => 25.00,
            'tax_rate_snapshot' => 0.10,
            'taxable_amount' => $fulfillable * 25.00,
            'tax_amount' => $fulfillable * 2.50,
            'line_total' => $fulfillable * 27.50,
        ]);

        return $order->fresh(['items']);
    }

    /**
     * 1. Conservation Law & Mathematical Invariant Tests
     */
    public function test_conservation_law_ordered_equals_cancelled_plus_fulfillable(): void
    {
        $order = $this->createApprovedOrderWithItem(25, 5);
        $item = $order->items->first();

        $this->assertEquals(25, $item->ordered_quantity);
        $this->assertEquals(5, $item->cancelled_quantity);
        $this->assertEquals(20, $item->fulfillableQuantity());
        $this->assertEquals($item->ordered_quantity, $item->cancelled_quantity + $item->fulfillableQuantity());

        // Domain validation passes
        $this->validator->validateItemConservation($item);
    }

    public function test_conservation_law_detects_artificially_violated_quantities(): void
    {
        $order = $this->createApprovedOrderWithItem(10, 0);
        $item = $order->items->first();

        // Create an allocation of 10
        $this->allocationService->allocateItemQuantity($item, 10, $this->admin);

        // Artificially change item's ordered_quantity without updating allocations
        $item->ordered_quantity = 5;
        $item->save();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Allocation sum invariant violated');

        $this->validator->validateItemConservation($item);
    }

    /**
     * 2. Boundary Limit Tests
     */
    public function test_cannot_allocate_zero_quantity(): void
    {
        $order = $this->createApprovedOrderWithItem(10, 0);
        $item = $order->items->first();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Allocation quantity must be between 1 and 999,999');

        $this->allocationService->allocateItemQuantity($item, 0, $this->admin);
    }

    public function test_cannot_allocate_negative_quantity(): void
    {
        $order = $this->createApprovedOrderWithItem(10, 0);
        $item = $order->items->first();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Allocation quantity must be between 1 and 999,999');

        $this->allocationService->allocateItemQuantity($item, -5, $this->admin);
    }

    public function test_cannot_allocate_quantity_exceeding_system_maximum(): void
    {
        $order = $this->createApprovedOrderWithItem(10, 0);
        $item = $order->items->first();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Allocation quantity must be between 1 and 999,999');

        $this->allocationService->allocateItemQuantity($item, 1000000, $this->admin);
    }

    public function test_boundary_extreme_valid_quantity_succeeds_when_fulfillable_allows(): void
    {
        $order = $this->createApprovedOrderWithItem(999999, 0);
        $item = $order->items->first();

        $allocation = $this->allocationService->allocateItemQuantity($item, 999999, $this->admin);

        $this->assertEquals(999999, $allocation->allocated_quantity);
        $this->assertEquals(999999, $item->fresh()->allocatedQuantity());
        $this->assertEquals(0, $item->fresh()->unallocatedQuantity());
    }

    /**
     * 3. Over-Allocation Constraints
     */
    public function test_single_allocation_exceeding_fulfillable_is_rejected(): void
    {
        $order = $this->createApprovedOrderWithItem(10, 2); // fulfillable = 8
        $item = $order->items->first();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot allocate 9 units. Only 8 fulfillable units remain unallocated.');

        $this->allocationService->allocateItemQuantity($item, 9, $this->admin);
    }

    public function test_cumulative_partial_allocations_exceeding_fulfillable_are_rejected(): void
    {
        $order = $this->createApprovedOrderWithItem(10, 2); // fulfillable = 8
        $item = $order->items->first();

        // 1st allocation: 5 units (3 remain)
        $this->allocationService->allocateItemQuantity($item, 5, $this->admin);
        $this->assertEquals(3, $item->fresh()->unallocatedQuantity());

        // 2nd allocation: attempt 4 units (fails)
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cannot allocate 4 units. Only 3 fulfillable units remain unallocated.');

        $this->allocationService->allocateItemQuantity($item, 4, $this->admin);
    }

    /**
     * 4. Release & Cancellation Invariants (Non-Destructive History)
     */
    public function test_release_allocation_restores_unallocated_pool_and_updates_rollups(): void
    {
        $order = $this->createApprovedOrderWithItem(10, 0); // fulfillable = 10
        $item = $order->items->first();

        $alc1 = $this->allocationService->allocateItemQuantity($item, 6, $this->admin);
        $alc2 = $this->allocationService->allocateItemQuantity($item, 4, $this->admin);

        $item->refresh();
        $this->assertEquals(10, $item->allocatedQuantity());
        $this->assertEquals(0, $item->unallocatedQuantity());
        $this->assertEquals(10, $item->reserved_quantity);

        // Release first allocation (6 units)
        $released = $this->allocationService->releaseAllocation($alc1, $this->admin, 'Customer postponed split delivery');

        $this->assertEquals(AllocationStatus::RELEASED, $released->status);
        $this->assertEquals(0, $released->reserved_quantity);
        $this->assertEquals(6, $released->allocated_quantity); // Historical quantity preserved
        $this->assertStringContainsString('Release reason: Customer postponed split delivery', $released->notes);

        $item->refresh();
        // Released row is excluded from active allocation sum
        $this->assertEquals(4, $item->allocatedQuantity());
        $this->assertEquals(6, $item->unallocatedQuantity());
        $this->assertEquals(4, $item->reserved_quantity); // Rollup synchronized

        // Now we can allocate up to 6 units again
        $alc3 = $this->allocationService->allocateItemQuantity($item, 6, $this->admin);
        $this->assertEquals(6, $alc3->allocated_quantity);
        $this->assertEquals(10, $item->fresh()->allocatedQuantity());
    }

    public function test_cancel_allocation_restores_unallocated_pool_and_preserves_audit(): void
    {
        $order = $this->createApprovedOrderWithItem(10, 0);
        $item = $order->items->first();

        $alc = $this->allocationService->allocateItemQuantity($item, 7, $this->admin);

        $item->refresh();
        $this->assertEquals(7, $item->allocatedQuantity());
        $this->assertEquals(3, $item->unallocatedQuantity());

        // Cancel allocation
        $cancelled = $this->allocationService->cancelAllocation($alc, $this->admin, 'Route optimization cancelled this batch');

        $this->assertEquals(AllocationStatus::CANCELLED, $cancelled->status);
        $this->assertEquals(0, $cancelled->reserved_quantity);
        $this->assertEquals(7, $cancelled->allocated_quantity); // Historical preserved
        $this->assertStringContainsString('Cancellation reason: Route optimization cancelled this batch', $cancelled->notes);

        $item->refresh();
        $this->assertEquals(0, $item->allocatedQuantity());
        $this->assertEquals(10, $item->unallocatedQuantity());
        $this->assertEquals(0, $item->reserved_quantity);
    }

    public function test_cannot_release_allocation_that_has_been_picked(): void
    {
        $order = $this->createApprovedOrderWithItem(10, 0);
        $item = $order->items->first();

        $alc = $this->allocationService->allocateItemQuantity($item, 5, $this->admin);

        // Simulate warehouse picking
        $alc->picked_quantity = 3;
        $alc->status = AllocationStatus::PICKED;
        $alc->save();

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage("in status 'PICKED' cannot be released");

        $this->allocationService->releaseAllocation($alc, $this->admin);
    }

    public function test_cannot_cancel_allocation_that_has_been_picked(): void
    {
        $order = $this->createApprovedOrderWithItem(10, 0);
        $item = $order->items->first();

        $alc = $this->allocationService->allocateItemQuantity($item, 5, $this->admin);

        // Simulate warehouse picking
        $alc->picked_quantity = 2;
        $alc->status = AllocationStatus::PICKED;
        $alc->save();

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage("in status 'PICKED' cannot be cancelled");

        $this->allocationService->cancelAllocation($alc, $this->admin);
    }

    /**
     * 5. Fulfillment Progression Invariants
     */
    public function test_fulfillment_progression_validates_strict_unidirectional_bounds(): void
    {
        // Valid progression: allocated=10, reserved=10, picked=8, dispatched=6, delivered=5, returned=1
        $this->validator->validateProgression(
            allocated: 10,
            reserved: 10,
            picked: 8,
            dispatched: 6,
            delivered: 5,
            returned: 1
        );
        $this->assertTrue(true); // Reached here without exception
    }

    public function test_dispatched_exceeding_picked_fails_progression_validation(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Dispatched quantity (6) cannot exceed picked quantity (5)');

        $this->validator->validateProgression(
            allocated: 10,
            reserved: 10,
            picked: 5,
            dispatched: 6,
            delivered: 0,
            returned: 0
        );
    }

    public function test_delivered_exceeding_dispatched_fails_progression_validation(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Delivered quantity (7) cannot exceed dispatched quantity (6)');

        $this->validator->validateProgression(
            allocated: 10,
            reserved: 10,
            picked: 8,
            dispatched: 6,
            delivered: 7,
            returned: 0
        );
    }

    public function test_returned_exceeding_delivered_fails_progression_validation(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Returned quantity (3) cannot exceed delivered quantity (2)');

        $this->validator->validateProgression(
            allocated: 10,
            reserved: 10,
            picked: 5,
            dispatched: 4,
            delivered: 2,
            returned: 3
        );
    }

    public function test_picked_exceeding_allocated_fails_progression_validation(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Picked quantity (12) must be between 0 and allocated quantity (10)');

        $this->validator->validateProgression(
            allocated: 10,
            reserved: 10,
            picked: 12,
            dispatched: 0,
            delivered: 0,
            returned: 0
        );
    }

    /**
     * 6. PostgreSQL Progression Check Constraints
     */
    public function test_postgresql_dispatched_and_delivered_constraints(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL progression check constraints run only on pgsql.');
        }

        $order = $this->createApprovedOrderWithItem(10, 0);
        $item = $order->items->first();

        // 1. dispatched > picked must be rejected by PostgreSQL CHECK constraint
        try {
            DB::table('order_item_allocations')->insert([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'allocation_number' => 'ALC-CHK-PROG-01',
                'allocated_quantity' => 10,
                'reserved_quantity' => 10,
                'picked_quantity' => 4,
                'dispatched_quantity' => 5, // Violates dispatched <= picked
                'delivered_quantity' => 0,
                'returned_quantity' => 0,
                'status' => 'DISPATCHED',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('PostgreSQL constraint order_item_allocations_dispatched_quantity_check did not trigger.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('order_item_allocations_dispatched_quantity_check', $e->getMessage());
        }

        // 2. delivered > dispatched must be rejected by PostgreSQL CHECK constraint
        try {
            DB::table('order_item_allocations')->insert([
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'allocation_number' => 'ALC-CHK-PROG-02',
                'allocated_quantity' => 10,
                'reserved_quantity' => 10,
                'picked_quantity' => 5,
                'dispatched_quantity' => 5,
                'delivered_quantity' => 6, // Violates delivered <= dispatched
                'returned_quantity' => 0,
                'status' => 'DELIVERED',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('PostgreSQL constraint order_item_allocations_delivered_quantity_check did not trigger.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('order_item_allocations_delivered_quantity_check', $e->getMessage());
        }
    }

    /**
     * 7. Sequence Numbering & Collision Avoidance
     */
    public function test_sequence_numbering_avoids_collisions_even_after_release_or_cancellation(): void
    {
        $order = $this->createApprovedOrderWithItem(20, 0);
        $item = $order->items->first();

        // 1st allocation: seq 01
        $alc1 = $this->allocationService->allocateItemQuantity($item, 5, $this->admin);
        $this->assertStringEndsWith('-01', $alc1->allocation_number);

        // 2nd allocation: seq 02
        $alc2 = $this->allocationService->allocateItemQuantity($item, 5, $this->admin);
        $this->assertStringEndsWith('-02', $alc2->allocation_number);

        // Release 2nd allocation
        $this->allocationService->releaseAllocation($alc2, $this->admin, 'Released batch');

        // 3rd allocation: must be seq 03 (NOT reusing 02, and NOT colliding with 01)
        $alc3 = $this->allocationService->allocateItemQuantity($item, 5, $this->admin);
        $this->assertStringEndsWith('-03', $alc3->allocation_number);

        // Cancel 3rd allocation
        $this->allocationService->cancelAllocation($alc3, $this->admin, 'Cancelled batch');

        // 4th allocation: must be seq 04
        $alc4 = $this->allocationService->allocateItemQuantity($item, 5, $this->admin);
        $this->assertStringEndsWith('-04', $alc4->allocation_number);

        $allAllocations = OrderItemAllocation::where('order_item_id', $item->id)->orderBy('id')->get();
        $this->assertCount(4, $allAllocations);
        $this->assertEquals(['01', '02', '03', '04'], $allAllocations->map(fn ($a) => substr($a->allocation_number, -2))->values()->all());
    }

    /**
     * 8. Rollup Drift Detection & Authoritative Synchronization
     */
    public function test_detects_rollup_drift_and_repairs_via_sync(): void
    {
        $order = $this->createApprovedOrderWithItem(10, 0);
        $item = $order->items->first();

        $alc = $this->allocationService->allocateItemQuantity($item, 7, $this->admin);

        // Artificially create drift in order_items table
        $item->reserved_quantity = 99;
        $item->picked_quantity = 42;
        $item->save();

        // Check drift detection
        $drift = $this->validator->detectRollupDrift($item);
        $this->assertTrue($drift['has_drift']);
        $this->assertArrayHasKey('reserved_quantity', $drift['drift_details']);
        $this->assertArrayHasKey('picked_quantity', $drift['drift_details']);

        // Check validation exception throws
        try {
            $this->allocationService->validateRollupConsistency($item);
            $this->fail('Should have thrown ValidationException on rollup drift.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('rollup_drift', $e->errors());
        }

        // Authoritatively repair rollups
        $this->allocationService->syncOrderItemRollups($item);

        $item->refresh();
        $this->assertEquals(7, $item->reserved_quantity);
        $this->assertEquals(0, $item->picked_quantity);

        $driftAfterSync = $this->validator->detectRollupDrift($item);
        $this->assertFalse($driftAfterSync['has_drift']);
    }

    /**
     * 9. Adjustment Capacity Pre-Check Boundary
     */
    public function test_adjustment_reduction_capacity_enforces_allocation_conservation(): void
    {
        $order = $this->createApprovedOrderWithItem(10, 0); // fulfillable = 10
        $item = $order->items->first();

        // No allocations yet -> fulfillable=10, unallocated=10
        $this->assertTrue($this->allocationService->canReduceFulfillableQuantity($item, 10));
        $this->assertTrue($this->allocationService->canReduceFulfillableQuantity($item, 5));
        $this->assertFalse($this->allocationService->canReduceFulfillableQuantity($item, 11));
        $this->assertFalse($this->allocationService->canReduceFulfillableQuantity($item, 0));
        $this->assertFalse($this->allocationService->canReduceFulfillableQuantity($item, -1));

        // Allocate 7 units -> unallocated=3
        $this->allocationService->allocateItemQuantity($item, 7, $this->admin);
        $item->refresh();

        $this->assertTrue($this->allocationService->canReduceFulfillableQuantity($item, 3));
        $this->assertTrue($this->allocationService->canReduceFulfillableQuantity($item, 1));
        $this->assertFalse($this->allocationService->canReduceFulfillableQuantity($item, 4)); // Cannot reduce by 4 because 7 are allocated!
    }

    /**
     * 10. Financial Immutability Verification
     */
    public function test_allocation_operations_never_mutate_financial_or_order_pricing_fields(): void
    {
        $order = $this->createApprovedOrderWithItem(10, 2);
        $item = $order->items->first();

        $subtotal = (string) $order->subtotal;
        $taxTotal = (string) $order->tax_total;
        $grandTotal = (string) $order->grand_total;
        $unitPrice = (string) $item->unit_price;
        $taxRate = (string) $item->tax_rate_snapshot;
        $taxableAmount = (string) $item->taxable_amount;
        $taxAmount = (string) $item->tax_amount;
        $lineTotal = (string) $item->line_total;

        // 1. Create allocation
        $alc = $this->allocationService->allocateItemQuantity($item, 5, $this->admin);

        // 2. Release allocation
        $this->allocationService->releaseAllocation($alc, $this->admin, 'Test release');

        // 3. Create another allocation
        $alc2 = $this->allocationService->allocateItemQuantity($item, 4, $this->admin);

        // 4. Cancel allocation
        $this->allocationService->cancelAllocation($alc2, $this->admin, 'Test cancellation');

        $order->refresh();
        $item->refresh();

        $this->assertEquals($subtotal, (string) $order->subtotal);
        $this->assertEquals($taxTotal, (string) $order->tax_total);
        $this->assertEquals($grandTotal, (string) $order->grand_total);
        $this->assertEquals($unitPrice, (string) $item->unit_price);
        $this->assertEquals($taxRate, (string) $item->tax_rate_snapshot);
        $this->assertEquals($taxableAmount, (string) $item->taxable_amount);
        $this->assertEquals($taxAmount, (string) $item->tax_amount);
        $this->assertEquals($lineTotal, (string) $item->line_total);
    }
}
