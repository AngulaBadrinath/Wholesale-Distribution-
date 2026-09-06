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
use Tests\TestCase;

class AdminAdjustmentApplicationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
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
    }

    public function test_super_admin_can_apply_adjustment(): void
    {
        $response = $this->actingAs($this->superAdmin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/apply"
        );

        $response->assertRedirect();
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $this->adjustment->fresh()->status);
    }

    public function test_admin_can_apply_adjustment(): void
    {
        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/apply"
        );

        $response->assertRedirect();
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $this->adjustment->fresh()->status);
    }

    public function test_salesman_cannot_apply_adjustment(): void
    {
        $response = $this->actingAs($this->salesman)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/apply"
        );

        $response->assertForbidden();
        $this->assertEquals(OrderAdjustmentStatus::APPROVED, $this->adjustment->fresh()->status);
    }

    public function test_warehouse_manager_cannot_apply_adjustment(): void
    {
        $response = $this->actingAs($this->warehouse)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/apply"
        );

        $response->assertForbidden();
        $this->assertEquals(OrderAdjustmentStatus::APPROVED, $this->adjustment->fresh()->status);
    }

    public function test_delivery_partner_cannot_apply_adjustment(): void
    {
        $response = $this->actingAs($this->delivery)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/apply"
        );

        $response->assertForbidden();
        $this->assertEquals(OrderAdjustmentStatus::APPROVED, $this->adjustment->fresh()->status);
    }

    public function test_accountant_cannot_apply_adjustment(): void
    {
        $response = $this->actingAs($this->accountant)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/apply"
        );

        $response->assertForbidden();
        $this->assertEquals(OrderAdjustmentStatus::APPROVED, $this->adjustment->fresh()->status);
    }

    public function test_inactive_admin_cannot_apply_adjustment(): void
    {
        $response = $this->actingAs($this->inactiveAdmin)->post(
            "/admin/orders/{$this->order->id}/adjustments/{$this->adjustment->id}/apply"
        );

        $response->assertRedirect('/login');
        $this->assertEquals(OrderAdjustmentStatus::APPROVED, $this->adjustment->fresh()->status);
    }

    public function test_idor_mismatched_order_and_adjustment_fails(): void
    {
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
            'idempotency_key' => 'idemp-ord-other-' . uniqid(),
            'subtotal' => '100.00',
            'tax_total' => '18.00',
            'adjustment_total' => '0.00',
            'grand_total' => '118.00',
            'version' => 1,
            'submitted_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$anotherOrder->id}/adjustments/{$this->adjustment->id}/apply"
        );

        // IDOR check fails closed with 404 or 403
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }
}
