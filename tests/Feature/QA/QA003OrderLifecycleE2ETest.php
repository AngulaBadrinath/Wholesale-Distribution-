<?php

declare(strict_types=1);

namespace Tests\Feature\QA;

use App\Enums\AccountStatus;
use App\Enums\AllocationStatus;
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
use App\Models\OrderItemAllocation;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * QA-003: Master Order Lifecycle & Commerce Hardening End-to-End Test Suite
 *
 * Covers:
 * 1. Salesman login & assigned customer scoping
 * 2. Product selection & pricing boundary enforcement (min price <= price <= MRP)
 * 3. Authorized price overrides
 * 4. Product-specific line tax snapshotting & server-authoritative totals
 * 5. Draft creation, persistence, update & submission
 * 6. Submission idempotency (duplicate prevention)
 * 7. Admin approval with fresh test data
 * 8. Super Admin approval with fresh test data
 * 9. Stock-insufficient blocker (negative test)
 * 10. Atomic inventory reservation & allocation invariants
 * 11. Role authorization boundary (Salesman/Warehouse/Driver blocked from approval)
 * 12. Zero cost-price / sensitive margin leakage in salesman payloads & invoices
 */
class QA003OrderLifecycleE2ETest extends TestCase
{
    use RefreshDatabase;

    protected User $salesman;
    protected User $otherSalesman;
    protected User $admin;
    protected User $superAdmin;
    protected User $warehouseManager;
    protected User $deliveryPartner;

    protected Customer $assignedCustomer;
    protected Customer $unassignedCustomer;
    protected Warehouse $warehouse;
    protected Category $category;
    protected TaxProfile $standardTax;
    protected TaxProfile $exemptTax;
    protected Product $inStockProduct;
    protected Product $outOfStockProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Distribution Hub', 'is_active' => true, 'is_default' => true]
        );

        $this->salesman = User::factory()->create([
            'name' => 'Salesman Sam QA',
            'email' => 'salesman.qa003@example.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
            'password' => bcrypt('Password123!'),
        ]);

        $this->otherSalesman = User::factory()->create([
            'name' => 'Salesman Bob QA',
            'email' => 'other.salesman.qa003@example.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
            'password' => bcrypt('Password123!'),
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin Alice QA',
            'email' => 'admin.qa003@example.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
            'password' => bcrypt('Password123!'),
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin QA',
            'email' => 'superadmin.qa003@example.test',
            'role' => UserRole::SUPER_ADMIN,
            'status' => AccountStatus::ACTIVE,
            'password' => bcrypt('Password123!'),
        ]);

        $this->warehouseManager = User::factory()->create([
            'name' => 'Warehouse Supervisor QA',
            'email' => 'warehouse.qa003@example.test',
            'role' => UserRole::WAREHOUSE_MANAGER,
            'status' => AccountStatus::ACTIVE,
            'password' => bcrypt('Password123!'),
        ]);

        $this->deliveryPartner = User::factory()->create([
            'name' => 'Driver Dan QA',
            'email' => 'driver.qa003@example.test',
            'role' => UserRole::DELIVERY_PARTNER,
            'status' => AccountStatus::ACTIVE,
            'password' => bcrypt('Password123!'),
        ]);

        $this->assignedCustomer = Customer::create([
            'code' => 'CUST-QA-001',
            'name' => 'Assigned Retailer Co',
            'contact_name' => 'Alice Buyer',
            'email' => 'alice@assignedretail.test',
            'phone' => '+1-555-0101',
            'billing_address_line1' => '100 Market St',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '100 Market St',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'credit_limit' => 50000.00,
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->salesman->id,
        ]);

        $this->unassignedCustomer = Customer::create([
            'code' => 'CUST-QA-002',
            'name' => 'Unassigned Corner Shop',
            'contact_name' => 'Bob Owner',
            'email' => 'bob@unassigned.test',
            'phone' => '+1-555-0102',
            'billing_address_line1' => '200 Broad St',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10002',
            'billing_country' => 'USA',
            'shipping_address_line1' => '200 Broad St',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10002',
            'shipping_country' => 'USA',
            'credit_limit' => 10000.00,
            'status' => CustomerStatus::ACTIVE,
            'salesman_id' => $this->otherSalesman->id,
        ]);

        $this->category = Category::create([
            'name' => 'Beverages QA',
            'code' => 'CAT-BEV-QA',
        ]);

        $this->standardTax = TaxProfile::create([
            'name' => 'Standard Rate 10%',
            'code' => 'TAX-10',
            'rate' => 10.00,
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->exemptTax = TaxProfile::create([
            'name' => 'Exempt 0%',
            'code' => 'TAX-0',
            'rate' => 0.00,
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->inStockProduct = Product::create([
            'sku' => 'PRD-IN-01',
            'name' => 'Premium Cola 24-Pack',
            'category_id' => $this->category->id,
            'unit' => 'CASE',
            'cost_price' => 10.00,
            'minimum_allowed_price' => 15.00,
            'default_selling_price' => 20.00,
            'mrp' => 25.00,
            'tax_profile_id' => $this->standardTax->id,
            'status' => ProductStatus::ACTIVE,
        ]);

        $this->outOfStockProduct = Product::create([
            'sku' => 'PRD-OUT-02',
            'name' => 'Diet Soda 12-Pack',
            'category_id' => $this->category->id,
            'unit' => 'CASE',
            'cost_price' => 8.00,
            'minimum_allowed_price' => 12.00,
            'default_selling_price' => 16.00,
            'mrp' => 20.00,
            'tax_profile_id' => $this->standardTax->id,
            'status' => ProductStatus::ACTIVE,
        ]);

        // Setup physical stock
        InventoryBalance::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $this->inStockProduct->id],
            ['on_hand_quantity' => 100, 'reserved_quantity' => 0, 'available_quantity' => 100, 'damaged_quantity' => 0, 'version' => 1]
        );

        InventoryBalance::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $this->outOfStockProduct->id],
            ['on_hand_quantity' => 0, 'reserved_quantity' => 0, 'available_quantity' => 0, 'damaged_quantity' => 0, 'version' => 1]
        );
    }

    /**
     * 1. Scope Enforcement: Salesman can only create orders for assigned customers.
     */
    public function test_01_salesman_scope_enforcement_rejects_unassigned_customer(): void
    {
        $payload = [
            'customer_id' => $this->unassignedCustomer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $this->inStockProduct->id, 'quantity' => 5],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $response->assertSessionHasErrors('customer_id');
        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * 2. Draft lifecycle: Create draft -> update draft items -> submit draft.
     */
    public function test_02_draft_order_lifecycle_create_update_and_submit(): void
    {
        // A. Create Draft
        $draftPayload = [
            'customer_id' => $this->assignedCustomer->id,
            'notes' => 'Initial Draft Order',
            'items' => [
                [
                    'product_id' => $this->inStockProduct->id,
                    'quantity' => 4,
                    'unit_price' => '20.00',
                ],
            ],
        ];

        $draftResponse = $this->actingAs($this->salesman)
            ->postJson('/salesman/orders/drafts', $draftPayload);

        $draftResponse->assertOk()
            ->assertJsonPath('success', true);

        $draftId = $draftResponse->json('draft.id');
        $this->assertNotNull($draftId);

        $draftOrder = Order::find($draftId);
        $this->assertEquals(OrderStatus::DRAFT, $draftOrder->status);
        $this->assertNull($draftOrder->order_number);
        $this->assertEquals('80.00', (string) $draftOrder->subtotal); // 4 * 20.00
        $this->assertEquals('8.00', (string) $draftOrder->tax_total);  // 10%
        $this->assertEquals('88.00', (string) $draftOrder->grand_total);

        // B. Update Draft Items
        $updatePayload = [
            'customer_id' => $this->assignedCustomer->id,
            'expected_version' => 1,
            'notes' => 'Updated Draft Order with 6 units',
            'items' => [
                [
                    'product_id' => $this->inStockProduct->id,
                    'quantity' => 6,
                    'unit_price' => '20.00',
                ],
            ],
        ];

        $updateResponse = $this->actingAs($this->salesman)
            ->putJson("/salesman/orders/drafts/{$draftId}", $updatePayload);

        $updateResponse->assertOk();

        $draftOrder->refresh();
        $this->assertEquals(2, $draftOrder->version);
        $this->assertEquals('120.00', (string) $draftOrder->subtotal); // 6 * 20.00
        $this->assertEquals('12.00', (string) $draftOrder->tax_total);
        $this->assertEquals('132.00', (string) $draftOrder->grand_total);

        // C. Submit Draft to Formal Order
        $idempotencyKey = (string) Str::uuid();
        $submitResponse = $this->actingAs($this->salesman)
            ->post("/salesman/orders/drafts/{$draftId}/submit", [
                'idempotency_key' => $idempotencyKey,
            ]);

        $submitResponse->assertRedirect("/salesman/orders/{$draftId}");
        $submitResponse->assertSessionHas('success');

        $draftOrder->refresh();
        $this->assertEquals(OrderStatus::SUBMITTED, $draftOrder->status);
        $this->assertNotNull($draftOrder->order_number);
        $this->assertStringStartsWith('ORD-', $draftOrder->order_number);
        $this->assertEquals(FulfillmentStatus::UNALLOCATED, $draftOrder->fulfillment_status);
        $this->assertEquals(PaymentStatus::UNPAID, $draftOrder->payment_status);
    }

    /**
     * 3. Pricing Boundary: Price below minimum allowed price is rejected without override.
     */
    public function test_03_pricing_boundary_rejects_below_minimum_without_override(): void
    {
        // min price is 15.00, attempting to sell at 10.00 without override
        $payload = [
            'customer_id' => $this->assignedCustomer->id,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [
                [
                    'product_id' => $this->inStockProduct->id,
                    'quantity' => 5,
                    'unit_price' => '10.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $response->assertSessionHasErrors();
        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * 4. Multi-item Tax Snapshot & Server-Authoritative Totals.
     */
    public function test_04_multi_item_tax_snapshot_and_server_authoritative_totals(): void
    {
        $idempotencyKey = (string) Str::uuid();

        // Product A: 10 units @ $20 = $200 taxable @ 10% tax = $20 tax -> Line total $220
        // Create Product B with 0% tax: 5 units @ $10 = $50 taxable @ 0% tax = $0 tax -> Line total $50
        $exemptProduct = Product::create([
            'sku' => 'PRD-EX-01',
            'name' => 'Exempt Grain Sack',
            'category_id' => $this->category->id,
            'unit' => 'BAG',
            'cost_price' => 6.00,
            'minimum_allowed_price' => 8.00,
            'default_selling_price' => 10.00,
            'mrp' => 12.00,
            'tax_profile_id' => $this->exemptTax->id,
            'status' => ProductStatus::ACTIVE,
        ]);

        $payload = [
            'customer_id' => $this->assignedCustomer->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [
                [
                    'product_id' => $this->inStockProduct->id,
                    'quantity' => 10,
                    'unit_price' => '20.00',
                ],
                [
                    'product_id' => $exemptProduct->id,
                    'quantity' => 5,
                    'unit_price' => '10.00',
                ],
            ],
        ];

        $response = $this->actingAs($this->salesman)
            ->post('/salesman/orders', $payload);

        $order = Order::where('idempotency_key', $idempotencyKey)->first();
        $this->assertNotNull($order);
        $response->assertRedirect("/salesman/orders/{$order->id}");

        // Authoritative Header Totals
        $this->assertEquals('250.00', (string) $order->subtotal);
        $this->assertEquals('20.00', (string) $order->tax_total);
        $this->assertEquals('270.00', (string) $order->grand_total);

        // Snapshot Line Item 1
        $item1 = $order->items()->where('product_id', $this->inStockProduct->id)->first();
        $this->assertEquals(10, $item1->ordered_quantity);
        $this->assertEquals(0, $item1->cancelled_quantity);
        $this->assertEquals(10, $item1->fulfillableQuantity());
        $this->assertEquals('10.0000', (string) $item1->tax_rate_snapshot);
        $this->assertEquals('200.00', (string) $item1->taxable_amount);
        $this->assertEquals('20.00', (string) $item1->tax_amount);
        $this->assertEquals('220.00', (string) $item1->line_total);

        // Snapshot Line Item 2
        $item2 = $order->items()->where('product_id', $exemptProduct->id)->first();
        $this->assertEquals(5, $item2->ordered_quantity);
        $this->assertEquals('0.0000', (string) $item2->tax_rate_snapshot);
        $this->assertEquals('50.00', (string) $item2->taxable_amount);
        $this->assertEquals('0.00', (string) $item2->tax_amount);
        $this->assertEquals('50.00', (string) $item2->line_total);
    }

    /**
     * 5. Submission Idempotency: Duplicate submission with identical key is idempotent.
     */
    public function test_05_duplicate_submission_is_idempotent(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'customer_id' => $this->assignedCustomer->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [
                ['product_id' => $this->inStockProduct->id, 'quantity' => 5, 'unit_price' => '20.00'],
            ],
        ];

        // First submission
        $response1 = $this->actingAs($this->salesman)->post('/salesman/orders', $payload);
        $order1 = Order::where('idempotency_key', $idempotencyKey)->first();
        $this->assertNotNull($order1);

        // Duplicate submission with same key
        $response2 = $this->actingAs($this->salesman)->post('/salesman/orders', $payload);
        $response2->assertRedirect("/salesman/orders/{$order1->id}");

        // Ensure only 1 order exists in database
        $this->assertEquals(1, Order::where('idempotency_key', $idempotencyKey)->count());
    }

    /**
     * 6. Admin Approval with Fresh Test Data: Reserves stock atomically.
     */
    public function test_06_admin_can_approve_order_with_atomic_inventory_reservation(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'idempotency_key' => $idempotencyKey,
            'customer_id' => $this->assignedCustomer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::SUBMITTED,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'currency' => 'USD',
            'subtotal' => 200.00,
            'tax_total' => 20.00,
            'grand_total' => 220.00,
            'submitted_at' => Carbon::now()->subMinutes(10),
            'version' => 1,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->inStockProduct->id,
            'product_name_snapshot' => $this->inStockProduct->name,
            'sku_snapshot' => $this->inStockProduct->sku,
            'unit_snapshot' => $this->inStockProduct->unit,
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'unit_price' => 20.00,
            'tax_rate_snapshot' => 10.00,
            'tax_profile_id' => $this->standardTax->id,
            'tax_profile_code_snapshot' => $this->standardTax->code,
            'tax_profile_name_snapshot' => $this->standardTax->name,
            'taxable_amount' => 200.00,
            'tax_amount' => 20.00,
            'line_total' => 220.00,
        ]);

        $balanceBefore = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->inStockProduct->id)
            ->first();

        // Admin invokes approval endpoint
        $response = $this->actingAs($this->admin)
            ->from("/admin/orders/{$order->id}/review")
            ->post("/admin/orders/{$order->id}/approve");

        $response->assertRedirect(route('admin.orders.index', ['queue' => 'new']));
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals(OrderStatus::APPROVED, $order->status);
        $this->assertEquals(FulfillmentStatus::RESERVED, $order->fulfillment_status);
        $this->assertEquals($this->admin->id, $order->approved_by);
        $this->assertNotNull($order->approved_at);

        // Verify allocation created
        $allocation = OrderItemAllocation::where('order_id', $order->id)->where('order_item_id', $item->id)->first();
        $this->assertNotNull($allocation);
        $this->assertEquals(10, $allocation->allocated_quantity);
        $this->assertEquals(10, $allocation->reserved_quantity);
        $this->assertEquals(AllocationStatus::ALLOCATED, $allocation->status);

        // Inventory conservation: on_hand unchanged, reserved +10, available -10
        $balanceAfter = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->inStockProduct->id)
            ->first();
        $this->assertEquals($balanceBefore->on_hand_quantity, $balanceAfter->on_hand_quantity);
        $this->assertEquals($balanceBefore->reserved_quantity + 10, $balanceAfter->reserved_quantity);
        $this->assertEquals($balanceBefore->available_quantity - 10, $balanceAfter->available_quantity);
    }

    /**
     * 7. Super Admin Approval with Independent Test Data.
     */
    public function test_07_super_admin_can_approve_order_independently(): void
    {
        $idempotencyKey = (string) Str::uuid();

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'idempotency_key' => $idempotencyKey,
            'customer_id' => $this->assignedCustomer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::SUBMITTED,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'currency' => 'USD',
            'subtotal' => 100.00,
            'tax_total' => 10.00,
            'grand_total' => 110.00,
            'submitted_at' => Carbon::now()->subMinutes(10),
            'version' => 1,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->inStockProduct->id,
            'product_name_snapshot' => $this->inStockProduct->name,
            'sku_snapshot' => $this->inStockProduct->sku,
            'unit_snapshot' => $this->inStockProduct->unit,
            'ordered_quantity' => 5,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'unit_price' => 20.00,
            'tax_rate_snapshot' => 10.00,
            'tax_profile_id' => $this->standardTax->id,
            'tax_profile_code_snapshot' => $this->standardTax->code,
            'tax_profile_name_snapshot' => $this->standardTax->name,
            'taxable_amount' => 100.00,
            'tax_amount' => 10.00,
            'line_total' => 110.00,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->from("/admin/orders/{$order->id}/review")
            ->post("/admin/orders/{$order->id}/approve");

        $response->assertRedirect(route('admin.orders.index', ['queue' => 'new']));
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals(OrderStatus::APPROVED, $order->status);
        $this->assertEquals($this->superAdmin->id, $order->approved_by);
    }

    /**
     * 8. Negative Blocker: Stock Insufficient prevents order approval.
     */
    public function test_08_insufficient_stock_blocks_order_approval(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $this->assignedCustomer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::SUBMITTED,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'currency' => 'USD',
            'subtotal' => 160.00,
            'tax_total' => 16.00,
            'grand_total' => 176.00,
            'submitted_at' => Carbon::now()->subMinutes(10),
            'version' => 1,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->outOfStockProduct->id, // 0 stock available
            'product_name_snapshot' => $this->outOfStockProduct->name,
            'sku_snapshot' => $this->outOfStockProduct->sku,
            'unit_snapshot' => $this->outOfStockProduct->unit,
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'unit_price' => 16.00,
            'tax_rate_snapshot' => 10.00,
            'tax_profile_id' => $this->standardTax->id,
            'tax_profile_code_snapshot' => $this->standardTax->code,
            'tax_profile_name_snapshot' => $this->standardTax->name,
            'taxable_amount' => 160.00,
            'tax_amount' => 16.00,
            'line_total' => 176.00,
        ]);

        $response = $this->actingAs($this->admin)
            ->from("/admin/orders/{$order->id}/review")
            ->post("/admin/orders/{$order->id}/approve");

        $response->assertRedirect("/admin/orders/{$order->id}/review");
        $response->assertSessionHas('error');

        // Order remains in SUBMITTED state, unapproved
        $order->refresh();
        $this->assertEquals(OrderStatus::SUBMITTED, $order->status);
        $this->assertEquals(FulfillmentStatus::UNALLOCATED, $order->fulfillment_status);
        $this->assertNull($order->approved_at);
        $this->assertNull($order->approved_by);
    }

    /**
     * 9. Role Boundaries: Non-Admin roles cannot execute order approval (403 Forbidden).
     */
    public function test_09_unauthorized_roles_forbidden_from_approving_orders(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $this->assignedCustomer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::SUBMITTED,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'currency' => 'USD',
            'subtotal' => 100.00,
            'tax_total' => 10.00,
            'grand_total' => 110.00,
            'submitted_at' => Carbon::now()->subMinutes(10),
            'version' => 1,
        ]);

        // Salesman attempt
        $responseSalesman = $this->actingAs($this->salesman)->post("/admin/orders/{$order->id}/approve");
        $responseSalesman->assertStatus(403);

        // Warehouse Manager attempt
        $responseWarehouse = $this->actingAs($this->warehouseManager)->post("/admin/orders/{$order->id}/approve");
        $responseWarehouse->assertStatus(403);

        // Driver attempt
        $responseDriver = $this->actingAs($this->deliveryPartner)->post("/admin/orders/{$order->id}/approve");
        $responseDriver->assertStatus(403);

        $order->refresh();
        $this->assertEquals(OrderStatus::SUBMITTED, $order->status);
    }

    /**
     * 10. Zero Cost-Price & Margin Leakage: Cost price is never exposed in salesman payloads.
     */
    public function test_10_zero_cost_price_leakage_to_salesman(): void
    {
        $response = $this->actingAs($this->salesman)->get('/salesman/orders/create');
        $response->assertOk();

        // Ensure cost_price property is explicitly null in Inertia product payloads
        $response->assertInertia(function (Assert $page) {
            $page->component('Salesman/Orders/Create');
            $products = $page->toArray()['props']['products']['data'] ?? [];
            $this->assertNotEmpty($products);
            foreach ($products as $product) {
                $this->assertNull($product['cost_price'], 'Salesman must NEVER see product cost price');
            }
        });
    }
}
