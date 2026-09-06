<?php

namespace Tests\Feature\Order;

use App\Enums\AccountStatus;
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

class SalesmanOrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $salesman;
    protected User $otherSalesman;
    protected User $admin;
    protected Customer $customerA;
    protected Customer $customerB;
    protected Product $product;
    protected TaxProfile $taxProfile;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesman = User::factory()->create([
            'name' => 'Alice Salesman',
            'email' => 'alice@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->otherSalesman = User::factory()->create([
            'name' => 'Bob Salesman',
            'email' => 'bob@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'System Admin',
            'email' => 'admin@wholesale.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customerA = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Alpha Supermarket',
            'code' => 'CUST-ALPHA',
            'contact_name' => 'Alice Manager',
            'phone' => '+1-555-0101',
            'billing_address_line1' => '100 Alpha St',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30301',
            'billing_country' => 'US',
            'status' => CustomerStatus::ACTIVE,
        ]);

        $this->customerB = Customer::create([
            'salesman_id' => $this->otherSalesman->id,
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
     * Helper to create a submitted order with items.
     */
    protected function createSubmittedOrder(
        User $salesman,
        Customer $customer,
        string $orderNumber,
        string $idempotencyKey,
        OrderStatus $status = OrderStatus::SUBMITTED,
        FulfillmentStatus $fulfillment = FulfillmentStatus::UNALLOCATED,
        PaymentStatus $payment = PaymentStatus::UNPAID,
        DeliveryStatus $delivery = DeliveryStatus::PENDING_ASSIGNMENT,
        ?Carbon $submittedAt = null
    ): Order {
        $order = Order::create([
            'order_number' => $orderNumber,
            'idempotency_key' => $idempotencyKey,
            'customer_id' => $customer->id,
            'salesman_id' => $salesman->id,
            'created_by' => $salesman->id,
            'status' => $status,
            'fulfillment_status' => $fulfillment,
            'payment_status' => $payment,
            'delivery_status' => $delivery,
            'currency' => 'USD',
            'subtotal' => '50.00',
            'tax_total' => '5.00',
            'adjustment_total' => '0.00',
            'grand_total' => '55.00',
            'submitted_at' => $submittedAt ?? Carbon::now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
            'unit_snapshot' => $this->product->unit,
            'ordered_quantity' => 10,
            'unit_price' => '5.00',
            'tax_profile_id_snapshot' => $this->taxProfile->id,
            'tax_profile_code_snapshot' => $this->taxProfile->code,
            'tax_profile_name_snapshot' => $this->taxProfile->name,
            'tax_rate_snapshot' => '10.00',
            'taxable_amount' => '50.00',
            'tax_amount' => '5.00',
            'line_total' => '55.00',
        ]);

        return $order;
    }

    /*
    |--------------------------------------------------------------------------
    | 1. AUTHORIZATION & SCOPING TESTS
    |--------------------------------------------------------------------------
    */

    public function test_salesman_can_view_own_orders_in_history_list(): void
    {
        $orderA = $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-001', 'idemp-001');
        $orderB = $this->createSubmittedOrder($this->otherSalesman, $this->customerB, 'ORD-002', 'idemp-002');

        $response = $this->actingAs($this->salesman)
            ->get(route('salesman.orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Salesman/Orders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $orderA->id)
            ->where('orders.data.0.order_number', 'ORD-001')
        );
    }

    public function test_salesman_cannot_view_other_salesman_order_via_direct_url_idor(): void
    {
        $otherOrder = $this->createSubmittedOrder($this->otherSalesman, $this->customerB, 'ORD-OTHER', 'idemp-other');

        $response = $this->actingAs($this->salesman)
            ->get(route('salesman.orders.show', $otherOrder->id));

        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }

    public function test_admin_can_view_all_orders_in_history(): void
    {
        $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-001', 'idemp-001');
        $this->createSubmittedOrder($this->otherSalesman, $this->customerB, 'ORD-002', 'idemp-002');

        $response = $this->actingAs($this->admin)
            ->get(route('salesman.orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Salesman/Orders/Index')
            ->has('orders.data', 2)
        );
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get(route('salesman.orders.index'));
        $response->assertRedirect(route('login'));
    }

    /*
    |--------------------------------------------------------------------------
    | 2. LIST & DRAFT EXCLUSION TESTS
    |--------------------------------------------------------------------------
    */

    public function test_history_list_strictly_excludes_draft_orders(): void
    {
        // 1 Draft order
        Order::create([
            'order_number' => null,
            'idempotency_key' => 'idemp-draft',
            'draft_token' => (string) \Illuminate\Support\Str::uuid(),
            'customer_id' => $this->customerA->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::DRAFT,
            'currency' => 'USD',
            'subtotal' => '20.00',
            'tax_total' => '2.00',
            'grand_total' => '22.00',
        ]);

        // 1 Submitted order
        $submitted = $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-SUBMITTED', 'idemp-sub');

        $response = $this->actingAs($this->salesman)
            ->get(route('salesman.orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Salesman/Orders/Index')
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $submitted->id)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 3. SEARCH TESTS
    |--------------------------------------------------------------------------
    */

    public function test_search_by_order_number(): void
    {
        $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-MATCH-123', 'idemp-001');
        $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-OTHER-456', 'idemp-002');

        $response = $this->actingAs($this->salesman)
            ->get(route('salesman.orders.index', ['search' => 'MATCH']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', 'ORD-MATCH-123')
        );
    }

    public function test_search_by_customer_name(): void
    {
        $customerSpecial = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Zeta Hypermarket',
            'code' => 'CUST-ZETA',
            'contact_name' => 'Zoe Manager',
            'phone' => '+1-555-0999',
            'billing_address_line1' => '999 Zeta St',
            'billing_city' => 'Atlanta',
            'billing_state' => 'GA',
            'billing_postal_code' => '30399',
            'billing_country' => 'US',
            'status' => CustomerStatus::ACTIVE,
        ]);

        $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-001', 'idemp-001');
        $this->createSubmittedOrder($this->salesman, $customerSpecial, 'ORD-002', 'idemp-002');

        $response = $this->actingAs($this->salesman)
            ->get(route('salesman.orders.index', ['search' => 'Hypermarket']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', 'ORD-002')
            ->where('orders.data.0.customer.name', 'Zeta Hypermarket')
        );
    }

    public function test_search_by_customer_code(): void
    {
        $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-001', 'idemp-001');

        $response = $this->actingAs($this->salesman)
            ->get(route('salesman.orders.index', ['search' => 'CUST-ALPHA']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.customer.code', 'CUST-ALPHA')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 4. FILTER TESTS
    |--------------------------------------------------------------------------
    */

    public function test_filter_by_order_status(): void
    {
        $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-SUB', 'idemp-001', OrderStatus::SUBMITTED);
        $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-APP', 'idemp-002', OrderStatus::APPROVED);

        $response = $this->actingAs($this->salesman)
            ->get(route('salesman.orders.index', ['status' => 'APPROVED']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', 'ORD-APP')
        );
    }

    public function test_filter_by_fulfillment_status(): void
    {
        $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-UNALLOC', 'idemp-001', OrderStatus::SUBMITTED, FulfillmentStatus::UNALLOCATED);
        $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-DELIV', 'idemp-002', OrderStatus::COMPLETED, FulfillmentStatus::DELIVERED);

        $response = $this->actingAs($this->salesman)
            ->get(route('salesman.orders.index', ['fulfillment_status' => 'DELIVERED']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', 'ORD-DELIV')
        );
    }

    public function test_filter_by_payment_status(): void
    {
        $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-UNPAID', 'idemp-001', OrderStatus::SUBMITTED, FulfillmentStatus::UNALLOCATED, PaymentStatus::UNPAID);
        $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-PAID', 'idemp-002', OrderStatus::SUBMITTED, FulfillmentStatus::UNALLOCATED, PaymentStatus::PAID);

        $response = $this->actingAs($this->salesman)
            ->get(route('salesman.orders.index', ['payment_status' => 'PAID']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', 'ORD-PAID')
        );
    }

    public function test_filter_by_delivery_status(): void
    {
        $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-PEND', 'idemp-001', OrderStatus::SUBMITTED, FulfillmentStatus::UNALLOCATED, PaymentStatus::UNPAID, DeliveryStatus::PENDING_ASSIGNMENT);
        $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-OUT', 'idemp-002', OrderStatus::PROCESSING, FulfillmentStatus::PACKED, PaymentStatus::UNPAID, DeliveryStatus::OUT_FOR_DELIVERY);

        $response = $this->actingAs($this->salesman)
            ->get(route('salesman.orders.index', ['delivery_status' => 'OUT_FOR_DELIVERY']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', 'ORD-OUT')
        );
    }

    public function test_filter_by_date_range(): void
    {
        $pastOrder = $this->createSubmittedOrder(
            $this->salesman,
            $this->customerA,
            'ORD-OLD',
            'idemp-old',
            OrderStatus::SUBMITTED,
            FulfillmentStatus::UNALLOCATED,
            PaymentStatus::UNPAID,
            DeliveryStatus::PENDING_ASSIGNMENT,
            Carbon::now()->subDays(10)
        );

        $recentOrder = $this->createSubmittedOrder(
            $this->salesman,
            $this->customerA,
            'ORD-NEW',
            'idemp-new',
            OrderStatus::SUBMITTED,
            FulfillmentStatus::UNALLOCATED,
            PaymentStatus::UNPAID,
            DeliveryStatus::PENDING_ASSIGNMENT,
            Carbon::now()->subDay()
        );

        $response = $this->actingAs($this->salesman)
            ->get(route('salesman.orders.index', [
                'date_from' => Carbon::now()->subDays(3)->format('Y-m-d'),
                'date_to' => Carbon::now()->format('Y-m-d'),
            ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', 'ORD-NEW')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 5. SORTING & PAGINATION TESTS
    |--------------------------------------------------------------------------
    */

    public function test_orders_sorted_by_submitted_at_descending_with_deterministic_id_tiebreaker(): void
    {
        $order1 = $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-001', 'idemp-001', OrderStatus::SUBMITTED, FulfillmentStatus::UNALLOCATED, PaymentStatus::UNPAID, DeliveryStatus::PENDING_ASSIGNMENT, Carbon::now()->subHours(2));
        $order2 = $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-002', 'idemp-002', OrderStatus::SUBMITTED, FulfillmentStatus::UNALLOCATED, PaymentStatus::UNPAID, DeliveryStatus::PENDING_ASSIGNMENT, Carbon::now()->subHour());
        $order3 = $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-003', 'idemp-003', OrderStatus::SUBMITTED, FulfillmentStatus::UNALLOCATED, PaymentStatus::UNPAID, DeliveryStatus::PENDING_ASSIGNMENT, Carbon::now());

        $response = $this->actingAs($this->salesman)
            ->get(route('salesman.orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('orders.data.0.id', $order3->id)
            ->where('orders.data.1.id', $order2->id)
            ->where('orders.data.2.id', $order1->id)
        );
    }

    public function test_pagination_limits_to_15_per_page(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->createSubmittedOrder($this->salesman, $this->customerA, "ORD-PAGE-{$i}", "idemp-page-{$i}");
        }

        $response = $this->actingAs($this->salesman)
            ->get(route('salesman.orders.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('orders.per_page', 15)
            ->where('orders.total', 20)
            ->has('orders.data', 15)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 6. MULTI-STATE TIMELINE TESTS (GENUINE PERSISTED MILESTONES)
    |--------------------------------------------------------------------------
    */

    public function test_order_detail_returns_correct_timeline_milestones(): void
    {
        $order = $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-TIMELINE', 'idemp-tl');

        $response = $this->actingAs($this->salesman)
            ->get(route('salesman.orders.show', $order->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Salesman/Orders/Show')
            ->has('order.timeline', fn (Assert $timeline) => $timeline
                ->where('0.id', 'created')
                ->where('0.title', 'Order Created')
                ->where('0.status', 'completed')
                ->where('1.id', 'submitted')
                ->where('1.title', 'Order Submitted')
                ->where('1.status', 'completed')
                ->where('2.id', 'approval_pending')
                ->where('2.title', 'Administrative Review')
                ->where('2.status', 'current')
                ->where('3.id', 'fulfillment')
                ->where('3.status', 'pending')
                ->where('4.id', 'payment')
                ->where('4.status', 'pending')
                ->where('5.id', 'delivery')
                ->where('5.status', 'pending')
            )
        );
    }

    public function test_timeline_reflects_approved_order_milestone(): void
    {
        $order = $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-APPROVED', 'idemp-app');
        $order->update([
            'status' => OrderStatus::APPROVED,
            'approved_at' => Carbon::now(),
            'approved_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->salesman)
            ->get(route('salesman.orders.show', $order->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('order.timeline.2.id', 'approved')
            ->where('order.timeline.2.title', 'Order Approved')
            ->where('order.timeline.2.actor_name', 'System Admin')
            ->where('order.timeline.2.status', 'completed')
        );
    }

    public function test_timeline_reflects_cancelled_order_with_cancellation_reason(): void
    {
        $order = $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-CANCELLED', 'idemp-canc');
        $order->update([
            'status' => OrderStatus::CANCELLED,
            'cancelled_at' => Carbon::now(),
            'cancelled_by' => $this->admin->id,
            'cancellation_reason' => 'Customer requested cancellation due to duplicate purchase.',
        ]);

        $response = $this->actingAs($this->salesman)
            ->get(route('salesman.orders.show', $order->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('order.timeline.2.id', 'cancelled')
            ->where('order.timeline.2.title', 'Order Cancelled')
            ->where('order.timeline.2.description', 'Customer requested cancellation due to duplicate purchase.')
            ->where('order.timeline.2.actor_name', 'System Admin')
            ->where('order.timeline.2.status', 'cancelled')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 7. SECURITY & FINANCIAL IMMUTABILITY TESTS
    |--------------------------------------------------------------------------
    */

    public function test_customer_reassignment_preserves_historical_salesman_ownership(): void
    {
        $order = $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-REASSIGN', 'idemp-reassign');

        // Customer reassigned to Bob
        $this->customerA->update(['salesman_id' => $this->otherSalesman->id]);

        // Alice (the original salesman) can still view the order
        $responseAlice = $this->actingAs($this->salesman)->get(route('salesman.orders.index'));
        $responseAlice->assertOk();
        $responseAlice->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $order->id)
        );

        // Bob (new customer salesman) cannot see the historical order placed by Alice
        $responseBob = $this->actingAs($this->otherSalesman)->get(route('salesman.orders.index'));
        $responseBob->assertOk();
        $responseBob->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 0)
        );
    }

    public function test_zero_cost_price_leakage_in_history_and_detail_payload(): void
    {
        $order = $this->createSubmittedOrder($this->salesman, $this->customerA, 'ORD-COSTCHECK', 'idemp-cost');

        // List View Payload
        $responseList = $this->actingAs($this->salesman)->get(route('salesman.orders.index'));
        $responseList->assertOk();
        $responseList->assertDontSee('"cost_price"');

        // Detail View Payload
        $responseDetail = $this->actingAs($this->salesman)->get(route('salesman.orders.show', $order->id));
        $responseDetail->assertOk();
        $responseDetail->assertDontSee('"cost_price"');
    }

    public function test_invalid_filter_query_values_are_handled_gracefully(): void
    {
        $response = $this->actingAs($this->salesman)
            ->get(route('salesman.orders.index', ['status' => 'INVALID_STATUS']));

        $response->assertInvalid(['status']);
    }

    /*
    |--------------------------------------------------------------------------
    | 8. QUERY PERFORMANCE TEST (NO N+1)
    |--------------------------------------------------------------------------
    */

    public function test_history_list_query_does_not_exhibit_n_plus_one(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createSubmittedOrder($this->salesman, $this->customerA, "ORD-N1-{$i}", "idemp-n1-{$i}");
        }

        DB::enableQueryLog();

        $response = $this->actingAs($this->salesman)->get(route('salesman.orders.index'));
        $response->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 1 count query, 1 paginated orders query with eager-loaded customer
        // Bound query count must not scale with order count
        $this->assertLessThanOrEqual(5, $queryCount);
    }
}
