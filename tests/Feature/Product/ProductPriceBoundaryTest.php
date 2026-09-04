<?php

namespace Tests\Feature\Product;

use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProductPriceBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function createUserWithRole(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }

    protected function createCategory(): Category
    {
        return Category::create([
            'name' => 'Fasteners & Hardware',
            'code' => 'CAT-FAST-01',
            'description' => 'Industrial fasteners',
            'status' => 'ACTIVE',
            'sort_order' => 1,
        ]);
    }

    // =========================================================================
    // 1. Product Creation Pricing Hierarchy
    // =========================================================================

    public function test_admin_can_create_product_with_valid_pricing_boundaries(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        $category = $this->createCategory();

        $payload = [
            'sku' => 'PROD-PRICE-001',
            'name' => 'High Grade Hex Nut',
            'category_id' => $category->id,
            'unit' => 'BOX',
            'status' => ProductStatus::ACTIVE->value,
            'cost_price' => '15.00',
            'minimum_allowed_price' => '20.00',
            'default_selling_price' => '25.00',
            'mrp' => '30.00',
        ];

        $response = $this->actingAs($admin)->post(route('products.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', [
            'sku' => 'PROD-PRICE-001',
            'cost_price' => '15.00',
            'minimum_allowed_price' => '20.00',
            'default_selling_price' => '25.00',
            'mrp' => '30.00',
        ]);
    }

    public function test_product_creation_allows_equal_boundaries(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        // Fixed price product: min = default = mrp
        $payload = [
            'sku' => 'PROD-FIXED-001',
            'name' => 'Fixed Price Standard Item',
            'unit' => 'PCS',
            'status' => ProductStatus::ACTIVE->value,
            'cost_price' => '10.00',
            'minimum_allowed_price' => '50.00',
            'default_selling_price' => '50.00',
            'mrp' => '50.00',
        ];

        $response = $this->actingAs($admin)->post(route('products.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', [
            'sku' => 'PROD-FIXED-001',
            'cost_price' => '10.00',
            'minimum_allowed_price' => '50.00',
            'default_selling_price' => '50.00',
            'mrp' => '50.00',
        ]);
    }

    public function test_product_creation_rejects_values_with_more_than_two_decimal_places(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $payload = [
            'sku' => 'PROD-DEC-001',
            'name' => 'Decimal Test Item',
            'unit' => 'PCS',
            'status' => ProductStatus::ACTIVE->value,
            'cost_price' => '10.00',
            'minimum_allowed_price' => '15.999', // Invalid > 2 decimals
            'default_selling_price' => '20.00',
            'mrp' => '25.00',
        ];

        $response = $this->actingAs($admin)->post(route('products.store'), $payload);

        $response->assertSessionHasErrors(['minimum_allowed_price']);
    }

    public function test_product_creation_rejects_scientific_notation(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $payload = [
            'sku' => 'PROD-SCI-001',
            'name' => 'Scientific Notation Item',
            'unit' => 'PCS',
            'status' => ProductStatus::ACTIVE->value,
            'cost_price' => '10.00',
            'minimum_allowed_price' => '1e2', // Invalid scientific notation
            'default_selling_price' => '20.00',
            'mrp' => '25.00',
        ];

        $response = $this->actingAs($admin)->post(route('products.store'), $payload);

        $response->assertSessionHasErrors(['minimum_allowed_price']);
    }

    public function test_product_creation_rejects_negative_cost_price(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $payload = [
            'name' => 'Negative Cost Item',
            'unit' => 'PCS',
            'status' => ProductStatus::ACTIVE->value,
            'cost_price' => '-5.00',
            'minimum_allowed_price' => '10.00',
            'default_selling_price' => '15.00',
            'mrp' => '20.00',
        ];

        $response = $this->actingAs($admin)->post(route('products.store'), $payload);

        $response->assertSessionHasErrors(['cost_price']);
    }

    public function test_product_creation_rejects_zero_minimum_allowed_price(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $payload = [
            'name' => 'Zero Minimum Item',
            'unit' => 'PCS',
            'status' => ProductStatus::ACTIVE->value,
            'cost_price' => '10.00',
            'minimum_allowed_price' => '0.00',
            'default_selling_price' => '15.00',
            'mrp' => '20.00',
        ];

        $response = $this->actingAs($admin)->post(route('products.store'), $payload);

        $response->assertSessionHasErrors(['minimum_allowed_price']);
    }

    public function test_product_creation_rejects_default_less_than_minimum(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $payload = [
            'name' => 'Default Below Minimum Item',
            'unit' => 'PCS',
            'status' => ProductStatus::ACTIVE->value,
            'cost_price' => '10.00',
            'minimum_allowed_price' => '30.00',
            'default_selling_price' => '25.00',
            'mrp' => '40.00',
        ];

        $response = $this->actingAs($admin)->post(route('products.store'), $payload);

        $response->assertSessionHasErrors(['default_selling_price']);
    }

    public function test_product_creation_rejects_mrp_less_than_default(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        $payload = [
            'name' => 'MRP Below Default Item',
            'unit' => 'PCS',
            'status' => ProductStatus::ACTIVE->value,
            'cost_price' => '10.00',
            'minimum_allowed_price' => '20.00',
            'default_selling_price' => '35.00',
            'mrp' => '30.00',
        ];

        $response = $this->actingAs($admin)->post(route('products.store'), $payload);

        $response->assertSessionHasErrors(['mrp']);
    }

    // =========================================================================
    // 2. Resulting-State Pricing Validation on Product Update
    // =========================================================================

    public function test_resulting_state_validation_case_a_raising_min_above_default_fails(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        // Baseline: min 100, default 120, mrp 150
        $product = Product::create([
            'sku' => 'PROD-BASE-100',
            'name' => 'Baseline Product',
            'unit' => 'PCS',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => '80.00',
            'minimum_allowed_price' => '100.00',
            'default_selling_price' => '120.00',
            'mrp' => '150.00',
        ]);

        // Attempt Case A: min -> 140 while keeping default 120, mrp 150
        $payload = [
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => $product->unit,
            'status' => ProductStatus::ACTIVE->value,
            'cost_price' => '80.00',
            'minimum_allowed_price' => '140.00',
            'default_selling_price' => '120.00',
            'mrp' => '150.00',
        ];

        $response = $this->actingAs($admin)->put(route('products.update', $product), $payload);

        $response->assertSessionHasErrors(['default_selling_price']);

        $product->refresh();
        $this->assertSame('100.00', (string) $product->minimum_allowed_price);
    }

    public function test_resulting_state_validation_case_b_lowering_default_below_min_fails(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        // Baseline: min 100, default 120, mrp 150
        $product = Product::create([
            'sku' => 'PROD-BASE-101',
            'name' => 'Baseline Product',
            'unit' => 'PCS',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => '80.00',
            'minimum_allowed_price' => '100.00',
            'default_selling_price' => '120.00',
            'mrp' => '150.00',
        ]);

        // Attempt Case B: default -> 90 while min is 100
        $payload = [
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => $product->unit,
            'status' => ProductStatus::ACTIVE->value,
            'cost_price' => '80.00',
            'minimum_allowed_price' => '100.00',
            'default_selling_price' => '90.00',
            'mrp' => '150.00',
        ];

        $response = $this->actingAs($admin)->put(route('products.update', $product), $payload);

        $response->assertSessionHasErrors(['default_selling_price']);

        $product->refresh();
        $this->assertSame('120.00', (string) $product->default_selling_price);
    }

    public function test_resulting_state_validation_case_c_raising_default_above_mrp_fails(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        // Baseline: min 100, default 120, mrp 150
        $product = Product::create([
            'sku' => 'PROD-BASE-102',
            'name' => 'Baseline Product',
            'unit' => 'PCS',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => '80.00',
            'minimum_allowed_price' => '100.00',
            'default_selling_price' => '120.00',
            'mrp' => '150.00',
        ]);

        // Attempt Case C: default -> 160 while mrp is 150
        $payload = [
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => $product->unit,
            'status' => ProductStatus::ACTIVE->value,
            'cost_price' => '80.00',
            'minimum_allowed_price' => '100.00',
            'default_selling_price' => '160.00',
            'mrp' => '150.00',
        ];

        $response = $this->actingAs($admin)->put(route('products.update', $product), $payload);

        $response->assertSessionHasErrors(['default_selling_price']);

        $product->refresh();
        $this->assertSame('120.00', (string) $product->default_selling_price);
    }

    public function test_resulting_state_validation_case_d_lowering_mrp_below_default_fails(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        // Baseline: min 100, default 120, mrp 150
        $product = Product::create([
            'sku' => 'PROD-BASE-103',
            'name' => 'Baseline Product',
            'unit' => 'PCS',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => '80.00',
            'minimum_allowed_price' => '100.00',
            'default_selling_price' => '120.00',
            'mrp' => '150.00',
        ]);

        // Attempt Case D: mrp -> 110 while default is 120
        $payload = [
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => $product->unit,
            'status' => ProductStatus::ACTIVE->value,
            'cost_price' => '80.00',
            'minimum_allowed_price' => '100.00',
            'default_selling_price' => '120.00',
            'mrp' => '110.00',
        ];

        $response = $this->actingAs($admin)->put(route('products.update', $product), $payload);

        $response->assertSessionHasErrors(['mrp']);

        $product->refresh();
        $this->assertSame('150.00', (string) $product->mrp);
    }

    public function test_resulting_state_validation_case_e_valid_multi_field_transition_succeeds(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        // Baseline: min 100, default 120, mrp 150
        $product = Product::create([
            'sku' => 'PROD-BASE-104',
            'name' => 'Baseline Product',
            'unit' => 'PCS',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => '80.00',
            'minimum_allowed_price' => '100.00',
            'default_selling_price' => '120.00',
            'mrp' => '150.00',
        ]);

        // Valid transition: min 140, default 145, mrp 160
        $payload = [
            'sku' => $product->sku,
            'name' => 'Updated Baseline Product',
            'unit' => $product->unit,
            'status' => ProductStatus::ACTIVE->value,
            'cost_price' => '95.00',
            'minimum_allowed_price' => '140.00',
            'default_selling_price' => '145.00',
            'mrp' => '160.00',
        ];

        $response = $this->actingAs($admin)->put(route('products.update', $product), $payload);

        $response->assertRedirect();
        $product->refresh();
        $this->assertSame('95.00', (string) $product->cost_price);
        $this->assertSame('140.00', (string) $product->minimum_allowed_price);
        $this->assertSame('145.00', (string) $product->default_selling_price);
        $this->assertSame('160.00', (string) $product->mrp);
    }

    // =========================================================================
    // 3. Authorization & Audit Verification
    // =========================================================================

    public function test_price_mutation_dispatches_product_pricing_updated_audit_event(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $context['action'] === 'PRODUCT_UPDATED';
            });

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $context['action'] === 'PRODUCT_PRICING_UPDATED'
                    && $context['previous_prices']['default_selling_price'] === '120.00'
                    && $context['new_prices']['default_selling_price'] === '130.00';
            });

        $product = Product::create([
            'sku' => 'PROD-AUDIT-001',
            'name' => 'Audit Test Product',
            'unit' => 'PCS',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => '80.00',
            'minimum_allowed_price' => '100.00',
            'default_selling_price' => '120.00',
            'mrp' => '150.00',
        ]);

        $payload = [
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => $product->unit,
            'status' => ProductStatus::ACTIVE->value,
            'cost_price' => '80.00',
            'minimum_allowed_price' => '100.00',
            'default_selling_price' => '130.00', // Changed
            'mrp' => '150.00',
        ];

        $this->actingAs($admin)->put(route('products.update', $product), $payload);
    }

    public function test_no_op_price_update_does_not_dispatch_product_pricing_updated_audit_event(): void
    {
        $admin = $this->createUserWithRole(UserRole::ADMIN);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $context['action'] === 'PRODUCT_UPDATED';
            });

        // Ensure PRODUCT_PRICING_UPDATED is NOT emitted
        Log::shouldReceive('info')
            ->never()
            ->withArgs(function ($message, $context) {
                return ($context['action'] ?? null) === 'PRODUCT_PRICING_UPDATED';
            });

        $product = Product::create([
            'sku' => 'PROD-NOOP-001',
            'name' => 'No-op Pricing Product',
            'unit' => 'PCS',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => '80.00',
            'minimum_allowed_price' => '100.00',
            'default_selling_price' => '120.00',
            'mrp' => '150.00',
        ]);

        $payload = [
            'sku' => $product->sku,
            'name' => 'Updated Name Only', // Metadata change only
            'unit' => $product->unit,
            'status' => ProductStatus::ACTIVE->value,
            'cost_price' => '80.00',
            'minimum_allowed_price' => '100.00',
            'default_selling_price' => '120.00',
            'mrp' => '150.00',
        ];

        $this->actingAs($admin)->put(route('products.update', $product), $payload);
    }

    public function test_user_without_product_price_update_cannot_mutate_prices(): void
    {
        // Salesman lacks product.update and product.price.update
        $salesman = $this->createUserWithRole(UserRole::SALESMAN);

        $product = Product::create([
            'sku' => 'PROD-UNAUTH-001',
            'name' => 'Unauthorized Edit Product',
            'unit' => 'PCS',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => '80.00',
            'minimum_allowed_price' => '100.00',
            'default_selling_price' => '120.00',
            'mrp' => '150.00',
        ]);

        $payload = [
            'sku' => $product->sku,
            'name' => $product->name,
            'unit' => $product->unit,
            'status' => ProductStatus::ACTIVE->value,
            'cost_price' => '80.00',
            'minimum_allowed_price' => '100.00',
            'default_selling_price' => '125.00',
            'mrp' => '150.00',
        ];

        $response = $this->actingAs($salesman)->put(route('products.update', $product), $payload);

        $response->assertForbidden();
    }

    // =========================================================================
    // 4. PostgreSQL Database Level CHECK Constraint Backstop
    // =========================================================================

    public function test_database_check_constraint_rejects_direct_sql_invariant_violation_in_postgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Database CHECK constraint is only applicable to PostgreSQL.');
        }

        $this->expectException(\Illuminate\Database\QueryException::class);

        // Attempt direct SQL insert violating minimum <= default
        DB::table('products')->insert([
            'sku' => 'PROD-RAW-INVALID',
            'name' => 'Raw SQL Invalid Product',
            'unit' => 'PCS',
            'status' => 'ACTIVE',
            'cost_price' => '10.00',
            'minimum_allowed_price' => '50.00',
            'default_selling_price' => '30.00', // Invalid: < 50.00
            'mrp' => '60.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
