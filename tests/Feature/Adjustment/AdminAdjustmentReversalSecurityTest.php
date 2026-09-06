<?php

namespace Tests\Feature\Adjustment;

use App\Enums\AccountStatus;
use App\Enums\AdjustmentReasonCode;
use App\Enums\AdjustmentStatus;
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
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AdminAdjustmentReversalSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
    protected User $anotherAdmin;
    protected User $salesman;
    protected User $warehouse;
    protected User $delivery;
    protected User $accountant;
    protected User $inactiveAdmin;

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

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->anotherAdmin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->warehouse = User::factory()->create([
            'role' => UserRole::WAREHOUSE_MANAGER,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->delivery = User::factory()->create([
            'role' => UserRole::DELIVERY_PARTNER,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->inactiveAdmin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::DISABLED,
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
            'adjustment_number' => 'ADJ-APP-' . uniqid(),
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
            'notes' => 'Customer requested reduction.',
            'requested_by' => $this->salesman->id,
            'requested_at' => Carbon::now()->subHour(),
            'reviewed_by' => $this->admin->id,
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

        // Apply it so it is in APPLIED state
        $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/apply"
        )->assertRedirect();
        $this->adjustment->refresh();
        $this->order->refresh();
        $this->item->refresh();
    }

    public function test_super_admin_can_reverse_adjustment(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
            ['reason' => 'Super admin reversing adjustment for customer request.']
        );

        $response->assertRedirect();
        $this->assertEquals(OrderAdjustmentStatus::REVERSED, $this->adjustment->fresh()->status);
    }

    public function test_admin_can_reverse_adjustment_requested_by_salesman(): void
    {
        $response = $this->actingAs($this->anotherAdmin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
            ['reason' => 'Another admin reversing adjustment safely.']
        );

        $response->assertRedirect();
        $this->assertEquals(OrderAdjustmentStatus::REVERSED, $this->adjustment->fresh()->status);
    }

    public function test_maker_checker_admin_cannot_reverse_adjustment_they_personally_requested(): void
    {
        // Create an adjustment requested by $this->admin
        $adj = OrderAdjustment::create([
            'adjustment_number' => 'ADJ-MC-' . uniqid(),
            'order_id' => $this->order->id,
            'order_number_snapshot' => $this->order->order_number,
            'order_version_snapshot' => $this->order->version,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => $this->order->subtotal,
            'order_tax_total_snapshot' => $this->order->tax_total,
            'order_grand_total_snapshot' => $this->order->grand_total,
            'type' => 'QUANTITY_REDUCTION',
            'status' => OrderAdjustmentStatus::APPROVED,
            'reason_code' => AdjustmentReasonCode::PRICING_DISPUTE,
            'notes' => 'Requested by admin',
            'requested_by' => $this->admin->id,
            'requested_at' => Carbon::now()->subHour(),
            'reviewed_by' => $this->anotherAdmin->id,
            'reviewed_at' => Carbon::now()->subMinutes(20),
            'projected_subtotal_reduction' => '25.00',
            'projected_tax_reduction' => '4.50',
            'projected_grand_total_reduction' => '29.50',
            'idempotency_key' => 'idem-mc-' . uniqid(),
            'request_fingerprint' => 'fp-mc-' . uniqid(),
        ]);

        OrderAdjustmentItem::create([
            'adjustment_id' => $adj->id,
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

        // Apply with anotherAdmin
        $this->actingAs($this->anotherAdmin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$adj->id}/apply"
        )->assertRedirect();

        // Now admin (who requested it) tries to reverse it -> 403 Forbidden
        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$adj->id}/reverse",
            ['reason' => 'Admin trying to reverse their own requested adjustment.']
        );

        $response->assertStatus(403);
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $adj->fresh()->status);
    }

    public function test_maker_checker_super_admin_cannot_reverse_own_adjustment_without_emergency_override(): void
    {
        // Adjustment requested by superAdmin
        $adj = OrderAdjustment::create([
            'adjustment_number' => 'ADJ-SA-MC-' . uniqid(),
            'order_id' => $this->order->id,
            'order_number_snapshot' => $this->order->order_number,
            'order_version_snapshot' => $this->order->version,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => $this->order->subtotal,
            'order_tax_total_snapshot' => $this->order->tax_total,
            'order_grand_total_snapshot' => $this->order->grand_total,
            'type' => 'QUANTITY_REDUCTION',
            'status' => OrderAdjustmentStatus::APPROVED,
            'reason_code' => AdjustmentReasonCode::PRICING_DISPUTE,
            'notes' => 'Requested by super admin',
            'requested_by' => $this->superAdmin->id,
            'requested_at' => Carbon::now()->subHour(),
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => Carbon::now()->subMinutes(20),
            'projected_subtotal_reduction' => '25.00',
            'projected_tax_reduction' => '4.50',
            'projected_grand_total_reduction' => '29.50',
            'idempotency_key' => 'idem-samc-' . uniqid(),
            'request_fingerprint' => 'fp-samc-' . uniqid(),
        ]);

        OrderAdjustmentItem::create([
            'adjustment_id' => $adj->id,
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
            "/admin/orders/{$this->order->id}/adjustments/{$adj->id}/apply"
        )->assertRedirect();

        // Attempt without emergency_override_reason -> Validation Error (302 with session error)
        $response = $this->actingAs($this->superAdmin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$adj->id}/reverse",
            ['reason' => 'Super admin trying to self-reverse without override.']
        );

        $response->assertSessionHasErrors(['emergency_override_reason']);
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $adj->fresh()->status);
    }

    public function test_maker_checker_super_admin_can_self_reverse_with_valid_emergency_override(): void
    {
        $adj = OrderAdjustment::create([
            'adjustment_number' => 'ADJ-SA-OVR-' . uniqid(),
            'order_id' => $this->order->id,
            'order_number_snapshot' => $this->order->order_number,
            'order_version_snapshot' => $this->order->version,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => $this->order->subtotal,
            'order_tax_total_snapshot' => $this->order->tax_total,
            'order_grand_total_snapshot' => $this->order->grand_total,
            'type' => 'QUANTITY_REDUCTION',
            'status' => OrderAdjustmentStatus::APPROVED,
            'reason_code' => AdjustmentReasonCode::PRICING_DISPUTE,
            'notes' => 'Requested by super admin for override test',
            'requested_by' => $this->superAdmin->id,
            'requested_at' => Carbon::now()->subHour(),
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => Carbon::now()->subMinutes(20),
            'projected_subtotal_reduction' => '25.00',
            'projected_tax_reduction' => '4.50',
            'projected_grand_total_reduction' => '29.50',
            'idempotency_key' => 'idem-saovr-' . uniqid(),
            'request_fingerprint' => 'fp-saovr-' . uniqid(),
        ]);

        OrderAdjustmentItem::create([
            'adjustment_id' => $adj->id,
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
            "/admin/orders/{$this->order->id}/adjustments/{$adj->id}/apply"
        )->assertRedirect();

        Log::spy();

        // Attempt WITH valid emergency override reason
        $response = $this->actingAs($this->superAdmin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$adj->id}/reverse",
            [
                'reason' => 'Super admin self-reversing adjustment under emergency procedure.',
                'emergency_override_reason' => 'Emergency authorization: sole administrator available during warehouse closure.',
            ]
        );

        $response->assertRedirect();
        $this->assertEquals(OrderAdjustmentStatus::REVERSED, $adj->fresh()->status);
        Log::shouldHaveReceived('info')->with('commerce.order_adjustment_event', \Mockery::on(function ($data) {
            return ($data['action'] ?? null) === 'ADJUSTMENT_EMERGENCY_OVERRIDE';
        }));
    }

    public function test_emergency_override_reason_must_be_at_least_10_characters(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
            [
                'reason' => 'Valid reason for reversal here.',
                'emergency_override_reason' => 'Short', // < 10 characters
            ]
        );

        $response->assertSessionHasErrors(['emergency_override_reason']);
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $this->adjustment->fresh()->status);
    }

    public function test_accountant_cannot_reverse_adjustment(): void
    {
        $response = $this->actingAs($this->accountant)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
            ['reason' => 'Accountant attempting reversal.']
        );

        $response->assertStatus(403);
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $this->adjustment->fresh()->status);
    }

    public function test_salesman_cannot_reverse_adjustment(): void
    {
        $response = $this->actingAs($this->salesman)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
            ['reason' => 'Salesman attempting reversal.']
        );

        $response->assertStatus(403);
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $this->adjustment->fresh()->status);
    }

    public function test_warehouse_manager_cannot_reverse_adjustment(): void
    {
        $response = $this->actingAs($this->warehouse)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
            ['reason' => 'Warehouse manager attempting reversal.']
        );

        $response->assertStatus(403);
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $this->adjustment->fresh()->status);
    }

    public function test_delivery_partner_cannot_reverse_adjustment(): void
    {
        $response = $this->actingAs($this->delivery)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
            ['reason' => 'Delivery partner attempting reversal.']
        );

        $response->assertStatus(403);
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $this->adjustment->fresh()->status);
    }

    public function test_inactive_admin_cannot_reverse_adjustment(): void
    {
        $response = $this->actingAs($this->inactiveAdmin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/reverse",
            ['reason' => 'Disabled admin attempting reversal.']
        );

        $response->assertRedirect('/login');
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $this->adjustment->fresh()->status);
    }

    public function test_anti_idor_mismatched_order_in_route_returns_404(): void
    {
        // Create another order
        $anotherOrder = Order::create([
            'order_number' => 'ORD-OTHER-' . uniqid(),
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

        // Attempt to reverse adjustment with wrong order ID in route
        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$anotherOrder->id}/adjustments/{$this->adjustment->id}/reverse",
            ['reason' => 'IDOR attempt with wrong order id in route.']
        );

        $response->assertStatus(404);
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $this->adjustment->fresh()->status);
    }
}
