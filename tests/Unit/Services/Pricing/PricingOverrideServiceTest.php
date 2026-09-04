<?php

namespace Tests\Unit\Services\Pricing;

use App\DTOs\Pricing\PriceOverrideDecision;
use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Enums\PriceOverrideDirection;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Pricing\PriceBoundaryService;
use App\Services\Pricing\PricingOverrideService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PricingOverrideServiceTest extends TestCase
{
    protected PricingOverrideService $service;
    protected PermissionService $permissionService;
    protected PriceBoundaryService $priceBoundaryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->permissionService = app(PermissionService::class);
        $this->priceBoundaryService = app(PriceBoundaryService::class);
        $this->service = new PricingOverrideService($this->permissionService, $this->priceBoundaryService);
    }

    protected function makeMockProduct(
        string $minPrice = '80.00',
        string $defaultPrice = '100.00',
        string $mrp = '120.00'
    ): Product {
        $product = new Product();
        $product->id = 42;
        $product->sku = 'PROD-000042';
        $product->name = 'Industrial Ball Bearings';
        $product->cost_price = '60.00';
        $product->minimum_allowed_price = $minPrice;
        $product->default_selling_price = $defaultPrice;
        $product->mrp = $mrp;

        return $product;
    }

    protected function makeAdminUser(bool $active = true): User
    {
        $user = new User();
        $user->id = 1;
        $user->name = 'Test Admin';
        $user->email = 'admin@example.com';
        $user->role = UserRole::ADMIN;
        $user->status = $active ? AccountStatus::ACTIVE : AccountStatus::SUSPENDED;
        $user->exists = true;

        return $user;
    }

    protected function makeSalesmanUser(): User
    {
        $user = new User();
        $user->id = 2;
        $user->name = 'Test Salesman';
        $user->email = 'sales@example.com';
        $user->role = UserRole::SALESMAN;
        $user->status = AccountStatus::ACTIVE;
        $user->exists = true;

        return $user;
    }

    // ==========================================
    // 1. BOUNDARY & DIRECTION EVALUATION
    // ==========================================

    public function test_normal_in_range_price_evaluates_as_no_override(): void
    {
        $product = $this->makeMockProduct('80.00', '100.00', '120.00');

        $decision = $this->service->evaluate($product, '100.00');

        $this->assertFalse($decision->isOverride);
        $this->assertSame(PriceOverrideDirection::NONE, $decision->direction);
        $this->assertSame('100.00', $decision->unitPrice);
        $this->assertSame('80.00', $decision->minimumAllowedPrice);
        $this->assertSame('100.00', $decision->defaultSellingPrice);
        $this->assertSame('120.00', $decision->mrp);
        $this->assertSame('0.00', $decision->varianceAmount);
        $this->assertNull($decision->reason);
    }

    public function test_exact_minimum_boundary_evaluates_as_no_override(): void
    {
        $product = $this->makeMockProduct('80.00', '100.00', '120.00');

        $decision = $this->service->evaluate($product, '80.00');

        $this->assertFalse($decision->isOverride);
        $this->assertSame(PriceOverrideDirection::NONE, $decision->direction);
        $this->assertSame('80.00', $decision->unitPrice);
        $this->assertSame('0.00', $decision->varianceAmount);
    }

    public function test_exact_mrp_boundary_evaluates_as_no_override(): void
    {
        $product = $this->makeMockProduct('80.00', '100.00', '120.00');

        $decision = $this->service->evaluate($product, '120.00');

        $this->assertFalse($decision->isOverride);
        $this->assertSame(PriceOverrideDirection::NONE, $decision->direction);
        $this->assertSame('120.00', $decision->unitPrice);
        $this->assertSame('0.00', $decision->varianceAmount);
    }

    public function test_price_below_minimum_evaluates_as_below_minimum_override(): void
    {
        $product = $this->makeMockProduct('80.00', '100.00', '120.00');

        $decision = $this->service->evaluate($product, '75.50');

        $this->assertTrue($decision->isOverride);
        $this->assertSame(PriceOverrideDirection::BELOW_MINIMUM, $decision->direction);
        $this->assertSame('75.50', $decision->unitPrice);
        $this->assertSame('4.50', $decision->varianceAmount); // 80.00 - 75.50 = 4.50
    }

    public function test_price_above_mrp_evaluates_as_above_mrp_override(): void
    {
        $product = $this->makeMockProduct('80.00', '100.00', '120.00');

        $decision = $this->service->evaluate($product, '135.25');

        $this->assertTrue($decision->isOverride);
        $this->assertSame(PriceOverrideDirection::ABOVE_MRP, $decision->direction);
        $this->assertSame('135.25', $decision->unitPrice);
        $this->assertSame('15.25', $decision->varianceAmount); // 135.25 - 120.00 = 15.25
    }

    // ==========================================
    // 2. BCMATH EXACT DECIMAL DISCIPLINE
    // ==========================================

    public function test_decimal_normalization_and_exact_bcmath_variance(): void
    {
        $product = $this->makeMockProduct('100.50', '150.00', '200.75');

        // Integer input '90' normalized to '90.00'
        $decisionBelow = $this->service->evaluate($product, '90');
        $this->assertSame('90.00', $decisionBelow->unitPrice);
        $this->assertSame('10.50', $decisionBelow->varianceAmount);

        // One decimal '210.5' normalized to '210.50'
        $decisionAbove = $this->service->evaluate($product, '210.5');
        $this->assertSame('210.50', $decisionAbove->unitPrice);
        $this->assertSame('9.75', $decisionAbove->varianceAmount); // 210.50 - 200.75 = 9.75
    }

    public function test_rejects_price_with_more_than_two_decimals(): void
    {
        $product = $this->makeMockProduct();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot have more than 2 decimal places');

        $this->service->evaluate($product, '95.999');
    }

    public function test_rejects_scientific_notation_and_non_numeric(): void
    {
        $product = $this->makeMockProduct();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('has an invalid numeric format');

        $this->service->evaluate($product, '1e3');
    }

    public function test_rejects_zero_price(): void
    {
        $product = $this->makeMockProduct();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must be greater than zero');

        $this->service->evaluate($product, '0.00');
    }

    public function test_rejects_negative_price(): void
    {
        $product = $this->makeMockProduct();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot be negative');

        $this->service->evaluate($product, '-15.00');
    }

    // ==========================================
    // 3. AUTHORIZATION & REASON VALIDATION
    // ==========================================

    public function test_normal_price_authorizes_without_reason_or_override_permission(): void
    {
        $product = $this->makeMockProduct('80.00', '100.00', '120.00');
        $salesman = $this->makeSalesmanUser();

        Log::shouldReceive('info')->never();

        $decision = $this->service->authorizeOverride($product, '95.00', $salesman);

        $this->assertFalse($decision->isOverride);
        $this->assertSame(PriceOverrideDirection::NONE, $decision->direction);
        $this->assertSame('95.00', $decision->unitPrice);
        $this->assertNull($decision->reason);
        $this->assertNull($decision->authorizedById);
    }

    public function test_authorized_admin_can_authorize_below_minimum_override(): void
    {
        $product = $this->makeMockProduct('80.00', '100.00', '120.00');
        $admin = $this->makeAdminUser();

        Log::shouldReceive('info')
            ->once()
            ->with('Authorized price override decision evaluated', \Mockery::on(function (array $payload) use ($admin, $product) {
                return $payload['action'] === 'PRODUCT_PRICE_OVERRIDE_AUTHORIZED'
                    && $payload['actor_id'] === $admin->id
                    && $payload['actor_email'] === $admin->email
                    && $payload['product_id'] === $product->id
                    && $payload['requested_price'] === '70.00'
                    && $payload['direction'] === 'BELOW_MINIMUM'
                    && $payload['variance_amount'] === '10.00'
                    && $payload['reason'] === 'Volume tier contractual discount agreed for strategic client';
            }));

        $decision = $this->service->authorizeOverride(
            $product,
            '70.00',
            $admin,
            '  Volume tier contractual discount agreed for strategic client  ',
            '192.168.1.50',
            ['channel' => 'B2B_PORTAL']
        );

        $this->assertTrue($decision->isOverride);
        $this->assertSame(PriceOverrideDirection::BELOW_MINIMUM, $decision->direction);
        $this->assertSame('70.00', $decision->unitPrice);
        $this->assertSame('10.00', $decision->varianceAmount);
        $this->assertSame('Volume tier contractual discount agreed for strategic client', $decision->reason);
        $this->assertSame($admin->id, $decision->authorizedById);
        $this->assertSame($admin->email, $decision->authorizedByEmail);
        $this->assertNotNull($decision->authorizedAt);
        $this->assertSame('ADMIN', $decision->authorizationContext['actor_role']);
        $this->assertSame('192.168.1.50', $decision->authorizationContext['ip_address']);
        $this->assertSame('B2B_PORTAL', $decision->authorizationContext['channel']);
    }

    public function test_authorized_admin_can_authorize_above_mrp_override(): void
    {
        $product = $this->makeMockProduct('80.00', '100.00', '120.00');
        $admin = $this->makeAdminUser();

        Log::shouldReceive('info')->once();

        $decision = $this->service->authorizeOverride(
            $product,
            '130.00',
            $admin,
            'Emergency weekend expedited fabrication surcharge applied'
        );

        $this->assertTrue($decision->isOverride);
        $this->assertSame(PriceOverrideDirection::ABOVE_MRP, $decision->direction);
        $this->assertSame('130.00', $decision->unitPrice);
        $this->assertSame('10.00', $decision->varianceAmount);
        $this->assertSame('Emergency weekend expedited fabrication surcharge applied', $decision->reason);
    }

    public function test_unauthorized_user_is_rejected_and_security_warning_is_logged(): void
    {
        $product = $this->makeMockProduct('80.00', '100.00', '120.00');
        $salesman = $this->makeSalesmanUser();

        Log::shouldReceive('warning')
            ->once()
            ->with('Unauthorized price override attempt rejected', \Mockery::on(function (array $payload) use ($salesman, $product) {
                return $payload['action'] === 'PRICE_OVERRIDE_UNAUTHORIZED'
                    && $payload['actor_id'] === $salesman->id
                    && $payload['product_id'] === $product->id
                    && $payload['requested_price'] === '70.00'
                    && $payload['direction'] === 'BELOW_MINIMUM';
            }));

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('You do not have permission to authorize price overrides');

        $this->service->authorizeOverride(
            $product,
            '70.00',
            $salesman,
            'Valid reason string given by unauthorized actor'
        );
    }

    public function test_inactive_admin_user_is_rejected(): void
    {
        $product = $this->makeMockProduct('80.00', '100.00', '120.00');
        $suspendedAdmin = $this->makeAdminUser(active: false);

        Log::shouldReceive('warning')->once();

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('You do not have permission to authorize price overrides');

        $this->service->authorizeOverride(
            $product,
            '70.00',
            $suspendedAdmin,
            'Valid reason string but account is suspended'
        );
    }

    public function test_override_requires_non_empty_reason(): void
    {
        $product = $this->makeMockProduct('80.00', '100.00', '120.00');
        $admin = $this->makeAdminUser();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('An override reason is mandatory');

        $this->service->authorizeOverride($product, '70.00', $admin, null);
    }

    public function test_override_rejects_whitespace_only_reason(): void
    {
        $product = $this->makeMockProduct('80.00', '100.00', '120.00');
        $admin = $this->makeAdminUser();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('An override reason is mandatory');

        $this->service->authorizeOverride($product, '70.00', $admin, "   \t \n  ");
    }

    public function test_override_rejects_reason_shorter_than_5_chars(): void
    {
        $product = $this->makeMockProduct('80.00', '100.00', '120.00');
        $admin = $this->makeAdminUser();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The override reason must be at least 5 characters');

        $this->service->authorizeOverride($product, '70.00', $admin, 'four');
    }

    public function test_override_rejects_reason_longer_than_500_chars(): void
    {
        $product = $this->makeMockProduct('80.00', '100.00', '120.00');
        $admin = $this->makeAdminUser();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The override reason cannot exceed 500 characters');

        $longReason = str_repeat('a', 501);
        $this->service->authorizeOverride($product, '70.00', $admin, $longReason);
    }

    public function test_dto_to_array_serialization(): void
    {
        $product = $this->makeMockProduct('80.00', '100.00', '120.00');
        $admin = $this->makeAdminUser();

        Log::shouldReceive('info')->once();

        $decision = $this->service->authorizeOverride(
            $product,
            '75.00',
            $admin,
            'Approved manager promotional allowance',
            '10.0.0.1'
        );

        $array = $decision->toArray();

        $this->assertTrue($array['is_override']);
        $this->assertSame('BELOW_MINIMUM', $array['direction']);
        $this->assertSame('Below Minimum Allowed Price', $array['direction_label']);
        $this->assertSame('75.00', $array['unit_price']);
        $this->assertSame('80.00', $array['minimum_allowed_price']);
        $this->assertSame('100.00', $array['default_selling_price']);
        $this->assertSame('120.00', $array['mrp']);
        $this->assertSame('5.00', $array['variance_amount']);
        $this->assertSame('Approved manager promotional allowance', $array['reason']);
        $this->assertSame($admin->id, $array['authorized_by_id']);
        $this->assertSame($admin->email, $array['authorized_by_email']);
        $this->assertNotNull($array['authorized_at']);
        $this->assertSame('10.0.0.1', $array['authorization_context']['ip_address']);
    }
}
