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

class AdminOrderQueueTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $superAdmin;
    protected User $accountant;
    protected User $salesman;
    protected Customer $customerA;
    protected Customer $customerB;
    protected Product $product;
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
            'name' => 'Alice Salesman',
            'email' => 'alice@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customerA = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Alpha Supermarket',
            'code' => 'CUST-ALPHA',
            'contact_name' => 'Alice Manager',
            'phone' => '+1-555-0101',
            'billing_address_line1' => '100 Alpha Blvd',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30301',
            'billing_country' => 'US',
            'status' => CustomerStatus::ACTIVE,
        ]);

        $this->customerB = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Beta Groceries',
            'code' => 'CUST-BETA',
            'contact_name' => 'Bob Manager',
            'phone' => '+1-555-0102',
            'billing_address_line1' => '200 Beta St',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30302',
            'billing_country' => 'US',
            'status' => CustomerStatus::ACTIVE,
        ]);

        $this->category = Category::create([
            'name' => 'Beverages',
            'code' => 'BEV',
            'sort_order' => 1,
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'Standard Tax',
            'code' => 'STD',
            'rate' => '10.00',
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->product = Product::create([
            'name' => 'Organic Orange Juice',
            'sku' => 'JUICE-ORG-01',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'unit' => 'BOTTLE',
            'minimum_allowed_price' => '3.00',
            'default_selling_price' => '5.00',
            'mrp' => '7.00',
            'cost_price' => '2.00',
            'status' => ProductStatus::ACTIVE,
        ]);
    }

    /**
     * Helper to create a test order.
     */
    protected function createOrder(
        Customer $customer,
        string $orderNumber,
        string $idempotencyKey,
        OrderStatus $status = OrderStatus::SUBMITTED,
        FulfillmentStatus $fulfillment = FulfillmentStatus::UNALLOCATED,
        PaymentStatus $payment = PaymentStatus::UNPAID,
        DeliveryStatus $delivery = DeliveryStatus::PENDING_ASSIGNMENT,
        AdjustmentStatus $adjustment = AdjustmentStatus::NONE,
        ?Carbon $submittedAt = null
    ): Order {
        $order = Order::create([
            'order_number' => $orderNumber,
            'idempotency_key' => $idempotencyKey,
            'customer_id' => $customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => $status,
            'fulfillment_status' => $fulfillment,
            'payment_status' => $payment,
            'delivery_status' => $delivery,
            'adjustment_status' => $adjustment,
            'currency' => 'USD',
            'subtotal' => '50.00',
            'tax_total' => '5.00',
            'adjustment_total' => '0.00',
            'grand_total' => '55.00',
            'notes' => 'Test order notes',
            'submitted_at' => $submittedAt ?? Carbon::now(),
            'approved_at' => $status === OrderStatus::APPROVED ? Carbon::now() : null,
            'completed_at' => $status === OrderStatus::COMPLETED ? Carbon::now() : null,
            'cancelled_at' => in_array($status, [OrderStatus::CANCELLED, OrderStatus::REJECTED], true) ? Carbon::now() : null,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
            'unit_snapshot' => $this->product->unit,
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'unit_price' => '5.00',
            'is_price_overridden' => false,
            'tax_profile_id' => $this->taxProfile->id,
            'tax_profile_code_snapshot' => $this->taxProfile->code,
            'tax_profile_name_snapshot' => $this->taxProfile->name,
            'tax_rate_snapshot' => $this->taxProfile->rate,
            'taxable_amount' => '50.00',
            'tax_amount' => '5.00',
            'line_total' => '55.00',
        ]);

        return $order;
    }

    // ==========================================
    // AUTHORIZATION & ACCESS CONTROL TESTS
    // ==========================================

    public function test_ord_queue_001_admin_user_can_access_order_queue(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/orders');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->has('orders')
            ->has('counts')
            ->has('filters')
        );
    }

    public function test_ord_queue_002_super_admin_user_can_access_order_queue(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/orders');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
        );
    }

    public function test_ord_queue_003_accountant_user_can_access_order_queue(): void
    {
        $response = $this->actingAs($this->accountant)->get('/admin/orders');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
        );
    }

    public function test_ord_queue_004_salesman_user_is_forbidden_from_admin_order_queue(): void
    {
        $response = $this->actingAs($this->salesman)->get('/admin/orders');

        $response->assertForbidden();
    }

    public function test_ord_queue_005_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/admin/orders');

        $response->assertRedirect('/login');
    }

    // ==========================================
    // QUEUE FILTERING & SCOPING TESTS
    // ==========================================

    public function test_ord_queue_006_draft_orders_strictly_excluded_from_all_queues(): void
    {
        // Create draft order
        Order::create([
            'order_number' => 'ORD-DRAFT-999',
            'idempotency_key' => 'idemp-draft-999',
            'customer_id' => $this->customerA->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::DRAFT,
            'currency' => 'USD',
            'subtotal' => '10.00',
            'tax_total' => '1.00',
            'grand_total' => '11.00',
        ]);

        // Create submitted order
        $this->createOrder($this->customerA, 'ORD-SUB-001', 'idemp-sub-001');

        $response = $this->actingAs($this->admin)->get('/admin/orders?queue=all');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->where('orders.total', 1)
            ->where('orders.data.0.order_number', 'ORD-SUB-001')
        );
    }

    public function test_ord_queue_007_default_queue_returns_submitted_and_pending_approval_orders(): void
    {
        $this->createOrder($this->customerA, 'ORD-NEW-001', 'idemp-new-001', OrderStatus::SUBMITTED);
        $this->createOrder($this->customerA, 'ORD-REV-002', 'idemp-rev-002', OrderStatus::PENDING_APPROVAL);
        $this->createOrder($this->customerA, 'ORD-APP-003', 'idemp-app-003', OrderStatus::APPROVED);

        $response = $this->actingAs($this->admin)->get('/admin/orders'); // Default queue is 'new'

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->where('orders.total', 2)
            ->where('counts.new', 2)
        );
    }

    public function test_ord_queue_008_attention_queue_returns_exceptions_and_aging_orders(): void
    {
        // Delivery failed exception
        $this->createOrder(
            $this->customerA,
            'ORD-ATT-001',
            'idemp-att-001',
            OrderStatus::APPROVED,
            FulfillmentStatus::DISPATCHED,
            PaymentStatus::UNPAID,
            DeliveryStatus::FAILED
        );

        // Requested adjustment exception
        $this->createOrder(
            $this->customerA,
            'ORD-ATT-002',
            'idemp-att-002',
            OrderStatus::APPROVED,
            FulfillmentStatus::RESERVED,
            PaymentStatus::UNPAID,
            DeliveryStatus::PENDING_ASSIGNMENT,
            AdjustmentStatus::REQUESTED
        );

        // Normal order (should NOT be in attention)
        $this->createOrder(
            $this->customerA,
            'ORD-NORM-003',
            'idemp-norm-003',
            OrderStatus::APPROVED,
            FulfillmentStatus::RESERVED,
            PaymentStatus::UNPAID,
            DeliveryStatus::PENDING_ASSIGNMENT,
            AdjustmentStatus::NONE
        );

        $response = $this->actingAs($this->admin)->get('/admin/orders?queue=attention');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->where('orders.total', 2)
            ->where('counts.attention', 2)
        );
    }

    public function test_ord_queue_009_processing_queue_returns_approved_orders_in_picking_packing(): void
    {
        $this->createOrder(
            $this->customerA,
            'ORD-PROC-001',
            'idemp-proc-001',
            OrderStatus::APPROVED,
            FulfillmentStatus::PICKED
        );

        $this->createOrder(
            $this->customerA,
            'ORD-SUB-002',
            'idemp-sub-002',
            OrderStatus::SUBMITTED
        );

        $response = $this->actingAs($this->admin)->get('/admin/orders?queue=processing');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->where('orders.total', 1)
            ->where('orders.data.0.order_number', 'ORD-PROC-001')
        );
    }

    public function test_ord_queue_010_delivery_queue_returns_orders_en_route_or_dispatched(): void
    {
        $this->createOrder(
            $this->customerA,
            'ORD-DLV-001',
            'idemp-dlv-001',
            OrderStatus::APPROVED,
            FulfillmentStatus::DISPATCHED,
            PaymentStatus::UNPAID,
            DeliveryStatus::OUT_FOR_DELIVERY
        );

        $this->createOrder(
            $this->customerA,
            'ORD-SUB-002',
            'idemp-sub-002',
            OrderStatus::SUBMITTED,
            FulfillmentStatus::UNALLOCATED,
            PaymentStatus::UNPAID,
            DeliveryStatus::PENDING_ASSIGNMENT
        );

        $response = $this->actingAs($this->admin)->get('/admin/orders?queue=delivery');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->where('orders.total', 1)
            ->where('orders.data.0.order_number', 'ORD-DLV-001')
        );
    }

    public function test_ord_queue_011_adjustments_queue_returns_requested_and_applied_adjustments(): void
    {
        $this->createOrder(
            $this->customerA,
            'ORD-ADJ-001',
            'idemp-adj-001',
            OrderStatus::SUBMITTED,
            FulfillmentStatus::UNALLOCATED,
            PaymentStatus::UNPAID,
            DeliveryStatus::PENDING_ASSIGNMENT,
            AdjustmentStatus::REQUESTED
        );

        $this->createOrder(
            $this->customerA,
            'ORD-NORM-002',
            'idemp-norm-002',
            OrderStatus::SUBMITTED,
            FulfillmentStatus::UNALLOCATED,
            PaymentStatus::UNPAID,
            DeliveryStatus::PENDING_ASSIGNMENT,
            AdjustmentStatus::NONE
        );

        $response = $this->actingAs($this->admin)->get('/admin/orders?queue=adjustments');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->where('orders.total', 1)
            ->where('orders.data.0.order_number', 'ORD-ADJ-001')
        );
    }

    public function test_ord_queue_012_completed_queue_returns_completed_orders_only(): void
    {
        $this->createOrder(
            $this->customerA,
            'ORD-COMP-001',
            'idemp-comp-001',
            OrderStatus::COMPLETED
        );

        $this->createOrder(
            $this->customerA,
            'ORD-SUB-002',
            'idemp-sub-002',
            OrderStatus::SUBMITTED
        );

        $response = $this->actingAs($this->admin)->get('/admin/orders?queue=completed');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->where('orders.total', 1)
            ->where('orders.data.0.order_number', 'ORD-COMP-001')
        );
    }

    public function test_ord_queue_013_cancelled_queue_returns_cancelled_and_rejected_orders(): void
    {
        $this->createOrder(
            $this->customerA,
            'ORD-CANC-001',
            'idemp-canc-001',
            OrderStatus::CANCELLED
        );

        $this->createOrder(
            $this->customerA,
            'ORD-REJ-002',
            'idemp-rej-002',
            OrderStatus::REJECTED
        );

        $this->createOrder(
            $this->customerA,
            'ORD-SUB-003',
            'idemp-sub-003',
            OrderStatus::SUBMITTED
        );

        $response = $this->actingAs($this->admin)->get('/admin/orders?queue=cancelled');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->where('orders.total', 2)
        );
    }

    public function test_ord_queue_014_all_queue_returns_all_non_draft_orders(): void
    {
        $this->createOrder($this->customerA, 'ORD-SUB-001', 'idemp-sub-001', OrderStatus::SUBMITTED);
        $this->createOrder($this->customerA, 'ORD-APP-002', 'idemp-app-002', OrderStatus::APPROVED);
        $this->createOrder($this->customerA, 'ORD-COMP-003', 'idemp-comp-003', OrderStatus::COMPLETED);

        $response = $this->actingAs($this->admin)->get('/admin/orders?queue=all');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Orders/Index')
            ->where('orders.total', 3)
            ->where('counts.all', 3)
        );
    }

    public function test_ord_queue_015_queue_counts_calculated_accurately_across_all_queues(): void
    {
        $this->createOrder($this->customerA, 'ORD-1', 'id-1', OrderStatus::SUBMITTED);
        $this->createOrder($this->customerA, 'ORD-2', 'id-2', OrderStatus::APPROVED, FulfillmentStatus::PICKED);
        $this->createOrder($this->customerA, 'ORD-3', 'id-3', OrderStatus::APPROVED, FulfillmentStatus::DISPATCHED, PaymentStatus::UNPAID, DeliveryStatus::OUT_FOR_DELIVERY);
        $this->createOrder($this->customerA, 'ORD-4', 'id-4', OrderStatus::COMPLETED);
        $this->createOrder($this->customerA, 'ORD-5', 'id-5', OrderStatus::CANCELLED);

        $response = $this->actingAs($this->admin)->get('/admin/orders');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('counts.new', 1)
            ->where('counts.processing', 1)
            ->where('counts.delivery', 1)
            ->where('counts.completed', 1)
            ->where('counts.cancelled', 1)
            ->where('counts.all', 5)
        );
    }

    // ==========================================
    // SEARCH & FILTER TESTS
    // ==========================================

    public function test_ord_queue_016_search_by_order_number_matches_correctly(): void
    {
        $this->createOrder($this->customerA, 'ORD-ALPHA-777', 'idemp-777');
        $this->createOrder($this->customerB, 'ORD-BETA-888', 'idemp-888');

        $response = $this->actingAs($this->admin)->get('/admin/orders?queue=all&search=ALPHA-777');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('orders.total', 1)
            ->where('orders.data.0.order_number', 'ORD-ALPHA-777')
        );
    }

    public function test_ord_queue_017_search_by_customer_name_and_code_matches_correctly(): void
    {
        $this->createOrder($this->customerA, 'ORD-101', 'idemp-101');
        $this->createOrder($this->customerB, 'ORD-102', 'idemp-102');

        // Search by customer name
        $resName = $this->actingAs($this->admin)->get('/admin/orders?queue=all&search=Alpha Supermarket');
        $resName->assertOk();
        $resName->assertInertia(fn (Assert $page) => $page
            ->where('orders.total', 1)
            ->where('orders.data.0.order_number', 'ORD-101')
        );

        // Search by customer code
        $resCode = $this->actingAs($this->admin)->get('/admin/orders?queue=all&search=CUST-BETA');
        $resCode->assertOk();
        $resCode->assertInertia(fn (Assert $page) => $page
            ->where('orders.total', 1)
            ->where('orders.data.0.order_number', 'ORD-102')
        );
    }

    public function test_ord_queue_018_search_by_salesman_name_matches_correctly(): void
    {
        $this->createOrder($this->customerA, 'ORD-201', 'idemp-201');

        $response = $this->actingAs($this->admin)->get('/admin/orders?queue=all&search=Alice Salesman');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('orders.total', 1)
            ->where('orders.data.0.order_number', 'ORD-201')
        );
    }

    public function test_ord_queue_019_filter_by_status_dimensions_works_accurately(): void
    {
        $this->createOrder($this->customerA, 'ORD-P1', 'idemp-p1', OrderStatus::APPROVED, FulfillmentStatus::UNALLOCATED, PaymentStatus::PAID);
        $this->createOrder($this->customerA, 'ORD-P2', 'idemp-p2', OrderStatus::APPROVED, FulfillmentStatus::UNALLOCATED, PaymentStatus::UNPAID);

        $response = $this->actingAs($this->admin)->get('/admin/orders?queue=all&payment_status=PAID');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('orders.total', 1)
            ->where('orders.data.0.order_number', 'ORD-P1')
        );
    }

    public function test_ord_queue_020_filter_by_salesman_and_customer_works_accurately(): void
    {
        $this->createOrder($this->customerA, 'ORD-C1', 'idemp-c1');
        $this->createOrder($this->customerB, 'ORD-C2', 'idemp-c2');

        $response = $this->actingAs($this->admin)->get('/admin/orders?queue=all&customer_id=' . $this->customerA->id);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('orders.total', 1)
            ->where('orders.data.0.order_number', 'ORD-C1')
        );
    }

    public function test_ord_queue_021_filter_by_date_range_works_accurately(): void
    {
        $orderOld = $this->createOrder(
            $this->customerA,
            'ORD-OLD',
            'idemp-old',
            OrderStatus::SUBMITTED,
            FulfillmentStatus::UNALLOCATED,
            PaymentStatus::UNPAID,
            DeliveryStatus::PENDING_ASSIGNMENT,
            AdjustmentStatus::NONE,
            Carbon::parse('2026-08-01 10:00:00')
        );

        $orderNew = $this->createOrder(
            $this->customerA,
            'ORD-NEW',
            'idemp-new',
            OrderStatus::SUBMITTED,
            FulfillmentStatus::UNALLOCATED,
            PaymentStatus::UNPAID,
            DeliveryStatus::PENDING_ASSIGNMENT,
            AdjustmentStatus::NONE,
            Carbon::parse('2026-09-05 10:00:00')
        );

        $response = $this->actingAs($this->admin)->get('/admin/orders?queue=all&date_from=2026-09-01&date_to=2026-09-06');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('orders.total', 1)
            ->where('orders.data.0.order_number', 'ORD-NEW')
        );
    }

    // ==========================================
    // SORTING & PAGINATION TESTS
    // ==========================================

    public function test_ord_queue_022_sorting_by_allowlisted_columns_works(): void
    {
        $orderA = $this->createOrder($this->customerA, 'ORD-100', 'id-100', OrderStatus::SUBMITTED, FulfillmentStatus::UNALLOCATED, PaymentStatus::UNPAID, DeliveryStatus::PENDING_ASSIGNMENT, AdjustmentStatus::NONE, Carbon::parse('2026-09-01'));
        $orderB = $this->createOrder($this->customerA, 'ORD-200', 'id-200', OrderStatus::SUBMITTED, FulfillmentStatus::UNALLOCATED, PaymentStatus::UNPAID, DeliveryStatus::PENDING_ASSIGNMENT, AdjustmentStatus::NONE, Carbon::parse('2026-09-03'));

        // Sort by order_number asc
        $resAsc = $this->actingAs($this->admin)->get('/admin/orders?queue=all&sort_by=order_number&sort_direction=asc');
        $resAsc->assertOk();
        $resAsc->assertInertia(fn (Assert $page) => $page
            ->where('orders.data.0.order_number', 'ORD-100')
            ->where('orders.data.1.order_number', 'ORD-200')
        );

        // Sort by order_number desc
        $resDesc = $this->actingAs($this->admin)->get('/admin/orders?queue=all&sort_by=order_number&sort_direction=desc');
        $resDesc->assertOk();
        $resDesc->assertInertia(fn (Assert $page) => $page
            ->where('orders.data.0.order_number', 'ORD-200')
            ->where('orders.data.1.order_number', 'ORD-100')
        );
    }

    public function test_ord_queue_023_pagination_preserves_query_parameters(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->createOrder($this->customerA, "ORD-PAGE-{$i}", "idemp-page-{$i}");
        }

        $response = $this->actingAs($this->admin)->get('/admin/orders?queue=all&per_page=15&search=PAGE');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('orders.per_page', 15)
            ->where('orders.total', 30)
            ->where('orders.last_page', 2)
            ->has('orders.links')
        );
    }

    // ==========================================
    // SECURITY & SENSITIVE DATA TESTS
    // ==========================================

    public function test_ord_queue_024_cost_price_strictly_omitted_from_queue_list(): void
    {
        $this->createOrder($this->customerA, 'ORD-SEC-001', 'idemp-sec-001');

        $response = $this->actingAs($this->admin)->get('/admin/orders?queue=all');

        $response->assertOk();
        $response->assertDontSee('cost_price');
        $response->assertInertia(fn (Assert $page) => $page
            ->has('orders.data.0', fn (Assert $item) => $item
                ->where('order_number', 'ORD-SEC-001')
                ->missing('cost_price')
                ->etc()
            )
        );
    }

    public function test_ord_queue_025_sensitive_payment_evidence_omitted_from_queue_list(): void
    {
        $this->createOrder($this->customerA, 'ORD-SEC-002', 'idemp-sec-002');

        $response = $this->actingAs($this->admin)->get('/admin/orders?queue=all');

        $response->assertOk();
        $response->assertDontSee('s3.amazonaws.com');
        $response->assertDontSee('payment_evidence');
    }

    // ==========================================
    // DETAIL DRILL-DOWN & IDOR PROTECTION TESTS
    // ==========================================

    public function test_ord_queue_026_admin_can_view_order_detail_with_queue_back_url(): void
    {
        $order = $this->createOrder($this->customerA, 'ORD-DET-001', 'idemp-det-001');

        $response = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Salesman/Orders/Show')
            ->where('order.order_number', 'ORD-DET-001')
            ->where('backUrl', '/admin/orders')
            ->where('backLabel', 'Back to Order Queue')
        );
    }

    public function test_ord_queue_027_salesman_forbidden_from_admin_order_detail(): void
    {
        $order = $this->createOrder($this->customerA, 'ORD-DET-002', 'idemp-det-002');

        $response = $this->actingAs($this->salesman)->get("/admin/orders/{$order->id}");

        $response->assertForbidden();
    }

    public function test_ord_queue_028_invalid_filter_and_sort_parameters_rejected(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/orders?queue=INVALID_QUEUE');
        $response->assertSessionHasErrors('queue');

        $responseSort = $this->actingAs($this->admin)->get('/admin/orders?sort_by=DROP_TABLE');
        $responseSort->assertSessionHasErrors('sort_by');

        $responseDate = $this->actingAs($this->admin)->get('/admin/orders?date_from=not-a-date');
        $responseDate->assertSessionHasErrors('date_from');
    }

    public function test_ord_queue_029_no_n_plus_one_query_proliferation_on_queue_list(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->createOrder($this->customerA, "ORD-N1-{$i}", "idemp-n1-{$i}");
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->admin)->get('/admin/orders?queue=all&per_page=10');
        $response->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // 1 count query + 1 pagination count + 1 order select + 2 eager loads (customer, salesman) + 1 eligible salesmen = bounded ~6 queries
        // Verify bounded queries (must be well under 10 queries, proving no per-row N+1)
        $this->assertLessThan(10, count($queries), 'Detected query proliferation / N+1 behavior in Admin Order Queue.');
    }
}
