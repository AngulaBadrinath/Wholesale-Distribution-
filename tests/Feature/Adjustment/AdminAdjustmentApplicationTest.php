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
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AdminAdjustmentApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $superAdmin;
    protected User $salesman;

    protected Customer $customer;
    protected Category $category;
    protected TaxProfile $taxProfile;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Alice Admin',
            'email' => 'admin.a@wholesale.test',
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
            'email' => 'sales@wholesale.test',
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

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'name' => 'Premium Olive Oil 1L',
            'sku' => 'SKU-OIL-1L',
            'minimum_allowed_price' => '20.00',
            'default_selling_price' => '25.00',
            'mrp' => '30.00',
            'status' => ProductStatus::ACTIVE,
        ]);
    }

    protected function createApprovedScenario(
        int $orderedQty,
        int $allocatedQty,
        int $reservedQty,
        int $pickedQty,
        int $reductionQty,
        string $allocationStatus = AllocationStatus::ALLOCATED->value
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
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
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
                'product_id' => $this->product->id,
                'allocated_quantity' => $allocatedQty,
                'reserved_quantity' => $reservedQty,
                'picked_quantity' => $pickedQty,
                'dispatched_quantity' => 0,
                'delivered_quantity' => 0,
                'returned_quantity' => 0,
                'status' => $allocationStatus,
                'allocated_by' => $this->admin->id,
                'allocated_at' => Carbon::now()->subHour(),
            ]);
        }

        $unallocated = max(0, $orderedQty - $allocatedQty);
        $affectedAlloc = max(0, $reductionQty - $unallocated);

        $adj = OrderAdjustment::create([
            'adjustment_number' => 'ADJ-APP-' . uniqid(),
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
            'reviewed_by' => $this->admin->id,
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
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
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

        return [$order, $adj, $item, $allocation, $adjItem];
    }

    public function test_admin_can_apply_valid_case_a_adjustment(): void
    {
        // 10 ordered, 4 allocated, 4 reserved, 0 picked, 3 reduction => unallocated is 6, reduction (3) <= 6 (Case A)
        [$order, $adj, $item, $allocation] = $this->createApprovedScenario(10, 4, 4, 0, 3);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/apply"
        );

        $response->assertRedirect(route('admin.orders.adjustments.review', [
            'order' => $order->id,
            'adjustment' => $adj->id,
        ]));
        $response->assertSessionHas('success');

        // Verify adjustment status
        $adjFresh = $adj->fresh();
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $adjFresh->status);
        $this->assertNotNull($adjFresh->applied_at);

        // Verify order state
        $orderFresh = $order->fresh();
        $this->assertEquals(AdjustmentStatus::APPLIED, $orderFresh->adjustment_status);
        $this->assertEquals(2, $orderFresh->version); // version incremented from 1 to 2
        $this->assertEquals('175.00', $orderFresh->subtotal); // 7 * 25.00
        $this->assertEquals('31.50', $orderFresh->tax_total); // 7 * 4.50
        $this->assertEquals('206.50', $orderFresh->grand_total); // 7 * 29.50
        $this->assertEquals('88.50', $orderFresh->adjustment_total); // 3 * 29.50

        // Verify item quantities & quantity conservation: ordered = cancelled + fulfillable
        $itemFresh = $item->fresh();
        $this->assertEquals(10, $itemFresh->ordered_quantity); // RULE-DOM-001: immutable!
        $this->assertEquals(3, $itemFresh->cancelled_quantity);
        $this->assertEquals(7, $itemFresh->fulfillableQuantity());
        $this->assertEquals(10, $itemFresh->cancelled_quantity + $itemFresh->fulfillableQuantity());

        // Verify line financials
        $this->assertEquals('175.00', $itemFresh->taxable_amount);
        $this->assertEquals('31.50', $itemFresh->tax_amount);
        $this->assertEquals('206.50', $itemFresh->line_total);

        // Verify allocations untouched in Case A
        $this->assertEquals(4, $allocation->fresh()->allocated_quantity);
        $this->assertEquals(4, $allocation->fresh()->reserved_quantity);
        $this->assertEquals(AllocationStatus::ALLOCATED, $allocation->fresh()->status);
    }

    public function test_admin_can_apply_case_b_full_allocation_release(): void
    {
        // 10 ordered, 3 allocated, 3 reserved, 0 picked, 10 reduction
        // unallocated = 7. reduction = 10.
        // A = 10 - 7 = 3 units to release.
        // Allocation has 3 units allocated, 3 reserved.
        [$order, $adj, $item, $allocation] = $this->createApprovedScenario(10, 3, 3, 0, 10);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/apply"
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Full release of allocation of 3 units
        $allocFresh = $allocation->fresh();
        $this->assertEquals(AllocationStatus::RELEASED, $allocFresh->status);
        $this->assertEquals(3, $allocFresh->allocated_quantity); // retains historical allocated quantity
        $this->assertEquals(0, $allocFresh->reserved_quantity);
        $this->assertEquals(0, $allocFresh->picked_quantity);

        // Verify item rollups: active allocated = 0, reserved = 0
        $itemFresh = $item->fresh();
        $this->assertEquals(10, $itemFresh->ordered_quantity);
        $this->assertEquals(10, $itemFresh->cancelled_quantity);
        $this->assertEquals(0, $itemFresh->fulfillableQuantity());
        $this->assertEquals(0, $itemFresh->reserved_quantity);
    }

    public function test_admin_can_apply_case_b_partial_allocation_release_with_split(): void
    {
        // 10 ordered, 8 allocated, 8 reserved, 0 picked, 5 reduction
        // unallocated = 2. reduction = 5.
        // A = 5 - 2 = 3 units to release from allocation of 8.
        [$order, $adj, $item, $allocation] = $this->createApprovedScenario(10, 8, 8, 0, 5);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/apply"
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Original allocation row becomes active remainder
        $allocFresh = $allocation->fresh();
        $this->assertEquals(AllocationStatus::ALLOCATED, $allocFresh->status);
        $this->assertEquals(5, $allocFresh->allocated_quantity); // 8 - 3 = 5
        $this->assertEquals(5, $allocFresh->reserved_quantity); // 8 - 3 = 5
        $this->assertEquals(0, $allocFresh->picked_quantity);

        // Released child row created
        $releasedChild = OrderItemAllocation::where('order_item_id', $item->id)
            ->where('status', AllocationStatus::RELEASED->value)
            ->first();

        $this->assertNotNull($releasedChild);
        $this->assertEquals(3, $releasedChild->allocated_quantity);
        $this->assertEquals(0, $releasedChild->reserved_quantity);
        $this->assertEquals(0, $releasedChild->picked_quantity);
        $this->assertEquals(0, $releasedChild->dispatched_quantity);
        $this->assertEquals(0, $releasedChild->delivered_quantity);
        $this->assertEquals(0, $releasedChild->returned_quantity);

        // Allocation number pattern: ALC-{order_number}-{order_item_id}-{seq}
        $expectedNumber = 'ALC-' . $order->order_number . '-' . $item->id . '-02';
        $this->assertEquals($expectedNumber, $releasedChild->allocation_number);

        // Mathematical conservation: active + released = 5 + 3 = 8
        $this->assertEquals(8, $allocFresh->allocated_quantity + $releasedChild->allocated_quantity);

        // Item rollups
        $itemFresh = $item->fresh();
        $this->assertEquals(10, $itemFresh->ordered_quantity);
        $this->assertEquals(5, $itemFresh->cancelled_quantity);
        $this->assertEquals(5, $itemFresh->fulfillableQuantity());
        $this->assertEquals(5, $itemFresh->reserved_quantity);
    }

    public function test_case_b_partial_release_with_zero_or_low_reserved_quantity(): void
    {
        // Mandatory Correction 1:
        // Allocation has allocated = 8, but reserved = 0 (or reserved = 2 < A)
        // Release A = 3 units.
        // released_reserved = min(3, 0) = 0.
        // remaining_allocated = 5, remaining_reserved = 0.
        // Assert NO negative reserved quantity!
        [$order, $adj, $item, $allocation] = $this->createApprovedScenario(10, 8, 0, 0, 5);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/apply"
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $allocFresh = $allocation->fresh();
        $this->assertEquals(5, $allocFresh->allocated_quantity);
        $this->assertEquals(0, $allocFresh->reserved_quantity);
        $this->assertTrue($allocFresh->reserved_quantity >= 0);
        $this->assertTrue($allocFresh->reserved_quantity <= $allocFresh->allocated_quantity);

        $releasedChild = OrderItemAllocation::where('order_item_id', $item->id)
            ->where('status', AllocationStatus::RELEASED->value)
            ->first();

        $this->assertNotNull($releasedChild);
        $this->assertEquals(3, $releasedChild->allocated_quantity);
        $this->assertEquals(0, $releasedChild->reserved_quantity);
    }

    public function test_deterministic_release_order_allocated_before_reserved(): void
    {
        // Order with 2 allocations:
        // Alloc 1: RESERVED, allocated=4, reserved=4
        // Alloc 2: ALLOCATED, allocated=4, reserved=0
        // Total allocated = 8. Unallocated = 2.
        // Reduction = 5. A = 5 - 2 = 3 units to release.
        // Deterministic release strategy: ALLOCATED (less operationally progressed) is released first.
        [$order, $adj, $item, $alloc1] = $this->createApprovedScenario(10, 4, 4, 0, 5, AllocationStatus::RESERVED->value);

        $alloc2 = OrderItemAllocation::create([
            'allocation_number' => 'ALC-' . $order->order_number . '-' . $item->id . '-2',
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'product_id' => $this->product->id,
            'allocated_quantity' => 4,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'status' => AllocationStatus::ALLOCATED,
            'allocated_by' => $this->admin->id,
            'allocated_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/apply"
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Alloc 2 (ALLOCATED) should have been partially released (4 - 3 = 1)
        $alloc2Fresh = $alloc2->fresh();
        $this->assertEquals(1, $alloc2Fresh->allocated_quantity);

        // Alloc 1 (RESERVED) should remain completely untouched!
        $alloc1Fresh = $alloc1->fresh();
        $this->assertEquals(4, $alloc1Fresh->allocated_quantity);
        $this->assertEquals(4, $alloc1Fresh->reserved_quantity);
    }

    public function test_cannot_apply_unapproved_adjustment(): void
    {
        [$order, $adj, $item] = $this->createApprovedScenario(10, 4, 4, 0, 3);
        $adj->update(['status' => OrderAdjustmentStatus::SUBMITTED]);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/apply"
        );

        $response->assertStatus(409);
        $this->assertEquals(OrderAdjustmentStatus::SUBMITTED, $adj->fresh()->status);
        $this->assertEquals(1, $order->fresh()->version);
    }

    public function test_double_apply_returns_409_conflict(): void
    {
        [$order, $adj, $item] = $this->createApprovedScenario(10, 4, 4, 0, 3);

        // First apply succeeds
        $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/apply"
        )->assertRedirect();

        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $adj->fresh()->status);
        $this->assertEquals(2, $order->fresh()->version);

        // Second apply fails with 409 Conflict
        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/apply"
        );

        $response->assertStatus(409);
        $this->assertEquals(2, $order->fresh()->version); // No second version increment!
        $this->assertEquals(3, $item->fresh()->cancelled_quantity); // No double cancellation!
    }

    public function test_cannot_apply_if_allocation_picked_in_meantime(): void
    {
        // 10 ordered, 8 allocated, 0 picked, 5 reduction (Case B, needs 3 from allocation)
        [$order, $adj, $item, $allocation] = $this->createApprovedScenario(10, 8, 8, 0, 5);

        // Warehouse picks 7 units in the meantime! Unpicked capacity = 8 - 7 = 1.
        // But adjustment needs 3 units released!
        $allocation->update([
            'picked_quantity' => 7,
            'status' => AllocationStatus::PICKED,
        ]);
        $item->update(['picked_quantity' => 7]);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/apply"
        );

        $response->assertStatus(409);

        // Verify transaction rolled back completely
        $this->assertEquals(OrderAdjustmentStatus::APPROVED, $adj->fresh()->status);
        $this->assertEquals(1, $order->fresh()->version);
        $this->assertEquals(0, $item->fresh()->cancelled_quantity);
        $this->assertEquals(7, $allocation->fresh()->picked_quantity);
    }

    public function test_cannot_apply_on_ineligible_order_lifecycle(): void
    {
        [$order, $adj, $item] = $this->createApprovedScenario(10, 4, 4, 0, 3);
        $order->update(['status' => OrderStatus::CANCELLED]);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/apply"
        );

        $response->assertStatus(409);
        $this->assertEquals(OrderAdjustmentStatus::APPROVED, $adj->fresh()->status);
    }

    public function test_sequential_adjustments_maintain_accurate_financials(): void
    {
        // Adjustment 1: reduce 2 units
        [$order, $adj1, $item] = $this->createApprovedScenario(10, 4, 4, 0, 2);

        $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj1->id}/apply"
        )->assertRedirect();

        $orderFresh1 = $order->fresh();
        $this->assertEquals(2, $orderFresh1->version);
        $this->assertEquals('200.00', $orderFresh1->subtotal); // 8 * 25
        $this->assertEquals('36.00', $orderFresh1->tax_total); // 8 * 4.50
        $this->assertEquals('236.00', $orderFresh1->grand_total); // 8 * 29.50
        $this->assertEquals('59.00', $orderFresh1->adjustment_total); // 2 * 29.50

        // Adjustment 2: reduce another 3 units
        $adj2 = OrderAdjustment::create([
            'adjustment_number' => 'ADJ-APP-' . uniqid(),
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'order_version_snapshot' => 2,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => $orderFresh1->subtotal,
            'order_tax_total_snapshot' => $orderFresh1->tax_total,
            'order_grand_total_snapshot' => $orderFresh1->grand_total,
            'type' => 'QUANTITY_REDUCTION',
            'status' => OrderAdjustmentStatus::APPROVED,
            'reason_code' => AdjustmentReasonCode::CUSTOMER_REQUEST,
            'notes' => 'Customer requested second reduction.',
            'requested_by' => $this->salesman->id,
            'requested_at' => Carbon::now(),
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => Carbon::now(),
            'projected_subtotal_reduction' => '75.00',
            'projected_tax_reduction' => '13.50',
            'projected_grand_total_reduction' => '88.50',
            'idempotency_key' => 'idem-' . uniqid(),
            'request_fingerprint' => hash('sha256', 'payload-2-' . uniqid()),
        ]);

        OrderAdjustmentItem::create([
            'adjustment_id' => $adj2->id,
            'order_item_id' => $item->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
            'unit_price_snapshot' => '25.00',
            'tax_rate_snapshot' => '18.0000',
            'tax_profile_code_snapshot' => 'TAX-STD',
            'ordered_quantity_snapshot' => 10,
            'cancelled_quantity_snapshot' => 2,
            'fulfillable_quantity_snapshot' => 8,
            'allocated_quantity_snapshot' => 4,
            'unallocated_quantity_snapshot' => 4,
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

        $orderFresh2 = $order->fresh();
        $this->assertEquals(3, $orderFresh2->version);
        $this->assertEquals('125.00', $orderFresh2->subtotal); // 5 * 25
        $this->assertEquals('22.50', $orderFresh2->tax_total); // 5 * 4.50
        $this->assertEquals('147.50', $orderFresh2->grand_total); // 5 * 29.50
        $this->assertEquals('147.50', $orderFresh2->adjustment_total); // 5 * 29.50 total reduction

        $itemFresh = $item->fresh();
        $this->assertEquals(10, $itemFresh->ordered_quantity);
        $this->assertEquals(5, $itemFresh->cancelled_quantity);
        $this->assertEquals(5, $itemFresh->fulfillableQuantity());
    }
}
