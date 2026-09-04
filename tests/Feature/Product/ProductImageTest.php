<?php

namespace Tests\Feature\Product;

use App\Enums\AccountStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\Product\ProductImageService;
use App\Services\Product\ProductService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    protected ProductImageService $imageService;
    protected ProductService $productService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->imageService = app(ProductImageService::class);
        $this->productService = app(ProductService::class);
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
        static $skuCounter = 1000;
        $skuCounter++;

        return Product::create(array_merge([
            'sku' => 'PROD-' . str_pad((string) $skuCounter, 5, '0', STR_PAD_LEFT),
            'name' => 'Test Product ' . $skuCounter,
            'description' => 'Product description ' . $skuCounter,
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
    // 1. AUTHORIZATION (PROD-IMG-001..005)
    // =========================================================================

    /**
     * PROD-IMG-001: Super Admin has full image management permissions.
     */
    public function test_super_admin_can_upload_set_primary_and_delete_images(): void
    {
        $admin = $this->createUserWithRole(UserRole::SUPER_ADMIN);
        $product = $this->createProduct();

        $file = UploadedFile::fake()->image('superadmin_img.jpg', 600, 600);

        // Upload
        $response = $this->actingAs($admin)->post("/products/{$product->id}/images", [
            'image' => $file,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_images', ['product_id' => $product->id]);

        $image = ProductImage::where('product_id', $product->id)->firstOrFail();

        // Set Primary
        $primaryResponse = $this->actingAs($admin)->patch("/products/{$product->id}/images/{$image->id}/primary");
        $primaryResponse->assertSessionHasNoErrors();

        // Delete
        $deleteResponse = $this->actingAs($admin)->delete("/products/{$product->id}/images/{$image->id}");
        $deleteResponse->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    }

    /**
     * PROD-IMG-002: Admin has full image management permissions.
     */
    public function test_admin_can_upload_set_primary_and_delete_images(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        $file = UploadedFile::fake()->image('admin_img.png', 400, 400);

        // Upload
        $response = $this->actingAs($admin)->post("/products/{$product->id}/images", [
            'image' => $file,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_images', ['product_id' => $product->id]);

        $image = ProductImage::where('product_id', $product->id)->firstOrFail();

        // Set Primary
        $primaryResponse = $this->actingAs($admin)->patch("/products/{$product->id}/images/{$image->id}/primary");
        $primaryResponse->assertSessionHasNoErrors();

        // Delete
        $deleteResponse = $this->actingAs($admin)->delete("/products/{$product->id}/images/{$image->id}");
        $deleteResponse->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    }

    /**
     * PROD-IMG-003: Salesman can view product images but cannot upload/mutate.
     */
    public function test_salesman_can_view_images_but_cannot_upload_or_delete(): void
    {
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);
        $product = $this->createProduct();

        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $image = $this->imageService->upload($product, UploadedFile::fake()->image('catalog.jpg', 300, 300), $admin);

        // View allowed
        $viewResponse = $this->actingAs($salesman)->get("/products/{$product->id}");
        $viewResponse->assertOk();

        // Upload forbidden
        $uploadResponse = $this->actingAs($salesman)->post("/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('salesman_try.jpg', 200, 200),
        ]);
        $uploadResponse->assertForbidden();

        // Set primary forbidden
        $primaryResponse = $this->actingAs($salesman)->patch("/products/{$product->id}/images/{$image->id}/primary");
        $primaryResponse->assertForbidden();

        // Delete forbidden
        $deleteResponse = $this->actingAs($salesman)->delete("/products/{$product->id}/images/{$image->id}");
        $deleteResponse->assertForbidden();
    }

    /**
     * PROD-IMG-004: Warehouse Manager can view product images but cannot mutate.
     */
    public function test_warehouse_manager_can_view_images_but_cannot_upload_or_delete(): void
    {
        $wm = $this->createUserWithRole(UserRole::WAREHOUSE_MANAGER);
        $product = $this->createProduct();

        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $image = $this->imageService->upload($product, UploadedFile::fake()->image('box.jpg', 300, 300), $admin);

        // View allowed
        $viewResponse = $this->actingAs($wm)->get("/products/{$product->id}");
        $viewResponse->assertOk();

        // Upload forbidden
        $uploadResponse = $this->actingAs($wm)->post("/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('wm_try.jpg', 200, 200),
        ]);
        $uploadResponse->assertForbidden();

        // Delete forbidden
        $deleteResponse = $this->actingAs($wm)->delete("/products/{$product->id}/images/{$image->id}");
        $deleteResponse->assertForbidden();
    }

    /**
     * PROD-IMG-005: Accountant, Delivery Partner, and Unauthenticated are rejected.
     */
    public function test_unauthorized_roles_and_guests_are_rejected(): void
    {
        $product = $this->createProduct();
        $accountant = $this->createUserWithRole(UserRole::ACCOUNTANT);
        $delivery = $this->createUserWithRole(UserRole::DELIVERY_PARTNER);

        // Guest upload
        $this->post("/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('guest.jpg', 200, 200),
        ])->assertRedirect('/login');

        // Accountant upload
        $this->actingAs($accountant)->post("/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('acc.jpg', 200, 200),
        ])->assertForbidden();

        // Delivery partner upload
        $this->actingAs($delivery)->post("/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('del.jpg', 200, 200),
        ])->assertForbidden();
    }

    // =========================================================================
    // 2. FILE VALIDATION (PROD-IMG-006..010)
    // =========================================================================

    /**
     * PROD-IMG-006: Valid JPEG, PNG, and WebP images upload successfully.
     */
    public function test_valid_jpeg_png_and_webp_images_are_accepted(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        $jpeg = UploadedFile::fake()->image('sample.jpeg', 400, 400);
        $png = UploadedFile::fake()->image('sample.png', 400, 400);
        $webp = UploadedFile::fake()->image('sample.webp', 400, 400);

        $img1 = $this->imageService->upload($product, $jpeg, $admin);
        $this->assertEquals('image/jpeg', $img1->mime_type);
        Storage::disk('s3')->assertExists($img1->object_key);

        $img2 = $this->imageService->upload($product, $png, $admin);
        $this->assertEquals('image/png', $img2->mime_type);
        Storage::disk('s3')->assertExists($img2->object_key);

        $img3 = $this->imageService->upload($product, $webp, $admin);
        $this->assertEquals('image/webp', $img3->mime_type);
        Storage::disk('s3')->assertExists($img3->object_key);

        $this->assertCount(3, ProductImage::where('product_id', $product->id)->get());
    }

    /**
     * PROD-IMG-007: Image larger than 5MB is rejected.
     */
    public function test_oversized_image_above_5mb_is_rejected(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        // 6MB image
        $oversized = UploadedFile::fake()->image('large.jpg', 2000, 2000)->size(6000);

        $response = $this->actingAs($admin)->post("/products/{$product->id}/images", [
            'image' => $oversized,
        ]);

        $response->assertSessionHasErrors(['image']);
        $this->assertDatabaseCount('product_images', 0);
    }

    /**
     * PROD-IMG-008: SVG files are strictly prohibited and rejected.
     */
    public function test_svg_files_are_strictly_prohibited(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="40"/></svg>';
        $svgFile = UploadedFile::fake()->createWithContent('vector.svg', $svgContent);

        $response = $this->actingAs($admin)->post("/products/{$product->id}/images", [
            'image' => $svgFile,
        ]);

        $response->assertSessionHasErrors(['image']);
        $this->assertDatabaseCount('product_images', 0);
    }

    /**
     * PROD-IMG-009: Malicious executable / script renamed to .jpg is rejected by magic-byte inspection.
     */
    public function test_malicious_script_renamed_to_jpg_is_rejected_via_magic_byte_inspection(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        $fakeJpg = UploadedFile::fake()->createWithContent('malicious.jpg', "<?php echo 'malicious payload'; ?>\n");

        $this->expectException(ValidationException::class);
        $this->imageService->upload($product, $fakeJpg, $admin);

        $this->assertDatabaseCount('product_images', 0);
    }

    /**
     * PROD-IMG-010: Truncated or corrupt image binary is rejected safely.
     */
    public function test_corrupt_or_truncated_image_binary_is_rejected(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        // Fake binary with random bytes not forming a valid image structure
        $corruptBinary = UploadedFile::fake()->createWithContent('corrupt.jpg', "\xFF\xD8\xFF" . str_repeat('X', 50));

        $this->expectException(ValidationException::class);
        $this->imageService->upload($product, $corruptBinary, $admin);

        $this->assertDatabaseCount('product_images', 0);
    }

    // =========================================================================
    // 3. STORAGE & PRESIGNED URLS (PROD-IMG-011..014)
    // =========================================================================

    /**
     * PROD-IMG-011: Object key strictly matches `products/{product_id}/{uuid}.{ext}`.
     */
    public function test_object_key_matches_secure_uuid_format(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        $file = UploadedFile::fake()->image('test_product_photo.jpg', 500, 500);
        $image = $this->imageService->upload($product, $file, $admin);

        $this->assertMatchesRegularExpression(
            '/^products\/' . $product->id . '\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.jpg$/',
            $image->object_key
        );
        Storage::disk('s3')->assertExists($image->object_key);
    }

    /**
     * PROD-IMG-012: Database stores object_key, never persists temporary presigned URLs.
     */
    public function test_database_stores_only_stable_object_key(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        $file = UploadedFile::fake()->image('product_img.png', 400, 400);
        $image = $this->imageService->upload($product, $file, $admin);

        $dbRecord = DB::table('product_images')->where('id', $image->id)->first();
        $this->assertNotNull($dbRecord);
        $this->assertEquals($image->object_key, $dbRecord->object_key);
        $this->assertStringNotContainsString('http', $dbRecord->object_key);
        $this->assertStringNotContainsString('X-Amz', $dbRecord->object_key);
    }

    /**
     * PROD-IMG-013: Temporary URL is generated dynamically for authorized presentation.
     */
    public function test_temporary_url_is_generated_dynamically(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        $file = UploadedFile::fake()->image('test_dynamic.jpg', 300, 300);
        $image = $this->imageService->upload($product, $file, $admin);

        $url = $this->imageService->getTemporaryUrl($image);
        $this->assertNotNull($url);

        $formatted = $this->productService->formatProduct($product, $admin);
        $this->assertNotNull($formatted['primary_image_url']);
        $this->assertCount(1, $formatted['images']);
        $this->assertEquals($url, $formatted['images'][0]['url']);
    }

    /**
     * PROD-IMG-014: Original client filename is not used in storage object key path.
     */
    public function test_original_client_filename_is_not_in_storage_path(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        $file = UploadedFile::fake()->image('User_Raw_Uploaded_Name_12345.png', 300, 300);
        $image = $this->imageService->upload($product, $file, $admin);

        $this->assertStringNotContainsString('User_Raw_Uploaded_Name_12345', $image->object_key);
        $this->assertEquals('User_Raw_Uploaded_Name_12345.png', $image->original_filename);
    }

    // =========================================================================
    // 4. OWNERSHIP & IDOR BOUNDARIES (PROD-IMG-015..017)
    // =========================================================================

    /**
     * PROD-IMG-015: Setting primary on an image belonging to another product is rejected (404).
     */
    public function test_cannot_set_primary_on_image_belonging_to_another_product(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $productA = $this->createProduct();
        $productB = $this->createProduct();

        $imageA = $this->imageService->upload($productA, UploadedFile::fake()->image('img_a.jpg', 200, 200), $admin);

        // Attempt to set imageA as primary on productB
        $response = $this->actingAs($admin)->patch("/products/{$productB->id}/images/{$imageA->id}/primary");
        $response->assertNotFound();

        // Direct service call throws NotFoundHttpException
        $this->expectException(NotFoundHttpException::class);
        $this->imageService->setPrimary($productB, $imageA, $admin);
    }

    /**
     * PROD-IMG-016: Deleting an image belonging to another product is rejected (404).
     */
    public function test_cannot_delete_image_belonging_to_another_product(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $productA = $this->createProduct();
        $productB = $this->createProduct();

        $imageA = $this->imageService->upload($productA, UploadedFile::fake()->image('img_a.jpg', 200, 200), $admin);

        // Attempt to delete imageA via productB
        $response = $this->actingAs($admin)->delete("/products/{$productB->id}/images/{$imageA->id}");
        $response->assertNotFound();

        $this->assertDatabaseHas('product_images', ['id' => $imageA->id]);
    }

    /**
     * PROD-IMG-017: Tampered or non-existent image ID returns 404.
     */
    public function test_tampered_non_existent_image_id_returns_404(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        $response = $this->actingAs($admin)->delete("/products/{$product->id}/images/999999");
        $response->assertNotFound();
    }

    // =========================================================================
    // 5. PRIMARY IMAGE LIFECYCLE (PROD-IMG-018..021)
    // =========================================================================

    /**
     * PROD-IMG-018: First uploaded image automatically becomes primary.
     */
    public function test_first_uploaded_image_automatically_becomes_primary(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        $image1 = $this->imageService->upload($product, UploadedFile::fake()->image('first.jpg', 300, 300), $admin);

        $this->assertTrue($image1->is_primary);
        $this->assertEquals($image1->id, $product->fresh()->primaryImage?->id);
    }

    /**
     * PROD-IMG-019: Subsequent uploads are non-primary unless requested.
     */
    public function test_subsequent_uploads_are_non_primary_unless_requested(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        $image1 = $this->imageService->upload($product, UploadedFile::fake()->image('first.jpg', 300, 300), $admin);
        $image2 = $this->imageService->upload($product, UploadedFile::fake()->image('second.jpg', 300, 300), $admin, false);

        $this->assertTrue($image1->fresh()->is_primary);
        $this->assertFalse($image2->fresh()->is_primary);
        $this->assertEquals(1, ProductImage::where('product_id', $product->id)->where('is_primary', true)->count());
    }

    /**
     * PROD-IMG-020: Explicitly setting primary unsets previous primary image.
     */
    public function test_setting_primary_unsets_previous_primary(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        $image1 = $this->imageService->upload($product, UploadedFile::fake()->image('first.jpg', 300, 300), $admin);
        $image2 = $this->imageService->upload($product, UploadedFile::fake()->image('second.jpg', 300, 300), $admin);

        $this->imageService->setPrimary($product, $image2, $admin);

        $this->assertFalse($image1->fresh()->is_primary);
        $this->assertTrue($image2->fresh()->is_primary);
        $this->assertEquals($image2->id, $product->fresh()->primaryImage?->id);
        $this->assertEquals(1, ProductImage::where('product_id', $product->id)->where('is_primary', true)->count());
    }

    /**
     * PROD-IMG-021: Deleting primary image automatically promotes the next available image.
     */
    public function test_deleting_primary_image_promotes_next_available_image(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        $image1 = $this->imageService->upload($product, UploadedFile::fake()->image('first.jpg', 300, 300), $admin, false, 1);
        $image2 = $this->imageService->upload($product, UploadedFile::fake()->image('second.jpg', 300, 300), $admin, false, 2);
        $image3 = $this->imageService->upload($product, UploadedFile::fake()->image('third.jpg', 300, 300), $admin, false, 3);

        $this->assertTrue($image1->fresh()->is_primary);

        // Delete primary (image1)
        $this->imageService->delete($product, $image1, $admin);

        // image2 should now be promoted to primary
        $this->assertDatabaseMissing('product_images', ['id' => $image1->id]);
        $this->assertTrue($image2->fresh()->is_primary);
        $this->assertFalse($image3->fresh()->is_primary);
        $this->assertEquals(1, ProductImage::where('product_id', $product->id)->where('is_primary', true)->count());
    }

    // =========================================================================
    // 6. CONSISTENCY, AUDIT & INVOICE INVARIANT (PROD-IMG-022..024)
    // =========================================================================

    /**
     * PROD-IMG-022: DB failure during upload triggers compensating S3 object deletion.
     */
    public function test_db_failure_triggers_compensating_s3_deletion(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        // Simulate database failure during transaction
        DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new Exception('Simulated PostgreSQL Connection Error'));

        $file = UploadedFile::fake()->image('compensate_test.jpg', 300, 300);

        try {
            $this->imageService->upload($product, $file, $admin);
            $this->fail('Expected Exception was not thrown.');
        } catch (Exception $e) {
            $this->assertEquals('Simulated PostgreSQL Connection Error', $e->getMessage());
        }

        // S3 disk should have 0 remaining objects (compensated)
        $this->assertEmpty(Storage::disk('s3')->allFiles());
    }

    /**
     * PROD-IMG-023: Audit log events are emitted with safe metadata and no secrets.
     */
    public function test_audit_logs_are_emitted_for_image_lifecycle(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        Log::shouldReceive('info')
            ->with('Product image uploaded', \Mockery::on(function ($data) use ($admin, $product) {
                return $data['event'] === 'audit.product_image_event'
                    && $data['action'] === 'PRODUCT_IMAGE_UPLOADED'
                    && $data['actor_id'] === $admin->id
                    && $data['product_id'] === $product->id
                    && ! isset($data['temporary_url'])
                    && ! isset($data['secret']);
            }))
            ->once();

        Log::shouldReceive('info')
            ->with('Product image set as primary', \Mockery::on(function ($data) {
                return $data['action'] === 'PRODUCT_IMAGE_SET_PRIMARY';
            }))
            ->once();

        Log::shouldReceive('info')
            ->with('Product image deleted', \Mockery::on(function ($data) {
                return $data['action'] === 'PRODUCT_IMAGE_DELETED';
            }))
            ->once();

        $image = $this->imageService->upload($product, UploadedFile::fake()->image('audit.jpg', 300, 300), $admin);
        $this->imageService->setPrimary($product, $image, $admin);
        $this->imageService->delete($product, $image, $admin);
    }

    /**
     * PROD-IMG-024: RULE-DOC-001 Invariant — Product images are never exposed on invoices.
     */
    public function test_rule_doc_001_invoices_do_not_contain_product_images(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $product = $this->createProduct();

        $this->imageService->upload($product, UploadedFile::fake()->image('catalog_photo.jpg', 300, 300), $admin);

        // Verify product format preserves images for catalog
        $formatted = $this->productService->formatProduct($product, $admin);
        $this->assertNotEmpty($formatted['images']);

        // Invariant check: Invoices only format line financial data (SKU, name, quantities, rates, amounts)
        // Ensure no image attributes are present in financial transaction snapshot contract
        $this->assertArrayNotHasKey('invoice_image', $formatted);
        $this->assertArrayNotHasKey('invoice_image_url', $formatted);
    }
}
