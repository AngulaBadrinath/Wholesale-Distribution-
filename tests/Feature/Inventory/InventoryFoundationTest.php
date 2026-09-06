<?php

namespace Tests\Feature\Inventory;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Enums\ProductStatus;
use App\Enums\StockStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryInitializationService;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
    protected User $warehouseManager;
    protected User $accountant;
    protected User $salesman;
    protected User $deliveryPartner;

    protected Warehouse $mainWarehouse;
    protected Category $category;
    protected TaxProfile $taxProfile;
    protected Product $productA;
    protected Product $productB;

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

        $this->category = Category::create([
            'name' => 'Beverages',
            'code' => 'BEV',
            'status' => 'ACTIVE',
        ]);

        $this->taxProfile = TaxProfile::create([
            'name' => 'Standard Tax',
            'code' => 'STD_TAX',
            'rate' => 0.0500,
            'status' => 'ACTIVE',
        ]);

        $this->productA = Product::create([
            'sku' => 'SKU-001-A',
            'name' => 'Premium Coffee Beans 1kg',
            'description' => 'Dark roast whole coffee beans',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'unit' => 'BAG',
            'cost_price' => 10.00,
            'minimum_allowed_price' => 15.00,
            'default_selling_price' => 20.00,
            'mrp' => 25.00,
            'status' => ProductStatus::ACTIVE,
        ]);

        $this->productB = Product::create([
            'sku' => 'SKU-002-B',
            'name' => 'Green Tea Pack 500g',
            'description' => 'Organic green tea bags',
            'category_id' => $this->category->id,
            'tax_profile_id' => $this->taxProfile->id,
            'unit' => 'BOX',
            'cost_price' => 5.00,
            'minimum_allowed_price' => 8.00,
            'default_selling_price' => 12.00,
            'mrp' => 15.00,
            'status' => ProductStatus::ACTIVE,
        ]);
    }

    // ==========================================
    // 1. Warehouse Master Foundation
    // ==========================================

    public function test_main_default_warehouse_exists_and_is_unique(): void
    {
        $warehouse = Warehouse::where('code', 'MAIN')->first();
        $this->assertNotNull($warehouse);
        $this->assertTrue($warehouse->is_default);
        $this->assertTrue($warehouse->is_active);
        $this->assertEquals('Main Distribution Center', $warehouse->name);

        $defaultWarehouse = Warehouse::getDefault();
        $this->assertNotNull($defaultWarehouse);
        $this->assertEquals($warehouse->id, $defaultWarehouse->id);
    }

    public function test_duplicate_warehouse_code_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        Warehouse::create([
            'code' => 'MAIN', // Duplicate
            'name' => 'Duplicate Warehouse',
            'country_code' => 'US',
            'is_active' => true,
            'is_default' => false,
        ]);
    }

    // ==========================================
    // 2. Inventory Balance Mathematical & Integrity Invariants
    // ==========================================

    public function test_product_creation_automatically_initializes_baseline_stock_balance(): void
    {
        $balanceA = InventoryBalance::where('warehouse_id', $this->mainWarehouse->id)
            ->where('product_id', $this->productA->id)
            ->first();

        $this->assertNotNull($balanceA);
        $this->assertEquals(0, $balanceA->on_hand_quantity);
        $this->assertEquals(0, $balanceA->reserved_quantity);
        $this->assertEquals(0, $balanceA->available_quantity);
        $this->assertEquals(0, $balanceA->damaged_quantity);
        $this->assertEquals(1, $balanceA->version);
        $this->assertTrue($balanceA->is_active);
    }

    public function test_composite_unique_warehouse_and_product_prevents_duplicate_balances(): void
    {
        $this->expectException(QueryException::class);

        // Attempt to insert duplicate balance row for same (warehouse, product)
        DB::table('inventory_balances')->insert([
            'warehouse_id' => $this->mainWarehouse->id,
            'product_id' => $this->productA->id,
            'bin_location' => 'AISLE-1',
            'reorder_point' => 10,
            'safety_stock' => 5,
            'on_hand_quantity' => 100,
            'reserved_quantity' => 0,
            'available_quantity' => 100,
            'damaged_quantity' => 0,
            'is_active' => true,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgresql_check_constraint_rejects_negative_quantities(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        if (! $isPgsql) {
            $this->markTestSkipped('PostgreSQL CHECK constraint test requires pgsql driver.');
        }

        $this->expectException(QueryException::class);

        DB::table('inventory_balances')->where('product_id', $this->productA->id)->update([
            'on_hand_quantity' => -5,
            'available_quantity' => -5,
        ]);
    }

    public function test_postgresql_check_constraint_rejects_reserved_plus_damaged_exceeding_on_hand(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        if (! $isPgsql) {
            $this->markTestSkipped('PostgreSQL CHECK constraint test requires pgsql driver.');
        }

        $this->expectException(QueryException::class);

        // on_hand = 50, reserved = 40, damaged = 20 (40 + 20 = 60 > 50 -> INVALID)
        DB::table('inventory_balances')->where('product_id', $this->productA->id)->update([
            'on_hand_quantity' => 50,
            'reserved_quantity' => 40,
            'damaged_quantity' => 20,
            'available_quantity' => -10,
        ]);
    }

    public function test_postgresql_check_constraint_rejects_unsynchronized_available_formula(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';
        if (! $isPgsql) {
            $this->markTestSkipped('PostgreSQL CHECK constraint test requires pgsql driver.');
        }

        $this->expectException(QueryException::class);

        // on_hand = 100, reserved = 10, damaged = 5 -> available MUST be 85, but setting 90
        DB::table('inventory_balances')->where('product_id', $this->productA->id)->update([
            'on_hand_quantity' => 100,
            'reserved_quantity' => 10,
            'damaged_quantity' => 5,
            'available_quantity' => 90, // Violated math check: 90 != 85
        ]);
    }

    // ==========================================
    // 3. Relationships & Stock Status Interpretation
    // ==========================================

    public function test_model_relationships_and_stock_status_interpretation(): void
    {
        // Update productA to healthy stock: on_hand=100, reserved=10, damaged=5, available=85, reorder=20
        InventoryBalance::where('product_id', $this->productA->id)->update([
            'bin_location' => 'A-01-02',
            'reorder_point' => 20,
            'safety_stock' => 10,
            'on_hand_quantity' => 100,
            'reserved_quantity' => 10,
            'damaged_quantity' => 5,
            'available_quantity' => 85,
        ]);

        // Update productB to low stock: on_hand=15, reserved=0, damaged=0, available=15, reorder=20
        InventoryBalance::where('product_id', $this->productB->id)->update([
            'bin_location' => 'B-03-01',
            'reorder_point' => 20,
            'safety_stock' => 5,
            'on_hand_quantity' => 15,
            'reserved_quantity' => 0,
            'damaged_quantity' => 0,
            'available_quantity' => 15,
        ]);

        /** @var InventoryBalance $balanceA */
        $balanceA = InventoryBalance::where('product_id', $this->productA->id)->first();
        /** @var InventoryBalance $balanceB */
        $balanceB = InventoryBalance::where('product_id', $this->productB->id)->first();

        // Check relationships
        $this->assertEquals($this->productA->name, $balanceA->product->name);
        $this->assertEquals($this->mainWarehouse->code, $balanceA->warehouse->code);
        $this->assertEquals(1, $this->productA->inventoryBalances()->count());

        // Check stock status helpers
        $this->assertEquals(StockStatus::IN_STOCK, $balanceA->getStockStatus());
        $this->assertTrue($balanceA->isInStock());
        $this->assertFalse($balanceA->isLowStock());
        $this->assertFalse($balanceA->isOutOfStock());

        $this->assertEquals(StockStatus::LOW_STOCK, $balanceB->getStockStatus());
        $this->assertFalse($balanceB->isInStock());
        $this->assertTrue($balanceB->isLowStock());
        $this->assertFalse($balanceB->isOutOfStock());

        // Check Scopes
        $this->assertEquals(1, InventoryBalance::inStock()->count());
        $this->assertEquals(1, InventoryBalance::lowStock()->count());
        $this->assertEquals(0, InventoryBalance::outOfStock()->count());

        // Test search scope
        $this->assertEquals(1, InventoryBalance::search('Coffee')->count());
        $this->assertEquals(1, InventoryBalance::search('SKU-002-B')->count());
        $this->assertEquals(1, InventoryBalance::search('A-01-02')->count());
        $this->assertEquals(0, InventoryBalance::search('NonExistentTerm')->count());
    }

    // ==========================================
    // 4. Initialization Service & Artisan Command
    // ==========================================

    public function test_inventory_initialization_service_and_artisan_command_are_idempotent(): void
    {
        // Mutate productA balance to non-zero stock
        InventoryBalance::where('product_id', $this->productA->id)->update([
            'on_hand_quantity' => 50,
            'available_quantity' => 50,
        ]);

        $initializationService = app(InventoryInitializationService::class);
        $stats = $initializationService->initializeCatalog();

        // Since both productA and productB already had balance rows, 0 are initialized, 2 already existed
        $this->assertEquals(2, $stats['total_products']);
        $this->assertEquals(0, $stats['initialized']);
        $this->assertEquals(2, $stats['already_existed']);

        // Verify productA stock was NOT overwritten or reset
        $balanceA = InventoryBalance::where('product_id', $this->productA->id)->first();
        $this->assertEquals(50, $balanceA->on_hand_quantity);
        $this->assertEquals(50, $balanceA->available_quantity);

        // Run artisan command
        $this->artisan('inventory:initialize')
            ->expectsOutputToContain('Target Warehouse: Main Distribution Center (MAIN)')
            ->expectsOutputToContain('Physical inventory initialization completed successfully.')
            ->assertSuccessful();
    }

    // ==========================================
    // 5. Pessimistic Row Lock Ordering
    // ==========================================

    public function test_inventory_service_locks_balances_in_ascending_id_order(): void
    {
        $service = app(InventoryService::class);

        DB::transaction(function () use ($service) {
            // Pass product IDs in reverse order
            $productIds = [$this->productB->id, $this->productA->id];
            $lockedBalances = $service->lockBalancesForUpdate($this->mainWarehouse->id, $productIds);

            $this->assertCount(2, $lockedBalances);

            // Assert they are returned sorted by ascending primary key ID
            $ids = $lockedBalances->pluck('id')->all();
            $sortedIds = $ids;
            sort($sortedIds);
            $this->assertEquals($sortedIds, $ids);
        });
    }

    // ==========================================
    // 6. RBAC & Security Enforcement
    // ==========================================

    public function test_super_admin_admin_and_warehouse_manager_can_access_inventory_workspace(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin/inventory')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Inventory/Index'));

        $this->actingAs($this->admin)
            ->get('/admin/inventory')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Inventory/Index'));

        $this->actingAs($this->warehouseManager)
            ->get('/admin/inventory')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Inventory/Index'));
    }

    public function test_salesman_accountant_and_delivery_partner_are_denied_with_403(): void
    {
        $this->actingAs($this->salesman)
            ->get('/admin/inventory')
            ->assertForbidden();

        $this->actingAs($this->accountant)
            ->get('/admin/inventory')
            ->assertForbidden();

        $this->actingAs($this->deliveryPartner)
            ->get('/admin/inventory')
            ->assertForbidden();
    }

    public function test_inactive_accounts_and_unauthenticated_requests_are_rejected(): void
    {
        $this->get('/admin/inventory')
            ->assertRedirect('/login');

        $inactiveAdmin = User::factory()->create([
            'role' => UserRole::ADMIN,
            'status' => AccountStatus::SUSPENDED,
        ]);

        $this->actingAs($inactiveAdmin)
            ->get('/admin/inventory')
            ->assertRedirect('/login');
    }

    // ==========================================
    // 7. Filtering, Sorting, and Pagination
    // ==========================================

    public function test_inventory_workspace_search_and_stock_status_filters(): void
    {
        // Setup distinct stock status
        InventoryBalance::where('product_id', $this->productA->id)->update([
            'on_hand_quantity' => 100,
            'available_quantity' => 100,
            'reorder_point' => 10,
        ]);

        InventoryBalance::where('product_id', $this->productB->id)->update([
            'on_hand_quantity' => 0,
            'available_quantity' => 0,
            'reorder_point' => 10,
        ]);

        // 1. Filter by IN_STOCK
        $this->actingAs($this->admin)
            ->get('/admin/inventory?stock_status=IN_STOCK')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Inventory/Index')
                ->has('balances.data', 1)
                ->where('balances.data.0.sku', 'SKU-001-A')
            );

        // 2. Filter by OUT_OF_STOCK
        $this->actingAs($this->admin)
            ->get('/admin/inventory?stock_status=OUT_OF_STOCK')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Inventory/Index')
                ->has('balances.data', 1)
                ->where('balances.data.0.sku', 'SKU-002-B')
            );

        // 3. Search by SKU
        $this->actingAs($this->admin)
            ->get('/admin/inventory?search=SKU-001')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Inventory/Index')
                ->has('balances.data', 1)
                ->where('balances.data.0.product_name', 'Premium Coffee Beans 1kg')
            );

        // 4. Invalid filter rejection
        $this->actingAs($this->admin)
            ->get('/admin/inventory?sort_by=malicious_column_drop_table')
            ->assertSessionHasErrors(['sort_by']);
    }

    // ==========================================
    // 8. Query Stability (No N+1)
    // ==========================================

    public function test_inventory_workspace_query_count_remains_stable_without_n_plus_one(): void
    {
        // Create 10 additional products with category and tax profiles
        for ($i = 3; $i <= 12; $i++) {
            Product::create([
                'sku' => sprintf('SKU-%03d-BULK', $i),
                'name' => "Bulk Product {$i}",
                'category_id' => $this->category->id,
                'tax_profile_id' => $this->taxProfile->id,
                'unit' => 'UNIT',
                'cost_price' => 5.00,
                'minimum_allowed_price' => 8.00,
                'default_selling_price' => 10.00,
                'mrp' => 12.00,
                'status' => ProductStatus::ACTIVE,
            ]);
        }

        // Measure query count for per_page = 2
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->actingAs($this->admin)->get('/admin/inventory?per_page=2');
        $queriesFor2 = count(DB::getQueryLog());

        // Measure query count for per_page = 10
        DB::flushQueryLog();
        $this->actingAs($this->admin)->get('/admin/inventory?per_page=10');
        $queriesFor10 = count(DB::getQueryLog());

        // Query counts should be identical (stable pagination without N+1)
        $this->assertEquals($queriesFor2, $queriesFor10);
    }
}
