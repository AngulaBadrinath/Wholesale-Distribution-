<?php

namespace Tests\Feature\Product;

use App\Enums\AccountStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\Product\ProductImageService;
use App\Services\Product\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected ProductService $productService;
    protected ProductImageService $productImageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productService = app(ProductService::class);
        $this->productImageService = app(ProductImageService::class);
        Storage::fake('s3');
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
     * Helper to create a test product.
     */
    protected function createProduct(array $overrides = []): Product
    {
        static $skuCounter = 5000;
        $skuCounter++;

        return Product::create(array_merge([
            'sku' => 'PROD-' . str_pad((string) $skuCounter, 5, '0', STR_PAD_LEFT),
            'name' => 'Test Lifecycle Product ' . $skuCounter,
            'description' => 'Lifecycle testing description ' . $skuCounter,
            'unit' => 'PCS',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => 50.00,
            'minimum_allowed_price' => 60.00,
            'default_selling_price' => 75.00,
            'mrp' => 100.00,
            'tax_profile_id' => null,
        ], $overrides));
    }

    // =========================================================================
    // 1. ORDER READINESS INVARIANTS (PROD-LIFE-001 & PROD-LIFE-002)
    // =========================================================================

    /**
     * PROD-LIFE-001: Active product order readiness (canOrder() true, ensureCanOrder() passes).
     */
    public function test_active_product_order_readiness(): void
    {
        $product = $this->createProduct(['status' => ProductStatus::ACTIVE]);

        $this->assertTrue($product->isActive());
        $this->assertTrue($product->canOrder());

        // ensureCanOrder() must pass without throwing ValidationException
        $exceptionThrown = false;
        try {
            $product->ensureCanOrder();
        } catch (ValidationException $e) {
            $exceptionThrown = true;
        }

        $this->assertFalse($exceptionThrown, 'ensureCanOrder() must not throw for ACTIVE products.');
    }

    /**
     * PROD-LIFE-002: Inactive product order block (canOrder() false, ensureCanOrder() throws ValidationException).
     */
    public function test_inactive_product_order_block(): void
    {
        $product = $this->createProduct(['status' => ProductStatus::INACTIVE]);

        $this->assertFalse($product->isActive());
        $this->assertFalse($product->canOrder());

        $this->expectException(ValidationException::class);
        $product->ensureCanOrder();
    }

    // =========================================================================
    // 2. AUTHORIZED LIFECYCLE TRANSITIONS (PROD-LIFE-003 to PROD-LIFE-005)
    // =========================================================================

    /**
     * PROD-LIFE-003: Admin deactivation (ACTIVE -> INACTIVE with reason).
     */
    public function test_admin_can_deactivate_active_product_with_reason(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct(['status' => ProductStatus::ACTIVE]);

        $response = $this->actingAs($admin)->patch(route('products.status', $product), [
            'status' => 'INACTIVE',
            'reason' => 'Vendor discontinued packaging line',
        ]);

        $response->assertRedirect(route('products.show', $product));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => ProductStatus::INACTIVE->value,
        ]);

        $product->refresh();
        $this->assertSame(ProductStatus::INACTIVE, $product->status);
        $this->assertFalse($product->canOrder());
    }

    /**
     * PROD-LIFE-004: Admin reactivation (INACTIVE -> ACTIVE with reason).
     */
    public function test_admin_can_reactivate_inactive_product_with_reason(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct(['status' => ProductStatus::INACTIVE]);

        $response = $this->actingAs($admin)->patch(route('products.status', $product), [
            'status' => 'ACTIVE',
            'reason' => 'Inventory replenished from secondary distributor',
        ]);

        $response->assertRedirect(route('products.show', $product));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => ProductStatus::ACTIVE->value,
        ]);

        $product->refresh();
        $this->assertSame(ProductStatus::ACTIVE, $product->status);
        $this->assertTrue($product->canOrder());
    }

    /**
     * PROD-LIFE-005: Super Admin can transition product lifecycle states.
     */
    public function test_super_admin_can_transition_product_lifecycle_states(): void
    {
        $superAdmin = $this->createUserWithRole(UserRole::SUPER_ADMIN);
        $product = $this->createProduct(['status' => ProductStatus::ACTIVE]);

        // Deactivate
        $response1 = $this->actingAs($superAdmin)->patch(route('products.status', $product), [
            'status' => 'INACTIVE',
            'reason' => 'Superadmin deactivation test',
        ]);
        $response1->assertRedirect(route('products.show', $product));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => ProductStatus::INACTIVE->value]);

        // Reactivate
        $response2 = $this->actingAs($superAdmin)->patch(route('products.status', $product), [
            'status' => 'ACTIVE',
            'reason' => 'Superadmin reactivation test',
        ]);
        $response2->assertRedirect(route('products.show', $product));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => ProductStatus::ACTIVE->value]);
    }

    // =========================================================================
    // 3. AUTHORIZATION BOUNDARIES & DENIALS (PROD-LIFE-006 to PROD-LIFE-010)
    // =========================================================================

    /**
     * PROD-LIFE-006: Salesman cannot update product lifecycle status (403 Forbidden).
     */
    public function test_salesman_cannot_update_product_lifecycle_status(): void
    {
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $product = $this->createProduct(['status' => ProductStatus::ACTIVE]);

        $response = $this->actingAs($salesman)->patch(route('products.status', $product), [
            'status' => 'INACTIVE',
            'reason' => 'Unauthorized salesman attempt',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => ProductStatus::ACTIVE->value]);
    }

    /**
     * PROD-LIFE-007: Warehouse Manager cannot update product lifecycle status (403 Forbidden).
     */
    public function test_warehouse_manager_cannot_update_product_lifecycle_status(): void
    {
        $warehouse = $this->createUserWithRole(UserRole::WAREHOUSE_MANAGER);
        $product = $this->createProduct(['status' => ProductStatus::ACTIVE]);

        $response = $this->actingAs($warehouse)->patch(route('products.status', $product), [
            'status' => 'INACTIVE',
            'reason' => 'Unauthorized warehouse attempt',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => ProductStatus::ACTIVE->value]);
    }

    /**
     * PROD-LIFE-008: Accountant and Delivery Partner cannot update lifecycle status (403 Forbidden).
     */
    public function test_accountant_and_delivery_partner_cannot_update_lifecycle_status(): void
    {
        $accountant = $this->createUserWithRole(UserRole::ACCOUNTANT);
        $delivery = $this->createUserWithRole(UserRole::DELIVERY_PARTNER);
        $product = $this->createProduct(['status' => ProductStatus::ACTIVE]);

        $res1 = $this->actingAs($accountant)->patch(route('products.status', $product), [
            'status' => 'INACTIVE',
        ]);
        $res1->assertForbidden();

        $res2 = $this->actingAs($delivery)->patch(route('products.status', $product), [
            'status' => 'INACTIVE',
        ]);
        $res2->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => ProductStatus::ACTIVE->value]);
    }

    /**
     * PROD-LIFE-009: Unauthenticated guest status update redirects to login.
     */
    public function test_unauthenticated_guest_status_update_redirects_to_login(): void
    {
        $product = $this->createProduct(['status' => ProductStatus::ACTIVE]);

        $response = $this->patch(route('products.status', $product), [
            'status' => 'INACTIVE',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => ProductStatus::ACTIVE->value]);
    }

    /**
     * PROD-LIFE-010: Suspended or inactive administrator account blocked from mutating status.
     */
    public function test_inactive_or_suspended_admin_cannot_update_product_lifecycle(): void
    {
        $suspendedAdmin = $this->createUserWithRole(UserRole::ADMIN, AccountStatus::SUSPENDED);
        $disabledAdmin = $this->createUserWithRole(UserRole::ADMIN, AccountStatus::DISABLED);
        $product = $this->createProduct(['status' => ProductStatus::ACTIVE]);

        $res1 = $this->actingAs($suspendedAdmin)->patch(route('products.status', $product), [
            'status' => 'INACTIVE',
        ]);
        $res1->assertRedirect(route('login'));

        $res2 = $this->actingAs($disabledAdmin)->patch(route('products.status', $product), [
            'status' => 'INACTIVE',
        ]);
        $res2->assertRedirect(route('login'));

        // Also assert that direct Service invocation throws AuthorizationException
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->productService->updateStatus(
            product: $product,
            newStatus: ProductStatus::INACTIVE,
            actor: $suspendedAdmin
        );

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => ProductStatus::ACTIVE->value]);
    }

    // =========================================================================
    // 4. NO-OP SUPPRESSION & VALIDATION (PROD-LIFE-011 to PROD-LIFE-013)
    // =========================================================================

    /**
     * PROD-LIFE-011: No-op status transition suppression (same state returns cleanly without audit duplication).
     */
    public function test_no_op_status_transition_suppression(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct(['status' => ProductStatus::ACTIVE]);

        Log::shouldReceive('info')
            ->never()
            ->withArgs(function ($message, $context) {
                return isset($context['action']) && in_array($context['action'], ['PRODUCT_ACTIVATED', 'PRODUCT_DEACTIVATED'], true);
            });

        // Request ACTIVE -> ACTIVE
        $response = $this->actingAs($admin)->patch(route('products.status', $product), [
            'status' => 'ACTIVE',
            'reason' => 'Redundant active request',
        ]);

        $response->assertRedirect(route('products.show', $product));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => ProductStatus::ACTIVE->value]);
    }

    /**
     * PROD-LIFE-012: Invalid status string payload rejected with 422 Unprocessable Entity.
     */
    public function test_invalid_status_payload_rejected(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct(['status' => ProductStatus::ACTIVE]);

        $invalidStatuses = ['SUSPENDED', 'DELETED', 'ARCHIVED', 'PENDING', 'DRAFT', 'UNKNOWN'];

        foreach ($invalidStatuses as $invalidStatus) {
            $response = $this->actingAs($admin)->patch(route('products.status', $product), [
                'status' => $invalidStatus,
            ]);

            $response->assertSessionHasErrors('status');
        }

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => ProductStatus::ACTIVE->value]);
    }

    /**
     * PROD-LIFE-013: Reason field max length validation (>500 characters rejected with 422).
     */
    public function test_reason_field_max_length_validation(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct(['status' => ProductStatus::ACTIVE]);

        $longReason = str_repeat('A', 501);

        $response = $this->actingAs($admin)->patch(route('products.status', $product), [
            'status' => 'INACTIVE',
            'reason' => $longReason,
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => ProductStatus::ACTIVE->value]);
    }

    // =========================================================================
    // 5. AUDIT LOGGING & ASSET PRESERVATION (PROD-LIFE-014 to PROD-LIFE-016)
    // =========================================================================

    /**
     * PROD-LIFE-014: Audit log classification (PRODUCT_ACTIVATED / PRODUCT_DEACTIVATED) emitted.
     */
    public function test_lifecycle_transitions_emit_correct_audit_events(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct(['status' => ProductStatus::ACTIVE]);

        $loggedActions = [];

        Log::shouldReceive('info')
            ->andReturnUsing(function ($message, $context) use (&$loggedActions) {
                if (isset($context['action'])) {
                    $loggedActions[] = $context['action'];
                }
            });

        // Deactivate: should emit PRODUCT_DEACTIVATED
        $this->actingAs($admin)->patch(route('products.status', $product), [
            'status' => 'INACTIVE',
            'reason' => 'Seasonal product pause',
        ]);

        $this->assertContains('PRODUCT_DEACTIVATED', $loggedActions);

        // Reactivate: should emit PRODUCT_ACTIVATED
        $this->actingAs($admin)->patch(route('products.status', $product), [
            'status' => 'ACTIVE',
            'reason' => 'Season resumed',
        ]);

        $this->assertContains('PRODUCT_ACTIVATED', $loggedActions);
    }

    /**
     * PROD-LIFE-015: Image preservation invariant (deactivating product preserves S3 objects & product_images rows).
     */
    public function test_product_deactivation_preserves_images_and_s3_objects(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct(['status' => ProductStatus::ACTIVE]);

        // Upload an image
        $file = UploadedFile::fake()->image('catalog_widget.jpg', 640, 480);
        $uploadResponse = $this->actingAs($admin)->post("/products/{$product->id}/images", [
            'image' => $file,
            'is_primary' => '1',
        ]);
        $uploadResponse->assertSessionHasNoErrors();

        /** @var ProductImage $image */
        $image = ProductImage::where('product_id', $product->id)->firstOrFail();
        $this->assertTrue($image->is_primary);
        $this->assertNotEmpty($image->object_key);
        $this->assertTrue(Storage::disk('s3')->exists($image->object_key));

        $objectKeyBefore = $image->object_key;
        $imageCountBefore = ProductImage::where('product_id', $product->id)->count();

        // Deactivate product
        $statusResponse = $this->actingAs($admin)->patch(route('products.status', $product), [
            'status' => 'INACTIVE',
            'reason' => 'Catalog retirement',
        ]);
        $statusResponse->assertSessionHasNoErrors();

        // Verify product is inactive
        $product->refresh();
        $this->assertSame(ProductStatus::INACTIVE, $product->status);

        // Invariant checks:
        // 1. Image record count unchanged
        $this->assertSame($imageCountBefore, ProductImage::where('product_id', $product->id)->count());

        // 2. Image database row remains unchanged
        $image->refresh();
        $this->assertSame($product->id, $image->product_id);
        $this->assertTrue($image->is_primary);
        $this->assertSame($objectKeyBefore, $image->object_key);

        // 3. S3 object physically exists and was NOT deleted
        $this->assertTrue(Storage::disk('s3')->exists($image->object_key));
    }

    /**
     * PROD-LIFE-016: Status change through general update path produces dedicated lifecycle audit log.
     */
    public function test_status_change_through_general_update_path_emits_lifecycle_audit(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct([
            'status' => ProductStatus::ACTIVE,
            'name' => 'Original Product Name',
        ]);

        $loggedActions = [];

        Log::shouldReceive('info')
            ->andReturnUsing(function ($message, $context) use (&$loggedActions) {
                if (isset($context['action'])) {
                    $loggedActions[] = $context['action'];
                }
            });

        // Update product attributes including status to INACTIVE via general PUT route
        $response = $this->actingAs($admin)->put(route('products.update', $product), [
            'sku' => $product->sku,
            'name' => 'Updated Product Name',
            'description' => $product->description,
            'category_id' => $product->category_id,
            'unit' => $product->unit,
            'status' => 'INACTIVE',
            'cost_price' => 50.00,
            'minimum_allowed_price' => 60.00,
            'default_selling_price' => 75.00,
            'mrp' => 100.00,
        ]);

        $response->assertRedirect(route('products.show', $product));

        $product->refresh();
        $this->assertSame('Updated Product Name', $product->name);
        $this->assertSame(ProductStatus::INACTIVE, $product->status);

        // Assert both PRODUCT_UPDATED and PRODUCT_DEACTIVATED were logged
        $this->assertContains('PRODUCT_UPDATED', $loggedActions);
        $this->assertContains('PRODUCT_DEACTIVATED', $loggedActions);
    }
}
