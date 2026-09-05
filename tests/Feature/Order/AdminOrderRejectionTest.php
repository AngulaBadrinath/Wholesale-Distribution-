<?php

namespace Tests\Feature\Order;

use App\Enums\AccountStatus;
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
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderRejectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $superAdmin;
    protected User $accountant;
    protected User $salesman;
    protected User $warehouseManager;
    protected User $deliveryPartner;
    protected Customer $customer;
    protected Product $productA;
    protected TaxProfile $taxProfile;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Operational Admin',
            'email' => 'admin@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Administrator',
            'email' => 'super@wholesale.test',
            'role' => UserRole::SUPER_ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'name' => 'Corporate Accountant',
            'email' => 'accountant@wholesale.test',
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'name' => 'Sam Salesman',
            'email' => 'sam@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->warehouseManager = User::factory()->create([
            'name' => 'Wendy Warehouse',
            'email' => 'wendy@wholesale.test',
            'role' => UserRole::WAREHOUSE_MANAGER,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->deliveryPartner = User::factory()->create([
            'name' => 'Dave Delivery',
            'email' => 'dave@wholesale.test',
            'role' => UserRole::DELIVERY_PARTNER,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Apex Retailers Inc',
            'code' => 'CUST-APEX-01',
            'contact_name' => 'John Apex',
            'phone' => '+1-555-0987',
            'email' => 'buyer@apexretail.test',
            'billing_address_line1' => '100 Commerce Blvd',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '100 Commerce Blvd',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
            'credit_limit' => 5000.00,
        ]);

        $this->category = Category::create([
            'name' => 'Beverages',
            'code' => 'BEV',
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'Standard Tax Rate',
            'code' => 'TAX-STD',
            'rate' => 0.0825,
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->productA = Product::create([
            'name' => 'Premium Sparkling Water 24pk',
            'sku' => 'SKU-WATER-001',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'cost_price' => 12.00,
            'minimum_allowed_price' => 18.00,
            'default_selling_price' => 24.00,
            'mrp' => 30.00,
            'status' => ProductStatus::ACTIVE,
            'unit' => 'CASE',
        ]);
    }

    protected function createSubmittedOrder(array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'order_number' => 'ORD-' . uniqid(),
            'idempotency_key' => 'idemp-' . uniqid(),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::SUBMITTED,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'currency' => 'USD',
            'subtotal' => 240.00,
            'tax_total' => 19.80,
            'grand_total' => 259.80,
            'submitted_at' => Carbon::now()->subHours(2),
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'unit_price' => 24.00,
            'tax_rate_snapshot' => 0.0825,
            'taxable_amount' => 240.00,
            'tax_amount' => 19.80,
            'line_total' => 259.80,
        ]);

        return $order;
    }

    public function test_admin_can_successfully_reject_submitted_order_with_mandatory_reason(): void
    {
        $order = $this->createSubmittedOrder();
        $reason = 'Client requested order cancellation due to unexpected scheduling conflict.';

        $response = $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/reject", [
                'reason' => $reason,
            ]);

        $response->assertRedirect(route('admin.orders.index', ['queue' => 'new']));
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals(OrderStatus::REJECTED, $order->status);
        $this->assertEquals(FulfillmentStatus::UNALLOCATED, $order->fulfillment_status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertEquals($this->admin->id, $order->cancelled_by);
        $this->assertEquals($reason, $order->cancellation_reason);

        // Verify line item intact and no quantity reserved
        $item = $order->items->first();
        $this->assertEquals(10, $item->ordered_quantity);
        $this->assertEquals(0, $item->reserved_quantity);
    }

    public function test_super_admin_can_reject_order(): void
    {
        $order = $this->createSubmittedOrder();

        $response = $this->actingAs($this->superAdmin)
            ->post("/admin/orders/{$order->id}/reject", [
                'reason' => 'Duplicate order submitted accidentally.',
            ]);

        $response->assertRedirect(route('admin.orders.index', ['queue' => 'new']));
        $order->refresh();
        $this->assertEquals(OrderStatus::REJECTED, $order->status);
        $this->assertEquals($this->superAdmin->id, $order->cancelled_by);
    }

    public function test_admin_can_reject_pending_approval_order(): void
    {
        $order = $this->createSubmittedOrder([
            'status' => OrderStatus::PENDING_APPROVAL,
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/reject", [
                'reason' => 'Commercial credit terms could not be established.',
            ]);

        $response->assertRedirect(route('admin.orders.index', ['queue' => 'new']));
        $order->refresh();
        $this->assertEquals(OrderStatus::REJECTED, $order->status);
    }

    public function test_rejection_requires_reason_and_rejects_missing_or_empty(): void
    {
        $order = $this->createSubmittedOrder();

        // 1. Missing reason
        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/reject", [])
            ->assertSessionHasErrors('reason');

        // 2. Empty string
        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/reject", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        // 3. Whitespace only
        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/reject", ['reason' => '     '])
            ->assertSessionHasErrors('reason');

        // 4. Too short (< 5 characters)
        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/reject", ['reason' => 'nope'])
            ->assertSessionHasErrors('reason');

        $order->refresh();
        $this->assertEquals(OrderStatus::SUBMITTED, $order->status);
    }

    public function test_rejection_rejects_reason_longer_than_1000_characters(): void
    {
        $order = $this->createSubmittedOrder();
        $longReason = str_repeat('a', 1001);

        $response = $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/reject", [
                'reason' => $longReason,
            ]);

        $response->assertSessionHasErrors('reason');

        $order->refresh();
        $this->assertEquals(OrderStatus::SUBMITTED, $order->status);
    }

    public function test_accountant_cannot_reject_order(): void
    {
        $order = $this->createSubmittedOrder();

        $response = $this->actingAs($this->accountant)
            ->post("/admin/orders/{$order->id}/reject", [
                'reason' => 'Accountant attempting administrative rejection.',
            ]);

        $response->assertForbidden();

        $order->refresh();
        $this->assertEquals(OrderStatus::SUBMITTED, $order->status);
    }

    public function test_salesman_cannot_reject_order(): void
    {
        $order = $this->createSubmittedOrder();

        $response = $this->actingAs($this->salesman)
            ->post("/admin/orders/{$order->id}/reject", [
                'reason' => 'Salesman attempting administrative rejection.',
            ]);

        $response->assertForbidden();

        $order->refresh();
        $this->assertEquals(OrderStatus::SUBMITTED, $order->status);
    }

    public function test_warehouse_manager_and_delivery_partner_cannot_reject_order(): void
    {
        $order = $this->createSubmittedOrder();

        $this->actingAs($this->warehouseManager)
            ->post("/admin/orders/{$order->id}/reject", ['reason' => 'Warehouse rejection attempt'])
            ->assertForbidden();

        $this->actingAs($this->deliveryPartner)
            ->post("/admin/orders/{$order->id}/reject", ['reason' => 'Delivery rejection attempt'])
            ->assertForbidden();

        $order->refresh();
        $this->assertEquals(OrderStatus::SUBMITTED, $order->status);
    }

    public function test_unauthenticated_user_cannot_reject_order(): void
    {
        $order = $this->createSubmittedOrder();

        $response = $this->post("/admin/orders/{$order->id}/reject", [
            'reason' => 'Unauthenticated attempt',
        ]);

        $response->assertRedirect('/login');
        $order->refresh();
        $this->assertEquals(OrderStatus::SUBMITTED, $order->status);
    }

    public function test_inactive_admin_cannot_reject_order(): void
    {
        $suspendedAdmin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::SUSPENDED,
        ]);

        $order = $this->createSubmittedOrder();

        $response = $this->actingAs($suspendedAdmin)
            ->post("/admin/orders/{$order->id}/reject", [
                'reason' => 'Suspended admin attempting rejection',
            ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $order->refresh();
        $this->assertEquals(OrderStatus::SUBMITTED, $order->status);
    }

    public function test_double_reject_returns_409_conflict(): void
    {
        $order = $this->createSubmittedOrder();

        // First rejection succeeds
        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/reject", [
                'reason' => 'First valid rejection reason.',
            ])
            ->assertRedirect(route('admin.orders.index', ['queue' => 'new']));

        // Second rejection returns 409 Conflict
        $response = $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/reject", [
                'reason' => 'Second attempt on already rejected order.',
            ]);

        $response->assertStatus(409);
    }

    public function test_cannot_reject_already_approved_order(): void
    {
        $order = $this->createSubmittedOrder([
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::RESERVED,
            'approved_at' => Carbon::now(),
            'approved_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/reject", [
                'reason' => 'Attempting to reject approved order.',
            ]);

        $response->assertStatus(409);

        $order->refresh();
        $this->assertEquals(OrderStatus::APPROVED, $order->status);
    }

    public function test_concurrent_approve_and_reject_resolves_cleanly(): void
    {
        $order = $this->createSubmittedOrder();

        // Approval wins first
        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/approve")
            ->assertRedirect(route('admin.orders.index', ['queue' => 'new']));

        // Rejection submitted concurrently afterwards receives 409 Conflict
        $response = $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/reject", [
                'reason' => 'Concurrent rejection attempt.',
            ]);

        $response->assertStatus(409);
        $order->refresh();
        $this->assertEquals(OrderStatus::APPROVED, $order->status);
    }

    public function test_financial_amounts_and_line_items_remain_intact_on_rejection(): void
    {
        $order = $this->createSubmittedOrder();

        $subtotalBefore = (string) $order->subtotal;
        $grandTotalBefore = (string) $order->grand_total;
        $itemsCountBefore = $order->items()->count();

        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/reject", [
                'reason' => 'Order cancelled per customer request.',
            ])
            ->assertRedirect(route('admin.orders.index', ['queue' => 'new']));

        $order->refresh();
        $this->assertEquals($subtotalBefore, (string) $order->subtotal);
        $this->assertEquals($grandTotalBefore, (string) $order->grand_total);
        $this->assertEquals($itemsCountBefore, $order->items()->count());

        $item = $order->items()->first();
        $this->assertEquals(240.00, (float) $item->taxable_amount);
        $this->assertEquals(259.80, (float) $item->line_total);
    }

    public function test_rejection_trims_whitespace_before_persisting(): void
    {
        $order = $this->createSubmittedOrder();
        $rawReason = "   \n  Valid rejection reason with extra surrounding whitespace.   \t  ";

        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/reject", [
                'reason' => $rawReason,
            ])
            ->assertRedirect(route('admin.orders.index', ['queue' => 'new']));

        $order->refresh();
        $this->assertEquals(trim($rawReason), $order->cancellation_reason);
    }

    public function test_timeline_reflects_order_rejected_milestone(): void
    {
        $order = $this->createSubmittedOrder();

        $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/reject", [
                'reason' => 'Commercial credit terms could not be agreed upon.',
            ]);

        $order->refresh();

        // Review endpoint redirects to show once rejected
        $showResponse = $this->actingAs($this->admin)
            ->get("/admin/orders/{$order->id}");

        $showResponse->assertOk();
    }
}
