<?php

namespace Tests\Feature\Inventory;

use App\Enums\AccountStatus;
use App\Enums\AllocationStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\StockStatus;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryViewsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
    protected User $warehouseManager;
    protected User $accountant;
    protected User $salesman;
    protected User $deliveryPartner;

    protected Warehouse $mainWarehouse;
    protected Warehouse $secondaryWarehouse;
    protected Category $beveragesCategory;
    protected Category $snacksCategory;
    protected TaxProfile $taxProfile;
    protected Customer $customer;

    protected Product $productA;
    protected Product $productB;
    protected Product $productC;

    protected InventoryBalance $balanceA;
    protected InventoryBalance $balanceB;
    protected InventoryBalance $balanceC;

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

        $this->warehouseManager = User::factory()->create([
            'name' => 'Wendy Warehouse',
            'email' => 'warehouse@wholesale.test',
            'role' => UserRole::WAREHOUSE_MANAGER,
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
            'email' => 'salesman@wholesale.test',
            'role' => UserRole::SALESMAN,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->deliveryPartner = User::factory()->create([
            'name' => 'Dave Delivery',
            'email' => 'delivery@wholesale.test',
            'role' => UserRole::DELIVERY_PARTNER,
            'status' => AccountStatus::ACTIVE,
        ]);

        $this->mainWarehouse = Warehouse::getDefault() ?? Warehouse::create([
            'code' => 'MAIN',
            'name' => 'Main Distribution Center',
            'country_code' => 'US',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->secondaryWarehouse = Warehouse::create([
            'code' => 'EAST',
            'name' => 'East Coast Fulfillment Hub',
            'country_code' => 'US',
            'is_active' => true,
            'is_default' => false,
        ]);

        $this->beveragesCategory = Category::create([
            'name' => 'Beverages',
            'code' => 'BEV',
            'status' => 'ACTIVE',
        ]);

        $this->snacksCategory = Category::create([
            'name' => 'Snacks',
            'code' => 'SNK',
            'status' => 'ACTIVE',
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'Standard Rate',
            'code' => 'STD',
            'rate' => 0.1000,
            'status' => 'ACTIVE',
        ]);

        $this->customer = Customer::create([
            'salesman_id' => $this->salesman->id,
            'name' => 'Acme Wholesale Corp',
            'code' => 'CUST-ACME-001',
            'contact_name' => 'John Acme',
            'phone' => '+1-555-0100',
            'email' => 'acme@test.com',
            'billing_address_line1' => '123 Market St',
            'billing_city' => 'Metropolis',
            'billing_state' => 'NY',
            'billing_postal_code' => '10001',
            'billing_country' => 'USA',
            'shipping_address_line1' => '123 Market St',
            'shipping_city' => 'Metropolis',
            'shipping_state' => 'NY',
            'shipping_postal_code' => '10001',
            'shipping_country' => 'USA',
            'status' => 'ACTIVE',
            'credit_limit' => 5000.00,
        ]);

        // Product A: In Stock (OnHand: 100, Reserved: 10, Damaged: 5, Available: 85, Reorder: 20)
        $this->productA = Product::create([
            'sku' => 'BEV-COLA-001',
            'name' => 'Classic Cola 12-Pack',
            'description' => '12 pack cola cans',
            'category_id' => $this->beveragesCategory->id,
            'tax_profile_id' => $this->taxProfile->id,
            'unit' => 'CASE',
            'cost_price' => 12.00,
            'default_selling_price' => 20.00,
            'mrp' => 25.00,
            'minimum_allowed_price' => 15.00,
            'status' => ProductStatus::ACTIVE,
        ]);

        $this->balanceA = InventoryBalance::where('warehouse_id', $this->mainWarehouse->id)
            ->where('product_id', $this->productA->id)
            ->first();

        $this->balanceA->update([
            'on_hand_quantity' => 100,
            'reserved_quantity' => 10,
            'damaged_quantity' => 5,
            'available_quantity' => 85,
            'reorder_point' => 20,
            'safety_stock' => 10,
            'bin_location' => 'A-01-01',
        ]);

        // Product B: Low Stock (OnHand: 15, Reserved: 0, Damaged: 0, Available: 15, Reorder: 20)
        $this->productB = Product::create([
            'sku' => 'BEV-ORANGE-002',
            'name' => 'Orange Soda 12-Pack',
            'description' => '12 pack orange cans',
            'category_id' => $this->beveragesCategory->id,
            'tax_profile_id' => $this->taxProfile->id,
            'unit' => 'CASE',
            'cost_price' => 10.00,
            'default_selling_price' => 18.00,
            'mrp' => 22.00,
            'minimum_allowed_price' => 14.00,
            'status' => ProductStatus::ACTIVE,
        ]);

        $this->balanceB = InventoryBalance::where('warehouse_id', $this->mainWarehouse->id)
            ->where('product_id', $this->productB->id)
            ->first();

        $this->balanceB->update([
            'on_hand_quantity' => 15,
            'reserved_quantity' => 0,
            'damaged_quantity' => 0,
            'available_quantity' => 15,
            'reorder_point' => 20,
            'safety_stock' => 5,
            'bin_location' => 'A-01-02',
        ]);

        // Product C: Out of Stock (OnHand: 0, Reserved: 0, Damaged: 0, Available: 0)
        $this->productC = Product::create([
            'sku' => 'SNK-CHIPS-001',
            'name' => 'Salted Potato Chips 24-Pack',
            'description' => '24 pack chips',
            'category_id' => $this->snacksCategory->id,
            'tax_profile_id' => $this->taxProfile->id,
            'unit' => 'BOX',
            'cost_price' => 8.00,
            'default_selling_price' => 15.00,
            'mrp' => 18.00,
            'minimum_allowed_price' => 10.00,
            'status' => ProductStatus::ACTIVE,
        ]);

        $this->balanceC = InventoryBalance::where('warehouse_id', $this->mainWarehouse->id)
            ->where('product_id', $this->productC->id)
            ->first();

        $this->balanceC->update([
            'on_hand_quantity' => 0,
            'reserved_quantity' => 0,
            'damaged_quantity' => 0,
            'available_quantity' => 0,
            'reorder_point' => 10,
            'safety_stock' => 5,
            'bin_location' => 'B-02-01',
        ]);
    }

    /**
     * RBAC Test: Authorized roles can view inventory index.
     */
    public function test_authorized_roles_can_view_inventory_index(): void
    {
        foreach ([$this->superAdmin, $this->admin, $this->warehouseManager] as $user) {
            $response = $this->actingAs($user)->get('/admin/inventory');
            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Inventory/Index')
                ->has('balances.data')
                ->has('summaryMetrics')
                ->has('warehouses')
                ->has('categories')
            );
        }
    }

    /**
     * RBAC Test: Unauthorized roles receive 403 Forbidden on inventory index.
     */
    public function test_unauthorized_roles_cannot_view_inventory_index(): void
    {
        foreach ([$this->accountant, $this->salesman, $this->deliveryPartner] as $user) {
            $response = $this->actingAs($user)->get('/admin/inventory');
            $response->assertForbidden();
        }
    }

    /**
     * Inactive user is rejected with 403 Forbidden.
     */
    public function test_inactive_user_cannot_view_inventory_index(): void
    {
        $inactiveAdmin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::DISABLED,
        ]);

        $response = $this->actingAs($inactiveAdmin)->get('/admin/inventory');
        $response->assertRedirect('/login');
    }

    /**
     * Domain Test: Physical quantities and stock statuses are presented accurately.
     */
    public function test_physical_quantities_and_statuses_are_presented_accurately(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/inventory');
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Inventory/Index')
            ->where('balances.data.0.sku', 'BEV-COLA-001')
            ->where('balances.data.0.on_hand_quantity', 100)
            ->where('balances.data.0.reserved_quantity', 10)
            ->where('balances.data.0.damaged_quantity', 5)
            ->where('balances.data.0.available_quantity', 85)
            ->where('balances.data.0.stock_status', StockStatus::IN_STOCK->value)
        );
    }

    /**
     * Domain Test: Commercial allocations from order_item_allocations are rolled up accurately.
     */
    public function test_commercial_allocations_are_rolled_up_accurately(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-20260906-0001',
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $this->customer->id,
            'user_id' => $this->salesman->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::RESERVED,
            'subtotal' => 200.00,
            'tax_total' => 20.00,
            'grand_total' => 220.00,
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => 15,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 15,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'unit_price' => 20.00,
            'tax_profile_id' => $this->taxProfile->id,
            'tax_rate_snapshot' => 0.1000,
            'taxable_amount' => 300.00,
            'tax_amount' => 30.00,
            'line_total' => 330.00,
        ]);

        OrderItemAllocation::create([
            'allocation_number' => 'ALC-ORD-20260906-0001-01',
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'product_id' => $this->productA->id,
            'allocated_quantity' => 15,
            'reserved_quantity' => 15,
            'picked_quantity' => 0,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'status' => AllocationStatus::ALLOCATED,
            'warehouse_code' => 'MAIN',
            'allocated_by' => $this->admin->id,
            'allocated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/inventory');
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Inventory/Index')
            ->where('balances.data.0.sku', 'BEV-COLA-001')
            ->where('balances.data.0.commercial_allocated_quantity', 15)
            ->where('balances.data.0.on_hand_quantity', 100) // Physical on-hand remains untouched
            ->where('balances.data.0.reserved_quantity', 10) // Physical reserved remains untouched
            ->where('balances.data.0.available_quantity', 85) // Physical available remains untouched
        );
    }

    /**
     * Domain Test: Summary KPI metrics compute unit sums and SKU counts correctly.
     */
    public function test_summary_kpi_metrics_compute_accurately(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/inventory');
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Inventory/Index')
            ->where('summaryMetrics.total_skus', 3)
            ->where('summaryMetrics.total_on_hand_units', 115) // 100 + 15 + 0
            ->where('summaryMetrics.total_available_units', 100) // 85 + 15 + 0
            ->where('summaryMetrics.total_damaged_units', 5) // 5 + 0 + 0
            ->where('summaryMetrics.in_stock_skus', 1)
            ->where('summaryMetrics.low_stock_skus', 1)
            ->where('summaryMetrics.out_of_stock_skus', 1)
        );
    }

    /**
     * Filter Test: Filtering by warehouse scopes balances.
     */
    public function test_warehouse_filter_scopes_inventory_records(): void
    {
        $response = $this->actingAs($this->admin)->get("/admin/inventory?warehouse_id={$this->mainWarehouse->id}");
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Inventory/Index')
            ->where('filters.warehouse_id', $this->mainWarehouse->id)
            ->has('balances.data', 3)
        );

        $emptyResponse = $this->actingAs($this->admin)->get("/admin/inventory?warehouse_id={$this->secondaryWarehouse->id}");
        $emptyResponse->assertOk();

        $emptyResponse->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Inventory/Index')
            ->where('filters.warehouse_id', $this->secondaryWarehouse->id)
            ->has('balances.data', 0)
        );
    }

    /**
     * Filter Test: Filtering by category.
     */
    public function test_category_filter_restricts_inventory_records(): void
    {
        $response = $this->actingAs($this->admin)->get("/admin/inventory?category_id={$this->snacksCategory->id}");
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Inventory/Index')
            ->has('balances.data', 1)
            ->where('balances.data.0.sku', 'SNK-CHIPS-001')
        );
    }

    /**
     * Filter Test: Filtering by stock status.
     */
    public function test_stock_status_filter_filters_items(): void
    {
        $lowStockResponse = $this->actingAs($this->admin)->get('/admin/inventory?stock_status=LOW_STOCK');
        $lowStockResponse->assertOk();
        $lowStockResponse->assertInertia(fn (Assert $page) => $page
            ->has('balances.data', 1)
            ->where('balances.data.0.sku', 'BEV-ORANGE-002')
        );

        $outOfStockResponse = $this->actingAs($this->admin)->get('/admin/inventory?stock_status=OUT_OF_STOCK');
        $outOfStockResponse->assertOk();
        $outOfStockResponse->assertInertia(fn (Assert $page) => $page
            ->has('balances.data', 1)
            ->where('balances.data.0.sku', 'SNK-CHIPS-001')
        );
    }

    /**
     * Filter Test: Damaged stock toggle.
     */
    public function test_damaged_stock_toggle_returns_only_damaged_items(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/inventory?has_damaged=1');
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->has('balances.data', 1)
            ->where('balances.data.0.sku', 'BEV-COLA-001')
            ->where('balances.data.0.damaged_quantity', 5)
        );
    }

    /**
     * Search Test: Searches by SKU, product name, and bin location.
     */
    public function test_search_matches_sku_name_and_bin(): void
    {
        $skuSearch = $this->actingAs($this->admin)->get('/admin/inventory?search=COLA');
        $skuSearch->assertOk();
        $skuSearch->assertInertia(fn (Assert $page) => $page->has('balances.data', 1));

        $binSearch = $this->actingAs($this->admin)->get('/admin/inventory?search=B-02-01');
        $binSearch->assertOk();
        $binSearch->assertInertia(fn (Assert $page) => $page
            ->has('balances.data', 1)
            ->where('balances.data.0.sku', 'SNK-CHIPS-001')
        );
    }

    /**
     * Sorting Test: Sorting by allowed columns.
     */
    public function test_sort_allow_listing_sorts_correctly(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/inventory?sort_by=available_quantity&sort_direction=desc');
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->where('balances.data.0.sku', 'BEV-COLA-001') // 85 available
            ->where('balances.data.1.sku', 'BEV-ORANGE-002') // 15 available
            ->where('balances.data.2.sku', 'SNK-CHIPS-001') // 0 available
        );
    }

    /**
     * Detail Workspace Test: Authorized user can view product inventory detail workspace.
     */
    public function test_authorized_user_can_view_product_stock_detail_workspace(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-20260906-0002',
            'idempotency_key' => (string) Str::uuid(),
            'customer_id' => $this->customer->id,
            'user_id' => $this->salesman->id,
            'salesman_id' => $this->salesman->id,
            'created_by' => $this->salesman->id,
            'status' => OrderStatus::APPROVED,
            'fulfillment_status' => FulfillmentStatus::RESERVED,
            'subtotal' => 200.00,
            'tax_total' => 20.00,
            'grand_total' => 220.00,
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productA->id,
            'product_name_snapshot' => $this->productA->name,
            'sku_snapshot' => $this->productA->sku,
            'unit_snapshot' => $this->productA->unit,
            'ordered_quantity' => 10,
            'cancelled_quantity' => 0,
            'reserved_quantity' => 10,
            'picked_quantity' => 2,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'unit_price' => 20.00,
            'tax_profile_id' => $this->taxProfile->id,
            'tax_rate_snapshot' => 0.1000,
            'taxable_amount' => 200.00,
            'tax_amount' => 20.00,
            'line_total' => 220.00,
        ]);

        OrderItemAllocation::create([
            'allocation_number' => 'ALC-ORD-20260906-0002-01',
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'product_id' => $this->productA->id,
            'allocated_quantity' => 10,
            'reserved_quantity' => 10,
            'picked_quantity' => 2,
            'dispatched_quantity' => 0,
            'delivered_quantity' => 0,
            'returned_quantity' => 0,
            'status' => AllocationStatus::PICKED,
            'warehouse_code' => 'MAIN',
            'allocated_by' => $this->admin->id,
            'allocated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get("/admin/inventory/{$this->balanceA->id}");
        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Inventory/Show')
            ->where('detail.balance.sku', 'BEV-COLA-001')
            ->where('detail.balance.on_hand_quantity', 100)
            ->where('detail.balance.available_quantity', 85)
            ->where('detail.commercial_summary.allocated_quantity', 10)
            ->where('detail.commercial_summary.is_surplus', true)
            ->where('detail.composition_proportions.available_percent', fn ($val) => (float)$val == 85.0)
            ->where('detail.composition_proportions.reserved_percent', fn ($val) => (float)$val == 10.0)
            ->where('detail.composition_proportions.damaged_percent', fn ($val) => (float)$val == 5.0)
            ->has('detail.active_allocations', 1)
            ->where('detail.active_allocations.0.allocation_number', 'ALC-ORD-20260906-0002-01')
            ->where('detail.active_allocations.0.picked_quantity', 2)
        );
    }

    /**
     * Anti-IDOR Test: Non-existent balance ID returns 404.
     */
    public function test_detail_workspace_returns_404_for_invalid_id(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/inventory/999999');
        $response->assertNotFound();
    }

    /**
     * Performance Test: Inventory index executes with bounded constant query count (no N+1).
     */
    public function test_inventory_views_execute_without_n_plus_one_loops(): void
    {
        DB::enableQueryLog();

        $response = $this->actingAs($this->admin)->get('/admin/inventory');
        $response->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Ensure total queries remain bounded (< 15) and do not scale linearly with row count
        $this->assertLessThanOrEqual(15, $queryCount, "Inventory index executed {$queryCount} queries; N+1 detected.");
    }
}
