<?php

namespace Tests\Feature\Pricing;

use App\DTOs\Pricing\PriceOverrideDecision;
use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Enums\PriceOverrideDirection;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\Pricing\PricingOverrideService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PriceOverrideIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected PricingOverrideService $pricingOverrideService;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricingOverrideService = app(PricingOverrideService::class);

        $category = Category::create([
            'name' => 'Industrial Fasteners',
            'code' => 'FASTENERS',
            'status' => 'ACTIVE',
        ]);

        $this->product = Product::create([
            'sku' => 'FAST-001',
            'name' => 'Grade 8 Hex Bolt 1/2-13',
            'description' => 'Heavy duty alloy steel hex bolt',
            'category_id' => $category->id,
            'unit' => 'PIECE',
            'status' => ProductStatus::ACTIVE,
            'cost_price' => '10.00',
            'minimum_allowed_price' => '15.00',
            'default_selling_price' => '20.00',
            'mrp' => '25.00',
        ]);
    }

    public function test_super_admin_can_authorize_below_minimum_override(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        Log::shouldReceive('info')
            ->once()
            ->with('Authorized price override decision evaluated', \Mockery::on(function (array $payload) use ($superAdmin) {
                return $payload['action'] === 'PRODUCT_PRICE_OVERRIDE_AUTHORIZED'
                    && $payload['actor_id'] === $superAdmin->id
                    && $payload['actor_role'] === UserRole::SUPER_ADMIN->value
                    && $payload['requested_price'] === '12.50'
                    && $payload['variance_amount'] === '2.50';
            }));

        $decision = $this->pricingOverrideService->authorizeOverride(
            $this->product,
            '12.50',
            $superAdmin,
            'Special strategic client introductory discount'
        );

        $this->assertInstanceOf(PriceOverrideDecision::class, $decision);
        $this->assertTrue($decision->isOverride);
        $this->assertSame(PriceOverrideDirection::BELOW_MINIMUM, $decision->direction);
        $this->assertSame('12.50', $decision->unitPrice);
        $this->assertSame('2.50', $decision->varianceAmount);
        $this->assertSame($superAdmin->id, $decision->authorizedById);
    }

    public function test_admin_can_authorize_above_mrp_override(): void
    {
        $admin = User::factory()->admin()->create();

        Log::shouldReceive('info')->once();

        $decision = $this->pricingOverrideService->authorizeOverride(
            $this->product,
            '28.00',
            $admin,
            'Expedited delivery and specialized packaging surcharge'
        );

        $this->assertTrue($decision->isOverride);
        $this->assertSame(PriceOverrideDirection::ABOVE_MRP, $decision->direction);
        $this->assertSame('28.00', $decision->unitPrice);
        $this->assertSame('3.00', $decision->varianceAmount);
    }

    public function test_salesman_cannot_authorize_out_of_bounds_price(): void
    {
        $salesman = User::factory()->salesman()->create();

        Log::shouldReceive('warning')->once();

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('You do not have permission to authorize price overrides');

        $this->pricingOverrideService->authorizeOverride(
            $this->product,
            '12.00',
            $salesman,
            'Sales representative attempted self-authorized discount'
        );
    }

    public function test_warehouse_manager_cannot_authorize_price_override(): void
    {
        $warehouse = User::factory()->warehouseManager()->create();

        Log::shouldReceive('warning')->once();

        $this->expectException(AuthorizationException::class);
        $this->pricingOverrideService->authorizeOverride(
            $this->product,
            '12.00',
            $warehouse,
            'Warehouse manager override attempt'
        );
    }

    public function test_accountant_cannot_authorize_price_override(): void
    {
        $accountant = User::factory()->accountant()->create();

        Log::shouldReceive('warning')->once();

        $this->expectException(AuthorizationException::class);
        $this->pricingOverrideService->authorizeOverride(
            $this->product,
            '12.00',
            $accountant,
            'Accountant override attempt'
        );
    }

    public function test_delivery_partner_cannot_authorize_price_override(): void
    {
        $delivery = User::factory()->deliveryPartner()->create();

        Log::shouldReceive('warning')->once();

        $this->expectException(AuthorizationException::class);
        $this->pricingOverrideService->authorizeOverride(
            $this->product,
            '12.00',
            $delivery,
            'Delivery driver override attempt'
        );
    }

    public function test_suspended_admin_is_rejected(): void
    {
        $admin = User::factory()->admin()->suspended()->create();

        Log::shouldReceive('warning')->once();

        $this->expectException(AuthorizationException::class);
        $this->pricingOverrideService->authorizeOverride(
            $this->product,
            '12.00',
            $admin,
            'Suspended admin attempted override'
        );
    }

    public function test_product_master_pricing_remains_completely_unmodified_in_database(): void
    {
        $admin = User::factory()->admin()->create();

        Log::shouldReceive('info')->once();

        $this->pricingOverrideService->authorizeOverride(
            $this->product,
            '10.00',
            $admin,
            'Approved exceptional override for clearance batch'
        );

        // Re-query product freshly from the PostgreSQL database
        $freshProduct = Product::findOrFail($this->product->id);

        $this->assertSame('10.00', (string) $freshProduct->cost_price);
        $this->assertSame('15.00', (string) $freshProduct->minimum_allowed_price);
        $this->assertSame('20.00', (string) $freshProduct->default_selling_price);
        $this->assertSame('25.00', (string) $freshProduct->mrp);
    }

    public function test_boundary_equality_is_normal_pricing_and_does_not_audit_or_require_override(): void
    {
        $salesman = User::factory()->salesman()->create();

        Log::shouldReceive('info')->never();
        Log::shouldReceive('warning')->never();

        // Exact minimum
        $minDecision = $this->pricingOverrideService->authorizeOverride(
            $this->product,
            '15.00',
            $salesman
        );
        $this->assertFalse($minDecision->isOverride);
        $this->assertSame(PriceOverrideDirection::NONE, $minDecision->direction);

        // Exact MRP
        $mrpDecision = $this->pricingOverrideService->authorizeOverride(
            $this->product,
            '25.00',
            $salesman
        );
        $this->assertFalse($mrpDecision->isOverride);
        $this->assertSame(PriceOverrideDirection::NONE, $mrpDecision->direction);
    }
}
