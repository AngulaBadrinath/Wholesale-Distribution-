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

class AdminAdjustmentRejectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
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
            'email' => 'admin@wholesale.test',
            'role' => UserRole::ADMIN,
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
        int $orderedQty = 10,
        int $allocatedQty = 0,
        int $pickedQty = 0,
        int $reductionQty = 2
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
            'adjustment_number' => 'ADJ-REJ-' . uniqid(),
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
            'requested_by' => $this->salesman->id,
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

    public function test_admin_can_reject_submitted_adjustment_with_valid_reason(): void
    {
        Log::spy();

        [$order, $adj] = $this->createScenario();

        $reason = 'Customer requested order to proceed without any quantity reductions.';

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            ['reason' => $reason]
        );

        $response->assertRedirect(route('admin.orders.adjustments.review', [
            'order' => $order->id,
            'adjustment' => $adj->id,
        ]));
        $response->assertSessionHas('success');

        $adjFresh = $adj->fresh();
        $this->assertEquals(OrderAdjustmentStatus::REJECTED, $adjFresh->status);
        $this->assertEquals($this->admin->id, $adjFresh->reviewed_by);
        $this->assertEquals($reason, $adjFresh->rejection_reason);
        $this->assertNotNull($adjFresh->reviewed_at);

        // Order adjustment_status resets to NONE
        $this->assertEquals(AdjustmentStatus::NONE, $order->fresh()->adjustment_status);

        // Assert post-commit event logged
        Log::shouldHaveReceived('info')->with(
            'commerce.order_adjustment_event',
            \Mockery::on(function ($data) use ($adj, $reason) {
                return $data['action'] === 'ADJUSTMENT_REJECTED'
                    && $data['adjustment_id'] === $adj->id
                    && $data['rejection_reason'] === $reason
                    && $data['is_emergency_override'] === false;
            })
        )->once();
    }

    public function test_rejection_fails_when_reason_is_missing(): void
    {
        [$order, $adj] = $this->createScenario();

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            []
        );

        $response->assertSessionHasErrors(['reason']);
        $this->assertEquals(OrderAdjustmentStatus::SUBMITTED, $adj->fresh()->status);
    }

    public function test_rejection_fails_when_reason_is_too_short(): void
    {
        [$order, $adj] = $this->createScenario();

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            ['reason' => 'No']
        );

        $response->assertSessionHasErrors(['reason']);
        $this->assertEquals(OrderAdjustmentStatus::SUBMITTED, $adj->fresh()->status);
    }

    public function test_rejection_fails_when_reason_is_too_long(): void
    {
        [$order, $adj] = $this->createScenario();

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            ['reason' => str_repeat('x', 1001)]
        );

        $response->assertSessionHasErrors(['reason']);
        $this->assertEquals(OrderAdjustmentStatus::SUBMITTED, $adj->fresh()->status);
    }

    public function test_rejection_fails_when_adjustment_is_already_rejected(): void
    {
        [$order, $adj] = $this->createScenario();

        // First rejection
        $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            ['reason' => 'Initial valid rejection reason.']
        )->assertRedirect();

        // Second rejection attempt MUST return 409 Conflict
        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            ['reason' => 'Duplicate rejection attempt.']
        );

        $response->assertStatus(409);
    }

    public function test_rejection_fails_when_adjustment_is_already_approved(): void
    {
        [$order, $adj] = $this->createScenario();

        // Approve it first
        $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve"
        )->assertRedirect();

        // Attempting to reject an approved adjustment MUST return 409 Conflict
        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            ['reason' => 'Attempting to reject approved request.']
        );

        $response->assertStatus(409);
        $this->assertEquals(OrderAdjustmentStatus::APPROVED, $adj->fresh()->status);
    }

    public function test_rejection_fails_when_adjustment_is_cancelled(): void
    {
        [$order, $adj] = $this->createScenario();

        // Cancel the adjustment directly
        $adj->status = OrderAdjustmentStatus::CANCELLED;
        $adj->save();

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            ['reason' => 'Attempting to reject cancelled request.']
        );

        $response->assertStatus(409);
    }

    public function test_rejection_succeeds_even_when_request_is_stale_or_conflicted(): void
    {
        // 10 ordered, 8 allocated, 7 picked. Reduction is 5 => Encroaches on picked stock!
        [$order, $adj, $item] = $this->createScenario(10, 8, 7, 5);

        // Increment order version to also make it stale
        $order->version = 2;
        $order->save();

        // Approval would fail (409), but REJECTION MUST SUCCEED!
        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            ['reason' => 'Rejected because requested reduction encroaches on warehouse picked stock.']
        );

        $response->assertRedirect();
        $this->assertEquals(OrderAdjustmentStatus::REJECTED, $adj->fresh()->status);
        $this->assertEquals(AdjustmentStatus::NONE, $order->fresh()->adjustment_status);
    }

    public function test_rejection_preserves_prior_applied_adjustment_status(): void
    {
        [$order, $adj] = $this->createScenario();

        // Create an earlier adjustment with status APPLIED
        OrderAdjustment::create([
            'adjustment_number' => 'ADJ-PREV-APPLIED',
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'order_version_snapshot' => 1,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => '100.00',
            'order_tax_total_snapshot' => '18.00',
            'order_grand_total_snapshot' => '118.00',
            'type' => 'QUANTITY_REDUCTION',
            'status' => OrderAdjustmentStatus::APPLIED,
            'reason_code' => AdjustmentReasonCode::CUSTOMER_REQUEST,
            'notes' => 'Earlier applied adjustment notes.',
            'requested_by' => $this->salesman->id,
            'requested_at' => Carbon::now()->subDays(2),
            'applied_at' => Carbon::now()->subDays(1),
            'projected_subtotal_reduction' => '25.00',
            'projected_tax_reduction' => '4.50',
            'projected_grand_total_reduction' => '29.50',
            'idempotency_key' => 'idem-prior-applied',
            'request_fingerprint' => hash('sha256', 'prior-applied'),
        ]);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            ['reason' => 'Rejecting second request; preserving prior applied status.']
        );

        $response->assertRedirect();
        // Since an earlier adjustment was APPLIED, order adjustment_status must reset to APPLIED (not NONE)
        $this->assertEquals(AdjustmentStatus::APPLIED, $order->fresh()->adjustment_status);
    }

    public function test_rejection_strictly_does_not_mutate_quantities_allocations_or_financials(): void
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

        $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            ['reason' => 'Valid rejection reason testing no mutations.']
        )->assertRedirect();

        // Financials unchanged
        $orderFresh = $order->fresh();
        $this->assertSame($originalSubtotal, (string) $orderFresh->subtotal);
        $this->assertSame($originalTaxTotal, (string) $orderFresh->tax_total);
        $this->assertSame($originalGrandTotal, (string) $orderFresh->grand_total);
        $this->assertSame($originalAdjustmentTotal, (string) $orderFresh->adjustment_total);

        // Quantities unchanged
        $itemFresh = $item->fresh();
        $this->assertSame($originalOrdered, (int) $itemFresh->ordered_quantity);
        $this->assertSame($originalCancelled, (int) $itemFresh->cancelled_quantity);
        $this->assertSame($originalFulfillable, $itemFresh->fulfillableQuantity());
        $this->assertSame($originalReserved, (int) $itemFresh->reserved_quantity);
    }

    public function test_rejection_fails_with_404_on_idor_mismatch(): void
    {
        [$order1, $adj1] = $this->createScenario();
        [$order2, $adj2] = $this->createScenario();

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order2->id}/adjustments/{$adj1->id}/reject",
            ['reason' => 'Valid rejection reason.']
        );

        $response->assertStatus(404);
        $this->assertEquals(OrderAdjustmentStatus::SUBMITTED, $adj1->fresh()->status);
    }
}
