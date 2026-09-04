<?php

namespace Tests\Feature\Tax;

use App\Enums\TaxProfileStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProductTaxIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;
    protected TaxProfile $activeProfile;
    protected TaxProfile $inactiveProfile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Machinery',
            'code' => 'MACHINERY',
            'status' => 'ACTIVE',
        ]);

        $this->activeProfile = TaxProfile::create([
            'name' => 'Standard Industrial Tax',
            'code' => 'TAX-IND-700',
            'rate' => '7.0000',
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $this->inactiveProfile = TaxProfile::create([
            'name' => 'Obsolete Tax Profile',
            'code' => 'TAX-OBS-400',
            'rate' => '4.0000',
            'status' => TaxProfileStatus::INACTIVE,
        ]);
    }

    protected function createAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_create_product_with_active_tax_profile(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/products', [
            'sku' => 'PROD-TAX-001',
            'name' => 'Industrial Lathe Chuck',
            'category_id' => $this->category->id,
            'unit' => 'UNIT',
            'status' => 'ACTIVE',
            'cost_price' => '200.00',
            'minimum_allowed_price' => '250.00',
            'default_selling_price' => '300.00',
            'mrp' => '350.00',
            'tax_profile_id' => $this->activeProfile->id,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('products', [
            'sku' => 'PROD-TAX-001',
            'tax_profile_id' => $this->activeProfile->id,
        ]);
    }

    public function test_creating_product_with_inactive_tax_profile_is_rejected(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/products', [
            'sku' => 'PROD-TAX-002',
            'name' => 'Drill Press',
            'category_id' => $this->category->id,
            'unit' => 'UNIT',
            'status' => 'ACTIVE',
            'cost_price' => '100.00',
            'minimum_allowed_price' => '120.00',
            'default_selling_price' => '150.00',
            'mrp' => '180.00',
            'tax_profile_id' => $this->inactiveProfile->id,
        ]);

        $response->assertSessionHasErrors(['tax_profile_id']);
    }

    public function test_admin_can_update_product_tax_profile(): void
    {
        $admin = $this->createAdmin();

        $newActiveProfile = TaxProfile::create([
            'name' => 'Specialized Machinery Tax',
            'code' => 'TAX-SPEC-850',
            'rate' => '8.5000',
            'status' => TaxProfileStatus::ACTIVE,
        ]);

        $product = Product::create([
            'sku' => 'PROD-TAX-003',
            'name' => 'Hydraulic Press',
            'category_id' => $this->category->id,
            'unit' => 'UNIT',
            'status' => 'ACTIVE',
            'cost_price' => '500.00',
            'minimum_allowed_price' => '600.00',
            'default_selling_price' => '700.00',
            'mrp' => '800.00',
            'tax_profile_id' => $this->activeProfile->id,
        ]);

        Log::spy();

        $response = $this->actingAs($admin)->put("/products/{$product->id}", [
            'sku' => 'PROD-TAX-003',
            'name' => 'Hydraulic Press',
            'category_id' => $this->category->id,
            'unit' => 'UNIT',
            'status' => 'ACTIVE',
            'cost_price' => '500.00',
            'minimum_allowed_price' => '600.00',
            'default_selling_price' => '700.00',
            'mrp' => '800.00',
            'tax_profile_id' => $newActiveProfile->id,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $product->refresh();
        $this->assertSame($newActiveProfile->id, $product->tax_profile_id);

        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) use ($product, $newActiveProfile) {
                return $message === 'Product tax profile assignment updated'
                    && ($context['action'] ?? null) === 'PRODUCT_TAX_PROFILE_CHANGED'
                    && ($context['product_id'] ?? null) === $product->id
                    && ($context['previous_tax_profile_id'] ?? null) === $this->activeProfile->id
                    && ($context['new_tax_profile_id'] ?? null) === $newActiveProfile->id;
            });
    }

    public function test_updating_product_to_inactive_tax_profile_is_rejected(): void
    {
        $admin = $this->createAdmin();

        $product = Product::create([
            'sku' => 'PROD-TAX-004',
            'name' => 'Surface Grinder',
            'category_id' => $this->category->id,
            'unit' => 'UNIT',
            'status' => 'ACTIVE',
            'cost_price' => '300.00',
            'minimum_allowed_price' => '350.00',
            'default_selling_price' => '400.00',
            'mrp' => '450.00',
            'tax_profile_id' => $this->activeProfile->id,
        ]);

        $response = $this->actingAs($admin)->put("/products/{$product->id}", [
            'sku' => 'PROD-TAX-004',
            'name' => 'Surface Grinder',
            'category_id' => $this->category->id,
            'unit' => 'UNIT',
            'status' => 'ACTIVE',
            'cost_price' => '300.00',
            'minimum_allowed_price' => '350.00',
            'default_selling_price' => '400.00',
            'mrp' => '450.00',
            'tax_profile_id' => $this->inactiveProfile->id,
        ]);

        $response->assertSessionHasErrors(['tax_profile_id']);
    }

    public function test_deactivating_tax_profile_preserves_existing_product_reference(): void
    {
        $admin = $this->createAdmin();

        $product = Product::create([
            'sku' => 'PROD-TAX-005',
            'name' => 'Milling Cutter',
            'category_id' => $this->category->id,
            'unit' => 'UNIT',
            'status' => 'ACTIVE',
            'cost_price' => '40.00',
            'minimum_allowed_price' => '50.00',
            'default_selling_price' => '60.00',
            'mrp' => '70.00',
            'tax_profile_id' => $this->activeProfile->id,
        ]);

        // Deactivate tax profile
        $this->activeProfile->status = TaxProfileStatus::INACTIVE;
        $this->activeProfile->save();

        // Product retains association
        $product->refresh();
        $this->assertSame($this->activeProfile->id, $product->tax_profile_id);

        // Non-tax update on this product succeeds
        $response = $this->actingAs($admin)->put("/products/{$product->id}", [
            'sku' => 'PROD-TAX-005',
            'name' => 'Milling Cutter Pro',
            'category_id' => $this->category->id,
            'unit' => 'UNIT',
            'status' => 'ACTIVE',
            'cost_price' => '40.00',
            'minimum_allowed_price' => '50.00',
            'default_selling_price' => '60.00',
            'mrp' => '70.00',
            'tax_profile_id' => $this->activeProfile->id, // unchanged
        ]);

        $response->assertSessionDoesntHaveErrors();
        $product->refresh();
        $this->assertSame('Milling Cutter Pro', $product->name);
        $this->assertSame($this->activeProfile->id, $product->tax_profile_id);
    }
}
