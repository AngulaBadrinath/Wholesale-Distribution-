<?php

namespace Tests\Feature\Order;

use App\Enums\AccountStatus;
use App\Enums\AdjustmentStatus;
use App\Enums\CustomerStatus;
use App\Enums\DeliveryStatus;
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
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminOrderReviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $superAdmin;
    protected User $accountant;
    protected User $salesman;
    protected Customer $customer;
    protected Product $productA;
    protected Product $productB;
    protected TaxProfile $taxProfileStandard;
    protected TaxProfile $taxProfileReduced;
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

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Apex Retailers Inc',
            'code' => 'CUST-APEX-01',
            'contact_name' => 'John Apex',
            'phone' => '+1-555-0987',
            'email' => 'john@apexretail.test',
            'billing_address_line1' => '742 Evergreen Terrace',
            'billing_city' => 'Springfield',
            'billing_state' => 'OR',
            'billing_postal_code' => '97477',
            'billing_country' => 'US',
            'shipping_address_line1' => '100 Industrial Parkway',
            'shipping_city' => 'Springfield',
            'shipping_state' => 'OR',
            'shipping_postal_code' => '97477',
            'shipping_country' => 'US',
            'tax_id' => 'TAX-US-99182',
            'credit_limit' => '1000.00',
            'payment_terms' => 'NET_30',
            'status' => CustomerStatus::ACTIVE,
        ]);

        $this->category = Category::create([
            'name' => 'Pantry Goods',
            'code' => 'PANTRY',
            'sort_order' => 1,
        ]);

        $this->taxProfileStandard = TaxProfile::create([
            'name' => 'Standard VAT',
            'code' => 'VAT_STD',
            'rate' => '10.00',
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->taxProfileReduced = TaxProfile::create([
            'name' => 'Reduced Food VAT',
            'code' => 'VAT_RED',
            'rate' => '5.00',
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->productA = Product::create([
            'name' => 'Extra Virgin Olive Oil 1L',
            'sku' => 'EVOO-1L-001',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfileStandard->id,
            'unit' => 'BOTTLE',
            'minimum_allowed_price' => '8.00',
            'default_selling_price' => '10.00',
            'mrp' => '14.00',
            'cost_price' => '6.50',
            'status' => ProductStatus::ACTIVE,
        ]);

        $this->productB = Product::create([
            'name' => 'Organic Honey 500g',
            'sku' => 'HONEY-500G-002',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfileReduced->id,
            'unit' => 'JAR',
            'minimum_allowed_price' => '4.00',
            'default_selling_price' => '6.00',
            'mrp' => '8.50',
            'cost_price' => '3.20',
            'status' => ProductStatus::ACTIVE,
        ]);
    }

    /**
     * Helper to create a reviewable test order.
     */
    protected function createTestOrder(
        OrderStatus $status = OrderStatus::SUBMITTED,
        ?Carbon $submittedAt = null,
        ?Customer $customer = null
    ): Order {
        $targetCustomer = $customer ?? $this->customer;

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'idempotency_key' => 'idemp_' . uniqid(),
            'customer_id' => $targetCustomer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => $status,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'delivery_status' => DeliveryStatus::PENDING_ASSIGNMENT,
            'adjustment_status' => AdjustmentStatus::NONE,
            'currency' => 'USD',
            'subtotal' => '160.00',
            'tax_total' => '13.00',
            'adjustment_total' => '0.00',
            'grand_total' => '173.00',
            'notes' => 'Deliver before 3 PM to rear loading dock.',
            'submitted_at' => $submittedAt ?? Carbon::now(),
        ]);

        // Item 1: 10 units of Product A @ 10.00 each, 10% tax
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'unit_price' => '10.00',
            'is_price_overridden' => false,
            'tax_profile_id_snapshot' => $this->taxProfileStandard->id,
            'tax_profile_code_snapshot' => $this->taxProfileStandard->code,
            'tax_profile_name_snapshot' => $this->taxProfileStandard->name,
            'tax_rate_snapshot' => '10.00',
            'taxable_amount' => '100.00',
            'tax_amount' => '10.00',
            'line_total' => '110.00',
        ]);

        // Item 2: 10 units of Product B @ 6.00 each, 5% tax
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productB->id,
            'product_name_snapshot' => $this->productB->name,
            'sku_snapshot' => $this->productB->sku,
            'unit_snapshot' => $this->productB->unit,
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'unit_price' => '6.00',
            'is_price_overridden' => false,
            'tax_profile_id_snapshot' => $this->taxProfileReduced->id,
            'tax_profile_code_snapshot' => $this->taxProfileReduced->code,
            'tax_profile_name_snapshot' => $this->taxProfileReduced->name,
            'tax_rate_snapshot' => '5.00',
            'taxable_amount' => '60.00',
            'tax_amount' => '3.00',
            'line_total' => '63.00',
        ]);

        return $order;
    }

    public function test_admin_can_access_review_workspace_for_submitted_order(): void
    {
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Review')
            ->has('reviewData.order', fn (Assert $orderAssert) => $orderAssert
                ->where('id', $order->id)
                ->where('order_number', $order->order_number)
                ->where('status', 'SUBMITTED')
                ->where('fulfillment_status', 'UNALLOCATED')
                ->where('payment_status', 'UNPAID')
                ->where('delivery_status', 'PENDING_ASSIGNMENT')
                ->where('adjustment_status', 'NONE')
                ->where('subtotal', '160.00')
                ->where('tax_total', '13.00')
                ->where('grand_total', '173.00')
                ->etc()
            )
            ->has('reviewData.customer', fn (Assert $custAssert) => $custAssert
                ->where('name', 'Apex Retailers Inc')
                ->where('code', 'CUST-APEX-01')
                ->where('credit_limit', '1000.00')
                ->where('payment_terms', 'Net 30 Days')
                ->etc()
            )
            ->has('reviewData.items', 2)
            ->where('backUrl', '/admin/orders?queue=new')
        );
    }

    public function test_super_admin_can_access_review_workspace(): void
    {
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        $response = $this->actingAs($this->superAdmin)->get(route('admin.orders.review', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Review')
            ->where('reviewData.order.id', $order->id)
        );
    }

    public function test_accountant_can_access_review_workspace_with_read_only_capability(): void
    {
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        $response = $this->actingAs($this->accountant)->get(route('admin.orders.review', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Review')
            ->where('reviewData.can.approve', false)
            ->where('reviewData.can.reject', false)
        );
    }

    public function test_admin_has_approve_and_reject_readiness_capabilities(): void
    {
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Review')
            ->where('reviewData.can.approve', true)
            ->where('reviewData.can.reject', true)
        );
    }

    public function test_salesman_is_strictly_forbidden_with_403(): void
    {
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        $response = $this->actingAs($this->salesman)->get(route('admin.orders.review', $order));

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        $response = $this->get(route('admin.orders.review', $order));

        $response->assertRedirect('/login');
    }

    public function test_inactive_user_is_denied(): void
    {
        $inactiveAdmin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::SUSPENDED,
        ]);

        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        $response = $this->actingAs($inactiveAdmin)->get(route('admin.orders.review', $order));

        $response->assertRedirect('/login');
    }

    public function test_draft_order_returns_404_not_found(): void
    {
        $draftOrder = $this->createTestOrder(OrderStatus::DRAFT);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $draftOrder));

        $response->assertNotFound();
    }

    public function test_pending_approval_order_can_be_reviewed(): void
    {
        $pendingOrder = $this->createTestOrder(OrderStatus::PENDING_APPROVAL);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $pendingOrder));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Review')
            ->where('reviewData.order.status', 'PENDING_APPROVAL')
        );
    }

    public function test_approved_order_redirects_to_show_with_notice(): void
    {
        $approvedOrder = $this->createTestOrder(OrderStatus::APPROVED);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $approvedOrder));

        $response->assertRedirect(route('admin.orders.show', $approvedOrder));
        $response->assertSessionHas('info');
    }

    public function test_completed_order_redirects_to_show_with_notice(): void
    {
        $completedOrder = $this->createTestOrder(OrderStatus::COMPLETED);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $completedOrder));

        $response->assertRedirect(route('admin.orders.show', $completedOrder));
        $response->assertSessionHas('info');
    }

    public function test_cancelled_order_redirects_to_show_with_notice(): void
    {
        $cancelledOrder = $this->createTestOrder(OrderStatus::CANCELLED);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $cancelledOrder));

        $response->assertRedirect(route('admin.orders.show', $cancelledOrder));
        $response->assertSessionHas('info');
    }

    public function test_rejected_order_redirects_to_show_with_notice(): void
    {
        $rejectedOrder = $this->createTestOrder(OrderStatus::REJECTED);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $rejectedOrder));

        $response->assertRedirect(route('admin.orders.show', $rejectedOrder));
        $response->assertSessionHas('info');
    }

    public function test_non_existent_order_returns_404(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/orders/9999999/review');

        $response->assertNotFound();
    }

    public function test_review_workspace_does_not_leak_cost_price_in_payload(): void
    {
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $order));

        $response->assertOk();
        $inertiaData = $response->viewData('page')['props']['reviewData'];

        // Verify top-level and items do not have cost_price or purchase_cost
        foreach ($inertiaData['items'] as $item) {
            $this->assertArrayNotHasKey('cost_price', $item);
            $this->assertArrayNotHasKey('purchase_cost', $item);
            if (isset($item['catalog_product'])) {
                $this->assertArrayNotHasKey('cost_price', $item['catalog_product']);
                $this->assertArrayNotHasKey('purchase_cost', $item['catalog_product']);
            }
        }
    }

    public function test_review_workspace_does_not_leak_payment_evidence_or_secrets(): void
    {
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $order));

        $response->assertOk();
        $inertiaData = $response->viewData('page')['props']['reviewData'];

        // JSON string representation must not contain private storage path or presigned URLs
        $jsonPayload = json_encode($inertiaData);
        $this->assertStringNotContainsString('presigned', $jsonPayload);
        $this->assertStringNotContainsString('s3.amazonaws.com', $jsonPayload);
        $this->assertStringNotContainsString('private_key', $jsonPayload);
    }

    public function test_review_workspace_presents_immutable_historical_order_and_item_snapshots(): void
    {
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        // Mutate current product master prices and name
        $this->productA->update([
            'name' => 'Olive Oil - 2027 New Formula',
            'default_selling_price' => '25.00',
            'minimum_allowed_price' => '20.00',
            'mrp' => '30.00',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Review')
            ->has('reviewData.items.0', fn (Assert $item) => $item
                ->where('product_name', 'Extra Virgin Olive Oil 1L') // Snapshot preserved
                ->where('unit_price', '10.00') // Snapshot preserved
                ->where('ordered_quantity', 10)
                ->where('line_total', '110.00')
                ->where('catalog_product.name', 'Olive Oil - 2027 New Formula') // Catalog context separated
                ->where('catalog_product.default_selling_price', '25.00')
                ->etc()
            )
        );
    }

    public function test_review_workspace_displays_price_override_authorizer_and_reason_without_cost(): void
    {
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        // Update item 1 with authorized price override
        $item = $order->items()->first();
        $item->update([
            'is_price_overridden' => true,
            'price_override_reason' => 'Bulk client promotional allowance',
            'price_override_approved_by' => $this->admin->id,
            'price_override_approved_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Review')
            ->has('reviewData.items.0', fn (Assert $itemAssert) => $itemAssert
                ->where('is_price_overridden', true)
                ->where('price_override_reason', 'Bulk client promotional allowance')
                ->where('price_override_approver.name', 'Operational Admin')
                ->etc()
            )
        );
    }

    public function test_review_workspace_aggregates_multi_tax_profiles_deterministically(): void
    {
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Review')
            ->has('reviewData.tax_breakdown', 2)
            ->has('reviewData.tax_breakdown.0', fn (Assert $tax) => $tax
                ->where('code', 'VAT_STD')
                ->where('taxable_amount', '100.00')
                ->where('tax_amount', '10.00')
                ->where('rate', '10.00')
                ->etc()
            )
            ->has('reviewData.tax_breakdown.1', fn (Assert $tax) => $tax
                ->where('code', 'VAT_RED')
                ->where('taxable_amount', '60.00')
                ->where('tax_amount', '3.00')
                ->where('rate', '5.00')
                ->etc()
            )
        );
    }

    public function test_warning_customer_on_hold_flagged_as_blocker(): void
    {
        $this->customer->update(['status' => CustomerStatus::ON_HOLD]);
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Review')
            ->has('reviewData.warnings', fn (Assert $warn) => $warn
                ->where('0.code', 'CUSTOMER_ON_HOLD')
                ->where('0.severity', 'blocker')
                ->etc()
            )
        );
    }

    public function test_warning_customer_inactive_flagged_as_blocker(): void
    {
        $this->customer->update(['status' => CustomerStatus::INACTIVE]);
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Review')
            ->has('reviewData.warnings', fn (Assert $warn) => $warn
                ->where('0.code', 'CUSTOMER_INACTIVE')
                ->where('0.severity', 'blocker')
                ->etc()
            )
        );
    }

    public function test_warning_credit_limit_exceeded_flagged_as_warning(): void
    {
        // Set customer credit limit lower than order grand total (173.00)
        $this->customer->update(['credit_limit' => '150.00']);
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Review')
            ->has('reviewData.warnings', fn (Assert $warn) => $warn
                ->where('0.code', 'CREDIT_LIMIT_EXCEEDED')
                ->where('0.severity', 'warning')
                ->etc()
            )
        );
    }

    public function test_warning_price_override_present_flagged_as_warning(): void
    {
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);
        $order->items()->first()->update(['is_price_overridden' => true]);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Review')
            ->has('reviewData.warnings', fn (Assert $warn) => $warn
                ->where('0.code', 'PRICE_OVERRIDE_PRESENT')
                ->where('0.severity', 'info')
                ->etc()
            )
        );
    }

    public function test_warning_aging_order_flagged_when_submitted_over_24h_ago(): void
    {
        // Submitted 26 hours ago
        $agingSubmittedAt = Carbon::now()->subHours(26);
        $order = $this->createTestOrder(OrderStatus::SUBMITTED, $agingSubmittedAt);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Review')
            ->has('reviewData.warnings', fn (Assert $warn) => $warn
                ->where('0.code', 'AGING_ORDER')
                ->where('0.severity', 'warning')
                ->etc()
            )
        );
    }

    public function test_warning_product_inactive_flagged_as_warning(): void
    {
        $this->productA->update(['status' => ProductStatus::INACTIVE]);
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $order));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Review')
            ->has('reviewData.warnings', fn (Assert $warn) => $warn
                ->where('0.code', 'PRODUCT_INACTIVE')
                ->where('0.severity', 'blocker')
                ->etc()
            )
        );
    }

    public function test_query_execution_is_bounded_and_prevents_n_plus_one(): void
    {
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->admin)->get(route('admin.orders.review', $order));
        $response->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Bounded query execution: Order + customer + salesman + creator + approver + canceller + items + items.product + items.priceOverrideApprover
        // Must never exceed 15 queries regardless of how many items the order has
        $this->assertLessThanOrEqual(15, $queryCount, "Query count {$queryCount} exceeded bounded threshold.");
    }
}
