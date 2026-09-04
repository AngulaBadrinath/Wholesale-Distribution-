<?php

namespace Tests\Unit\Services\Tax;

use App\DTOs\Tax\TaxCalculationResult;
use App\DTOs\Tax\TaxSnapshotData;
use App\Enums\TaxProfileStatus;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Services\Tax\TaxCalculationService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TaxCalculationServiceTest extends TestCase
{
    protected TaxCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TaxCalculationService();
    }

    // ==========================================
    // 1. ROUND_HALF_UP EXACT ARITHMETIC TESTS
    // ==========================================

    public function test_round_half_up_exact_decimal_cases(): void
    {
        // 1.234 -> 1.23
        $this->assertSame('1.23', TaxCalculationService::roundHalfUp('1.234', 2));

        // 1.235 -> 1.24
        $this->assertSame('1.24', TaxCalculationService::roundHalfUp('1.235', 2));

        // 1.236 -> 1.24
        $this->assertSame('1.24', TaxCalculationService::roundHalfUp('1.236', 2));

        // 0.005 -> 0.01
        $this->assertSame('0.01', TaxCalculationService::roundHalfUp('0.005', 2));

        // 0.004 -> 0.00
        $this->assertSame('0.00', TaxCalculationService::roundHalfUp('0.004', 2));

        // 1.995 -> 2.00
        $this->assertSame('2.00', TaxCalculationService::roundHalfUp('1.995', 2));

        // 0.000 -> 0.00
        $this->assertSame('0.00', TaxCalculationService::roundHalfUp('0.000', 2));
    }

    // ==========================================
    // 2. TAX RATE NORMALIZATION & VALIDATION
    // ==========================================

    public function test_normalize_rate_valid_decimal_strings(): void
    {
        $this->assertSame('0.0000', TaxCalculationService::normalizeRate('0'));
        $this->assertSame('0.0000', TaxCalculationService::normalizeRate('0.0'));
        $this->assertSame('6.2500', TaxCalculationService::normalizeRate('6.25'));
        $this->assertSame('8.8750', TaxCalculationService::normalizeRate('8.875'));
        $this->assertSame('100.0000', TaxCalculationService::normalizeRate('100'));
        $this->assertSame('100.0000', TaxCalculationService::normalizeRate('100.0000'));
        $this->assertSame('0.0000', TaxCalculationService::normalizeRate(null));
        $this->assertSame('0.0000', TaxCalculationService::normalizeRate(''));
    }

    public function test_normalize_rate_rejects_more_than_four_decimal_places(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot have more than 4 decimal places');

        TaxCalculationService::normalizeRate('8.87501');
    }

    public function test_normalize_rate_rejects_negative_rate(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot be negative');

        TaxCalculationService::normalizeRate('-1');
    }

    public function test_normalize_rate_rejects_rate_exceeding_100_percent(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('cannot exceed 100.0000%');

        TaxCalculationService::normalizeRate('100.0001');
    }

    public function test_normalize_rate_rejects_scientific_notation(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('has an invalid numeric rate format');

        TaxCalculationService::normalizeRate('1e2');
    }

    public function test_normalize_rate_rejects_alphabetic_strings(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('has an invalid numeric rate format');

        TaxCalculationService::normalizeRate('abc');
    }

    public function test_normalize_rate_rejects_floats(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('must be a valid numeric decimal string');

        TaxCalculationService::normalizeRate(6.25);
    }

    // ==========================================
    // 3. LINE-LEVEL TAX CALCULATIONS
    // ==========================================

    public function test_calculate_line_tax_standard_percentage(): void
    {
        $taxProfile = new TaxProfile([
            'name' => 'Standard Sales Tax (6.25%)',
            'code' => 'TAX-STD-625',
            'rate' => '6.2500',
            'status' => TaxProfileStatus::ACTIVE,
        ]);
        $taxProfile->id = 10;

        $timestamp = CarbonImmutable::parse('2026-09-05 12:00:00');
        $result = $this->service->calculateLineTax($taxProfile, '100.00', 1, $timestamp);

        $this->assertInstanceOf(TaxCalculationResult::class, $result);
        $this->assertSame('100.00', $result->taxableAmount);
        $this->assertSame('6.2500', $result->taxRate);
        $this->assertSame('6.25', $result->taxAmount);
        $this->assertSame('106.25', $result->lineTotal);

        $snapshot = $result->snapshot;
        $this->assertInstanceOf(TaxSnapshotData::class, $snapshot);
        $this->assertSame(10, $snapshot->taxProfileId);
        $this->assertSame('TAX-STD-625', $snapshot->taxProfileCode);
        $this->assertSame('Standard Sales Tax (6.25%)', $snapshot->taxProfileName);
        $this->assertSame('6.2500', $snapshot->taxRate);
        $this->assertSame('100.00', $snapshot->taxableAmount);
        $this->assertSame('6.25', $snapshot->taxAmount);
        $this->assertSame('106.25', $snapshot->lineTotal);
        $this->assertSame('2026-09-05T12:00:00+00:00', $snapshot->calculatedAt->toIso8601String());
    }

    public function test_calculate_line_tax_multi_quantity_and_exact_fraction_rounding(): void
    {
        // 3 items @ 45.50 = 136.50 taxable
        // Tax rate = 8.8750%
        // Raw tax = 136.50 * 0.08875 = 12.114375
        // ROUND_HALF_UP(12.114375, 2) = 12.11
        // Line total = 136.50 + 12.11 = 148.61
        $taxProfile = new TaxProfile([
            'name' => 'NY State & City Tax',
            'code' => 'TAX-NYC-8875',
            'rate' => '8.8750',
            'status' => TaxProfileStatus::ACTIVE,
        ]);
        $taxProfile->id = 20;

        $result = $this->service->calculateLineTax($taxProfile, '45.50', 3);

        $this->assertSame('136.50', $result->taxableAmount);
        $this->assertSame('8.8750', $result->taxRate);
        $this->assertSame('12.11', $result->taxAmount);
        $this->assertSame('148.61', $result->lineTotal);
    }

    public function test_calculate_line_tax_zero_rate_profile(): void
    {
        $zeroProfile = new TaxProfile([
            'name' => 'Zero Rate Agricultural Goods',
            'code' => 'TAX-ZERO',
            'rate' => '0.0000',
            'status' => TaxProfileStatus::ACTIVE,
        ]);
        $zeroProfile->id = 30;

        $result = $this->service->calculateLineTax($zeroProfile, '250.00', 2);

        $this->assertSame('500.00', $result->taxableAmount);
        $this->assertSame('0.0000', $result->taxRate);
        $this->assertSame('0.00', $result->taxAmount);
        $this->assertSame('500.00', $result->lineTotal);
    }

    public function test_calculate_line_tax_null_profile_defaults_to_zero_tax(): void
    {
        $result = $this->service->calculateLineTax(null, '75.00', 1);

        $this->assertSame('75.00', $result->taxableAmount);
        $this->assertSame('0.0000', $result->taxRate);
        $this->assertSame('0.00', $result->taxAmount);
        $this->assertSame('75.00', $result->lineTotal);
        $this->assertNull($result->snapshot->taxProfileId);
        $this->assertNull($result->snapshot->taxProfileCode);
    }

    public function test_calculate_line_tax_from_product_model_with_loaded_tax_profile(): void
    {
        $profile = new TaxProfile([
            'name' => 'General Merchandise Tax',
            'code' => 'TAX-GEN-500',
            'rate' => '5.0000',
            'status' => TaxProfileStatus::ACTIVE,
        ]);
        $profile->id = 45;

        $product = new Product([
            'sku' => 'TOOL-001',
            'name' => 'Socket Wrench Set',
            'default_selling_price' => '80.00',
            'tax_profile_id' => 45,
        ]);
        $product->setRelation('taxProfile', $profile);

        $result = $this->service->calculateLineTax($product, '80.00', 2);

        $this->assertSame('160.00', $result->taxableAmount);
        $this->assertSame('5.0000', $result->taxRate);
        $this->assertSame('8.00', $result->taxAmount);
        $this->assertSame('168.00', $result->lineTotal);
        $this->assertSame(45, $result->snapshot->taxProfileId);
    }

    // ==========================================
    // 4. HEADER TOTAL AGGREGATION
    // ==========================================

    public function test_order_totals_aggregate_sum_of_rounded_line_taxes(): void
    {
        // Line 1: 0.005 raw tax -> rounds to 0.01
        // Unit price = 0.10, qty = 1, rate = 5.0000% -> taxable = 0.10, raw tax = 0.005 -> 0.01
        $line1 = $this->service->calculateLineTax(['rate' => '5.0000'], '0.10', 1);
        $this->assertSame('0.01', $line1->taxAmount);

        // Line 2: 0.005 raw tax -> rounds to 0.01
        $line2 = $this->service->calculateLineTax(['rate' => '5.0000'], '0.10', 1);
        $this->assertSame('0.01', $line2->taxAmount);

        // Order header tax total MUST be 0.02 (0.01 + 0.01), NOT 0.01 (round(0.005 + 0.005))
        $totals = $this->service->calculateOrderTotals([$line1, $line2]);

        $this->assertSame('0.20', $totals['taxable_total']);
        $this->assertSame('0.02', $totals['tax_total']);
        $this->assertSame('0.22', $totals['grand_total']);
        $this->assertSame(2, $totals['line_count']);
    }

    // ==========================================
    // 5. VALIDATION & ERROR HANDLING
    // ==========================================

    public function test_rejects_zero_quantity(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Quantity must be a positive integer greater than zero');

        $this->service->calculateLineTax(null, '100.00', 0);
    }

    public function test_rejects_negative_quantity(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Quantity must be a positive integer greater than zero');

        $this->service->calculateLineTax(null, '100.00', -2);
    }

    public function test_dto_array_transformations(): void
    {
        $result = $this->service->calculateLineTax(['id' => 5, 'code' => 'TAX-TEST', 'name' => 'Test Tax', 'rate' => '7.0000'], '10.00', 1);

        $array = $result->toArray();

        $this->assertSame('10.00', $array['taxable_amount']);
        $this->assertSame('7.0000', $array['tax_rate']);
        $this->assertSame('0.70', $array['tax_amount']);
        $this->assertSame('10.70', $array['line_total']);
        $this->assertSame(5, $array['snapshot']['tax_profile_id']);
        $this->assertSame('TAX-TEST', $array['snapshot']['tax_profile_code']);
    }
}
