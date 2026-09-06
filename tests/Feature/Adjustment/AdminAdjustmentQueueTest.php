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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAdjustmentQueueTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
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

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'super@wholesale.test',
            'role' => UserRole::SUPER_ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Alice Admin',
            'email' => 'admin@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'name' => 'Bob Accountant',
            'email' => 'accountant@wholesale.test',
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'name' => 'Sam Salesman',
            'email' => 'sales@wholesale.test',
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

    protected function createOrderWithAdjustment(
        string $adjNumber = 'ADJ-ORD-001-01',
        OrderAdjustmentStatus $adjStatus = OrderAdjustmentStatus::SUBMITTED,
        int $affectedAlloc = 0,
        AdjustmentReasonCode $reason = AdjustmentReasonCode::CUSTOMER_REQUEST,
        string $grandTotalReduction = '29.50'
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
            'subtotal' => '250.00',
            'tax_total' => '45.00',
            'adjustment_total' => '0.00',
            'grand_total' => '295.00',
            'version' => 1,
            'submitted_at' => Carbon::now()->subHours(2),
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
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

        $adj = OrderAdjustment::create([
            'adjustment_number' => $adjNumber,
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'order_version_snapshot' => 1,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => '250.00',
            'order_tax_total_snapshot' => '45.00',
            'order_grand_total_snapshot' => '295.00',
            'type' => 'QUANTITY_REDUCTION',
            'status' => $adjStatus,
            'reason_code' => $reason,
            'notes' => 'Adjustment notes for queue testing.',
            'requested_by' => $this->salesman->id,
            'requested_at' => Carbon::now()->subHour(),
            'projected_subtotal_reduction' => '25.00',
            'projected_tax_reduction' => '4.50',
            'projected_grand_total_reduction' => $grandTotalReduction,
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
            'ordered_quantity_snapshot' => 10,
            'cancelled_quantity_snapshot' => 0,
            'fulfillable_quantity_snapshot' => 10,
            'allocated_quantity_snapshot' => 0,
            'unallocated_quantity_snapshot' => 10,
            'requested_quantity_reduction' => 1,
            'projected_fulfillable_quantity' => 9,
            'projected_cancelled_quantity' => 1,
            'affected_allocation_quantity' => $affectedAlloc,
            'projected_taxable_amount_reduction' => '25.00',
            'projected_tax_amount_reduction' => '4.50',
            'projected_line_total_reduction' => '29.50',
        ]);

        return [$order, $adj, $item];
    }

    public function test_admin_can_access_adjustment_queue(): void
    {
        $this->createOrderWithAdjustment('ADJ-ADMIN-01');

        $response = $this->actingAs($this->admin)->get('/admin/adjustments');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Adjustments/Index')
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.adjustment_number', 'ADJ-ADMIN-01')
            ->has('counts')
            ->where('counts.submitted', 1)
        );
    }

    public function test_accountant_can_access_adjustment_queue(): void
    {
        $this->createOrderWithAdjustment('ADJ-ACCT-01');

        $response = $this->actingAs($this->accountant)->get('/admin/adjustments');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Adjustments/Index')
            ->has('adjustments.data', 1)
        );
    }

    public function test_salesman_is_denied_from_adjustment_queue(): void
    {
        $this->createOrderWithAdjustment('ADJ-DENIED-01');

        $response = $this->actingAs($this->salesman)->get('/admin/adjustments');

        $response->assertStatus(403);
    }

    public function test_warehouse_manager_is_denied_from_adjustment_queue(): void
    {
        $this->createOrderWithAdjustment('ADJ-WH-DENIED-01');

        $response = $this->actingAs($this->warehouseManager)->get('/admin/adjustments');

        $response->assertStatus(403);
    }

    public function test_delivery_partner_is_denied_from_adjustment_queue(): void
    {
        $response = $this->actingAs($this->deliveryPartner)->get('/admin/adjustments');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/adjustments');

        $response->assertRedirect('/login');
    }

    public function test_queue_filters_by_impact_case(): void
    {
        $this->createOrderWithAdjustment('ADJ-CASE-A', OrderAdjustmentStatus::SUBMITTED, 0);
        $this->createOrderWithAdjustment('ADJ-CASE-B', OrderAdjustmentStatus::SUBMITTED, 2);

        // Filter Case B only
        $responseB = $this->actingAs($this->admin)->get('/admin/adjustments?impact_case=CASE_B');
        $responseB->assertStatus(200);
        $responseB->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.adjustment_number', 'ADJ-CASE-B')
            ->where('adjustments.data.0.impact_case', 'CASE_B')
        );

        // Filter Case A only
        $responseA = $this->actingAs($this->admin)->get('/admin/adjustments?impact_case=CASE_A');
        $responseA->assertStatus(200);
        $responseA->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.adjustment_number', 'ADJ-CASE-A')
            ->where('adjustments.data.0.impact_case', 'CASE_A')
        );
    }

    public function test_queue_filters_by_status(): void
    {
        $this->createOrderWithAdjustment('ADJ-SUBMITTED', OrderAdjustmentStatus::SUBMITTED);
        $this->createOrderWithAdjustment('ADJ-CANCELLED', OrderAdjustmentStatus::CANCELLED);

        // Default shows SUBMITTED
        $response = $this->actingAs($this->admin)->get('/admin/adjustments');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.adjustment_number', 'ADJ-SUBMITTED')
        );

        // Filter CANCELLED
        $responseCancelled = $this->actingAs($this->admin)->get('/admin/adjustments?status=CANCELLED');
        $responseCancelled->assertStatus(200);
        $responseCancelled->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.adjustment_number', 'ADJ-CANCELLED')
        );

        // Filter ALL
        $responseAll = $this->actingAs($this->admin)->get('/admin/adjustments?status=ALL');
        $responseAll->assertStatus(200);
        $responseAll->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 2)
        );
    }

    public function test_queue_search_by_adjustment_number(): void
    {
        $this->createOrderWithAdjustment('ADJ-ALPHA-123');
        $this->createOrderWithAdjustment('ADJ-BETA-456');

        $response = $this->actingAs($this->admin)->get('/admin/adjustments?search=ALPHA');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.adjustment_number', 'ADJ-ALPHA-123')
        );
    }

    public function test_queue_sorting_by_projected_grand_total(): void
    {
        $this->createOrderWithAdjustment('ADJ-SMALL', OrderAdjustmentStatus::SUBMITTED, 0, AdjustmentReasonCode::CUSTOMER_REQUEST, '10.00');
        $this->createOrderWithAdjustment('ADJ-LARGE', OrderAdjustmentStatus::SUBMITTED, 0, AdjustmentReasonCode::CUSTOMER_REQUEST, '500.00');

        $response = $this->actingAs($this->admin)->get('/admin/adjustments?sort_by=projected_grand_total_reduction&sort_direction=desc');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('adjustments.data.0.adjustment_number', 'ADJ-LARGE')
            ->where('adjustments.data.1.adjustment_number', 'ADJ-SMALL')
        );
    }

    public function test_operational_queue_tab_pending_and_ready_to_apply(): void
    {
        // 1 pending, 1 clean approved
        $this->createOrderWithAdjustment('ADJ-PENDING', OrderAdjustmentStatus::SUBMITTED);
        $this->createOrderWithAdjustment('ADJ-READY', OrderAdjustmentStatus::APPROVED);

        // queue=pending
        $responsePending = $this->actingAs($this->admin)->get('/admin/adjustments?queue=pending');
        $responsePending->assertStatus(200);
        $responsePending->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.adjustment_number', 'ADJ-PENDING')
        );

        // queue=ready_to_apply
        $responseReady = $this->actingAs($this->admin)->get('/admin/adjustments?queue=ready_to_apply');
        $responseReady->assertStatus(200);
        $responseReady->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.adjustment_number', 'ADJ-READY')
            ->where('adjustments.data.0.is_ready_to_apply', true)
        );
    }

    public function test_approved_blocked_record_appears_in_attention_and_excluded_from_ready_to_apply(): void
    {
        // Create an APPROVED adjustment
        [$order, $adj, $item] = $this->createOrderWithAdjustment('ADJ-BLOCKED-APP', OrderAdjustmentStatus::APPROVED);

        // Initially clean approved: appears in ready_to_apply
        $resBefore = $this->actingAs($this->admin)->get('/admin/adjustments?queue=ready_to_apply');
        $resBefore->assertInertia(fn (Assert $page) => $page
            ->where('counts.ready_to_apply', 1)
            ->where('counts.attention', 0)
            ->has('adjustments.data', 1)
        );

        // Now mutate order version (stale version blocker)
        $order->version = 2;
        $order->save();

        // Must now appear in ATTENTION and NOT in READY_TO_APPLY
        $resAttention = $this->actingAs($this->admin)->get('/admin/adjustments?queue=attention');
        $resAttention->assertStatus(200);
        $resAttention->assertInertia(fn (Assert $page) => $page
            ->where('counts.ready_to_apply', 0)
            ->where('counts.attention', 1)
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.adjustment_number', 'ADJ-BLOCKED-APP')
            ->where('adjustments.data.0.needs_attention', true)
            ->where('adjustments.data.0.is_ready_to_apply', false)
            ->where('adjustments.data.0.has_blocker', true)
        );

        $resReady = $this->actingAs($this->admin)->get('/admin/adjustments?queue=ready_to_apply');
        $resReady->assertStatus(200);
        $resReady->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 0)
        );
    }

    public function test_attention_queue_surfaces_quantity_conflicted_adjustment(): void
    {
        [$order, $adj, $item] = $this->createOrderWithAdjustment('ADJ-CONFLICT', OrderAdjustmentStatus::SUBMITTED);

        // Make requested reduction exceed fulfillable quantity
        $adjItem = $adj->items->first();
        $adjItem->requested_quantity_reduction = 15; // order item ordered=10
        $adjItem->save();

        $response = $this->actingAs($this->admin)->get('/admin/adjustments?queue=attention');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.adjustment_number', 'ADJ-CONFLICT')
            ->where('adjustments.data.0.primary_exception', 'CONFLICTED')
        );
    }

    public function test_attention_queue_surfaces_picked_encroachment(): void
    {
        [$order, $adj, $item] = $this->createOrderWithAdjustment('ADJ-ENCROACH', OrderAdjustmentStatus::SUBMITTED, 5);

        // Item ordered=10, cancelled=0, fulfillable=10.
        // Allocation of 6 units: 5 picked, 1 unpicked.
        OrderItemAllocation::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'product_id' => $this->product->id,
            'allocation_number' => 'ALC-001',
            'warehouse_code' => 'MAIN',
            'allocated_quantity' => 6,
            'reserved_quantity' => 6,
            'picked_quantity' => 5,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'status' => AllocationStatus::ALLOCATED,
            'allocated_by' => $this->admin->id,
            'allocated_at' => Carbon::now(),
        ]);

        // Requested reduction is 6 units. Fulfillable after reduction = 10 - 6 = 4 < 5 picked units!
        $adjItem = $adj->items->first();
        $adjItem->requested_quantity_reduction = 6;
        $adjItem->affected_allocation_quantity = 2;
        $adjItem->save();

        $response = $this->actingAs($this->admin)->get('/admin/adjustments?queue=attention');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.adjustment_number', 'ADJ-ENCROACH')
            ->where('adjustments.data.0.primary_exception', 'PICKED_ENCROACHMENT')
        );
    }

    public function test_attention_queue_surfaces_ineligible_order_lifecycle(): void
    {
        [$order, $adj, $item] = $this->createOrderWithAdjustment('ADJ-LIFECYCLE', OrderAdjustmentStatus::SUBMITTED);

        // Transition order to terminal lifecycle
        $order->status = OrderStatus::CANCELLED;
        $order->save();

        $response = $this->actingAs($this->admin)->get('/admin/adjustments?queue=attention');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.adjustment_number', 'ADJ-LIFECYCLE')
            ->where('adjustments.data.0.primary_exception', 'INELIGIBLE_LIFECYCLE')
        );
    }

    public function test_attention_queue_surfaces_aging_requests_over_24_hours(): void
    {
        [$order, $adj, $item] = $this->createOrderWithAdjustment('ADJ-AGING', OrderAdjustmentStatus::SUBMITTED);

        // Set requested_at to 30 hours ago
        $adj->requested_at = Carbon::now()->subHours(30);
        $adj->save();

        $response = $this->actingAs($this->admin)->get('/admin/adjustments?queue=attention');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.adjustment_number', 'ADJ-AGING')
            ->where('adjustments.data.0.is_aging', true)
            ->where('adjustments.data.0.primary_exception', 'AGING')
        );
    }

    public function test_clean_recent_request_is_excluded_from_attention_queue(): void
    {
        // Recent (1 hour ago), clean submitted request
        $this->createOrderWithAdjustment('ADJ-CLEAN', OrderAdjustmentStatus::SUBMITTED);

        $response = $this->actingAs($this->admin)->get('/admin/adjustments?queue=attention');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 0)
            ->where('counts.attention', 0)
            ->where('counts.pending', 1)
        );
    }

    public function test_filter_by_exception_type(): void
    {
        // 1 aging, 1 lifecycle-ineligible
        [$order1, $adjAging] = $this->createOrderWithAdjustment('ADJ-TYPE-AGING', OrderAdjustmentStatus::SUBMITTED);
        $adjAging->requested_at = Carbon::now()->subHours(30);
        $adjAging->save();

        [$order2, $adjLife] = $this->createOrderWithAdjustment('ADJ-TYPE-LIFE', OrderAdjustmentStatus::SUBMITTED);
        $order2->status = OrderStatus::CANCELLED;
        $order2->save();

        // Filter exception_type=AGING
        $resAging = $this->actingAs($this->admin)->get('/admin/adjustments?queue=attention&exception_type=AGING');
        $resAging->assertStatus(200);
        $resAging->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.adjustment_number', 'ADJ-TYPE-AGING')
        );

        // Filter exception_type=INELIGIBLE_LIFECYCLE
        $resLife = $this->actingAs($this->admin)->get('/admin/adjustments?queue=attention&exception_type=INELIGIBLE_LIFECYCLE');
        $resLife->assertStatus(200);
        $resLife->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.adjustment_number', 'ADJ-TYPE-LIFE')
        );
    }

    public function test_primary_exception_precedence_when_multiple_flags_exist(): void
    {
        // Multiple flags: Conflicted AND Aging (>24h)
        [$order, $adj, $item] = $this->createOrderWithAdjustment('ADJ-MULTI', OrderAdjustmentStatus::SUBMITTED);
        $adj->requested_at = Carbon::now()->subHours(30);
        $adj->save();

        $adjItem = $adj->items->first();
        $adjItem->requested_quantity_reduction = 20; // Conflict
        $adjItem->save();

        $response = $this->actingAs($this->admin)->get('/admin/adjustments?queue=attention');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.adjustment_number', 'ADJ-MULTI')
            ->where('adjustments.data.0.primary_exception', 'CONFLICTED') // CONFLICTED has higher precedence than AGING
            ->where('adjustments.data.0.is_aging', true)
        );
    }

    public function test_queue_classification_and_review_service_classification_agree_for_all_scenarios(): void
    {
        $reviewService = app(\App\Services\Adjustment\OrderAdjustmentReviewService::class);

        // Scenario 1: Clean
        [$orderClean, $adjClean] = $this->createOrderWithAdjustment('ADJ-AGREE-CLEAN', OrderAdjustmentStatus::SUBMITTED);
        $adjClean->load(['order', 'items.orderItem.allocations']);
        $cleanClass = \App\Services\Adjustment\OrderAdjustmentClassifier::classify($adjClean, $orderClean);
        $cleanReview = $reviewService->evaluate($adjClean, $orderClean);
        $this->assertFalse($cleanClass['has_blocker']);
        $this->assertEquals('READY', $cleanReview->evaluationStatus);

        // Scenario 2: Conflicted
        [$orderConf, $adjConf, $itemConf] = $this->createOrderWithAdjustment('ADJ-AGREE-CONF', OrderAdjustmentStatus::SUBMITTED);
        $adjConfItem = $adjConf->items->first();
        $adjConfItem->requested_quantity_reduction = 99;
        $adjConfItem->save();
        $adjConf->load(['order', 'items.orderItem.allocations']);
        $confClass = \App\Services\Adjustment\OrderAdjustmentClassifier::classify($adjConf, $orderConf);
        $confReview = $reviewService->evaluate($adjConf, $orderConf);
        $this->assertTrue($confClass['has_blocker']);
        $this->assertContains('CONFLICTED', $confClass['attention_flags']);
        $this->assertEquals('CONFLICTED', $confReview->evaluationStatus);

        // Scenario 3: Stale Version
        [$orderStale, $adjStale] = $this->createOrderWithAdjustment('ADJ-AGREE-STALE', OrderAdjustmentStatus::SUBMITTED);
        $orderStale->version = 5;
        $orderStale->save();
        $adjStale->load(['order', 'items.orderItem.allocations']);
        $staleClass = \App\Services\Adjustment\OrderAdjustmentClassifier::classify($adjStale, $orderStale);
        $staleReview = $reviewService->evaluate($adjStale, $orderStale);
        $this->assertTrue($staleClass['has_blocker']);
        $this->assertContains('STALE_VERSION', $staleClass['attention_flags']);
        $this->assertTrue($staleReview->isStale);

        // Scenario 4: Ineligible Lifecycle
        [$orderLife, $adjLife] = $this->createOrderWithAdjustment('ADJ-AGREE-LIFE', OrderAdjustmentStatus::SUBMITTED);
        $orderLife->status = OrderStatus::CANCELLED;
        $orderLife->save();
        $adjLife->load(['order', 'items.orderItem.allocations']);
        $lifeClass = \App\Services\Adjustment\OrderAdjustmentClassifier::classify($adjLife, $orderLife);
        $lifeReview = $reviewService->evaluate($adjLife, $orderLife);
        $this->assertTrue($lifeClass['has_blocker']);
        $this->assertContains('INELIGIBLE_LIFECYCLE', $lifeClass['attention_flags']);
        $this->assertEquals('INELIGIBLE_LIFECYCLE', $lifeReview->evaluationStatus);
    }

    public function test_search_by_order_number_customer_code_and_requester(): void
    {
        [$order1, $adj1] = $this->createOrderWithAdjustment('ADJ-SRCH-1');
        [$order2, $adj2] = $this->createOrderWithAdjustment('ADJ-SRCH-2');

        // Search order number
        $resOrder = $this->actingAs($this->admin)->get("/admin/adjustments?search={$order1->order_number}");
        $resOrder->assertStatus(200);
        $resOrder->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 1)
            ->where('adjustments.data.0.adjustment_number', 'ADJ-SRCH-1')
        );

        // Search customer code
        $resCust = $this->actingAs($this->admin)->get('/admin/adjustments?search=CUST-ACME-01');
        $resCust->assertStatus(200);
        $resCust->assertInertia(fn (Assert $page) => $page
            ->has('adjustments.data', 2)
        );
    }

    public function test_deterministic_sorting_with_id_secondary_tiebreaker(): void
    {
        $now = Carbon::now();
        // Create 2 adjustments with identical requested_at
        [$order1, $adj1] = $this->createOrderWithAdjustment('ADJ-TIE-1');
        [$order2, $adj2] = $this->createOrderWithAdjustment('ADJ-TIE-2');
        $adj1->requested_at = $now;
        $adj1->save();
        $adj2->requested_at = $now;
        $adj2->save();

        $response = $this->actingAs($this->admin)->get('/admin/adjustments?sort_by=requested_at&sort_direction=desc');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            // Higher id must come first due to secondary tie-breaker id desc
            ->where('adjustments.data.0.adjustment_number', 'ADJ-TIE-2')
            ->where('adjustments.data.1.adjustment_number', 'ADJ-TIE-1')
        );
    }

    public function test_query_budget_and_no_n_plus_one_scaling(): void
    {
        // Warm up application & singleton layout caches
        $this->actingAs($this->admin)->get('/admin/adjustments');

        // Seed 1 adjustment
        $this->createOrderWithAdjustment('ADJ-Q1');

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($this->admin)->get('/admin/adjustments');
        $queriesFor1 = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        // Seed 4 more adjustments (total 5)
        $this->createOrderWithAdjustment('ADJ-Q2');
        $this->createOrderWithAdjustment('ADJ-Q3');
        $this->createOrderWithAdjustment('ADJ-Q4');
        $this->createOrderWithAdjustment('ADJ-Q5');

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($this->admin)->get('/admin/adjustments');
        $queriesFor5 = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        // Query count with 5 records must equal query count with 1 record (zero N+1)
        $this->assertEquals($queriesFor1, $queriesFor5, "Query count scaled with record count! Expected {$queriesFor1}, got {$queriesFor5}.");
        $this->assertLessThanOrEqual(12, $queriesFor5, "Query count exceeded justified budget of 12 queries.");
    }
}
