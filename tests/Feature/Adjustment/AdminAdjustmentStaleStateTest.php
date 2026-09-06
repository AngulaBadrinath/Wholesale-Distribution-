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

class AdminAdjustmentStaleStateTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
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

    protected function createBaseAdjustment(int $ordered = 10, int $reduction = 2): array
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
            'subtotal' => bcmul('25.00', (string) $ordered, 2),
            'tax_total' => bcmul('4.50', (string) $ordered, 2),
            'adjustment_total' => '0.00',
            'grand_total' => bcmul('29.50', (string) $ordered, 2),
            'version' => 1,
            'submitted_at' => Carbon::now()->subHours(2),
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name_snapshot' => $this->product->name,
            'sku_snapshot' => $this->product->sku,
            'unit_snapshot' => 'BOTTLE',
            'ordered_quantity' => $ordered,
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
            'taxable_amount' => bcmul('25.00', (string) $ordered, 2),
            'tax_amount' => bcmul('4.50', (string) $ordered, 2),
            'line_total' => bcmul('29.50', (string) $ordered, 2),
        ]);

        $adj = OrderAdjustment::create([
            'adjustment_number' => 'ADJ-STALE-001',
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'order_version_snapshot' => 1,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => bcmul('25.00', (string) $ordered, 2),
            'order_tax_total_snapshot' => bcmul('4.50', (string) $ordered, 2),
            'order_grand_total_snapshot' => bcmul('29.50', (string) $ordered, 2),
            'type' => 'QUANTITY_REDUCTION',
            'status' => OrderAdjustmentStatus::SUBMITTED,
            'reason_code' => AdjustmentReasonCode::CUSTOMER_REQUEST,
            'notes' => 'Checking stale state.',
            'requested_by' => $this->salesman->id,
            'requested_at' => Carbon::now()->subHour(),
            'projected_subtotal_reduction' => bcmul('25.00', (string) $reduction, 2),
            'projected_tax_reduction' => bcmul('4.50', (string) $reduction, 2),
            'projected_grand_total_reduction' => bcmul('29.50', (string) $reduction, 2),
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
            'ordered_quantity_snapshot' => $ordered,
            'cancelled_quantity_snapshot' => 0,
            'fulfillable_quantity_snapshot' => $ordered,
            'allocated_quantity_snapshot' => 0,
            'unallocated_quantity_snapshot' => $ordered,
            'requested_quantity_reduction' => $reduction,
            'projected_fulfillable_quantity' => $ordered - $reduction,
            'projected_cancelled_quantity' => $reduction,
            'affected_allocation_quantity' => 0,
            'projected_taxable_amount_reduction' => bcmul('25.00', (string) $reduction, 2),
            'projected_tax_amount_reduction' => bcmul('4.50', (string) $reduction, 2),
            'projected_line_total_reduction' => bcmul('29.50', (string) $reduction, 2),
        ]);

        return [$order, $adj, $item];
    }

    public function test_stale_version_detected(): void
    {
        [$order, $adj, $item] = $this->createBaseAdjustment();

        // Increment order version
        $order->update(['version' => 2]);

        $response = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}/adjustments/{$adj->id}/review");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('evaluation.is_stale', true)
            ->where('evaluation.current_order_version', 2)
            ->where('evaluation.order_version_snapshot', 1)
            ->where('evaluation.evaluation_status', 'STALE')
        );
    }

    public function test_allocation_progression_drift_detected(): void
    {
        // Request was created when allocated = 0, unallocated = 10, reduction = 2 (Case A).
        [$order, $adj, $item] = $this->createBaseAdjustment(ordered: 10, reduction: 2);

        // Warehouse progresses: 9 units are allocated!
        // Now unallocated is 10 - 9 = 1.
        // Requested reduction is 2, so affected allocated = 2 - 1 = 1 unit (Case B)!
        OrderItemAllocation::create([
            'allocation_number' => 'ALC-' . uniqid(),
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'product_id' => $this->product->id,
            'allocated_quantity' => 9,
            'reserved_quantity' => 9,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'status' => AllocationStatus::ALLOCATED,
            'allocated_by' => $this->admin->id,
            'allocated_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}/adjustments/{$adj->id}/review");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('evaluation.is_stale', true)
            ->where('evaluation.line_evaluations.0.case_changed', true)
            ->where('evaluation.line_evaluations.0.snapshot_case', 'CASE_A')
            ->where('evaluation.line_evaluations.0.current_case', 'CASE_B')
            ->where('evaluation.line_evaluations.0.current_affected_allocation_quantity', 1)
            ->where('evaluation.evaluation_status', 'WARNING_ALLOCATION')
        );
    }

    public function test_mathematical_conflict_detected_when_reduction_exceeds_fulfillable(): void
    {
        // Request reduction is 5 units on an ordered 10 item.
        [$order, $adj, $item] = $this->createBaseAdjustment(ordered: 10, reduction: 5);

        // Meanwhile, 8 units were cancelled on the order line (e.g. customer emergency cancellation),
        // leaving fulfillable = 10 - 8 = 2 units.
        $item->update(['cancelled_quantity' => 8]);

        // Requested reduction 5 now exceeds current fulfillable 2!
        $response = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}/adjustments/{$adj->id}/review");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('evaluation.line_evaluations.0.is_conflicted', true)
            ->where('evaluation.evaluation_status', 'CONFLICTED')
        );
    }

    public function test_ineligible_order_lifecycle_detected(): void
    {
        [$order, $adj, $item] = $this->createBaseAdjustment();

        // Order transitions to CANCELLED
        $order->update(['status' => OrderStatus::CANCELLED]);

        $response = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}/adjustments/{$adj->id}/review");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('evaluation.evaluation_status', 'INELIGIBLE_LIFECYCLE')
        );
    }

    public function test_terminal_adjustment_request_detected(): void
    {
        [$order, $adj, $item] = $this->createBaseAdjustment();

        // Adjustment was withdrawn / cancelled
        $adj->update(['status' => OrderAdjustmentStatus::CANCELLED]);

        $response = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}/adjustments/{$adj->id}/review");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('evaluation.evaluation_status', 'TERMINAL_REQUEST')
        );
    }
}
