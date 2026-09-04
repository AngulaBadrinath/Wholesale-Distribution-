<?php

namespace Tests\Feature\Category;

use App\Enums\AccountStatus;
use App\Enums\CategoryStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Category\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected CategoryService $categoryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->categoryService = app(CategoryService::class);
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
     * Helper to create a Category record.
     */
    protected function createCategory(array $overrides = []): Category
    {
        static $catCounter = 200;
        $catCounter++;

        return Category::create(array_merge([
            'code' => 'CAT-' . str_pad((string) $catCounter, 5, '0', STR_PAD_LEFT),
            'name' => 'Category ' . $catCounter,
            'description' => 'Description for category ' . $catCounter,
            'parent_id' => null,
            'sort_order' => 0,
            'status' => CategoryStatus::ACTIVE,
        ], $overrides));
    }

    /**
     * Helper to create a Product record.
     */
    protected function createProduct(array $overrides = []): Product
    {
        static $prodCounter = 2000;
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
    // 1. IDENTITY & CREATION (CAT-MGT-001..005)
    // =========================================================================

    public function test_admin_can_create_root_category_with_explicit_code(): void
    {
        Log::spy();
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $response = $this->actingAs($admin)->post('/categories', [
            'code' => 'cat-root-01',
            'name' => 'Industrial Hardware',
            'description' => 'Root category for all industrial hardware.',
            'parent_id' => '',
            'sort_order' => 1,
            'status' => 'ACTIVE',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', [
            'code' => 'CAT-ROOT-01',
            'name' => 'Industrial Hardware',
            'parent_id' => null,
            'sort_order' => 1,
            'status' => 'ACTIVE',
        ]);

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function ($message, $context) use ($admin) {
                return $context['action'] === 'CATEGORY_CREATED'
                    && $context['actor_id'] === $admin->id
                    && $context['code'] === 'CAT-ROOT-01';
            });
    }

    public function test_admin_can_create_child_category_with_valid_parent(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $parent = $this->createCategory(['code' => 'CAT-ROOT', 'name' => 'Hardware']);

        $response = $this->actingAs($admin)->post('/categories', [
            'code' => 'CAT-CHILD',
            'name' => 'Fasteners',
            'parent_id' => (string) $parent->id,
            'sort_order' => 10,
            'status' => 'ACTIVE',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', [
            'code' => 'CAT-CHILD',
            'name' => 'Fasteners',
            'parent_id' => $parent->id,
            'sort_order' => 10,
        ]);
    }

    public function test_category_code_auto_generated_when_omitted(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $response = $this->actingAs($admin)->post('/categories', [
            'code' => '',
            'name' => 'Auto Coded Category',
            'parent_id' => '',
            'sort_order' => 0,
            'status' => 'ACTIVE',
        ]);

        $response->assertRedirect();
        $created = Category::where('name', 'Auto Coded Category')->first();
        $this->assertNotNull($created);
        $this->assertMatchesRegularExpression('/^CAT-\d{5}$/', $created->code);
    }

    public function test_duplicate_category_code_is_rejected(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $this->createCategory(['code' => 'CAT-DUP-01', 'name' => 'Existing Category']);

        $response = $this->actingAs($admin)->post('/categories', [
            'code' => 'CAT-DUP-01',
            'name' => 'New Category Same Code',
            'parent_id' => '',
            'status' => 'ACTIVE',
        ]);

        $response->assertSessionHasErrors(['code']);
    }

    public function test_sibling_name_uniqueness_is_enforced(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $parentA = $this->createCategory(['code' => 'CAT-PA', 'name' => 'Parent A']);
        $parentB = $this->createCategory(['code' => 'CAT-PB', 'name' => 'Parent B']);

        // Create child under Parent A
        $this->createCategory(['code' => 'CAT-C1', 'name' => 'Bolts', 'parent_id' => $parentA->id]);

        // Attempt to create another child named "Bolts" (case-insensitive) under Parent A -> FAIL
        $response1 = $this->actingAs($admin)->post('/categories', [
            'code' => 'CAT-C2',
            'name' => 'bolts',
            'parent_id' => (string) $parentA->id,
            'status' => 'ACTIVE',
        ]);
        $response1->assertSessionHasErrors(['name']);

        // Creating child named "Bolts" under Parent B -> SUCCESS (different parent)
        $response2 = $this->actingAs($admin)->post('/categories', [
            'code' => 'CAT-C3',
            'name' => 'Bolts',
            'parent_id' => (string) $parentB->id,
            'status' => 'ACTIVE',
        ]);
        $response2->assertSessionHasNoErrors();
        $this->assertDatabaseHas('categories', [
            'code' => 'CAT-C3',
            'name' => 'Bolts',
            'parent_id' => $parentB->id,
        ]);
    }

    // =========================================================================
    // 2. HIERARCHY & CYCLE PREVENTION (CAT-MGT-006..010)
    // =========================================================================

    public function test_multilevel_hierarchy_retrieval_and_path_generation(): void
    {
        $root = $this->createCategory(['code' => 'ROOT', 'name' => 'Industrial']);
        $sub = $this->createCategory(['code' => 'SUB', 'name' => 'Fasteners', 'parent_id' => $root->id]);
        $leaf = $this->createCategory(['code' => 'LEAF', 'name' => 'Hex Bolts', 'parent_id' => $sub->id]);

        $this->assertEquals('Industrial', $root->getHierarchyPath());
        $this->assertEquals('Industrial > Fasteners', $sub->getHierarchyPath());
        $this->assertEquals('Industrial > Fasteners > Hex Bolts', $leaf->getHierarchyPath());

        $ancestors = $leaf->ancestors();
        $this->assertCount(2, $ancestors);
        $this->assertEquals($root->id, $ancestors->first()->id);
        $this->assertEquals($sub->id, $ancestors->last()->id);
    }

    public function test_category_cannot_parent_itself(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $category = $this->createCategory(['code' => 'CAT-SELF', 'name' => 'Self Test']);

        $response = $this->actingAs($admin)->put("/categories/{$category->id}", [
            'code' => 'CAT-SELF',
            'name' => 'Self Test',
            'parent_id' => (string) $category->id,
            'sort_order' => 0,
            'status' => 'ACTIVE',
        ]);

        $response->assertSessionHasErrors(['parent_id']);
    }

    public function test_category_cannot_set_descendant_as_parent_preventing_cycles(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $root = $this->createCategory(['code' => 'CAT-A', 'name' => 'Node A']);
        $sub = $this->createCategory(['code' => 'CAT-B', 'name' => 'Node B', 'parent_id' => $root->id]);
        $leaf = $this->createCategory(['code' => 'CAT-C', 'name' => 'Node C', 'parent_id' => $sub->id]);

        // Attempt to make root (A) a child of leaf (C), which would cause cycle A -> B -> C -> A
        $response = $this->actingAs($admin)->put("/categories/{$root->id}", [
            'code' => 'CAT-A',
            'name' => 'Node A',
            'parent_id' => (string) $leaf->id,
            'sort_order' => 0,
            'status' => 'ACTIVE',
        ]);

        $response->assertSessionHasErrors(['parent_id']);
        // Verify root parent_id remains null in DB
        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_safe_reparenting_moves_entire_subtree(): void
    {
        Log::spy();
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $parentOld = $this->createCategory(['code' => 'OLD-P', 'name' => 'Old Parent']);
        $parentNew = $this->createCategory(['code' => 'NEW-P', 'name' => 'New Parent']);

        $sub = $this->createCategory(['code' => 'SUB-M', 'name' => 'Subtree Root', 'parent_id' => $parentOld->id]);
        $leaf = $this->createCategory(['code' => 'LEAF-M', 'name' => 'Subtree Leaf', 'parent_id' => $sub->id]);

        $response = $this->actingAs($admin)->put("/categories/{$sub->id}", [
            'code' => 'SUB-M',
            'name' => 'Subtree Root',
            'parent_id' => (string) $parentNew->id,
            'sort_order' => 5,
            'status' => 'ACTIVE',
        ]);

        $response->assertRedirect();
        $this->assertEquals($parentNew->id, $sub->fresh()->parent_id);
        // Descendant leaf still points to sub
        $this->assertEquals($sub->id, $leaf->fresh()->parent_id);
        $this->assertEquals('New Parent > Subtree Root > Subtree Leaf', $leaf->fresh()->getHierarchyPath());

        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) use ($sub, $parentOld, $parentNew) {
                return $context['action'] === 'CATEGORY_REPARENTED'
                    && $context['category_id'] === $sub->id
                    && $context['previous_parent_id'] === $parentOld->id
                    && $context['new_parent_id'] === $parentNew->id;
            });
    }

    public function test_sibling_sort_order_produces_deterministic_sequence(): void
    {
        $parent = $this->createCategory(['code' => 'SORT-P', 'name' => 'Parent For Sort']);
        $c3 = $this->createCategory(['code' => 'C3', 'name' => 'Zebra', 'parent_id' => $parent->id, 'sort_order' => 10]);
        $c1 = $this->createCategory(['code' => 'C1', 'name' => 'Alpha', 'parent_id' => $parent->id, 'sort_order' => 1]);
        $c2 = $this->createCategory(['code' => 'C2', 'name' => 'Beta', 'parent_id' => $parent->id, 'sort_order' => 5]);

        $children = $parent->fresh()->children;
        $this->assertEquals(['C1', 'C2', 'C3'], $children->pluck('code')->all());
    }

    // =========================================================================
    // 3. LIFECYCLE CONTROLS & STATUS (CAT-MGT-011..014)
    // =========================================================================

    public function test_admin_can_deactivate_and_reactivate_category(): void
    {
        Log::spy();
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $category = $this->createCategory(['code' => 'CAT-LIFE', 'name' => 'Lifecycle Test', 'status' => CategoryStatus::ACTIVE]);

        // Deactivate
        $response1 = $this->actingAs($admin)->patch("/categories/{$category->id}/status", [
            'status' => 'INACTIVE',
            'reason' => 'Retiring seasonal category',
        ]);
        $response1->assertRedirect();
        $this->assertEquals(CategoryStatus::INACTIVE, $category->fresh()->status);

        // Reactivate
        $response2 = $this->actingAs($admin)->patch("/categories/{$category->id}/status", [
            'status' => 'ACTIVE',
            'reason' => 'Re-enabling category',
        ]);
        $response2->assertRedirect();
        $this->assertEquals(CategoryStatus::ACTIVE, $category->fresh()->status);

        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) use ($category) {
                return $context['action'] === 'CATEGORY_DEACTIVATED'
                    && $context['category_id'] === $category->id
                    && $context['reason'] === 'Retiring seasonal category';
            });

        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) use ($category) {
                return $context['action'] === 'CATEGORY_ACTIVATED'
                    && $context['category_id'] === $category->id;
            });
    }

    public function test_same_state_lifecycle_transition_is_noop(): void
    {
        Log::spy();
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $category = $this->createCategory(['status' => CategoryStatus::ACTIVE]);

        $response = $this->actingAs($admin)->patch("/categories/{$category->id}/status", [
            'status' => 'ACTIVE',
            'reason' => 'No change',
        ]);

        $response->assertRedirect();
        $this->assertEquals(CategoryStatus::ACTIVE, $category->fresh()->status);

        Log::shouldNotHaveReceived('info', function ($message, $context) {
            return in_array($context['action'] ?? null, ['CATEGORY_ACTIVATED', 'CATEGORY_DEACTIVATED'], true);
        });
    }

    public function test_inactive_category_cannot_be_selected_for_new_product_creation(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $inactiveCategory = $this->createCategory(['code' => 'CAT-INACT', 'status' => CategoryStatus::INACTIVE]);

        $response = $this->actingAs($admin)->post('/products', [
            'sku' => 'PROD-NEW-01',
            'name' => 'New Product Inactive Cat',
            'category_id' => (string) $inactiveCategory->id,
            'unit' => 'PCS',
            'status' => 'ACTIVE',
            'cost_price' => 50.00,
            'minimum_allowed_price' => 60.00,
            'default_selling_price' => 80.00,
            'mrp' => 100.00,
        ]);

        $response->assertSessionHasErrors(['category_id']);
        $this->assertDatabaseMissing('products', ['sku' => 'PROD-NEW-01']);
    }

    public function test_inactive_category_cannot_be_selected_when_reassigning_product_category(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $activeCat = $this->createCategory(['code' => 'CAT-ACT', 'status' => CategoryStatus::ACTIVE]);
        $inactiveCat = $this->createCategory(['code' => 'CAT-INACT2', 'status' => CategoryStatus::INACTIVE]);

        $product = $this->createProduct(['category_id' => $activeCat->id]);

        $response = $this->actingAs($admin)->put("/products/{$product->id}", [
            'sku' => $product->sku,
            'name' => $product->name,
            'category_id' => (string) $inactiveCat->id,
            'unit' => $product->unit,
            'status' => 'ACTIVE',
            'cost_price' => 50.00,
            'minimum_allowed_price' => 70.00,
            'default_selling_price' => 85.00,
            'mrp' => 100.00,
        ]);

        $response->assertSessionHasErrors(['category_id']);
        $this->assertEquals($activeCat->id, $product->fresh()->category_id);
    }

    // =========================================================================
    // 4. PRODUCT RELATIONSHIP & DELETION INVARIANTS (CAT-MGT-015..018)
    // =========================================================================

    public function test_existing_product_retains_category_when_category_becomes_inactive(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $category = $this->createCategory(['code' => 'CAT-RET', 'status' => CategoryStatus::ACTIVE]);
        $product = $this->createProduct([
            'category_id' => $category->id,
            'default_selling_price' => 120.00,
        ]);

        // Deactivate category
        $this->actingAs($admin)->patch("/categories/{$category->id}/status", [
            'status' => 'INACTIVE',
        ]);

        $freshProduct = $product->fresh();
        $this->assertEquals($category->id, $freshProduct->category_id);
        $this->assertEquals(120.00, $freshProduct->default_selling_price);
        $this->assertEquals(CategoryStatus::INACTIVE, $freshProduct->category->status);
    }

    public function test_product_update_retains_existing_inactive_category_if_unchanged(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $inactiveCategory = $this->createCategory(['code' => 'CAT-INACT-3', 'status' => CategoryStatus::INACTIVE]);
        $product = $this->createProduct(['category_id' => $inactiveCategory->id]);

        // Update product title without changing category_id
        $response = $this->actingAs($admin)->put("/products/{$product->id}", [
            'sku' => $product->sku,
            'name' => 'Updated Product Name Same Inactive Category',
            'category_id' => (string) $inactiveCategory->id,
            'unit' => $product->unit,
            'status' => 'ACTIVE',
            'cost_price' => 50.00,
            'minimum_allowed_price' => 70.00,
            'default_selling_price' => 85.00,
            'mrp' => 100.00,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals('Updated Product Name Same Inactive Category', $product->fresh()->name);
        $this->assertEquals($inactiveCategory->id, $product->fresh()->category_id);
    }

    public function test_cannot_delete_category_with_attached_products(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $category = $this->createCategory(['code' => 'CAT-WITH-PROD']);
        $this->createProduct(['category_id' => $category->id]);

        $response = $this->actingAs($admin)->delete("/categories/{$category->id}");

        $response->assertSessionHasErrors(['category']);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_with_child_subcategories(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $parent = $this->createCategory(['code' => 'CAT-WITH-CHILD']);
        $this->createCategory(['code' => 'CAT-SUB-1', 'parent_id' => $parent->id]);

        $response = $this->actingAs($admin)->delete("/categories/{$parent->id}");

        $response->assertSessionHasErrors(['category']);
        $this->assertDatabaseHas('categories', ['id' => $parent->id]);
    }

    public function test_empty_leaf_category_can_be_deleted(): void
    {
        Log::spy();
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $emptyLeaf = $this->createCategory(['code' => 'CAT-EMPTY-LEAF', 'name' => 'Empty Leaf']);

        $response = $this->actingAs($admin)->delete("/categories/{$emptyLeaf->id}");

        $response->assertRedirect('/categories');
        $this->assertDatabaseMissing('categories', ['id' => $emptyLeaf->id]);

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function ($message, $context) use ($emptyLeaf, $admin) {
                return $context['action'] === 'CATEGORY_DELETED'
                    && $context['category_id'] === $emptyLeaf->id
                    && $context['actor_id'] === $admin->id;
            });
    }

    // =========================================================================
    // 5. AUTHORIZATION & ROLE BOUNDARIES (CAT-MGT-019..022)
    // =========================================================================

    public function test_super_admin_and_admin_have_full_category_crud_access(): void
    {
        $superAdmin = $this->createUserWithRole(UserRole::SUPER_ADMIN);
        $category = $this->createCategory(['code' => 'CAT-SUPER', 'name' => 'Super Admin Cat']);

        $response = $this->actingAs($superAdmin)->get('/categories');
        $response->assertOk();

        $responseCreate = $this->actingAs($superAdmin)->get('/categories/create');
        $responseCreate->assertOk();

        $responseShow = $this->actingAs($superAdmin)->get("/categories/{$category->id}");
        $responseShow->assertOk();

        $responseEdit = $this->actingAs($superAdmin)->get("/categories/{$category->id}/edit");
        $responseEdit->assertOk();
    }

    public function test_salesman_and_warehouse_manager_have_read_only_access(): void
    {
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $whManager = $this->createUserWithRole(UserRole::WAREHOUSE_MANAGER);
        $category = $this->createCategory();

        // Salesman can view list and detail
        $this->actingAs($salesman)->get('/categories')->assertOk();
        $this->actingAs($salesman)->get("/categories/{$category->id}")->assertOk();

        // Salesman cannot create, edit, update, delete
        $this->actingAs($salesman)->get('/categories/create')->assertForbidden();
        $this->actingAs($salesman)->post('/categories', ['name' => 'Salesman Try', 'status' => 'ACTIVE'])->assertForbidden();
        $this->actingAs($salesman)->get("/categories/{$category->id}/edit")->assertForbidden();
        $this->actingAs($salesman)->put("/categories/{$category->id}", ['name' => 'Salesman Try'])->assertForbidden();
        $this->actingAs($salesman)->delete("/categories/{$category->id}")->assertForbidden();

        // Warehouse Manager can view list and detail
        $this->actingAs($whManager)->get('/categories')->assertOk();
        $this->actingAs($whManager)->get("/categories/{$category->id}")->assertOk();

        // Warehouse Manager cannot create or mutate
        $this->actingAs($whManager)->get('/categories/create')->assertForbidden();
        $this->actingAs($whManager)->post('/categories', ['name' => 'WH Try', 'status' => 'ACTIVE'])->assertForbidden();
        $this->actingAs($whManager)->delete("/categories/{$category->id}")->assertForbidden();
    }

    public function test_accountant_and_delivery_partner_are_denied_all_access(): void
    {
        $accountant = $this->createUserWithRole(UserRole::ACCOUNTANT);
        $driver = $this->createUserWithRole(UserRole::DELIVERY_PARTNER);
        $category = $this->createCategory();

        // Accountant denied
        $this->actingAs($accountant)->get('/categories')->assertForbidden();
        $this->actingAs($accountant)->get("/categories/{$category->id}")->assertForbidden();
        $this->actingAs($accountant)->post('/categories', ['name' => 'Acct Try'])->assertForbidden();

        // Delivery Partner denied
        $this->actingAs($driver)->get('/categories')->assertForbidden();
        $this->actingAs($driver)->get("/categories/{$category->id}")->assertForbidden();
        $this->actingAs($driver)->post('/categories', ['name' => 'Driver Try'])->assertForbidden();
    }

    public function test_unauthenticated_requests_are_redirected_to_login(): void
    {
        $category = $this->createCategory();

        $this->get('/categories')->assertRedirect('/login');
        $this->get("/categories/{$category->id}")->assertRedirect('/login');
        $this->post('/categories', ['name' => 'Unauth'])->assertRedirect('/login');
    }

    public function test_inactive_admin_actor_is_blocked_from_category_operations(): void
    {
        $suspendedAdmin = $this->createUserWithRole(UserRole::ADMIN, AccountStatus::SUSPENDED);

        $this->actingAs($suspendedAdmin)->get('/categories')->assertRedirect('/login');
        $this->actingAs($suspendedAdmin)->post('/categories', ['name' => 'Suspended Try'])->assertRedirect('/login');
    }

    // =========================================================================
    // 6. SEARCH & FILTER QUERIES (CAT-MGT-023..024)
    // =========================================================================

    public function test_category_search_by_code_name_and_description(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $c1 = $this->createCategory(['code' => 'CAT-ALPHA', 'name' => 'Alpha Fasteners', 'description' => 'Grade 8 bolts']);
        $c2 = $this->createCategory(['code' => 'CAT-BETA', 'name' => 'Beta Wire', 'description' => 'Copper conductors']);

        // Search by code
        $resCode = $this->categoryService->list(['search' => 'ALPHA'], 15);
        $this->assertEquals(1, $resCode->total());
        $this->assertEquals($c1->id, $resCode->items()[0]['id']);

        // Search by description
        $resDesc = $this->categoryService->list(['search' => 'Copper'], 15);
        $this->assertEquals(1, $resDesc->total());
        $this->assertEquals($c2->id, $resDesc->items()[0]['id']);
    }

    public function test_category_status_and_root_filters(): void
    {
        $rootActive = $this->createCategory(['code' => 'ROOT-ACT', 'name' => 'Root Active', 'parent_id' => null, 'status' => CategoryStatus::ACTIVE]);
        $rootInactive = $this->createCategory(['code' => 'ROOT-INACT', 'name' => 'Root Inactive', 'parent_id' => null, 'status' => CategoryStatus::INACTIVE]);
        $childActive = $this->createCategory(['code' => 'CHILD-ACT', 'name' => 'Child Active', 'parent_id' => $rootActive->id, 'status' => CategoryStatus::ACTIVE]);

        // Filter active only
        $activeList = $this->categoryService->list(['status' => 'ACTIVE'], 15);
        $this->assertEquals(2, $activeList->total());

        // Filter root only
        $rootList = $this->categoryService->list(['root_only' => true], 15);
        $this->assertEquals(2, $rootList->total());

        // Filter root only + inactive only
        $rootInactiveList = $this->categoryService->list(['root_only' => true, 'status' => 'INACTIVE'], 15);
        $this->assertEquals(1, $rootInactiveList->total());
        $this->assertEquals($rootInactive->id, $rootInactiveList->items()[0]['id']);
    }
}
