<?php

namespace Tests\Feature\Order;

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AdminOrderApprovalHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
    protected User $accountant;
    protected User $salesman;
    protected User $warehouseManager;
    protected User $deliveryPartner;
    protected Customer $customer;
    protected Category $category;
    protected TaxProfile $taxProfile;
    protected Warehouse $warehouse;
    protected Product $productInStock;
    protected Product $productOutOfStock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Distribution Center', 'is_active' => true, 'is_default' => true]
        );

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Administrator QA',
            'email' => 'superadmin.qa@example.test',
            'role' => UserRole::SUPER_ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Operations Admin QA',
            'email' => 'admin.qa@example.test',
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->accountant = User::factory()->create([
            'name' => 'Finance Accountant QA',
            'email' => 'accountant.qa@example.test',
            'role' => UserRole::ACCOUNTANT,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->salesman = User::factory()->create([
            'name' => 'Sales Representative North QA',
            'email' => 'salesman.qa@example.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->warehouseManager = User::factory()->create([
            'name' => 'Warehouse Supervisor QA',
            'email' => 'warehouse.qa@example.test',
            'role' => UserRole::WAREHOUSE_MANAGER,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->deliveryPartner = User::factory()->create([
            'name' => 'Logistics Driver QA',
            'email' => 'driver.qa@example.test',
            'role' => UserRole::DELIVERY_PARTNER,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Apex Supermarket Group',
            'code' => 'CUST-APEX-01',
            'contact_name' => 'David Miller',
            'phone' => '+1-555-0987',
            'email' => 'david.m@apex-retail.test',
            'billing_address_line1' => '101 Commerce Blvd',
            'billing_city' => 'North Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '101 Commerce Blvd',
            'shipping_city' => 'North Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => CustomerStatus::ACTIVE,
            'credit_limit' => 50000.00,
        ]);

        $this->category = Category::create([
            'name' => 'Beverages & Snacks',
            'code' => 'CAT-BEV-SNAK',
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'Standard Wholesale Tax (10%)',
            'code' => 'TAX-STD-10',
            'rate' => 10.00,
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->productInStock = Product::create([
            'name' => 'Organic Orange Juice 1L (Case of 12)',
            'sku' => 'BEV-ORG-001',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'cost_price' => 20.00,
            'minimum_allowed_price' => 25.00,
            'default_selling_price' => 30.00,
            'mrp' => 35.00,
            'status' => ProductStatus::ACTIVE,
            'unit' => 'CASE',
        ]);

        $this->productOutOfStock = Product::create([
            'name' => 'Dark Chocolate Bars 85% (Display Box of 20)',
            'sku' => 'SNAK-CHOC-002',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'cost_price' => 15.00,
            'minimum_allowed_price' => 18.00,
            'default_selling_price' => 22.00,
            'mrp' => 25.00,
            'status' => ProductStatus::ACTIVE,
            'unit' => 'BOX',
        ]);

        // Sufficient stock balance for productInStock: 200 on hand, 0 reserved, 200 available
        InventoryBalance::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $this->productInStock->id],
            ['on_hand_quantity' => 200, 'reserved_quantity' => 0, 'available_quantity' => 200, 'damaged_quantity' => 0, 'version' => 1]
        );

        // Zero stock balance for productOutOfStock: 0 on hand, 0 reserved, 0 available
        InventoryBalance::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $this->productOutOfStock->id],
            ['on_hand_quantity' => 0, 'reserved_quantity' => 0, 'available_quantity' => 0, 'damaged_quantity' => 0, 'version' => 1]
        );
    }

    /**
     * Factory helper to create an isolated submitted order.
     */
    protected function createIsolatedSubmittedOrder(Product $product, int $quantity = 5, array $overrides = []): Order
    {
        $subtotal = bcmul((string) $product->default_selling_price, (string) $quantity, 2);
        $taxTotal = bcmul($subtotal, '0.10', 2);
        $grandTotal = bcadd($subtotal, $taxTotal, 2);

        $order = Order::create(array_merge([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'idempotency_key' => 'idemp-' . uniqid(),
            'customer_id' => $this->customer->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::SUBMITTED,
            'fulfillment_status' => FulfillmentStatus::UNALLOCATED,
            'payment_status' => PaymentStatus::UNPAID,
            'currency' => 'USD',
            'subtotal' => (float) $subtotal,
            'tax_total' => (float) $taxTotal,
            'grand_total' => (float) $grandTotal,
            'submitted_at' => Carbon::now()->subHours(1),
            'version' => 1,
        ], $overrides));

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $product->sku,
            'unit_snapshot' => $product->unit,
            'ordered_quantity' => $quantity,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 0,
            'unit_price' => (float) $product->default_selling_price,
            'tax_rate_snapshot' => 10.00,
            'tax_profile_id' => $product->tax_profile_id,
            'tax_profile_code_snapshot' => $this->taxProfile->code,
            'tax_profile_name_snapshot' => $this->taxProfile->name,
            'taxable_amount' => (float) $subtotal,
            'tax_amount' => (float) $taxTotal,
            'line_total' => (float) $grandTotal,
        ]);

        return $order;
    }

    /**
     * 1. Super Admin Approval Success Contract
     */
    public function test_super_admin_can_successfully_approve_eligible_order(): void
    {
        $order = $this->createIsolatedSubmittedOrder($this->productInStock, 10);
        $balanceBefore = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->productInStock->id)
            ->first();

        $response = $this->actingAs($this->superAdmin)
            ->from("/admin/orders/{$order->id}/review")
            ->post("/admin/orders/{$order->id}/approve");

        $response->assertRedirect(route('admin.orders.index', ['queue' => 'new']));
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals(OrderStatus::APPROVED, $order->status);
        $this->assertEquals(FulfillmentStatus::RESERVED, $order->fulfillment_status);
        $this->assertNotNull($order->approved_at);
        $this->assertEquals($this->superAdmin->id, $order->approved_by);

        // Order item reservation quantity
        $item = $order->items()->first();
        $this->assertEquals(10, $item->reserved_quantity);

        // Baseline OrderItemAllocation created
        $allocation = OrderItemAllocation::where('order_id', $order->id)->where('order_item_id', $item->id)->first();
        $this->assertNotNull($allocation);
        $this->assertEquals(10, $allocation->allocated_quantity);
        $this->assertEquals(10, $allocation->reserved_quantity);
        $this->assertEquals(AllocationStatus::ALLOCATED, $allocation->status);
        $this->assertEquals($this->superAdmin->id, $allocation->allocated_by);

        // Inventory conservation: on_hand intact, reserved +10, available -10
        $balanceAfter = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->productInStock->id)
            ->first();
        $this->assertEquals($balanceBefore->on_hand_quantity, $balanceAfter->on_hand_quantity);
        $this->assertEquals($balanceBefore->reserved_quantity + 10, $balanceAfter->reserved_quantity);
        $this->assertEquals($balanceBefore->available_quantity - 10, $balanceAfter->available_quantity);
        $this->assertEquals($balanceAfter->on_hand_quantity - $balanceAfter->reserved_quantity - $balanceAfter->damaged_quantity, $balanceAfter->available_quantity);
    }

    /**
     * 2. Admin Approval Success Contract (Independent Order)
     */
    public function test_admin_can_successfully_approve_eligible_order(): void
    {
        $order = $this->createIsolatedSubmittedOrder($this->productInStock, 6);
        $balanceBefore = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->productInStock->id)
            ->first();

        $response = $this->actingAs($this->admin)
            ->from("/admin/orders/{$order->id}/review")
            ->post("/admin/orders/{$order->id}/approve");

        $response->assertRedirect(route('admin.orders.index', ['queue' => 'new']));
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals(OrderStatus::APPROVED, $order->status);
        $this->assertEquals(FulfillmentStatus::RESERVED, $order->fulfillment_status);
        $this->assertNotNull($order->approved_at);
        $this->assertEquals($this->admin->id, $order->approved_by);

        // Inventory conservation
        $balanceAfter = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->productInStock->id)
            ->first();
        $this->assertEquals($balanceBefore->reserved_quantity + 6, $balanceAfter->reserved_quantity);
        $this->assertEquals($balanceBefore->available_quantity - 6, $balanceAfter->available_quantity);
    }

    /**
     * 3. Restricted Roles Denied (403 Forbidden)
     */
    public function test_restricted_roles_are_denied_from_approving_orders(): void
    {
        $order = $this->createIsolatedSubmittedOrder($this->productInStock, 4);

        // Salesman
        $resSalesman = $this->actingAs($this->salesman)->post("/admin/orders/{$order->id}/approve");
        $resSalesman->assertStatus(403);

        // Accountant
        $resAccountant = $this->actingAs($this->accountant)->post("/admin/orders/{$order->id}/approve");
        $resAccountant->assertStatus(403);

        // Warehouse Manager
        $resWarehouse = $this->actingAs($this->warehouseManager)->post("/admin/orders/{$order->id}/approve");
        $resWarehouse->assertStatus(403);

        // Delivery Partner
        $resDriver = $this->actingAs($this->deliveryPartner)->post("/admin/orders/{$order->id}/approve");
        $resDriver->assertStatus(403);

        // Unauthenticated
        auth()->logout();
        $resGuest = $this->post("/admin/orders/{$order->id}/approve");
        $resGuest->assertRedirect(route('login'));

        // Order remains unchanged
        $order->refresh();
        $this->assertEquals(OrderStatus::SUBMITTED, $order->status);
        $this->assertEquals(FulfillmentStatus::UNALLOCATED, $order->fulfillment_status);
    }

    /**
     * 4. Review Workspace Detects and Flags Out-of-Stock Products as Hard Blocker
     */
    public function test_review_workspace_flags_insufficient_stock_as_blocker(): void
    {
        $order = $this->createIsolatedSubmittedOrder($this->productOutOfStock, 2);

        $response = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}/review");
        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Orders/Review')
            ->where('reviewData.has_blockers', true)
            ->where('reviewData.warnings.0.code', 'INSUFFICIENT_STOCK')
            ->where('reviewData.warnings.0.severity', 'blocker')
        );
    }

    /**
     * 5. Review Workspace Allows Operational Decision When Stock Is Sufficient
     */
    public function test_review_workspace_shows_ready_when_stock_is_sufficient(): void
    {
        $order = $this->createIsolatedSubmittedOrder($this->productInStock, 5);

        $response = $this->actingAs($this->admin)->get("/admin/orders/{$order->id}/review");
        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Orders/Review')
            ->where('reviewData.has_blockers', false)
            ->where('reviewData.order.is_reviewable', true)
        );
    }

    /**
     * 6. Backend Insufficient Stock Rejection & Safe Rollback
     */
    public function test_backend_approval_fails_safely_on_insufficient_stock_with_full_rollback(): void
    {
        $order = $this->createIsolatedSubmittedOrder($this->productOutOfStock, 5);
        $balanceBefore = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->productOutOfStock->id)
            ->first();

        $response = $this->actingAs($this->admin)
            ->from("/admin/orders/{$order->id}/review")
            ->post("/admin/orders/{$order->id}/approve");

        // Redirects back to review page with session error and errors bag
        $response->assertRedirect("/admin/orders/{$order->id}/review");
        $response->assertSessionHasErrors(['inventory', 'order']);
        $response->assertSessionHas('error');

        // Order remains in original SUBMITTED / UNALLOCATED state
        $order->refresh();
        $this->assertEquals(OrderStatus::SUBMITTED, $order->status);
        $this->assertEquals(FulfillmentStatus::UNALLOCATED, $order->fulfillment_status);
        $this->assertNull($order->approved_at);

        // Zero allocations created
        $this->assertEquals(0, OrderItemAllocation::where('order_id', $order->id)->count());

        // Inventory untouched
        $balanceAfter = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->productOutOfStock->id)
            ->first();
        $this->assertEquals($balanceBefore->on_hand_quantity, $balanceAfter->on_hand_quantity);
        $this->assertEquals($balanceBefore->reserved_quantity, $balanceAfter->reserved_quantity);
        $this->assertEquals($balanceBefore->available_quantity, $balanceAfter->available_quantity);
    }

    /**
     * 7. Conflict / Stale State Guard (Non-Reviewable Order Rejection)
     */
    public function test_attempting_approval_on_stale_or_terminal_order_returns_conflict(): void
    {
        $order = $this->createIsolatedSubmittedOrder($this->productInStock, 3, [
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::RESERVED,
            'approved_at' => Carbon::now()->subMinutes(10),
            'approved_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post("/admin/orders/{$order->id}/approve");

        $response->assertStatus(409);
    }

    /**
     * 8. Rapid Double Click / Duplicate Approval Protection (Idempotency / Single Reservation)
     */
    public function test_duplicate_approval_attempt_is_rejected_without_double_reservation(): void
    {
        $order = $this->createIsolatedSubmittedOrder($this->productInStock, 5);
        $balanceBefore = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->productInStock->id)
            ->first();

        // First approval succeeds
        $res1 = $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/approve");
        $res1->assertRedirect(route('admin.orders.index', ['queue' => 'new']));

        // Second duplicate approval fails gracefully with conflict 409
        $res2 = $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/approve");
        $res2->assertStatus(409);

        // Assert exactly 1 reservation (+5) was recorded
        $balanceAfter = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->productInStock->id)
            ->first();
        $this->assertEquals($balanceBefore->reserved_quantity + 5, $balanceAfter->reserved_quantity);
        $this->assertEquals($balanceBefore->available_quantity - 5, $balanceAfter->available_quantity);

        // Exactly 1 allocation created
        $this->assertEquals(1, OrderItemAllocation::where('order_id', $order->id)->count());
    }

    /**
     * 9. Maker-Checker Segregation: Salesman Creates -> Admin Approves
     */
    public function test_salesman_created_order_is_approved_by_admin(): void
    {
        $order = $this->createIsolatedSubmittedOrder($this->productInStock, 2);
        $this->assertEquals($this->salesman->id, $order->created_by);

        $response = $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/approve");

        $response->assertRedirect(route('admin.orders.index', ['queue' => 'new']));
        $order->refresh();
        $this->assertEquals(OrderStatus::APPROVED, $order->status);
        $this->assertEquals($this->admin->id, $order->approved_by);
    }

    /**
     * 10. Maker-Checker Segregation: Salesman Creates -> Super Admin Approves
     */
    public function test_salesman_created_order_is_approved_by_super_admin(): void
    {
        $order = $this->createIsolatedSubmittedOrder($this->productInStock, 2);
        $this->assertEquals($this->salesman->id, $order->created_by);

        $response = $this->actingAs($this->superAdmin)
            ->post("/admin/orders/{$order->id}/approve");

        $response->assertRedirect(route('admin.orders.index', ['queue' => 'new']));
        $order->refresh();
        $this->assertEquals(OrderStatus::APPROVED, $order->status);
        $this->assertEquals($this->superAdmin->id, $order->approved_by);
    }

    /**
     * 11. Transaction Atomicity & Forced Failure Rollback
     */
    public function test_transaction_rolls_back_completely_if_unexpected_failure_occurs_after_stock_reservation(): void
    {
        $order = $this->createIsolatedSubmittedOrder($this->productInStock, 4);
        $balanceBefore = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->productInStock->id)
            ->first();

        // Simulate forced failure by creating a conflicting allocation number or dropping a constraint
        // We use a mock or event listener on order saving to simulate unexpected exception
        Order::saving(function ($savingOrder) use ($order) {
            if ($savingOrder->id === $order->id && $savingOrder->status === OrderStatus::APPROVED) {
                throw new \RuntimeException('Simulated unexpected post-reservation failure');
            }
        });

        try {
            $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/approve");
        } catch (\RuntimeException $e) {
            // Caught expected simulated exception
        } finally {
            // Remove event listener
            Order::flushEventListeners();
        }

        // Verify order remained SUBMITTED / UNALLOCATED
        $order->refresh();
        $this->assertEquals(OrderStatus::SUBMITTED, $order->status);
        $this->assertEquals(FulfillmentStatus::UNALLOCATED, $order->fulfillment_status);
        $this->assertNull($order->approved_at);

        // Verify zero allocations exist
        $this->assertEquals(0, OrderItemAllocation::where('order_id', $order->id)->count());

        // Verify inventory balances were completely restored / rolled back
        $balanceAfter = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->productInStock->id)
            ->first();
        $this->assertEquals($balanceBefore->on_hand_quantity, $balanceAfter->on_hand_quantity);
        $this->assertEquals($balanceBefore->reserved_quantity, $balanceAfter->reserved_quantity);
        $this->assertEquals($balanceBefore->available_quantity, $balanceAfter->available_quantity);
    }

    /**
     * 12. Real Inertia Request Contract: Headers, Flash, and Redirect Flow
     */
    public function test_inertia_request_receives_authoritative_redirect_and_flash_data(): void
    {
        $order = $this->createIsolatedSubmittedOrder($this->productInStock, 3);

        $response = $this->actingAs($this->admin)->post(
            "/admin/orders/{$order->id}/approve",
            [],
            [
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        );

        $response->assertStatus(302);
        $response->assertRedirect(route('admin.orders.index', ['queue' => 'new']));
        $response->assertSessionHas('success');

        // Following redirect yields the new Index page with Inertia shared props
        $followResponse = $this->actingAs($this->admin)->get(
            route('admin.orders.index', ['queue' => 'new'])
        );

        $followResponse->assertStatus(200);
        $followResponse->assertInertia(fn ($page) => $page
            ->component('Admin/Orders/Index')
            ->has('orders.data')
            ->has('flash.success')
        );
    }

    /**
     * 13. Super Admin Can Approve Order with Authorized Price Overrides
     */
    public function test_super_admin_can_approve_order_with_authorized_price_overrides(): void
    {
        $order = $this->createIsolatedSubmittedOrder($this->productInStock, 5);
        $item = $order->items()->first();
        $item->is_price_overridden = true;
        $item->price_override_reason = 'Enterprise corporate discount authorized by management';
        $item->price_override_approved_by = $this->superAdmin->id;
        $item->save();

        $response = $this->actingAs($this->superAdmin)
            ->post("/admin/orders/{$order->id}/approve");

        $response->assertRedirect(route('admin.orders.index', ['queue' => 'new']));
        $order->refresh();
        $this->assertEquals(OrderStatus::APPROVED, $order->status);
        $this->assertEquals($this->superAdmin->id, $order->approved_by);
    }

    /**
     * 14. Draft Order Cannot Be Approved (Fail-Closed Draft Isolation)
     */
    public function test_draft_orders_cannot_be_approved(): void
    {
        $order = $this->createIsolatedSubmittedOrder($this->productInStock, 2, [
            'status' => OrderStatus::DRAFT,
            'draft_token' => 'token-draft-123',
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/admin/orders/{$order->id}/approve");

        $response->assertStatus(409);
        $order->refresh();
        $this->assertEquals(OrderStatus::DRAFT, $order->status);
    }
}
