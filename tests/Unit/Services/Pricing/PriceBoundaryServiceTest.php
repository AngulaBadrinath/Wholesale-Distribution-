<?php

namespace Tests\Unit\Services\Pricing;

use App\Models\Product;
use App\Services\Pricing\PriceBoundaryService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PriceBoundaryServiceTest extends TestCase
{
    protected PriceBoundaryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PriceBoundaryService();
    }

    // =========================================================================
    // 1. Normalization & Decimal Precision
    // =========================================================================

    public function test_normalize_formats_valid_integer_and_decimals(): void
    {
        $this->assertSame('10.00', PriceBoundaryService::normalize('10'));
        $this->assertSame('10.00', PriceBoundaryService::normalize(10));
        $this->assertSame('10.00', PriceBoundaryService::normalize('10.0'));
        $this->assertSame('10.50', PriceBoundaryService::normalize('10.5'));
        $this->assertSame('10.50', PriceBoundaryService::normalize('10.50'));
        $this->assertSame('0.01', PriceBoundaryService::normalize('0.01'));
        $this->assertSame('99999999.99', PriceBoundaryService::normalize('99999999.99'));
    }

    public function test_normalize_rejects_values_with_more_than_two_decimal_places(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot have more than 2 decimal places');
        PriceBoundaryService::normalize('10.999');
    }

    public function test_normalize_rejects_scientific_notation(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('has an invalid numeric format');
        PriceBoundaryService::normalize('1e3');
    }

    public function test_normalize_rejects_non_numeric_strings(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('has an invalid numeric format');
        PriceBoundaryService::normalize('abc');
    }

    public function test_normalize_rejects_floats_directly_to_prevent_binary_float_drift(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must be a valid numeric value');
        PriceBoundaryService::normalize(10.55);
    }

    public function test_normalize_rejects_negative_when_not_allowed(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot be negative');
        PriceBoundaryService::normalize('-5.00', allowNegative: false);
    }

    public function test_normalize_rejects_zero_when_not_allowed(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must be greater than zero');
        PriceBoundaryService::normalize('0.00', allowZero: false);
    }

    // =========================================================================
    // 2. Product Master Hierarchy Invariant Checks
    // =========================================================================

    public function test_valid_hierarchy_passes(): void
    {
        $this->service->validateProductMasterPricing(
            costPrice: '20.00',
            minPrice: '30.00',
            defaultPrice: '40.00',
            mrp: '50.00'
        );

        $this->assertTrue(true);
    }

    public function test_boundary_equality_minimum_equals_default_passes(): void
    {
        $this->service->validateProductMasterPricing(
            costPrice: '10.00',
            minPrice: '25.00',
            defaultPrice: '25.00',
            mrp: '30.00'
        );

        $this->assertTrue(true);
    }

    public function test_boundary_equality_default_equals_mrp_passes(): void
    {
        $this->service->validateProductMasterPricing(
            costPrice: '10.00',
            minPrice: '20.00',
            defaultPrice: '30.00',
            mrp: '30.00'
        );

        $this->assertTrue(true);
    }

    public function test_boundary_equality_all_three_equal_passes(): void
    {
        $this->service->validateProductMasterPricing(
            costPrice: '10.00',
            minPrice: '25.00',
            defaultPrice: '25.00',
            mrp: '25.00'
        );

        $this->assertTrue(true);
    }

    public function test_cost_price_equal_to_zero_passes(): void
    {
        $this->service->validateProductMasterPricing(
            costPrice: '0.00',
            minPrice: '10.00',
            defaultPrice: '15.00',
            mrp: '20.00'
        );

        $this->assertTrue(true);
    }

    public function test_cost_greater_than_min_price_is_allowed_in_master_pricing(): void
    {
        // Business rule allows loss leaders: 0 <= cost_price, 0 < min <= default <= mrp
        $this->service->validateProductMasterPricing(
            costPrice: '50.00',
            minPrice: '40.00',
            defaultPrice: '45.00',
            mrp: '60.00'
        );

        $this->assertTrue(true);
    }

    public function test_negative_cost_price_throws_validation_exception(): void
    {
        try {
            $this->service->validateProductMasterPricing(
                costPrice: '-1.00',
                minPrice: '10.00',
                defaultPrice: '15.00',
                mrp: '20.00'
            );
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cost_price', $e->errors());
        }
    }

    public function test_zero_minimum_price_throws_validation_exception(): void
    {
        try {
            $this->service->validateProductMasterPricing(
                costPrice: '10.00',
                minPrice: '0.00',
                defaultPrice: '15.00',
                mrp: '20.00'
            );
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('minimum_allowed_price', $e->errors());
        }
    }

    public function test_default_selling_price_less_than_minimum_throws_validation_exception(): void
    {
        try {
            $this->service->validateProductMasterPricing(
                costPrice: '10.00',
                minPrice: '30.00',
                defaultPrice: '25.00',
                mrp: '50.00'
            );
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('default_selling_price', $e->errors());
        }
    }

    public function test_default_selling_price_greater_than_mrp_throws_validation_exception(): void
    {
        try {
            $this->service->validateProductMasterPricing(
                costPrice: '10.00',
                minPrice: '20.00',
                defaultPrice: '45.00',
                mrp: '40.00'
            );
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('default_selling_price', $e->errors());
        }
    }

    public function test_minimum_price_greater_than_mrp_throws_validation_exception(): void
    {
        try {
            $this->service->validateProductMasterPricing(
                costPrice: '10.00',
                minPrice: '60.00',
                defaultPrice: '50.00',
                mrp: '40.00'
            );
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('minimum_allowed_price', $e->errors());
        }
    }

    // =========================================================================
    // 3. Reusable Order Unit Price Invariant Checks (RULE-PRI-002)
    // =========================================================================

    public function test_order_unit_price_within_bounds_passes(): void
    {
        $pricing = [
            'minimum_allowed_price' => '25.00',
            'mrp' => '50.00',
        ];

        $normalized = $this->service->validateOrderUnitPrice($pricing, '35.00');
        $this->assertSame('35.00', $normalized);
        $this->assertTrue($this->service->isOrderUnitPriceWithinBounds($pricing, '35.00'));
    }

    public function test_order_unit_price_at_exact_minimum_passes(): void
    {
        $pricing = [
            'minimum_allowed_price' => '25.00',
            'mrp' => '50.00',
        ];

        $normalized = $this->service->validateOrderUnitPrice($pricing, '25.00');
        $this->assertSame('25.00', $normalized);
        $this->assertTrue($this->service->isOrderUnitPriceWithinBounds($pricing, '25.00'));
    }

    public function test_order_unit_price_at_exact_mrp_passes(): void
    {
        $pricing = [
            'minimum_allowed_price' => '25.00',
            'mrp' => '50.00',
        ];

        $normalized = $this->service->validateOrderUnitPrice($pricing, '50.00');
        $this->assertSame('50.00', $normalized);
        $this->assertTrue($this->service->isOrderUnitPriceWithinBounds($pricing, '50.00'));
    }

    public function test_order_unit_price_below_minimum_throws_validation_exception(): void
    {
        $pricing = [
            'minimum_allowed_price' => '25.00',
            'mrp' => '50.00',
        ];

        $this->assertFalse($this->service->isOrderUnitPriceWithinBounds($pricing, '24.99'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot be less than the minimum allowed price');
        $this->service->validateOrderUnitPrice($pricing, '24.99');
    }

    public function test_order_unit_price_above_mrp_throws_validation_exception(): void
    {
        $pricing = [
            'minimum_allowed_price' => '25.00',
            'mrp' => '50.00',
        ];

        $this->assertFalse($this->service->isOrderUnitPriceWithinBounds($pricing, '50.01'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot exceed the MRP / list price');
        $this->service->validateOrderUnitPrice($pricing, '50.01');
    }

    public function test_order_unit_price_works_with_product_eloquent_instance(): void
    {
        $product = new Product([
            'sku' => 'PROD-TEST-1',
            'name' => 'Test Unit Product',
            'minimum_allowed_price' => '100.00',
            'default_selling_price' => '120.00',
            'mrp' => '150.00',
        ]);

        $this->assertSame('120.00', $this->service->validateOrderUnitPrice($product, '120.00'));
        $this->assertTrue($this->service->isOrderUnitPriceWithinBounds($product, '100.00'));
        $this->assertTrue($this->service->isOrderUnitPriceWithinBounds($product, '150.00'));
        $this->assertFalse($this->service->isOrderUnitPriceWithinBounds($product, '99.99'));
        $this->assertFalse($this->service->isOrderUnitPriceWithinBounds($product, '150.01'));
    }
}
