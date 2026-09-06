<?php

namespace Tests\Feature\Adjustment;

use App\Enums\AccountStatus;
use App\Enums\AdjustmentReasonCode;
use App\Enums\AdjustmentStatus;
use App\Enums\AllocationStatus;
use App\Enums\CustomerStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderAdjustmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\TaxProfileStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderAdjustmentItem;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAdjustmentReversalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $reviewerAdmin;
    protected User $superAdmin;
    protected User $salesman;

    protected Customer $customer;
    protected Category $category;
    protected TaxProfile $taxProfile;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Alice Admin',
            'email' => 'admin.alice@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->reviewerAdmin = User::factory()->create([
            'name' => 'Bob Reviewer',
            'email' => 'admin.bob@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Samantha',
            'email' => 'super@wholesale.test',
            'role' => UserRole::SUPER_ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'name' => 'Sam Salesman',
            'email' => 'salesman.sam@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Acme Corporation',
            'code' => 'CUST-ACME-01',
            'contact_name' => 'John Doe',
            'phone' => '+1-555-0199',
            'email' => 'acme@wholesale.test',
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

        $this->category = Category::create([
            'name' => 'Dry Goods',
            'code' => 'CAT-DRY',
            'is_active' => true,
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'Standard Tax',
            'code' => 'TAX-STD',
            'rate' => 18.00,
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->productA = Product::create([
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'name' => 'Premium Olive Oil 1L',
            'sku' => 'SKU-OIL-1L',
            'minimum_allowed_price' => '20.00',
            'default_selling_price' => '25.00',
            'mrp' => '30.00',
            'status' => ProductStatus::ACTIVE,
        ]);

        $this->productB = Product::create([
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'name' => 'Organic Vinegar 500ml',
            'sku' => 'SKU-VIN-500M',
            'minimum_allowed_price' => '10.00',
            'default_selling_price' => '15.00',
            'mrp' => '20.00',
            'status' => ProductStatus::ACTIVE,
        ]);
    }

    protected function createAndApplyScenario(
        int $orderedQty,
        int $allocatedQty,
        int $reservedQty,
        int $pickedQty,
        int $reductionQty
    ): array {
        $order = Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'delivery_status' => \App\Enums\DeliveryStatus::PENDING_ASSIGNMENT,
            'adjustment_status' => AdjustmentStatus::REQUESTED,
            'currency' => 'USD',
            'idempotency_key' => 'idemp-ord-' . uniqid(),
            'subtotal' => bcmul('25.00', (string) $orderedQty, 2),
            'tax_total' => bcmul('4.50', (string) $orderedQty, 2),
            'adjustment_total' => '0.00',
            'grand_total' => bcmul('29.50', (string) $orderedQty, 2),
            'version' => 1,
            'submitted_at' => Carbon::now()->subHours(2),
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => 'BOTTLE',
            'ordered_quantity' => $orderedQty,
            'cancelled_quantity' => 0,
            'reserved_quantity' => $reservedQty,
            'picked_quantity' => $pickedQty,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'unit_price' => '25.00',
            'tax_profile_id' => $this->taxProfile->id,
            'tax_profile_code_snapshot' => $this->taxProfile->code,
            'tax_profile_name_snapshot' => $this->taxProfile->name,
            'tax_rate_snapshot' => $this->taxProfile->rate,
            'taxable_amount' => bcmul('25.00', (string) $orderedQty, 2),
            'tax_amount' => bcmul('4.50', (string) $orderedQty, 2),
            'line_total' => bcmul('29.50', (string) $orderedQty, 2),
        ]);

        $allocation = null;
        if ($allocatedQty > 0) {
            $allocation = OrderItemAllocation::create([
                'allocation_number' => 'ALC-' . $order->order_number . '-' . $item->id . '-1',
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'product_id' => $this->productA->id,
                'warehouse_code' => 'WH-WEST',
                'allocated_quantity' => $allocatedQty,
                'reserved_quantity' => $reservedQty,
                'picked_quantity' => $pickedQty,
                'dispatched_quantity' => 0,
                'delivered_quantity' => 0,
                'returned_quantity' => 0,
                'status' => AllocationStatus::ALLOCATED->value,
                'allocated_by' => $this->admin->id,
                'allocated_at' => Carbon::now()->subHour(),
            ]);
        }

        $unallocated = max(0, $orderedQty - $allocatedQty);
        $affectedAlloc = max(0, $reductionQty - $unallocated);

        $adj = OrderAdjustment::create([
            'adjustment_number' => 'ADJ-REV-' . uniqid(),
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'order_version_snapshot' => 1,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => bcmul('25.00', (string) $orderedQty, 2),
            'order_tax_total_snapshot' => bcmul('4.50', (string) $orderedQty, 2),
            'order_grand_total_snapshot' => bcmul('29.50', (string) $orderedQty, 2),
            'type' => 'QUANTITY_REDUCTION',
            'status' => OrderAdjustmentStatus::APPROVED,
            'reason_code' => AdjustmentReasonCode::CUSTOMER_REQUEST,
            'notes' => 'Customer requested reduction.',
            'requested_by' => $this->salesman->id,
            'requested_at' => Carbon::now()->subHour(),
            'reviewed_by' => $this->reviewerAdmin->id,
            'reviewed_at' => Carbon::now()->subMinutes(30),
            'projected_subtotal_reduction' => bcmul('25.00', (string) $reductionQty, 2),
            'projected_tax_reduction' => bcmul('4.50', (string) $reductionQty, 2),
            'projected_grand_total_reduction' => bcmul('29.50', (string) $reductionQty, 2),
            'idempotency_key' => 'idem-' . uniqid(),
            'request_fingerprint' => hash('sha256', 'payload-' . uniqid()),
        ]);

        $adjItem = OrderAdjustmentItem::create([
            'adjustment_id' => $adj->id,
            'order_item_id' => $item->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_price_snapshot' => '25.00',
            'tax_rate_snapshot' => '18.0000',
            'tax_profile_code_snapshot' => 'TAX-STD',
            'ordered_quantity_snapshot' => $orderedQty,
            'cancelled_quantity_snapshot' => 0,
            'fulfillable_quantity_snapshot' => $orderedQty,
            'allocated_quantity_snapshot' => $allocatedQty,
            'unallocated_quantity_snapshot' => $unallocated,
            'requested_quantity_reduction' => $reductionQty,
            'projected_fulfillable_quantity' => $orderedQty - $reductionQty,
            'projected_cancelled_quantity' => $reductionQty,
            'affected_allocation_quantity' => $affectedAlloc,
            'projected_taxable_amount_reduction' => bcmul('25.00', (string) $reductionQty, 2),
            'projected_tax_amount_reduction' => bcmul('4.50', (string) $reductionQty, 2),
            'projected_line_total_reduction' => bcmul('29.50', (string) $reductionQty, 2),
        ]);

        // Apply it first
        $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/apply"
        )->assertRedirect();

        return [$order->fresh(), $adj->fresh(), $item->fresh(), $allocation ? $allocation->fresh() : null, $adjItem->fresh()];
    }

    public function test_admin_can_reverse_case_a_adjustment(): void
    {
        // 10 ordered, 4 allocated, 4 reserved, 0 picked, 3 reduction (Case A: unallocated was 6 >= 3)
        [$order, $adj, $item, $allocation] = $this->createAndApplyScenario(10, 4, 4, 0, 3);

        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $adj->status);
        $this->assertEquals(3, $item->cancelled_quantity);
        $this->assertEquals(7, $item->fulfillableQuantity());
        $this->assertEquals(2, $order->version);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reverse",
            [
                'reason' => 'Customer changed their mind and requested quantity restoration.',
            ]
        );

        $response->assertRedirect(route('admin.orders.adjustments.review', [
            'order' => $order->id,
            'adjustment' => $adj->id,
        ]));
        $response->assertSessionHas('success');

        // Verify adjustment fields
        $adjFresh = $adj->fresh();
        $this->assertEquals(OrderAdjustmentStatus::REVERSED, $adjFresh->status);
        $this->assertNotNull($adjFresh->reversed_at);
        $this->assertEquals($this->admin->id, $adjFresh->reversed_by);
        $this->assertEquals('Customer changed their mind and requested quantity restoration.', $adjFresh->reversal_reason);

        // Verify order state
        $orderFresh = $order->fresh();
        $this->assertEquals(AdjustmentStatus::REVERSED, $orderFresh->adjustment_status);
        $this->assertEquals(3, $orderFresh->version); // version incremented exactly once

        // Verify financials restored
        $this->assertEquals('250.00', $orderFresh->subtotal);
        $this->assertEquals('45.00', $orderFresh->tax_total);
        $this->assertEquals('295.00', $orderFresh->grand_total);
        $this->assertEquals('0.00', $orderFresh->adjustment_total);

        // Verify item quantities restored
        $itemFresh = $item->fresh();
        $this->assertEquals(0, $itemFresh->cancelled_quantity);
        $this->assertEquals(10, $itemFresh->fulfillableQuantity());
        $this->assertEquals(10, $itemFresh->ordered_quantity);
        $this->assertEquals('250.00', $itemFresh->taxable_amount);
        $this->assertEquals('45.00', $itemFresh->tax_amount);
        $this->assertEquals('295.00', $itemFresh->line_total);

        // Invariant: ordered = cancelled + fulfillable
        $this->assertEquals($itemFresh->ordered_quantity, $itemFresh->cancelled_quantity + $itemFresh->fulfillableQuantity());

        // Allocations remain unchanged in Case A
        $this->assertEquals(1, $orderFresh->allocations()->count());
        $this->assertEquals(4, $orderFresh->allocations()->first()->allocated_quantity);
    }

    public function test_admin_can_reverse_case_b_adjustment_with_restoration_allocation(): void
    {
        // 10 ordered, 10 allocated, 10 reserved, 0 picked, 4 reduction (Case B: unallocated was 0, 4 released)
        [$order, $adj, $item, $allocation] = $this->createAndApplyScenario(10, 10, 10, 0, 4);

        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $adj->status);
        $this->assertEquals(4, $item->cancelled_quantity);
        $this->assertEquals(6, $item->fulfillableQuantity());

        // Check that application created a RELEASED row
        $releasedAlloc = OrderItemAllocation::where('order_item_id', $item->id)
            ->where('status', AllocationStatus::RELEASED->value)
            ->first();
        $this->assertNotNull($releasedAlloc);
        $this->assertEquals(4, $releasedAlloc->allocated_quantity);

        // Reverse adjustment
        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reverse",
            [
                'reason' => 'Restoring previously reduced items as inventory arrived.',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify historical RELEASED row is STILL RELEASED and untouched
        $releasedFresh = $releasedAlloc->fresh();
        $this->assertEquals(AllocationStatus::RELEASED, $releasedFresh->status);
        $this->assertEquals(4, $releasedFresh->allocated_quantity);

        // Verify a NEW restoration allocation was created
        $restorationAlloc = OrderItemAllocation::where('order_item_id', $item->id)
            ->where('status', AllocationStatus::ALLOCATED->value)
            ->where('allocation_number', '!=', $allocation->allocation_number)
            ->first();

        $this->assertNotNull($restorationAlloc);
        $this->assertEquals(4, $restorationAlloc->allocated_quantity);
        // CRITICAL: reserved_quantity must NOT be fabricated (must be 0 <= reserved <= allocated)
        $this->assertEquals(0, $restorationAlloc->reserved_quantity);
        $this->assertEquals(0, $restorationAlloc->picked_quantity);
        $this->assertEquals(0, $restorationAlloc->dispatched_quantity);
        $this->assertEquals(0, $restorationAlloc->delivered_quantity);
        $this->assertEquals(0, $restorationAlloc->returned_quantity);
        $this->assertEquals('WH-WEST', $restorationAlloc->warehouse_code);
        $this->assertStringContainsString('via reversed adjustment', $restorationAlloc->notes);

        // Verify item quantities
        $itemFresh = $item->fresh();
        $this->assertEquals(0, $itemFresh->cancelled_quantity);
        $this->assertEquals(10, $itemFresh->fulfillableQuantity());
        $this->assertEquals(10, $itemFresh->ordered_quantity);

        // Verify active allocated total is now 6 + 4 = 10
        $activeAllocatedTotal = OrderItemAllocation::where('order_item_id', $item->id)
            ->where('status', AllocationStatus::ALLOCATED->value)
            ->sum('allocated_quantity');
        $this->assertEquals(10, $activeAllocatedTotal);
        $this->assertLessThanOrEqual($itemFresh->fulfillableQuantity(), $activeAllocatedTotal);

        // Verify order version
        $this->assertEquals(3, $order->fresh()->version);
    }

    public function test_case_b_reversal_zero_reserved_source_case(): void
    {
        // 10 ordered, 10 allocated, 0 reserved (zero reserved source case), 4 reduction
        [$order, $adj, $item, $allocation] = $this->createAndApplyScenario(10, 10, 0, 0, 4);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reverse",
            [
                'reason' => 'Zero-reserved source restoration test reason.',
            ]
        );
        $response->assertRedirect();

        $restorationAlloc = OrderItemAllocation::where('order_item_id', $item->id)
            ->where('status', AllocationStatus::ALLOCATED->value)
            ->where('allocation_number', '!=', $allocation->allocation_number)
            ->first();

        $this->assertNotNull($restorationAlloc);
        $this->assertEquals(4, $restorationAlloc->allocated_quantity);
        $this->assertEquals(0, $restorationAlloc->reserved_quantity);
        $this->assertTrue(0 <= $restorationAlloc->reserved_quantity && $restorationAlloc->reserved_quantity <= $restorationAlloc->allocated_quantity);
    }

    public function test_multi_line_mixed_case_a_and_case_b_reversal(): void
    {
        // Create order with 2 items
        $order = Order::create([
            'order_number' => 'ORD-MIXED-' . uniqid(),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'delivery_status' => \App\Enums\DeliveryStatus::PENDING_ASSIGNMENT,
            'adjustment_status' => AdjustmentStatus::REQUESTED,
            'currency' => 'USD',
            'idempotency_key' => 'idemp-ord-' . uniqid(),
            'subtotal' => '400.00',
            'tax_total' => '72.00',
            'adjustment_total' => '0.00',
            'grand_total' => '472.00',
            'version' => 1,
            'submitted_at' => Carbon::now()->subHours(2),
        ]);

        // Item 1: 10 ordered, 2 allocated, 3 reduction (Case A: unallocated 8 >= 3)
        $item1 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => 'BOTTLE',
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 2,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'unit_price' => '25.00',
            'tax_profile_id' => $this->taxProfile->id,
            'tax_profile_code_snapshot' => $this->taxProfile->code,
            'tax_profile_name_snapshot' => $this->taxProfile->name,
            'tax_rate_snapshot' => $this->taxProfile->rate,
            'taxable_amount' => '250.00',
            'tax_amount' => '45.00',
            'line_total' => '295.00',
        ]);

        OrderItemAllocation::create([
            'allocation_number' => 'ALC-' . $order->order_number . '-' . $item1->id . '-1',
            'order_id' => $order->id,
            'order_item_id' => $item1->id,
            'product_id' => $this->productA->id,
            'warehouse_code' => 'WH-WEST',
            'allocated_quantity' => 2,
            'reserved_quantity' => 2,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'status' => AllocationStatus::ALLOCATED->value,
            'allocated_by' => $this->admin->id,
            'allocated_at' => Carbon::now()->subHour(),
        ]);

        // Item 2: 10 ordered, 10 allocated, 4 reduction (Case B: unallocated 0, 4 affected)
        $item2 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productB->id,
            'product_name_snapshot' => $this->productB->name,
            'sku_snapshot' => $this->productB->sku,
            'unit_snapshot' => 'BOTTLE',
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 10,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'unit_price' => '15.00',
            'tax_profile_id' => $this->taxProfile->id,
            'tax_profile_code_snapshot' => $this->taxProfile->code,
            'tax_profile_name_snapshot' => $this->taxProfile->name,
            'tax_rate_snapshot' => $this->taxProfile->rate,
            'taxable_amount' => '150.00',
            'tax_amount' => '27.00',
            'line_total' => '177.00',
        ]);

        OrderItemAllocation::create([
            'allocation_number' => 'ALC-' . $order->order_number . '-' . $item2->id . '-1',
            'order_id' => $order->id,
            'order_item_id' => $item2->id,
            'product_id' => $this->productB->id,
            'warehouse_code' => 'WH-EAST',
            'allocated_quantity' => 10,
            'reserved_quantity' => 10,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'status' => AllocationStatus::ALLOCATED->value,
            'allocated_by' => $this->admin->id,
            'allocated_at' => Carbon::now()->subHour(),
        ]);

        $adj = OrderAdjustment::create([
            'adjustment_number' => 'ADJ-MIXED-' . uniqid(),
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'order_version_snapshot' => 1,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => '400.00',
            'order_tax_total_snapshot' => '72.00',
            'order_grand_total_snapshot' => '472.00',
            'type' => 'QUANTITY_REDUCTION',
            'status' => OrderAdjustmentStatus::APPROVED,
            'reason_code' => AdjustmentReasonCode::CUSTOMER_REQUEST,
            'notes' => 'Mixed multi-line reduction.',
            'requested_by' => $this->salesman->id,
            'requested_at' => Carbon::now()->subHour(),
            'reviewed_by' => $this->reviewerAdmin->id,
            'reviewed_at' => Carbon::now()->subMinutes(30),
            'projected_subtotal_reduction' => '135.00',
            'projected_tax_reduction' => '24.30',
            'projected_grand_total_reduction' => '159.30',
            'idempotency_key' => 'idem-' . uniqid(),
            'request_fingerprint' => hash('sha256', 'payload-' . uniqid()),
        ]);

        OrderAdjustmentItem::create([
            'adjustment_id' => $adj->id,
            'order_item_id' => $item1->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_price_snapshot' => '25.00',
            'tax_rate_snapshot' => '18.0000',
            'tax_profile_code_snapshot' => 'TAX-STD',
            'ordered_quantity_snapshot' => 10,
            'cancelled_quantity_snapshot' => 0,
            'fulfillable_quantity_snapshot' => 10,
            'allocated_quantity_snapshot' => 2,
            'unallocated_quantity_snapshot' => 8,
            'requested_quantity_reduction' => 3,
            'projected_fulfillable_quantity' => 7,
            'projected_cancelled_quantity' => 3,
            'affected_allocation_quantity' => 0,
            'projected_taxable_amount_reduction' => '75.00',
            'projected_tax_amount_reduction' => '13.50',
            'projected_line_total_reduction' => '88.50',
        ]);

        OrderAdjustmentItem::create([
            'adjustment_id' => $adj->id,
            'order_item_id' => $item2->id,
            'product_id' => $this->productB->id,
            'product_name_snapshot' => $this->productB->name,
            'sku_snapshot' => $this->productB->sku,
            'unit_price_snapshot' => '15.00',
            'tax_rate_snapshot' => '18.0000',
            'tax_profile_code_snapshot' => 'TAX-STD',
            'ordered_quantity_snapshot' => 10,
            'cancelled_quantity_snapshot' => 0,
            'fulfillable_quantity_snapshot' => 10,
            'allocated_quantity_snapshot' => 10,
            'unallocated_quantity_snapshot' => 0,
            'requested_quantity_reduction' => 4,
            'projected_fulfillable_quantity' => 6,
            'projected_cancelled_quantity' => 4,
            'affected_allocation_quantity' => 4,
            'projected_taxable_amount_reduction' => '60.00',
            'projected_tax_amount_reduction' => '10.80',
            'projected_line_total_reduction' => '70.80',
        ]);

        // Apply adjustment
        $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/apply"
        )->assertRedirect();

        // Now reverse
        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reverse",
            [
                'reason' => 'Customer requested reversal of full multi-line reduction.',
            ]
        );
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Check Item 1: no new allocations created
        $item1Allocations = OrderItemAllocation::where('order_item_id', $item1->id)->get();
        $this->assertCount(1, $item1Allocations);
        $this->assertEquals(0, $item1->fresh()->cancelled_quantity);
        $this->assertEquals(10, $item1->fresh()->fulfillableQuantity());

        // Check Item 2: has forward restoration allocation
        $item2ActiveAllocations = OrderItemAllocation::where('order_item_id', $item2->id)
            ->where('status', AllocationStatus::ALLOCATED->value)
            ->get();
        $this->assertCount(2, $item2ActiveAllocations);
        $this->assertEquals(10, $item2ActiveAllocations->sum('allocated_quantity'));
        $this->assertEquals(0, $item2->fresh()->cancelled_quantity);
        $this->assertEquals(10, $item2->fresh()->fulfillableQuantity());

        // Check order financials restored
        $orderFresh = $order->fresh();
        $this->assertEquals('400.00', $orderFresh->subtotal);
        $this->assertEquals('72.00', $orderFresh->tax_total);
        $this->assertEquals('472.00', $orderFresh->grand_total);
        $this->assertEquals('0.00', $orderFresh->adjustment_total);
        $this->assertEquals(3, $orderFresh->version);
    }

    public function test_sequential_adjustments_lifo_reversal_success(): void
    {
        // 10 ordered, unallocated
        $order = Order::create([
            'order_number' => 'ORD-LIFO-' . uniqid(),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'delivery_status' => \App\Enums\DeliveryStatus::PENDING_ASSIGNMENT,
            'adjustment_status' => AdjustmentStatus::REQUESTED,
            'currency' => 'USD',
            'idempotency_key' => 'idemp-ord-' . uniqid(),
            'subtotal' => '250.00',
            'tax_total' => '45.00',
            'adjustment_total' => '0.00',
            'grand_total' => '295.00',
            'version' => 1,
            'submitted_at' => Carbon::now()->subHours(3),
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => 'BOTTLE',
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'unit_price' => '25.00',
            'tax_profile_id' => $this->taxProfile->id,
            'tax_profile_code_snapshot' => $this->taxProfile->code,
            'tax_profile_name_snapshot' => $this->taxProfile->name,
            'tax_rate_snapshot' => $this->taxProfile->rate,
            'taxable_amount' => '250.00',
            'tax_amount' => '45.00',
            'line_total' => '295.00',
        ]);

        // Adjustment 1: reduce 2
        $adj1 = OrderAdjustment::create([
            'adjustment_number' => 'ADJ-LIFO-1',
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'order_version_snapshot' => 1,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => '250.00',
            'order_tax_total_snapshot' => '45.00',
            'order_grand_total_snapshot' => '295.00',
            'type' => 'QUANTITY_REDUCTION',
            'status' => OrderAdjustmentStatus::APPROVED,
            'reason_code' => AdjustmentReasonCode::CUSTOMER_REQUEST,
            'notes' => 'Adj 1',
            'requested_by' => $this->salesman->id,
            'requested_at' => Carbon::now()->subHours(2),
            'reviewed_by' => $this->reviewerAdmin->id,
            'reviewed_at' => Carbon::now()->subHours(2),
            'projected_subtotal_reduction' => '50.00',
            'projected_tax_reduction' => '9.00',
            'projected_grand_total_reduction' => '59.00',
            'idempotency_key' => 'idem-1',
            'request_fingerprint' => 'fp-1',
        ]);

        OrderAdjustmentItem::create([
            'adjustment_id' => $adj1->id,
            'order_item_id' => $item->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_price_snapshot' => '25.00',
            'tax_rate_snapshot' => '18.0000',
            'tax_profile_code_snapshot' => 'TAX-STD',
            'ordered_quantity_snapshot' => 10,
            'cancelled_quantity_snapshot' => 0,
            'fulfillable_quantity_snapshot' => 10,
            'allocated_quantity_snapshot' => 0,
            'unallocated_quantity_snapshot' => 10,
            'requested_quantity_reduction' => 2,
            'projected_fulfillable_quantity' => 8,
            'projected_cancelled_quantity' => 2,
            'affected_allocation_quantity' => 0,
            'projected_taxable_amount_reduction' => '50.00',
            'projected_tax_amount_reduction' => '9.00',
            'projected_line_total_reduction' => '59.00',
        ]);

        $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj1->id}/apply"
        )->assertRedirect();

        // Adjustment 2: reduce 3
        $adj2 = OrderAdjustment::create([
            'adjustment_number' => 'ADJ-LIFO-2',
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'order_version_snapshot' => 2,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => '200.00',
            'order_tax_total_snapshot' => '36.00',
            'order_grand_total_snapshot' => '236.00',
            'type' => 'QUANTITY_REDUCTION',
            'status' => OrderAdjustmentStatus::APPROVED,
            'reason_code' => AdjustmentReasonCode::CUSTOMER_REQUEST,
            'notes' => 'Adj 2',
            'requested_by' => $this->salesman->id,
            'requested_at' => Carbon::now()->subHour(),
            'reviewed_by' => $this->reviewerAdmin->id,
            'reviewed_at' => Carbon::now()->subHour(),
            'projected_subtotal_reduction' => '75.00',
            'projected_tax_reduction' => '13.50',
            'projected_grand_total_reduction' => '88.50',
            'idempotency_key' => 'idem-2',
            'request_fingerprint' => 'fp-2',
        ]);

        OrderAdjustmentItem::create([
            'adjustment_id' => $adj2->id,
            'order_item_id' => $item->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_price_snapshot' => '25.00',
            'tax_rate_snapshot' => '18.0000',
            'tax_profile_code_snapshot' => 'TAX-STD',
            'ordered_quantity_snapshot' => 10,
            'cancelled_quantity_snapshot' => 2,
            'fulfillable_quantity_snapshot' => 8,
            'allocated_quantity_snapshot' => 0,
            'unallocated_quantity_snapshot' => 8,
            'requested_quantity_reduction' => 3,
            'projected_fulfillable_quantity' => 5,
            'projected_cancelled_quantity' => 5,
            'affected_allocation_quantity' => 0,
            'projected_taxable_amount_reduction' => '75.00',
            'projected_tax_amount_reduction' => '13.50',
            'projected_line_total_reduction' => '88.50',
        ]);

        $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj2->id}/apply"
        )->assertRedirect();

        // Both adjustments applied now: total cancelled = 5
        $this->assertEquals(5, $item->fresh()->cancelled_quantity);
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $adj1->fresh()->status);
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $adj2->fresh()->status);

        // Reverse latest adjustment (Adj 2)
        $res2 = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj2->id}/reverse",
            ['reason' => 'Reversing the latest adjustment first as per LIFO.']
        );
        $res2->assertRedirect();
        $res2->assertSessionHas('success');

        $this->assertEquals(OrderAdjustmentStatus::REVERSED, $adj2->fresh()->status);
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $adj1->fresh()->status);
        // orders.adjustment_status MUST remain APPLIED because Adj 1 is still applied
        $this->assertEquals(AdjustmentStatus::APPLIED, $order->fresh()->adjustment_status);
        $this->assertEquals(2, $item->fresh()->cancelled_quantity);
        $this->assertEquals(8, $item->fresh()->fulfillableQuantity());

        // Now reverse Adj 1
        $res1 = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj1->id}/reverse",
            ['reason' => 'Now reversing the initial adjustment.']
        );
        $res1->assertRedirect();
        $res1->assertSessionHas('success');

        $this->assertEquals(OrderAdjustmentStatus::REVERSED, $adj1->fresh()->status);
        // Now no adjustments are applied, so orders.adjustment_status becomes REVERSED
        $this->assertEquals(AdjustmentStatus::REVERSED, $order->fresh()->adjustment_status);
        $this->assertEquals(0, $item->fresh()->cancelled_quantity);
        $this->assertEquals(10, $item->fresh()->fulfillableQuantity());
    }
}
