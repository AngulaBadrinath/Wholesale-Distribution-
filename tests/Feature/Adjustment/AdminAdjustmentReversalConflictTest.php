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

class AdminAdjustmentReversalConflictTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $reviewerAdmin;
    protected User $salesman;

    protected Customer $customer;
    protected Category $category;
    protected TaxProfile $taxProfile;
    protected Product $product;
    protected Order $order;
    protected OrderItem $item;
    protected OrderAdjustment $adjustment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->reviewerAdmin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
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

        $this->order = Order::create([
            'order_number' => 'ORD-CNC-' . uniqid(),
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
            'submitted_at' => Carbon::now()->subHours(2),
        ]);

        $this->item = OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
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

        $this->adjustment = OrderAdjustment::create([
            'adjustment_number' => 'ADJ-CNC-' . uniqid(),
            'order_id' => $this->order->id,
            'order_number_snapshot' => $this->order->order_number,
            'order_version_snapshot' => 1,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => '250.00',
            'order_tax_total_snapshot' => '45.00',
            'order_grand_total_snapshot' => '295.00',
            'type' => 'QUANTITY_REDUCTION',
            'status' => OrderAdjustmentStatus::APPROVED,
            'reason_code' => AdjustmentReasonCode::CUSTOMER_REQUEST,
            'notes' => 'Conflict test adjustment.',
            'requested_by' => $this->salesman->id,
            'requested_at' => Carbon::now()->subHour(),
            'reviewed_by' => $this->reviewerAdmin->id,
            'reviewed_at' => Carbon::now()->subMinutes(30),
            'projected_subtotal_reduction' => '50.00',
            'projected_tax_reduction' => '9.00',
            'projected_grand_total_reduction' => '59.00',
            'idempotency_key' => 'idem-' . uniqid(),
            'request_fingerprint' => hash('sha256', 'payload-' . uniqid()),
        ]);

        OrderAdjustmentItem::create([
            'adjustment_id' => $this->adjustment->id,
            'order_item_id' => $this->item->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
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
    }

    public function test_duplicate_reversal_rejected_with_409(): void
    {
        // Apply adjustment
        $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/apply"
        )->assertRedirect();

        // First reversal succeeds
        $res1 = $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
            ['reason' => 'First valid reversal request.']
        );
        $res1->assertRedirect();
        $this->assertEquals(OrderAdjustmentStatus::REVERSED, $this->adjustment->fresh()->status);

        // Second reversal fails with 409
        $res2 = $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
            ['reason' => 'Duplicate reversal attempt should fail.']
        );
        $res2->assertStatus(409);
    }

    public function test_cannot_reverse_adjustment_with_invalid_status(): void
    {
        // Adjustment is currently APPROVED, not APPLIED
        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
            ['reason' => 'Attempting to reverse an approved (not applied) adjustment.']
        );

        $response->assertStatus(409);

        // Test SUBMITTED status
        $this->adjustment->update(['status' => OrderAdjustmentStatus::SUBMITTED]);
        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
            ['reason' => 'Attempting to reverse a submitted adjustment.']
        );
        $response->assertStatus(409);

        // Test REJECTED status
        $this->adjustment->update(['status' => OrderAdjustmentStatus::REJECTED]);
        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
            ['reason' => 'Attempting to reverse a rejected adjustment.']
        );
        $response->assertStatus(409);

        // Test CANCELLED status
        $this->adjustment->update(['status' => OrderAdjustmentStatus::CANCELLED]);
        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
            ['reason' => 'Attempting to reverse a cancelled adjustment.']
        );
        $response->assertStatus(409);
    }

    public function test_cannot_reverse_when_order_is_cancelled(): void
    {
        // Apply adjustment
        $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/apply"
        )->assertRedirect();

        // Transition order to CANCELLED
        $this->order->update(['status' => OrderStatus::CANCELLED]);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
            ['reason' => 'Reversal on cancelled order should fail.']
        );

        $response->assertStatus(409);
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $this->adjustment->fresh()->status);
    }

    public function test_cannot_reverse_when_order_is_completed(): void
    {
        // Apply adjustment
        $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/apply"
        )->assertRedirect();

        // Transition order to COMPLETED
        $this->order->update(['status' => OrderStatus::COMPLETED]);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
            ['reason' => 'Reversal on completed order should fail.']
        );

        $response->assertStatus(409);
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $this->adjustment->fresh()->status);
    }

    public function test_cannot_reverse_when_fulfillment_has_progressed(): void
    {
        // Apply adjustment
        $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/apply"
        )->assertRedirect();

        foreach ([FulfillmentStatus::PACKED, FulfillmentStatus::DISPATCHED, FulfillmentStatus::DELIVERED] as $stage) {
            $this->order->update(['fulfillment_status' => $stage]);

            $response = $this->actingAs($this->admin)->post(
                "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
                ['reason' => "Reversal while fulfillment is {$stage->value} should fail."]
            );

            $response->assertStatus(409);
            $this->assertEquals(OrderAdjustmentStatus::APPLIED, $this->adjustment->fresh()->status);
        }
    }

    public function test_lifo_out_of_order_reversal_rejected_with_409(): void
    {
        // Apply adjustment 1
        $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/apply"
        )->assertRedirect();

        // Create and apply adjustment 2
        $adj2 = OrderAdjustment::create([
            'adjustment_number' => 'ADJ-LIFO-ORDER-2',
            'order_id' => $this->order->id,
            'order_number_snapshot' => $this->order->order_number,
            'order_version_snapshot' => 2,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => '200.00',
            'order_tax_total_snapshot' => '36.00',
            'order_grand_total_snapshot' => '236.00',
            'type' => 'QUANTITY_REDUCTION',
            'status' => OrderAdjustmentStatus::APPROVED,
            'reason_code' => AdjustmentReasonCode::CUSTOMER_REQUEST,
            'notes' => 'Second adjustment',
            'requested_by' => $this->salesman->id,
            'requested_at' => Carbon::now(),
            'reviewed_by' => $this->reviewerAdmin->id,
            'reviewed_at' => Carbon::now(),
            'projected_subtotal_reduction' => '25.00',
            'projected_tax_reduction' => '4.50',
            'projected_grand_total_reduction' => '29.50',
            'idempotency_key' => 'idem-lifo2',
            'request_fingerprint' => 'fp-lifo2',
        ]);

        OrderAdjustmentItem::create([
            'adjustment_id' => $adj2->id,
            'order_item_id' => $this->item->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
            'unit_price_snapshot' => '25.00',
            'tax_rate_snapshot' => '18.0000',
            'tax_profile_code_snapshot' => 'TAX-STD',
            'ordered_quantity_snapshot' => 10,
            'cancelled_quantity_snapshot' => 2,
            'fulfillable_quantity_snapshot' => 8,
            'allocated_quantity_snapshot' => 0,
            'unallocated_quantity_snapshot' => 8,
            'requested_quantity_reduction' => 1,
            'projected_fulfillable_quantity' => 7,
            'projected_cancelled_quantity' => 3,
            'affected_allocation_quantity' => 0,
            'projected_taxable_amount_reduction' => '25.00',
            'projected_tax_amount_reduction' => '4.50',
            'projected_line_total_reduction' => '29.50',
        ]);

        $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$adj2->id}/apply"
        )->assertRedirect();

        // Now both are APPLIED. Attempt to reverse Adjustment 1 (out of order):
        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
            ['reason' => 'Attempting out-of-order reversal of older adjustment.']
        );

        $response->assertStatus(409);
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $this->adjustment->fresh()->status);
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $adj2->fresh()->status);
    }

    public function test_all_or_nothing_atomicity_rollback_on_failure(): void
    {
        // Apply adjustment first
        $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/apply"
        )->assertRedirect();

        $this->order->refresh();
        $this->item->refresh();
        $this->adjustment->refresh();

        $baselineVersion = $this->order->version;
        $baselineCancelled = $this->item->cancelled_quantity;
        $baselineGrandTotal = $this->order->grand_total;

        // Force a data corruption / conflict: manually set item cancelled_quantity to 0
        // So during reversal, when it validates if cancelled_quantity >= requested_reduction (2),
        // it will detect 0 < 2 and abort with 409 Conflict.
        $this->item->update(['cancelled_quantity' => 0]);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
            ['reason' => 'Rollback test attempt that should abort.']
        );

        $response->assertStatus(409);

        // Verify total rollback
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $this->adjustment->fresh()->status);
        $this->assertNull($this->adjustment->fresh()->reversed_at);
        $this->assertNull($this->adjustment->fresh()->reversed_by);
        $this->assertEquals($baselineVersion, $this->order->fresh()->version);
        $this->assertEquals($baselineGrandTotal, $this->order->fresh()->grand_total);
    }
}
