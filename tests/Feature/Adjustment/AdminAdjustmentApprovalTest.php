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

class AdminAdjustmentApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $adminB;
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

        $this->adminB = User::factory()->create([
            'name' => 'Bob Admin',
            'email' => 'admin.b@wholesale.test',
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

    protected function createScenario(
        int $orderedQty,
        int $allocatedQty,
        int $pickedQty,
        int $reductionQty,
        ?User $requester = null
    ): array {
        $requester = $requester ?? $this->salesman;

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
            'reserved_quantity' => 0,
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
                'allocation_number' => 'ALC-' . uniqid(),
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'product_id' => $this->product->id,
                'allocated_quantity' => $allocatedQty,
                'reserved_quantity' => $allocatedQty,
                'picked_quantity' => $pickedQty,
                'dispatched_quantity' => 0,
                'delivered_quantity' => 0,
                'returned_quantity' => 0,
                'status' => $pickedQty > 0 ? AllocationStatus::PICKED : AllocationStatus::ALLOCATED,
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
            'status' => OrderAdjustmentStatus::SUBMITTED,
            'reason_code' => AdjustmentReasonCode::CUSTOMER_REQUEST,
            'notes' => 'Customer requested reduction.',
            'requested_by' => $requester->id,
            'requested_at' => Carbon::now()->subHour(),
            'projected_subtotal_reduction' => bcmul('25.00', (string) $reductionQty, 2),
            'projected_tax_reduction' => bcmul('4.50', (string) $reductionQty, 2),
            'projected_grand_total_reduction' => bcmul('29.50', (string) $reductionQty, 2),
            'idempotency_key' => 'idem-' . uniqid(),
            'request_fingerprint' => hash('sha256', 'payload-' . uniqid()),
        ]);

        OrderAdjustmentItem::create([
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

        return [$order, $adj, $item, $allocation];
    }

    public function test_admin_can_approve_valid_case_a_adjustment(): void
    {
        Log::spy();

        // 10 ordered, 4 allocated, 0 picked, 3 reduction => unallocated is 6, reduction (3) <= 6 (Case A)
        [$order, $adj, $item] = $this->createScenario(10, 4, 0, 3);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve"
        );

        $response->assertRedirect(route('admin.orders.adjustments.review', [
            'order' => $order->id,
            'adjustment' => $adj->id,
        ]));
        $response->assertSessionHas('success');

        $adjFresh = $adj->fresh();
        $this->assertEquals(OrderAdjustmentStatus::APPROVED, $adjFresh->status);
        $this->assertEquals($this->admin->id, $adjFresh->reviewed_by);
        $this->assertNotNull($adjFresh->reviewed_at);

        // Order adjustment_status remains REQUESTED pending application
        $this->assertEquals(AdjustmentStatus::REQUESTED, $order->fresh()->adjustment_status);

        // Assert post-commit event logged
        Log::shouldHaveReceived('info')->with(
            'commerce.order_adjustment_event',
            \Mockery::on(function ($data) use ($adj) {
                return $data['action'] === 'ADJUSTMENT_APPROVED'
                    && $data['adjustment_id'] === $adj->id
                    && $data['case_type'] === 'CASE_A'
                    && $data['affected_allocation_quantity'] === 0
                    && $data['is_emergency_override'] === false;
            })
        )->once();
    }

    public function test_admin_can_approve_valid_case_b_adjustment_with_acknowledgment(): void
    {
        Log::spy();

        // 10 ordered, 8 allocated, 0 picked, 5 reduction => unallocated is 2, reduction (5) > 2 (Case B: 3 affected)
        [$order, $adj, $item] = $this->createScenario(10, 8, 0, 5);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve",
            ['acknowledge_allocation_impact' => true]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $adjFresh = $adj->fresh();
        $this->assertEquals(OrderAdjustmentStatus::APPROVED, $adjFresh->status);
        $this->assertEquals($this->admin->id, $adjFresh->reviewed_by);

        Log::shouldHaveReceived('info')->with(
            'commerce.order_adjustment_event',
            \Mockery::on(function ($data) use ($adj) {
                return $data['action'] === 'ADJUSTMENT_APPROVED'
                    && $data['adjustment_id'] === $adj->id
                    && $data['case_type'] === 'CASE_B'
                    && $data['affected_allocation_quantity'] === 3;
            })
        )->once();
    }

    public function test_case_b_approval_fails_without_acknowledgment(): void
    {
        // Case B: 10 ordered, 8 allocated, 5 reduction => 3 affected
        [$order, $adj, $item] = $this->createScenario(10, 8, 0, 5);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve",
            ['acknowledge_allocation_impact' => false]
        );

        $response->assertSessionHasErrors(['acknowledge_allocation_impact']);
        $this->assertEquals(OrderAdjustmentStatus::SUBMITTED, $adj->fresh()->status);
    }

    public function test_approval_fails_when_order_version_is_stale(): void
    {
        [$order, $adj] = $this->createScenario(10, 0, 0, 2);

        // Mutate order version to simulate concurrent modification
        $order->version = 2;
        $order->save();

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve"
        );

        $response->assertStatus(409);
        $this->assertEquals(OrderAdjustmentStatus::SUBMITTED, $adj->fresh()->status);
    }

    public function test_approval_fails_when_fulfillable_quantity_conflicts(): void
    {
        [$order, $adj, $item] = $this->createScenario(10, 0, 0, 5);

        // Simulate upstream cancellation reducing fulfillable to 3 (requested reduction is 5)
        $item->cancelled_quantity = 7; // fulfillable is now 3
        $item->save();

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve"
        );

        $response->assertStatus(409);
        $this->assertEquals(OrderAdjustmentStatus::SUBMITTED, $adj->fresh()->status);
    }

    public function test_approval_fails_when_reduction_encroaches_on_picked_stock(): void
    {
        // 10 ordered, 8 allocated, 7 picked.
        // unallocated = 2. requested reduction = 5.
        // affected allocation = 3.
        // unpicked allocated = 8 - 7 = 1.
        // affected (3) > unpicked (1) => Encroaches on picked stock!
        [$order, $adj, $item] = $this->createScenario(10, 8, 7, 5);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve",
            ['acknowledge_allocation_impact' => true]
        );

        $response->assertStatus(409);
        $this->assertEquals(OrderAdjustmentStatus::SUBMITTED, $adj->fresh()->status);
    }

    public function test_approval_fails_when_order_is_in_ineligible_lifecycle(): void
    {
        [$order, $adj] = $this->createScenario(10, 0, 0, 2);

        $order->status = OrderStatus::COMPLETED;
        $order->save();

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve"
        );

        $response->assertStatus(409);
        $this->assertEquals(OrderAdjustmentStatus::SUBMITTED, $adj->fresh()->status);
    }

    public function test_duplicate_approval_attempt_returns_409_conflict(): void
    {
        [$order, $adj] = $this->createScenario(10, 0, 0, 2);

        // First approval succeeds
        $first = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve"
        );
        $first->assertRedirect();
        $this->assertEquals(OrderAdjustmentStatus::APPROVED, $adj->fresh()->status);

        // Second approval attempt MUST receive HTTP 409 Conflict (deterministic duplicate decision guard)
        $second = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve"
        );
        $second->assertStatus(409);
    }

    public function test_approval_strictly_does_not_mutate_quantities_allocations_or_financials(): void
    {
        [$order, $adj, $item, $allocation] = $this->createScenario(10, 5, 0, 2);

        $originalSubtotal = (string) $order->subtotal;
        $originalTaxTotal = (string) $order->tax_total;
        $originalGrandTotal = (string) $order->grand_total;
        $originalAdjustmentTotal = (string) $order->adjustment_total;

        $originalOrdered = (int) $item->ordered_quantity;
        $originalCancelled = (int) $item->cancelled_quantity;
        $originalFulfillable = $item->fulfillableQuantity();
        $originalReserved = (int) $item->reserved_quantity;

        $originalAllocated = (int) $allocation->allocated_quantity;
        $originalAllocStatus = $allocation->status;

        $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve"
        )->assertRedirect();

        // 1. Assert Order Financials Unchanged
        $orderFresh = $order->fresh();
        $this->assertSame($originalSubtotal, (string) $orderFresh->subtotal);
        $this->assertSame($originalTaxTotal, (string) $orderFresh->tax_total);
        $this->assertSame($originalGrandTotal, (string) $orderFresh->grand_total);
        $this->assertSame($originalAdjustmentTotal, (string) $orderFresh->adjustment_total);

        // 2. Assert Order Item Quantities Unchanged
        $itemFresh = $item->fresh();
        $this->assertSame($originalOrdered, (int) $itemFresh->ordered_quantity);
        $this->assertSame($originalCancelled, (int) $itemFresh->cancelled_quantity);
        $this->assertSame($originalFulfillable, $itemFresh->fulfillableQuantity());
        $this->assertSame($originalReserved, (int) $itemFresh->reserved_quantity);

        // 3. Assert Allocation Record Unchanged
        $allocationFresh = $allocation->fresh();
        $this->assertSame($originalAllocated, (int) $allocationFresh->allocated_quantity);
        $this->assertSame($originalAllocStatus, $allocationFresh->status);
    }

    public function test_approval_fails_with_404_on_idor_mismatch(): void
    {
        [$order1, $adj1] = $this->createScenario(10, 0, 0, 2);
        [$order2, $adj2] = $this->createScenario(10, 0, 0, 2);

        // Try approving adj1 with order2 route binding
        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order2->id}/adjustments/{$adj1->id}/approve"
        );

        $response->assertStatus(404);
        $this->assertEquals(OrderAdjustmentStatus::SUBMITTED, $adj1->fresh()->status);
    }
}
