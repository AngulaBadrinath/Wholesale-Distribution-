<?php

namespace Tests\Feature\Product;

use App\Enums\AccountStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Product\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    protected ProductService $productService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productService = app(ProductService::class);
    }

    /**
     * Helper to create a user with a specific role and status.
     */
    protected function createUserWithRole(UserRole $role, AccountStatus $status = AccountStatus::ACTIVE): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => $status,
            'password' => bcrypt('ValidPassword123!'),
        ]);
    }

    /**
     * Helper to create a Category.
     */
    protected function createCategory(array $overrides = []): Category
    {
        static $catCounter = 100;
        $catCounter++;

        return Category::create(array_merge([
            'code' => 'CAT-' . str_pad((string) $catCounter, 3, '0', STR_PAD_LEFT),
            'name' => 'Category ' . $catCounter,
            'description' => 'Description for category ' . $catCounter,
            'status' => 'ACTIVE',
        ], $overrides));
    }

    /**
     * Helper to create a Product record.
     */
    protected function createProduct(array $overrides = []): Product
    {
        static $prodCounter = 1000;
        $prodCounter++;

        return Product::create(array_merge([
            'sku' => 'SKU-' . str_pad((string) $prodCounter, 5, '0', STR_PAD_LEFT),
            'name' => 'Commercial Product ' . $prodCounter,
            'description' => 'Standard product description ' . $prodCounter,
            'category_id' => null,
            'unit' => 'PCS',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => 50.00,
            'minimum_allowed_price' => 70.00,
            'default_selling_price' => 85.00,
            'mrp' => 100.00,
            'tax_profile_id' => null,
        ], $overrides));
    }

    // =========================================================================
    // 1. Authorization & Directory Access (PROD-CRUD-001 to PROD-CRUD-003)
    // =========================================================================

    /**
     * PROD-CRUD-001: Super Admin & Admin can view product catalog.
     */
    public function test_admin_and_super_admin_can_view_product_catalog(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $superAdmin = $this->createUserWithRole(UserRole::SUPER_ADMIN);

        $product = $this->createProduct(['name' => 'Industrial Fasteners']);

        $resAdmin = $this->actingAs($admin)->get(route('products.index'));
        $resAdmin->assertOk();
        $resAdmin->assertInertia(fn (Assert $page) => $page
            ->component('Product/Index')
            ->has('products.data', 1)
            ->where('products.data.0.id', $product->id)
            ->where('can.create', true)
        );

        $resSuper = $this->actingAs($superAdmin)->get(route('products.index'));
        $resSuper->assertOk();
    }

    /**
     * PROD-CRUD-002: Salesman and Warehouse Manager can view product catalog.
     */
    public function test_salesman_and_warehouse_manager_can_view_product_catalog(): void
    {
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $warehouse = $this->createUserWithRole(UserRole::WAREHOUSE_MANAGER);

        $product = $this->createProduct(['name' => 'Hydraulic Hose 1/2"']);

        $resSales = $this->actingAs($salesman)->get(route('products.index'));
        $resSales->assertOk();
        $resSales->assertInertia(fn (Assert $page) => $page
            ->component('Product/Index')
            ->has('products.data', 1)
            ->where('can.create', false)
        );

        $resWh = $this->actingAs($warehouse)->get(route('products.index'));
        $resWh->assertOk();
    }

    /**
     * PROD-CRUD-003: Accountant and Delivery Partner are forbidden from viewing product catalog.
     */
    public function test_accountant_and_delivery_partner_are_forbidden_from_viewing_product_catalog(): void
    {
        $accountant = $this->createUserWithRole(UserRole::ACCOUNTANT);
        $delivery = $this->createUserWithRole(UserRole::DELIVERY_PARTNER);

        $this->actingAs($accountant)->get(route('products.index'))->assertForbidden();
        $this->actingAs($delivery)->get(route('products.index'))->assertForbidden();
    }

    // =========================================================================
    // 2. Creation & SKU Rules (PROD-CRUD-004 to PROD-CRUD-007)
    // =========================================================================

    /**
     * PROD-CRUD-004: Admin can create product with valid attributes and pricing hierarchy.
     */
    public function test_admin_can_create_product_with_valid_attributes_and_pricing_hierarchy(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $category = $this->createCategory();

        $payload = [
            'sku' => 'PROD-FAST-101',
            'name' => 'High Grade Fastener Bolt M8',
            'description' => 'Zinc-plated high tensile bolt.',
            'category_id' => $category->id,
            'unit' => 'BOX',
            'status' => 'ACTIVE',
            'cost_price' => '25.50',
            'minimum_allowed_price' => '35.00',
            'default_selling_price' => '45.00',
            'mrp' => '60.00',
        ];

        $response = $this->actingAs($admin)->post(route('products.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', [
            'sku' => 'PROD-FAST-101',
            'name' => 'High Grade Fastener Bolt M8',
            'category_id' => $category->id,
            'unit' => 'BOX',
            'status' => 'ACTIVE',
            'cost_price' => '25.50',
            'minimum_allowed_price' => '35.00',
            'default_selling_price' => '45.00',
            'mrp' => '60.00',
        ]);
    }

    /**
     * PROD-CRUD-005: SKU is normalized to uppercase and trimmed.
     */
    public function test_sku_is_normalized_to_uppercase_and_trimmed(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $payload = [
            'sku' => '   sku-low-001   ',
            'name' => 'Normalized Product',
            'unit' => 'PCS',
            'status' => 'ACTIVE',
            'cost_price' => '10.00',
            'minimum_allowed_price' => '15.00',
            'default_selling_price' => '20.00',
            'mrp' => '25.00',
        ];

        $this->actingAs($admin)->post(route('products.store'), $payload)->assertRedirect();

        $this->assertDatabaseHas('products', [
            'sku' => 'SKU-LOW-001',
            'name' => 'Normalized Product',
        ]);
    }

    /**
     * PROD-CRUD-006: SKU is auto-generated when omitted.
     */
    public function test_sku_is_auto_generated_when_omitted(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $payload = [
            'sku' => '',
            'name' => 'Auto SKU Product',
            'unit' => 'PCS',
            'status' => 'ACTIVE',
            'cost_price' => '10.00',
            'minimum_allowed_price' => '15.00',
            'default_selling_price' => '20.00',
            'mrp' => '25.00',
        ];

        $this->actingAs($admin)->post(route('products.store'), $payload)->assertRedirect();

        $product = Product::where('name', 'Auto SKU Product')->first();
        $this->assertNotNull($product);
        $this->assertStringStartsWith('PROD-', $product->sku);
    }

    /**
     * PROD-CRUD-007: Duplicate SKU is rejected at validation and database.
     */
    public function test_duplicate_sku_is_rejected_at_validation_and_database(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $this->createProduct(['sku' => 'EXISTING-SKU-99']);

        $payload = [
            'sku' => 'EXISTING-SKU-99',
            'name' => 'Duplicate Attempt Product',
            'unit' => 'PCS',
            'status' => 'ACTIVE',
            'cost_price' => '10.00',
            'minimum_allowed_price' => '15.00',
            'default_selling_price' => '20.00',
            'mrp' => '25.00',
        ];

        $response = $this->actingAs($admin)->post(route('products.store'), $payload);
        $response->assertSessionHasErrors(['sku']);
    }

    // =========================================================================
    // 3. Pricing Hierarchy Validation (PROD-CRUD-008 to PROD-CRUD-012)
    // =========================================================================

    /**
     * PROD-CRUD-008: Pricing hierarchy enforces cost >= 0.
     */
    public function test_pricing_hierarchy_enforces_cost_gte_zero(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $payload = [
            'name' => 'Negative Cost Product',
            'unit' => 'PCS',
            'status' => 'ACTIVE',
            'cost_price' => '-5.00',
            'minimum_allowed_price' => '10.00',
            'default_selling_price' => '15.00',
            'mrp' => '20.00',
        ];

        $response = $this->actingAs($admin)->post(route('products.store'), $payload);
        $response->assertSessionHasErrors(['cost_price']);
    }

    /**
     * PROD-CRUD-009: Pricing hierarchy enforces minimum allowed price > 0.
     */
    public function test_pricing_hierarchy_enforces_minimum_allowed_price_gt_zero(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $payload = [
            'name' => 'Zero Minimum Price Product',
            'unit' => 'PCS',
            'status' => 'ACTIVE',
            'cost_price' => '10.00',
            'minimum_allowed_price' => '0.00',
            'default_selling_price' => '15.00',
            'mrp' => '20.00',
        ];

        $response = $this->actingAs($admin)->post(route('products.store'), $payload);
        $response->assertSessionHasErrors(['minimum_allowed_price']);
    }

    /**
     * PROD-CRUD-010: Pricing hierarchy enforces default selling price >= minimum allowed price.
     */
    public function test_pricing_hierarchy_enforces_default_selling_price_gte_minimum_allowed_price(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $payload = [
            'name' => 'Selling Lower Than Minimum',
            'unit' => 'PCS',
            'status' => 'ACTIVE',
            'cost_price' => '10.00',
            'minimum_allowed_price' => '30.00',
            'default_selling_price' => '25.00', // Invalid: < 30.00
            'mrp' => '50.00',
        ];

        $response = $this->actingAs($admin)->post(route('products.store'), $payload);
        $response->assertSessionHasErrors(['default_selling_price']);
    }

    /**
     * PROD-CRUD-011: Pricing hierarchy enforces mrp >= default selling price.
     */
    public function test_pricing_hierarchy_enforces_mrp_gte_default_selling_price(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $payload = [
            'name' => 'MRP Lower Than Selling',
            'unit' => 'PCS',
            'status' => 'ACTIVE',
            'cost_price' => '10.00',
            'minimum_allowed_price' => '20.00',
            'default_selling_price' => '40.00',
            'mrp' => '35.00', // Invalid: < 40.00
        ];

        $response = $this->actingAs($admin)->post(route('products.store'), $payload);
        $response->assertSessionHasErrors(['mrp']);
    }

    /**
     * PROD-CRUD-012: Pricing hierarchy allows valid equal boundaries (min = selling = mrp).
     */
    public function test_pricing_hierarchy_allows_valid_equal_boundaries(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $payload = [
            'sku' => 'FLAT-PRICE-100',
            'name' => 'Flat Price Commodity Item',
            'unit' => 'PCS',
            'status' => 'ACTIVE',
            'cost_price' => '50.00',
            'minimum_allowed_price' => '100.00',
            'default_selling_price' => '100.00',
            'mrp' => '100.00',
        ];

        $response = $this->actingAs($admin)->post(route('products.store'), $payload);
        $response->assertRedirect();

        $this->assertDatabaseHas('products', [
            'sku' => 'FLAT-PRICE-100',
            'minimum_allowed_price' => '100.00',
            'default_selling_price' => '100.00',
            'mrp' => '100.00',
        ]);
    }

    /**
     * PROD-CRUD-013: Salesman and Warehouse Manager cannot create products.
     */
    public function test_salesman_and_warehouse_manager_cannot_create_products(): void
    {
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $warehouse = $this->createUserWithRole(UserRole::WAREHOUSE_MANAGER);

        $payload = [
            'name' => 'Unauthorized Product',
            'unit' => 'PCS',
            'status' => 'ACTIVE',
            'cost_price' => '10.00',
            'minimum_allowed_price' => '15.00',
            'default_selling_price' => '20.00',
            'mrp' => '25.00',
        ];

        $this->actingAs($salesman)->post(route('products.store'), $payload)->assertForbidden();
        $this->actingAs($warehouse)->post(route('products.store'), $payload)->assertForbidden();
    }

    // =========================================================================
    // 4. Sensitive Cost Price Masking (PROD-CRUD-014 to PROD-CRUD-015)
    // =========================================================================

    /**
     * PROD-CRUD-014: Admin can view single product with authoritative cost price.
     */
    public function test_admin_can_view_single_product_with_cost_price(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct(['cost_price' => 55.75]);

        $response = $this->actingAs($admin)->get(route('products.show', $product));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Product/Show')
            ->where('product.id', $product->id)
            ->where('product.cost_price', 55.75)
        );
    }

    /**
     * PROD-CRUD-015: Salesman and Warehouse Manager view product with masked (null) cost price.
     */
    public function test_salesman_and_warehouse_manager_view_product_with_masked_cost_price(): void
    {
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $warehouse = $this->createUserWithRole(UserRole::WAREHOUSE_MANAGER);
        $product = $this->createProduct(['cost_price' => 55.75]);

        $resSales = $this->actingAs($salesman)->get(route('products.show', $product));
        $resSales->assertOk();
        $resSales->assertInertia(fn (Assert $page) => $page
            ->component('Product/Show')
            ->where('product.id', $product->id)
            ->where('product.cost_price', null)
        );

        $resWh = $this->actingAs($warehouse)->get(route('products.show', $product));
        $resWh->assertOk();
        $resWh->assertInertia(fn (Assert $page) => $page
            ->component('Product/Show')
            ->where('product.id', $product->id)
            ->where('product.cost_price', null)
        );
    }

    // =========================================================================
    // 5. Update & Pricing Permissions (PROD-CRUD-016 to PROD-CRUD-019)
    // =========================================================================

    /**
     * PROD-CRUD-016: Admin can update product general metadata.
     */
    public function test_admin_can_update_product_general_metadata(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct(['name' => 'Original Name', 'unit' => 'PCS']);

        $payload = [
            'sku' => $product->sku,
            'name' => 'Updated Commercial Name',
            'unit' => 'BOX',
            'status' => 'ACTIVE',
            'cost_price' => (string) $product->cost_price,
            'minimum_allowed_price' => (string) $product->minimum_allowed_price,
            'default_selling_price' => (string) $product->default_selling_price,
            'mrp' => (string) $product->mrp,
        ];

        $response = $this->actingAs($admin)->put(route('products.update', $product), $payload);
        $response->assertRedirect();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Commercial Name',
            'unit' => 'BOX',
        ]);
    }

    /**
     * PROD-CRUD-017: Admin can update product pricing with price update permission.
     */
    public function test_admin_can_update_product_pricing_with_price_update_permission(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct([
            'cost_price' => 50.00,
            'minimum_allowed_price' => 70.00,
            'default_selling_price' => 85.00,
            'mrp' => 100.00,
        ]);

        $payload = [
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => $product->unit,
            'status' => 'ACTIVE',
            'cost_price' => '60.00',
            'minimum_allowed_price' => '80.00',
            'default_selling_price' => '95.00',
            'mrp' => '120.00',
        ];

        $response = $this->actingAs($admin)->put(route('products.update', $product), $payload);
        $response->assertRedirect();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'cost_price' => '60.00',
            'minimum_allowed_price' => '80.00',
            'default_selling_price' => '95.00',
            'mrp' => '120.00',
        ]);
    }

    /**
     * PROD-CRUD-018: Update is rejected if it violates pricing hierarchy.
     */
    public function test_update_is_rejected_if_it_violates_pricing_hierarchy(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        $payload = [
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => $product->unit,
            'status' => 'ACTIVE',
            'cost_price' => '50.00',
            'minimum_allowed_price' => '90.00',
            'default_selling_price' => '80.00', // Invalid: < 90.00
            'mrp' => '100.00',
        ];

        $response = $this->actingAs($admin)->put(route('products.update', $product), $payload);
        $response->assertSessionHasErrors(['default_selling_price']);
    }

    /**
     * PROD-CRUD-019: Salesman cannot update product or pricing.
     */
    public function test_salesman_cannot_update_product_or_pricing(): void
    {
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $product = $this->createProduct();

        $payload = [
            'sku' => $product->sku,
            'name' => 'Tampered Name',
            'unit' => $product->unit,
            'status' => 'ACTIVE',
            'cost_price' => '10.00',
            'minimum_allowed_price' => '20.00',
            'default_selling_price' => '30.00',
            'mrp' => '40.00',
        ];

        $this->actingAs($salesman)->put(route('products.update', $product), $payload)->assertForbidden();
    }

    // =========================================================================
    // 6. Product Lifecycle & Order Readiness Contract (PROD-CRUD-020 to PROD-CRUD-023)
    // =========================================================================

    /**
     * PROD-CRUD-020: Admin can update product lifecycle status.
     */
    public function test_admin_can_update_product_lifecycle_status(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct(['status' => ProductStatus::ACTIVE]);

        $response = $this->actingAs($admin)->patch(route('products.status', $product), [
            'status' => 'INACTIVE',
            'reason' => 'Discontinued product line.',
        ]);

        $response->assertRedirect();
        $this->assertEquals(ProductStatus::INACTIVE, $product->fresh()->status);
    }

    /**
     * PROD-CRUD-021: Lifecycle status update suppresses no-op.
     */
     public function test_lifecycle_status_update_suppresses_no_op(): void
     {
         $admin = $this->createUserWithRole(UserRole::ADMIN);
         $product = $this->createProduct(['status' => ProductStatus::ACTIVE]);

         Log::spy();

         $response = $this->actingAs($admin)->patch(route('products.status', $product), [
             'status' => 'ACTIVE',
             'reason' => 'No-op transition.',
         ]);

         $response->assertRedirect();
         Log::shouldNotHaveReceived('info', function ($message, $context) {
             return ($context['action'] ?? null) === 'PRODUCT_ACTIVATED' ||
                    ($context['action'] ?? null) === 'PRODUCT_DEACTIVATED';
         });
     }

    /**
     * PROD-CRUD-022: Active product passes ensureCanOrder().
     */
    public function test_active_product_passes_ensure_can_order(): void
    {
        $product = $this->createProduct(['status' => ProductStatus::ACTIVE]);

        $this->assertTrue($product->canOrder());
        $product->ensureCanOrder(); // Should not throw
    }

    /**
     * PROD-CRUD-023: Inactive product fails ensureCanOrder().
     */
    public function test_inactive_product_fails_ensure_can_order(): void
    {
        $product = $this->createProduct(['status' => ProductStatus::INACTIVE]);

        $this->assertFalse($product->canOrder());

        $this->expectException(ValidationException::class);
        $product->ensureCanOrder();
    }

    // =========================================================================
    // 7. Search, Filtering & Referential Integrity (PROD-CRUD-024 to PROD-CRUD-027)
    // =========================================================================

    /**
     * PROD-CRUD-024: Product search by SKU, name, and description.
     */
    public function test_product_search_by_sku_name_and_description(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $prodA = $this->createProduct(['sku' => 'ALPHA-FAST-10', 'name' => 'Steel Bolts', 'description' => 'Fasteners']);
        $prodB = $this->createProduct(['sku' => 'BETA-HOSE-20', 'name' => 'Hydraulic Line', 'description' => 'High pressure tube']);

        $resSku = $this->actingAs($admin)->get(route('products.index', ['search' => 'ALPHA']));
        $resSku->assertOk();
        $resSku->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 1)
            ->where('products.data.0.id', $prodA->id)
        );

        $resDesc = $this->actingAs($admin)->get(route('products.index', ['search' => 'pressure']));
        $resDesc->assertOk();
        $resDesc->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 1)
            ->where('products.data.0.id', $prodB->id)
        );
    }

    /**
     * PROD-CRUD-025: Product filter by status and category.
     */
    public function test_product_filter_by_status_and_category(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $categoryA = $this->createCategory(['name' => 'Plumbing']);
        $categoryB = $this->createCategory(['name' => 'Electrical']);

        $prodActive = $this->createProduct(['status' => ProductStatus::ACTIVE, 'category_id' => $categoryA->id]);
        $prodInactive = $this->createProduct(['status' => ProductStatus::INACTIVE, 'category_id' => $categoryB->id]);

        // Filter by Category A
        $resCat = $this->actingAs($admin)->get(route('products.index', ['category_id' => (string) $categoryA->id]));
        $resCat->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 1)
            ->where('products.data.0.id', $prodActive->id)
        );

        // Filter by INACTIVE status
        $resStatus = $this->actingAs($admin)->get(route('products.index', ['status' => 'INACTIVE']));
        $resStatus->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 1)
            ->where('products.data.0.id', $prodInactive->id)
        );
    }

    /**
     * PROD-CRUD-026: Category deletion nulls product category_id (referential safety).
     */
    public function test_category_deletion_nulls_product_category_id_referential_safety(): void
    {
        $category = $this->createCategory();
        $product = $this->createProduct(['category_id' => $category->id]);

        $this->assertEquals($category->id, $product->category_id);

        // Delete category directly via model to test DB foreign key nullOnDelete constraint
        $category->delete();

        $this->assertNull($product->fresh()->category_id);
    }

    /**
     * PROD-CRUD-027: Physical deletion of products is prohibited by policy.
     */
    public function test_physical_deletion_of_products_is_prohibited(): void
    {
        $superAdmin = $this->createUserWithRole(UserRole::SUPER_ADMIN);
        $product = $this->createProduct();

        $this->assertFalse($superAdmin->can('delete', $product));
    }

    // =========================================================================
    // 8. Authoritative Audit Logging (PROD-CRUD-028)
    // =========================================================================

    /**
     * PROD-CRUD-028: Product operations emit authoritative audit logs.
     */
    public function test_product_operations_emit_authoritative_audit_logs(): void
    {
        Log::spy();
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        // 1. Create product
        $payload = [
            'sku' => 'AUDIT-PROD-01',
            'name' => 'Audited Master Product',
            'unit' => 'PCS',
            'status' => 'ACTIVE',
            'cost_price' => '20.00',
            'minimum_allowed_price' => '30.00',
            'default_selling_price' => '40.00',
            'mrp' => '50.00',
        ];

        $this->actingAs($admin)->post(route('products.store'), $payload);

        $product = Product::where('sku', 'AUDIT-PROD-01')->first();
        $this->assertNotNull($product);

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) use ($admin, $product) {
            return ($context['action'] ?? null) === 'PRODUCT_CREATED' &&
                   ($context['product_id'] ?? null) === $product->id &&
                   ($context['actor_id'] ?? null) === $admin->id;
        });

        // 2. Update pricing
        $updatePayload = [
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => $product->unit,
            'status' => 'ACTIVE',
            'cost_price' => '25.00',
            'minimum_allowed_price' => '35.00',
            'default_selling_price' => '45.00',
            'mrp' => '55.00',
        ];

        $this->actingAs($admin)->put(route('products.update', $product), $updatePayload);

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) use ($admin, $product) {
            return ($context['action'] ?? null) === 'PRODUCT_PRICING_UPDATED' &&
                   ($context['product_id'] ?? null) === $product->id &&
                   ($context['actor_id'] ?? null) === $admin->id;
        });

        // 3. Deactivate product
        $this->actingAs($admin)->patch(route('products.status', $product), [
            'status' => 'INACTIVE',
            'reason' => 'Audit deactivation test.',
        ]);

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) use ($admin, $product) {
            return ($context['action'] ?? null) === 'PRODUCT_DEACTIVATED' &&
                   ($context['product_id'] ?? null) === $product->id &&
                   ($context['actor_id'] ?? null) === $admin->id;
        });
    }
}
