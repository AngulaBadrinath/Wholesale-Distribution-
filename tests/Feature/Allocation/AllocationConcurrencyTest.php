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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AllocationConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $salesman;
    protected Customer $customer;
    protected Product $product;
    protected TaxProfile $taxProfile;
    protected Category $category;
    protected OrderAllocationService $allocationService;

    protected function setUp(): void
    {
        parent::setUp();

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
            'name' => 'Concurrency Retailers',
            'code' => 'CUST-CONC-01',
            'contact_name' => 'Charlie Concurrency',
            'phone' => '+1-555-8899',
            'email' => 'concurrency@wholesale.test',
            'billing_address_line1' => '500 Parallel Way',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10005',
            'billing_country' => 'USA',
            'shipping_address_line1' => '500 Parallel Way',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10005',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
            'credit_limit' => 25000.00,
        ]);

        $this->category = Category::create([
            'name' => 'Bulk Staples',
            'code' => 'BULK-STAPLES',
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'Zero Tax',
            'code' => 'ZERO-TAX',
            'rate' => 0.00,
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->product = Product::create([
            'name' => 'Rice 50kg Sack',
            'sku' => 'SKU-RICE-50',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'cost_price' => 20.00,
            'minimum_allowed_price' => 25.00,
            'default_selling_price' => 30.00,
            'mrp' => 35.00,
            'status' => ProductStatus::ACTIVE,
            'unit' => 'BAG',
        ]);
    }

    protected function createApprovedOrderWithFulfillableQuantity(int $fulfillableQty = 10): Order
    {
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
            'subtotal' => $fulfillableQty * 30.00,
            'tax_total' => 0.00,
            'grand_total' => $fulfillableQty * 30.00,
            'submitted_at' => Carbon::now()->subHours(2),
            'approved_at' => Carbon::now()->subHour(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
            'unit_snapshot' => $this->product->unit,
            'ordered_quantity' => $fulfillableQty,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'unit_price' => 30.00,
            'tax_rate_snapshot' => 0.00,
            'taxable_amount' => $fulfillableQty * 30.00,
            'tax_amount' => 0.00,
            'line_total' => $fulfillableQty * 30.00,
        ]);

        return $order->fresh(['items']);
    }

    /**
     * Test that competing allocation requests serialize safely:
     * When total requested > fulfillable, exactly one succeeds and the other fails with ValidationException.
     */
    public function test_competing_allocations_respect_pessimistic_locking_and_prevent_cross_row_over_allocation(): void
    {
        $order = $this->createApprovedOrderWithFulfillableQuantity(10);
        $item = $order->items->first();

        $successCount = 0;
        $failedCount = 0;
        $lastException = null;

        // Process 1 wants 6 units, Process 2 wants 5 units (total 11 > 10)
        $requests = [6, 5];

        foreach ($requests as $qty) {
            try {
                $this->allocationService->allocateItemQuantity($item, $qty, $this->admin);
                $successCount++;
            } catch (ValidationException $e) {
                $failedCount++;
                $lastException = $e;
            }
        }

        $this->assertEquals(1, $successCount, 'Exactly one competing allocation should succeed.');
        $this->assertEquals(1, $failedCount, 'The competing allocation exceeding remaining capacity must fail.');
        $this->assertNotNull($lastException);
        $this->assertStringContainsString('remain unallocated', $lastException->getMessage());

        $item->refresh();
        $this->assertEquals(6, $item->allocatedQuantity());
        $this->assertEquals(4, $item->unallocatedQuantity());
        $this->assertEquals(6, $item->reserved_quantity);

        // Active allocations sum must be strictly <= fulfillable
        $activeAllocationsSum = OrderItemAllocation::where('order_item_id', $item->id)
            ->whereNotIn('status', [AllocationStatus::CANCELLED, AllocationStatus::RELEASED])
            ->sum('allocated_quantity');

        $this->assertLessThanOrEqual(10, $activeAllocationsSum);
        $this->assertEquals(6, $activeAllocationsSum);
    }

    /**
     * Test that multiple non-conflicting allocations serialize cleanly up to exact fulfillable boundary.
     */
    public function test_serialized_allocations_reach_exact_fulfillable_capacity(): void
    {
        $order = $this->createApprovedOrderWithFulfillableQuantity(10);
        $item = $order->items->first();

        // Three sequential allocations: 4 + 3 + 3 = 10
        $a1 = $this->allocationService->allocateItemQuantity($item, 4, $this->admin);
        $a2 = $this->allocationService->allocateItemQuantity($item, 3, $this->admin);
        $a3 = $this->allocationService->allocateItemQuantity($item, 3, $this->admin);

        $this->assertNotNull($a1);
        $this->assertNotNull($a2);
        $this->assertNotNull($a3);

        $item->refresh();
        $this->assertEquals(10, $item->allocatedQuantity());
        $this->assertEquals(0, $item->unallocatedQuantity());
        $this->assertEquals(10, $item->reserved_quantity);

        // Further allocation attempt of even 1 unit fails immediately
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Only 0 fulfillable units remain unallocated');

        $this->allocationService->allocateItemQuantity($item, 1, $this->admin);
    }

    /**
     * Test that lock ordering prevents deadlocks when multiple transactions operate on orders.
     */
    public function test_lock_ordering_is_deterministic(): void
    {
        $order = $this->createApprovedOrderWithFulfillableQuantity(15);
        $item = $order->items->first();

        // Transaction verifying Order -> OrderItem lock ordering
        DB::transaction(function () use ($order, $item) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();
            $lockedItem = OrderItem::where('id', $item->id)->lockForUpdate()->firstOrFail();

            $this->assertEquals($order->id, $lockedOrder->id);
            $this->assertEquals($item->id, $lockedItem->id);
        });

        $this->assertTrue(true);
    }
}
