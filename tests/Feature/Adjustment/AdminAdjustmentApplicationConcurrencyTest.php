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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminAdjustmentApplicationConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $superAdmin;
    protected User $salesman;

    protected Customer $customer;
    protected Category $category;
    protected TaxProfile $taxProfile;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
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

        $this->productA = Product::create([
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'name' => 'Product A',
            'sku' => 'SKU-A',
            'minimum_allowed_price' => '20.00',
            'default_selling_price' => '25.00',
            'mrp' => '30.00',
            'status' => ProductStatus::ACTIVE,
        ]);

        $this->productB = Product::create([
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'name' => 'Product B',
            'sku' => 'SKU-B',
            'minimum_allowed_price' => '10.00',
            'default_selling_price' => '15.00',
            'mrp' => '20.00',
            'status' => ProductStatus::ACTIVE,
        ]);
    }

    public function test_multi_line_atomicity_all_or_nothing_rollback(): void
    {
        // Setup order with 2 lines:
        // Item 1: 10 ordered, fulfillable = 10
        // Item 2: 10 ordered, fulfillable = 10
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
            'subtotal' => '400.00',
            'tax_total' => '72.00',
            'adjustment_total' => '0.00',
            'grand_total' => '472.00',
            'version' => 1,
            'submitted_at' => Carbon::now()->subHours(2),
        ]);

        $item1 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
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

        $item2 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productB->id,
            'product_name_snapshot' => $this->productB->name,
            'sku_snapshot' => $this->productB->sku,
            'unit_snapshot' => 'BOTTLE',
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'unit_price' => '15.00',
            'tax_profile_id' => $this->taxProfile->id,
            'tax_profile_code_snapshot' => $this->taxProfile->code,
            'tax_profile_name_snapshot' => $this->taxProfile->name,
            'tax_rate_snapshot' => $this->taxProfile->rate,
            'taxable_amount' => '150.00',
            'tax_amount' => '27.00',
            'line_total' => '177.00',
        ]);

        $adj = OrderAdjustment::create([
            'adjustment_number' => 'ADJ-ATOM-' . uniqid(),
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'order_version_snapshot' => 1,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => '400.00',
            'order_tax_total_snapshot' => '72.00',
            'order_grand_total_snapshot' => '472.00',
            'type' => 'QUANTITY_REDUCTION',
            'status' => OrderAdjustmentStatus::APPROVED,
            'reason_code' => AdjustmentReasonCode::CUSTOMER_REQUEST,
            'notes' => 'Multi-line reduction.',
            'requested_by' => $this->salesman->id,
            'requested_at' => Carbon::now()->subHour(),
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => Carbon::now()->subMinutes(30),
            'projected_subtotal_reduction' => '125.00',
            'projected_tax_reduction' => '22.50',
            'projected_grand_total_reduction' => '147.50',
            'idempotency_key' => 'idem-' . uniqid(),
            'request_fingerprint' => hash('sha256', 'payload-ml-' . uniqid()),
        ]);

        // Line 1: reduce 3 (valid)
        OrderAdjustmentItem::create([
            'adjustment_id' => $adj->id,
            'order_item_id' => $item1->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_price_snapshot' => '25.00',
            'tax_rate_snapshot' => '18.0000',
            'tax_profile_code_snapshot' => 'TAX-STD',
            'ordered_quantity_snapshot' => 10,
            'cancelled_quantity_snapshot' => 0,
            'fulfillable_quantity_snapshot' => 10,
            'allocated_quantity_snapshot' => 0,
            'unallocated_quantity_snapshot' => 10,
            'requested_quantity_reduction' => 3,
            'projected_fulfillable_quantity' => 7,
            'projected_cancelled_quantity' => 3,
            'affected_allocation_quantity' => 0,
            'projected_taxable_amount_reduction' => '75.00',
            'projected_tax_amount_reduction' => '13.50',
            'projected_line_total_reduction' => '88.50',
        ]);

        // Line 2: requested reduction = 15 (exceeds fulfillable quantity 10)
        OrderAdjustmentItem::create([
            'adjustment_id' => $adj->id,
            'order_item_id' => $item2->id,
            'product_id' => $this->productB->id,
            'product_name_snapshot' => $this->productB->name,
            'sku_snapshot' => $this->productB->sku,
            'unit_price_snapshot' => '15.00',
            'tax_rate_snapshot' => '18.0000',
            'tax_profile_code_snapshot' => 'TAX-STD',
            'ordered_quantity_snapshot' => 10,
            'cancelled_quantity_snapshot' => 0,
            'fulfillable_quantity_snapshot' => 10,
            'allocated_quantity_snapshot' => 0,
            'unallocated_quantity_snapshot' => 10,
            'requested_quantity_reduction' => 15, // INVALID: exceeds fulfillable!
            'projected_fulfillable_quantity' => -5,
            'projected_cancelled_quantity' => 15,
            'affected_allocation_quantity' => 0,
            'projected_taxable_amount_reduction' => '50.00',
            'projected_tax_amount_reduction' => '9.00',
            'projected_line_total_reduction' => '59.00',
        ]);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/apply"
        );

        $response->assertStatus(409);

        // Assert COMPLETE ROLLBACK of ALL lines:
        // Item 1 must NOT be partially modified!
        $this->assertEquals(0, $item1->fresh()->cancelled_quantity);
        $this->assertEquals(10, $item1->fresh()->fulfillableQuantity());
        $this->assertEquals('250.00', $item1->fresh()->taxable_amount);
        $this->assertEquals('295.00', $item1->fresh()->line_total);

        // Item 2 must NOT be modified
        $this->assertEquals(0, $item2->fresh()->cancelled_quantity);
        $this->assertEquals(10, $item2->fresh()->fulfillableQuantity());

        // Order financials, version, and adjustment status untouched
        $this->assertEquals(1, $order->fresh()->version);
        $this->assertEquals('400.00', $order->fresh()->subtotal);
        $this->assertEquals('472.00', $order->fresh()->grand_total);
        $this->assertEquals(AdjustmentStatus::REQUESTED, $order->fresh()->adjustment_status);

        // Adjustment remains APPROVED
        $this->assertEquals(OrderAdjustmentStatus::APPROVED, $adj->fresh()->status);
    }

    public function test_concurrent_apply_double_submission_protection(): void
    {
        // 10 ordered, 0 allocated, 3 reduction
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
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
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
            'adjustment_number' => 'ADJ-CNC-' . uniqid(),
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'order_version_snapshot' => 1,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => '250.00',
            'order_tax_total_snapshot' => '45.00',
            'order_grand_total_snapshot' => '295.00',
            'type' => 'QUANTITY_REDUCTION',
            'status' => OrderAdjustmentStatus::APPROVED,
            'reason_code' => AdjustmentReasonCode::CUSTOMER_REQUEST,
            'notes' => 'Concurrent test.',
            'requested_by' => $this->salesman->id,
            'requested_at' => Carbon::now()->subHour(),
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => Carbon::now()->subMinutes(30),
            'projected_subtotal_reduction' => '75.00',
            'projected_tax_reduction' => '13.50',
            'projected_grand_total_reduction' => '88.50',
            'idempotency_key' => 'idem-' . uniqid(),
            'request_fingerprint' => hash('sha256', 'payload-cnc-' . uniqid()),
        ]);

        OrderAdjustmentItem::create([
            'adjustment_id' => $adj->id,
            'order_item_id' => $item->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_price_snapshot' => '25.00',
            'tax_rate_snapshot' => '18.0000',
            'tax_profile_code_snapshot' => 'TAX-STD',
            'ordered_quantity_snapshot' => 10,
            'cancelled_quantity_snapshot' => 0,
            'fulfillable_quantity_snapshot' => 10,
            'allocated_quantity_snapshot' => 0,
            'unallocated_quantity_snapshot' => 10,
            'requested_quantity_reduction' => 3,
            'projected_fulfillable_quantity' => 7,
            'projected_cancelled_quantity' => 3,
            'affected_allocation_quantity' => 0,
            'projected_taxable_amount_reduction' => '75.00',
            'projected_tax_amount_reduction' => '13.50',
            'projected_line_total_reduction' => '88.50',
        ]);

        // First attempt succeeds
        $res1 = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/apply"
        );
        $res1->assertRedirect();

        // Second attempt fails with 409
        $res2 = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/adjustments/{$adj->id}/apply"
        );
        $res2->assertStatus(409);

        // Verification of state integrity
        $this->assertEquals(OrderAdjustmentStatus::APPLIED, $adj->fresh()->status);
        $this->assertEquals(2, $order->fresh()->version);
        $this->assertEquals(3, $item->fresh()->cancelled_quantity);
    }
}
