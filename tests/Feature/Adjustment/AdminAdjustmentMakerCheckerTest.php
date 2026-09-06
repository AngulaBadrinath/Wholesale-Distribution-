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

class AdminAdjustmentMakerCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminA;
    protected User $adminB;
    protected User $superAdmin;
    protected User $accountant;
    protected User $salesman;
    protected User $warehouseManager;
    protected User $deliveryPartner;

    protected Customer $customer;
    protected Category $category;
    protected TaxProfile $taxProfile;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminA = User::factory()->create([
            'name' => 'Alice Admin',
            'email' => 'alice@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->adminB = User::factory()->create([
            'name' => 'Bob Admin',
            'email' => 'bob@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Samantha',
            'email' => 'super@wholesale.test',
            'role' => UserRole::SUPER_ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'name' => 'Charlie Accountant',
            'email' => 'accountant@wholesale.test',
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'name' => 'Sam Salesman',
            'email' => 'salesman@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->warehouseManager = User::factory()->create([
            'name' => 'Wendy Warehouse',
            'email' => 'warehouse@wholesale.test',
            'role' => UserRole::WAREHOUSE_MANAGER,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->deliveryPartner = User::factory()->create([
            'name' => 'Dave Delivery',
            'email' => 'delivery@wholesale.test',
            'role' => UserRole::DELIVERY_PARTNER,
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

    protected function createScenario(User $requester, int $reductionQty = 2): array
    {
        $orderedQty = 10;

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
            'picked_quantity' => 0,
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

        $adj = OrderAdjustment::create([
            'adjustment_number' => 'ADJ-MC-' . uniqid(),
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
            'notes' => 'Maker checker test notes.',
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
            'allocated_quantity_snapshot' => 0,
            'unallocated_quantity_snapshot' => $orderedQty,
            'requested_quantity_reduction' => $reductionQty,
            'projected_fulfillable_quantity' => $orderedQty - $reductionQty,
            'projected_cancelled_quantity' => $reductionQty,
            'affected_allocation_quantity' => 0,
            'projected_taxable_amount_reduction' => bcmul('25.00', (string) $reductionQty, 2),
            'projected_tax_amount_reduction' => bcmul('4.50', (string) $reductionQty, 2),
            'projected_line_total_reduction' => bcmul('29.50', (string) $reductionQty, 2),
        ]);

        return [$order, $adj, $item];
    }

    public function test_admin_cannot_approve_own_adjustment_request(): void
    {
        // Admin A requests adjustment
        [$order, $adj] = $this->createScenario($this->adminA);

        // Admin A attempts to approve own request
        $response = $this->actingAs($this->adminA)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve"
        );

        $response->assertStatus(403);
        $this->assertEquals(OrderAdjustmentStatus::SUBMITTED, $adj->fresh()->status);
    }

    public function test_admin_cannot_reject_own_adjustment_request(): void
    {
        // Admin A requests adjustment
        [$order, $adj] = $this->createScenario($this->adminA);

        // Admin A attempts to reject own request
        $response = $this->actingAs($this->adminA)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            ['reason' => 'Admin self-rejection attempt.']
        );

        $response->assertStatus(403);
        $this->assertEquals(OrderAdjustmentStatus::SUBMITTED, $adj->fresh()->status);
    }

    public function test_admin_can_approve_another_admins_adjustment_request(): void
    {
        // Admin A requests adjustment
        [$order, $adj] = $this->createScenario($this->adminA);

        // Admin B approves Admin A's request
        $response = $this->actingAs($this->adminB)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve"
        );

        $response->assertRedirect();
        $this->assertEquals(OrderAdjustmentStatus::APPROVED, $adj->fresh()->status);
        $this->assertEquals($this->adminB->id, $adj->fresh()->reviewed_by);
    }

    public function test_admin_can_reject_another_admins_adjustment_request(): void
    {
        // Admin A requests adjustment
        [$order, $adj] = $this->createScenario($this->adminA);

        // Admin B rejects Admin A's request
        $response = $this->actingAs($this->adminB)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            ['reason' => 'Admin B rejecting Admin A adjustment request.']
        );

        $response->assertRedirect();
        $this->assertEquals(OrderAdjustmentStatus::REJECTED, $adj->fresh()->status);
        $this->assertEquals($this->adminB->id, $adj->fresh()->reviewed_by);
    }

    public function test_super_admin_cannot_self_approve_without_emergency_override_reason(): void
    {
        // Super Admin requests adjustment
        [$order, $adj] = $this->createScenario($this->superAdmin);

        // Super Admin attempts self-approval without override reason
        $response = $this->actingAs($this->superAdmin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve",
            []
        );

        $response->assertSessionHasErrors(['emergency_override_reason']);
        $this->assertEquals(OrderAdjustmentStatus::SUBMITTED, $adj->fresh()->status);
    }

    public function test_super_admin_cannot_self_approve_with_short_override_reason(): void
    {
        [$order, $adj] = $this->createScenario($this->superAdmin);

        $response = $this->actingAs($this->superAdmin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve",
            ['emergency_override_reason' => 'Too short'] // < 10 characters
        );

        $response->assertSessionHasErrors(['emergency_override_reason']);
        $this->assertEquals(OrderAdjustmentStatus::SUBMITTED, $adj->fresh()->status);
    }

    public function test_super_admin_can_self_approve_with_valid_emergency_override_reason(): void
    {
        Log::spy();

        [$order, $adj] = $this->createScenario($this->superAdmin);

        $overrideReason = 'Emergency override required due to customer urgent truck departure.';

        $response = $this->actingAs($this->superAdmin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve",
            ['emergency_override_reason' => $overrideReason]
        );

        $response->assertRedirect();
        $adjFresh = $adj->fresh();
        $this->assertEquals(OrderAdjustmentStatus::APPROVED, $adjFresh->status);
        $this->assertEquals($this->superAdmin->id, $adjFresh->reviewed_by);

        // Assert emergency override event logged
        Log::shouldHaveReceived('info')->with(
            'commerce.order_adjustment_event',
            \Mockery::on(function ($data) use ($adj, $overrideReason) {
                return $data['action'] === 'ADJUSTMENT_EMERGENCY_OVERRIDE'
                    && $data['adjustment_id'] === $adj->id
                    && $data['override_reason'] === $overrideReason
                    && $data['decision'] === 'APPROVED';
            })
        )->once();
    }

    public function test_super_admin_cannot_self_reject_without_emergency_override_reason(): void
    {
        [$order, $adj] = $this->createScenario($this->superAdmin);

        $response = $this->actingAs($this->superAdmin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            ['reason' => 'Valid rejection reason.']
        );

        $response->assertSessionHasErrors(['emergency_override_reason']);
        $this->assertEquals(OrderAdjustmentStatus::SUBMITTED, $adj->fresh()->status);
    }

    public function test_super_admin_can_self_reject_with_valid_emergency_override_reason(): void
    {
        Log::spy();

        [$order, $adj] = $this->createScenario($this->superAdmin);

        $reason = 'Self-rejection requested by creator.';
        $overrideReason = 'Documented emergency self-rejection reason by Super Admin.';

        $response = $this->actingAs($this->superAdmin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            [
                'reason' => $reason,
                'emergency_override_reason' => $overrideReason,
            ]
        );

        $response->assertRedirect();
        $adjFresh = $adj->fresh();
        $this->assertEquals(OrderAdjustmentStatus::REJECTED, $adjFresh->status);
        $this->assertEquals($this->superAdmin->id, $adjFresh->reviewed_by);

        Log::shouldHaveReceived('info')->with(
            'commerce.order_adjustment_event',
            \Mockery::on(function ($data) use ($adj, $overrideReason) {
                return $data['action'] === 'ADJUSTMENT_EMERGENCY_OVERRIDE'
                    && $data['adjustment_id'] === $adj->id
                    && $data['override_reason'] === $overrideReason
                    && $data['decision'] === 'REJECTED';
            })
        )->once();
    }

    public function test_accountant_denied_approval_and_rejection(): void
    {
        [$order, $adj] = $this->createScenario($this->salesman);

        $approveRes = $this->actingAs($this->accountant)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve"
        );
        $approveRes->assertStatus(403);

        $rejectRes = $this->actingAs($this->accountant)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            ['reason' => 'Accountant rejecting attempt.']
        );
        $rejectRes->assertStatus(403);
    }

    public function test_salesman_denied_approval_and_rejection(): void
    {
        [$order, $adj] = $this->createScenario($this->salesman);

        $approveRes = $this->actingAs($this->salesman)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve"
        );
        $approveRes->assertStatus(403);

        $rejectRes = $this->actingAs($this->salesman)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            ['reason' => 'Salesman rejecting attempt.']
        );
        $rejectRes->assertStatus(403);
    }

    public function test_warehouse_manager_denied_approval_and_rejection(): void
    {
        [$order, $adj] = $this->createScenario($this->salesman);

        $approveRes = $this->actingAs($this->warehouseManager)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve"
        );
        $approveRes->assertStatus(403);

        $rejectRes = $this->actingAs($this->warehouseManager)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            ['reason' => 'Warehouse rejecting attempt.']
        );
        $rejectRes->assertStatus(403);
    }

    public function test_delivery_partner_denied_approval_and_rejection(): void
    {
        [$order, $adj] = $this->createScenario($this->salesman);

        $approveRes = $this->actingAs($this->deliveryPartner)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/approve"
        );
        $approveRes->assertStatus(403);

        $rejectRes = $this->actingAs($this->deliveryPartner)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/reject",
            ['reason' => 'Delivery rejecting attempt.']
        );
        $rejectRes->assertStatus(403);
    }
}
