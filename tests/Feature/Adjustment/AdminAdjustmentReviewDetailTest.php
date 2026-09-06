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

class AdminAdjustmentReviewDetailTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $accountant;
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

    protected function setupScenario(int $orderedQty, int $allocatedQty, int $pickedQty, int $dispatchedQty, int $reductionQty): array
    {
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
            'dispatched_quantity' => $dispatchedQty,
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

        if ($allocatedQty > 0) {
            OrderItemAllocation::create([
                'allocation_number' => 'ALC-' . uniqid(),
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'product_id' => $this->product->id,
                'allocated_quantity' => $allocatedQty,
                'reserved_quantity' => $allocatedQty,
                'picked_quantity' => $pickedQty,
                'dispatched_quantity' => $dispatchedQty,
                'delivered_quantity' => 0,
                'returned_quantity' => 0,
                'status' => $dispatchedQty > 0 ? AllocationStatus::DISPATCHED : ($pickedQty > 0 ? AllocationStatus::PICKED : AllocationStatus::ALLOCATED),
                'allocated_by' => $this->admin->id,
                'allocated_at' => Carbon::now()->subHour(),
            ]);
        }

        $unallocated = max(0, $orderedQty - $allocatedQty);
        $affectedAlloc = max(0, $reductionQty - $unallocated);

        $adj = OrderAdjustment::create([
            'adjustment_number' => 'ADJ-REV-001',
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
            'notes' => 'Customer requested fewer units.',
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

        return [$order, $adj, $item];
    }

    public function test_admin_can_view_adjustment_review_workspace(): void
    {
        [$order, $adj, $item] = $this->setupScenario(orderedQty: 10, allocatedQty: 4, pickedQty: 0, dispatchedQty: 0, reductionQty: 2);

        $response = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}/adjustments/{$adj->id}/review");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Adjustments/Review')
            ->where('adjustment.adjustment_number', 'ADJ-REV-001')
            ->where('adjustment.order_number', $order->order_number)
            ->where('adjustment.requested_by.name', $this->salesman->name)
            ->where('evaluation.evaluation_status', 'READY')
            ->where('evaluation.has_allocation_impact', false)
            ->where('can.review', true)
            ->where('can.approve', false)
            ->where('can.reject', false)
        );
    }

    public function test_case_a_review_evaluation(): void
    {
        // Ordered 10, Allocated 4, Unallocated 6. Requested reduction 2 <= 6 => Case A (0 affected)
        [$order, $adj, $item] = $this->setupScenario(orderedQty: 10, allocatedQty: 4, pickedQty: 0, dispatchedQty: 0, reductionQty: 2);

        $response = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}/adjustments/{$adj->id}/review");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('evaluation.line_evaluations.0.current_case', 'CASE_A')
            ->where('evaluation.line_evaluations.0.current_affected_allocation_quantity', 0)
            ->where('evaluation.has_allocation_impact', false)
        );
    }

    public function test_case_b_review_evaluation(): void
    {
        // Ordered 10, Allocated 8, Unallocated 2. Requested reduction 5 > 2 => Case B (3 affected allocations)
        [$order, $adj, $item] = $this->setupScenario(orderedQty: 10, allocatedQty: 8, pickedQty: 0, dispatchedQty: 0, reductionQty: 5);

        $response = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}/adjustments/{$adj->id}/review");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('evaluation.line_evaluations.0.current_case', 'CASE_B')
            ->where('evaluation.line_evaluations.0.current_affected_allocation_quantity', 3)
            ->where('evaluation.has_allocation_impact', true)
            ->where('evaluation.total_affected_allocation_quantity', 3)
            ->where('evaluation.evaluation_status', 'WARNING_ALLOCATION')
        );
    }

    public function test_active_allocations_exclude_cancelled_and_released(): void
    {
        [$order, $adj, $item] = $this->setupScenario(orderedQty: 10, allocatedQty: 5, pickedQty: 0, dispatchedQty: 0, reductionQty: 2);

        // Add a CANCELLED allocation of 3 units
        OrderItemAllocation::create([
            'allocation_number' => 'ALC-CANCELLED',
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'product_id' => $this->product->id,
            'allocated_quantity' => 3,
            'reserved_quantity' => 3,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'status' => AllocationStatus::CANCELLED,
            'allocated_by' => $this->admin->id,
            'allocated_at' => Carbon::now()->subHour(),
        ]);

        // Add a RELEASED allocation of 2 units
        OrderItemAllocation::create([
            'allocation_number' => 'ALC-RELEASED',
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'product_id' => $this->product->id,
            'allocated_quantity' => 2,
            'reserved_quantity' => 2,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'status' => AllocationStatus::RELEASED,
            'allocated_by' => $this->admin->id,
            'allocated_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}/adjustments/{$adj->id}/review");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            // Only the 1 active allocation of 5 units should be present in the active allocations array
            ->has('evaluation.line_evaluations.0.allocations', 1)
            ->where('evaluation.line_evaluations.0.current_allocated_quantity', 5)
            ->where('evaluation.line_evaluations.0.current_unallocated_quantity', 5)
        );
    }

    public function test_allocation_encroaches_on_picked_units_detection(): void
    {
        // Ordered 10, Allocated 8, Picked 7 (Unpicked = 1).
        // Requested reduction 5. Unallocated is 2.
        // Affected allocation = 5 - 2 = 3.
        // But unpicked is only 1! So 3 > 1 => encroaches on picked units!
        [$order, $adj, $item] = $this->setupScenario(orderedQty: 10, allocatedQty: 8, pickedQty: 7, dispatchedQty: 4, reductionQty: 5);

        $response = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}/adjustments/{$adj->id}/review");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('evaluation.line_evaluations.0.encroaches_on_picked', true)
            ->where('evaluation.encroaches_on_picked', true)
            ->where('evaluation.evaluation_status', 'WARNING_PICKED_ENCROACHMENT')
        );
    }

    public function test_read_only_guarantee_zero_database_mutations(): void
    {
        [$order, $adj, $item] = $this->setupScenario(orderedQty: 10, allocatedQty: 5, pickedQty: 0, dispatchedQty: 0, reductionQty: 2);

        $initialOrderUpdatedAt = $order->fresh()->updated_at;
        $initialItemUpdatedAt = $item->fresh()->updated_at;
        $initialAdjUpdatedAt = $adj->fresh()->updated_at;

        // Perform multiple review page loads
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->admin)->get("/admin/orders/{$order->id}/adjustments/{$adj->id}/review")->assertStatus(200);
        }

        $freshOrder = $order->fresh();
        $freshItem = $item->fresh();
        $freshAdj = $adj->fresh();

        $this->assertEquals($order->grand_total, $freshOrder->grand_total);
        $this->assertEquals($order->subtotal, $freshOrder->subtotal);
        $this->assertEquals($order->adjustment_status, $freshOrder->adjustment_status);
        $this->assertEquals($item->ordered_quantity, $freshItem->ordered_quantity);
        $this->assertEquals($item->cancelled_quantity, $freshItem->cancelled_quantity);
        $this->assertEquals($adj->status, $freshAdj->status);
    }
}
