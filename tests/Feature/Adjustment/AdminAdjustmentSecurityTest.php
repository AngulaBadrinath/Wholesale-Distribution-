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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAdjustmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
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

    protected function createOrderAndAdjustment(): array
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
            'adjustment_number' => 'ADJ-' . uniqid(),
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'order_version_snapshot' => 1,
            'order_status_snapshot' => 'APPROVED',
            'order_subtotal_snapshot' => '250.00',
            'order_tax_total_snapshot' => '45.00',
            'order_grand_total_snapshot' => '295.00',
            'type' => 'QUANTITY_REDUCTION',
            'status' => OrderAdjustmentStatus::SUBMITTED,
            'reason_code' => AdjustmentReasonCode::CUSTOMER_REQUEST,
            'notes' => 'Security test notes.',
            'requested_by' => $this->salesman->id,
            'requested_at' => Carbon::now()->subHour(),
            'projected_subtotal_reduction' => '25.00',
            'projected_tax_reduction' => '4.50',
            'projected_grand_total_reduction' => '29.50',
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
            'affected_allocation_quantity' => 0,
            'projected_taxable_amount_reduction' => '25.00',
            'projected_tax_amount_reduction' => '4.50',
            'projected_line_total_reduction' => '29.50',
        ]);

        return [$order, $adj, $item];
    }

    public function test_idor_mismatched_order_and_adjustment_returns_404(): void
    {
        [$orderA, $adjA] = $this->createOrderAndAdjustment();
        [$orderB, $adjB] = $this->createOrderAndAdjustment();

        // Attempting to access adjustment A under Order B's URL path
        $response = $this->actingAs($this->admin)->get("/admin/orders/{$orderB->id}/adjustments/{$adjA->id}/review");

        $response->assertStatus(404);
    }

    public function test_salesman_denied_from_review_workspace(): void
    {
        [$order, $adj] = $this->createOrderAndAdjustment();

        $response = $this->actingAs($this->salesman)->get("/admin/orders/{$order->id}/adjustments/{$adj->id}/review");

        $response->assertStatus(403);
    }

    public function test_warehouse_manager_denied_from_review_workspace(): void
    {
        [$order, $adj] = $this->createOrderAndAdjustment();

        $response = $this->actingAs($this->warehouseManager)->get("/admin/orders/{$order->id}/adjustments/{$adj->id}/review");

        $response->assertStatus(403);
    }

    public function test_delivery_partner_denied_from_review_workspace(): void
    {
        [$order, $adj] = $this->createOrderAndAdjustment();

        $response = $this->actingAs($this->deliveryPartner)->get("/admin/orders/{$order->id}/adjustments/{$adj->id}/review");

        $response->assertStatus(403);
    }

    public function test_strict_review_boundary_can_approve_and_reject_are_false(): void
    {
        [$order, $adj] = $this->createOrderAndAdjustment();

        $response = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}/adjustments/{$adj->id}/review");

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->where('can.review', true)
            ->where('can.approve', false)
            ->where('can.reject', false)
        );
    }
}
