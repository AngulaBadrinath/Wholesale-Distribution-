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
use App\Models\InventoryBalance;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApprovalReproductionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $superAdmin;
    protected User $salesman;
    protected Customer $customer;
    protected Product $productInStock;
    protected Product $productOutOfStock;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Warehouse', 'is_active' => true, 'is_default' => true]
        );

        $this->admin = User::factory()->create([
            'name' => 'Operations Admin QA',
            'email' => 'admin.qa@example.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Administrator QA',
            'email' => 'superadmin.qa@example.test',
            'role' => UserRole::SUPER_ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'name' => 'Sales Representative North',
            'email' => 'salesman.a@example.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Apex Supermarket Group (North)',
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
            'credit_limit' => 50000.00,
        ]);

        $category = Category::create([
            'name' => 'Snacks',
            'code' => 'SNAK',
        ]);

        $taxProfile = TaxProfile::create([
            'name' => 'Standard Tax Rate',
            'code' => 'TAX-STD',
            'rate' => 0.0825,
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->productOutOfStock = Product::create([
            'name' => 'Dark Chocolate Bars 85% (Display Box of 20)',
            'sku' => 'SNAK-CHOC-002',
            'category_id' => $category->id,
            'tax_profile_id' => $taxProfile->id,
            'cost_price' => 12.00,
            'minimum_allowed_price' => 18.00,
            'default_selling_price' => 24.00,
            'mrp' => 30.00,
            'status' => ProductStatus::ACTIVE,
            'unit' => 'BOX',
        ]);

        $this->productInStock = Product::create([
            'name' => 'Eco Laundry Liquid Detergent 5L (Case of 2)',
            'sku' => 'HOUS-DET-001',
            'category_id' => $category->id,
            'tax_profile_id' => $taxProfile->id,
            'cost_price' => 15.00,
            'minimum_allowed_price' => 22.00,
            'default_selling_price' => 28.00,
            'mrp' => 35.00,
            'status' => ProductStatus::ACTIVE,
            'unit' => 'CASE',
        ]);

        // Product out of stock has 0 balance
        InventoryBalance::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $this->productOutOfStock->id],
            ['on_hand_quantity' => 0, 'reserved_quantity' => 0, 'available_quantity' => 0, 'damaged_quantity' => 0, 'version' => 1]
        );

        // Product in stock has 100 on hand
        InventoryBalance::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $this->productInStock->id],
            ['on_hand_quantity' => 100, 'reserved_quantity' => 0, 'available_quantity' => 100, 'damaged_quantity' => 0, 'version' => 1]
        );
    }

    public function test_admin_approving_order_with_insufficient_stock_behavior(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-2026-000001',
            'idempotency_key' => 'idemp-001',
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::SUBMITTED,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'currency' => 'USD',
            'subtotal' => 24.00,
            'tax_total' => 1.98,
            'grand_total' => 25.98,
            'submitted_at' => Carbon::now()->subHours(2),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productOutOfStock->id,
            'product_name_snapshot' => $this->productOutOfStock->name,
            'sku_snapshot' => $this->productOutOfStock->sku,
            'unit_snapshot' => $this->productOutOfStock->unit,
            'ordered_quantity' => 1,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'unit_price' => 24.00,
            'tax_rate_snapshot' => 0.0825,
            'taxable_amount' => 24.00,
            'tax_amount' => 1.98,
            'line_total' => 25.98,
        ]);

        // Test review page warnings: Does review page detect or warn about stock?
        $reviewRes = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}/review");
        $reviewRes->assertStatus(200);

        // Attempt approval
        $response = $this->actingAs($this->admin)
            ->from("/admin/orders/{$order->id}/review")
            ->post("/admin/orders/{$order->id}/approve");

        // The response redirects back with session errors and flash error
        $response->assertRedirect("/admin/orders/{$order->id}/review");
        $response->assertSessionHasErrors(['inventory']);
        $response->assertSessionHas('error');

        // Assert order was NOT approved and state remains untouched
        $order->refresh();
        $this->assertEquals(OrderStatus::SUBMITTED, $order->status);
        $this->assertEquals(FulfillmentStatus::UNALLOCATED, $order->fulfillment_status);
        $this->assertNull($order->approved_at);
    }
}
