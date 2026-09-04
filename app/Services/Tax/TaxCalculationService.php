<?php

namespace App\Services\Tax;

use App\DTOs\Tax\TaxCalculationResult;
use App\DTOs\Tax\TaxSnapshotData;
use App\Models\Product;
use App\Models\TaxProfile;
use App\Services\Pricing\PriceBoundaryService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class TaxCalculationService
{
    /**
     * Normalize a tax rate string to exact 4-decimal scale (0.0000 to 100.0000).
     * Rejects values with > 4 decimal places, negative rates, rates > 100%, scientific notation, or non-numeric strings.
     *
     * @throws ValidationException
     */
    public static function normalizeRate(mixed $value, string $fieldName = 'rate'): string
    {
        if ($value === null || $value === '') {
            return '0.0000';
        }

        // Strictly reject floats in authoritative financial path
        if (! is_string($value) && ! is_int($value)) {
            throw ValidationException::withMessages([
                $fieldName => ucfirst(str_replace('_', ' ', $fieldName)).' must be a valid numeric decimal string.',
            ]);
        }

        $strVal = trim((string) $value);

        // Reject scientific notation (e.g. 1e2) and non-numeric formats
        if (! preg_match('/^-?\d+(\.\d+)?$/', $strVal)) {
            throw ValidationException::withMessages([
                $fieldName => ucfirst(str_replace('_', ' ', $fieldName)).' has an invalid numeric rate format.',
            ]);
        }

        // Disallow negative rate
        if (str_starts_with($strVal, '-')) {
            throw ValidationException::withMessages([
                $fieldName => ucfirst(str_replace('_', ' ', $fieldName)).' cannot be negative.',
            ]);
        }

        // Strictly <= 4 decimal digits allowed
        $parts = explode('.', $strVal);
        if (isset($parts[1]) && strlen($parts[1]) > 4) {
            throw ValidationException::withMessages([
                $fieldName => ucfirst(str_replace('_', ' ', $fieldName)).' cannot have more than 4 decimal places.',
            ]);
        }

        // Format to exact 4 decimal places using bcadd
        $normalized = bcadd($strVal, '0', 4);

        // Bounds check: 0.0000 <= rate <= 100.0000
        if (bccomp($normalized, '100.0000', 4) === 1) {
            throw ValidationException::withMessages([
                $fieldName => ucfirst(str_replace('_', ' ', $fieldName)).' cannot exceed 100.0000%.',
            ]);
        }

        return $normalized;
    }

    /**
     * Deterministic, arbitrary-precision ROUND_HALF_UP rounding for exact decimal strings.
     * Operates purely via string decimal arithmetic without IEEE-754 float precision loss.
     */
    public static function roundHalfUp(string $value, int $decimals = 2): string
    {
        $str = trim($value);
        if (! preg_match('/^-?\d+(\.\d+)?$/', $str)) {
            throw new \InvalidArgumentException("Invalid decimal string for rounding: {$value}");
        }

        $isNegative = str_starts_with($str, '-');
        $absStr = ltrim($str, '-');

        // Half-adder for the requested scale: e.g. for scale 2, add 0.005
        $halfAdder = '0.'.str_repeat('0', $decimals).'5';
        $adjusted = bcadd($absStr, $halfAdder, $decimals + 1);

        // Truncate cleanly at $decimals scale
        $parts = explode('.', $adjusted);
        $intPart = $parts[0];
        $decPart = isset($parts[1]) ? substr($parts[1], 0, $decimals) : str_repeat('0', $decimals);
        if (strlen($decPart) < $decimals) {
            $decPart = str_pad($decPart, $decimals, '0', STR_PAD_RIGHT);
        }

        $result = $decimals > 0 ? "{$intPart}.{$decPart}" : $intPart;

        return $isNegative && bccomp($result, '0', $decimals) !== 0 ? "-{$result}" : $result;
    }

    /**
     * Authoritatively calculate line-level tax for a product or tax profile.
     *
     * @param  Product|TaxProfile|array<string, mixed>|null  $productOrTaxProfile
     *
     * @throws ValidationException
     */
    public function calculateLineTax(
        Product|TaxProfile|array|null $productOrTaxProfile,
        mixed $unitPrice,
        mixed $quantity = 1,
        ?CarbonImmutable $calculatedAt = null
    ): TaxCalculationResult {
        // 1. Normalize unit price (strictly non-negative 2-decimal string)
        $normalizedPrice = PriceBoundaryService::normalize($unitPrice, 'unit_price', allowZero: true, allowNegative: false);

        // 2. Normalize quantity (must be positive integer or positive decimal)
        $normalizedQty = $this->normalizeQuantity($quantity);

        // 3. Resolve tax profile and rate metadata
        [$profileId, $profileCode, $profileName, $rate] = $this->resolveTaxProfileMetadata($productOrTaxProfile);
        $normalizedRate = self::normalizeRate($rate, 'tax_rate');

        // 4. Calculate line taxable amount: unit_price * quantity (2-decimal string)
        $taxableAmount = bcmul($normalizedPrice, $normalizedQty, 2);

        // 5. Calculate line tax amount: taxable_amount * tax_rate / 100 with intermediate 8-decimal scale
        $rawTax = bcdiv(bcmul($taxableAmount, $normalizedRate, 8), '100', 8);
        $taxAmount = self::roundHalfUp($rawTax, 2);

        // 6. Calculate line total: taxable_amount + tax_amount
        $lineTotal = bcadd($taxableAmount, $taxAmount, 2);

        $timestamp = $calculatedAt ?? CarbonImmutable::now();

        $snapshot = new TaxSnapshotData(
            taxProfileId: $profileId,
            taxProfileCode: $profileCode,
            taxProfileName: $profileName,
            taxRate: $normalizedRate,
            taxableAmount: $taxableAmount,
            taxAmount: $taxAmount,
            lineTotal: $lineTotal,
            calculatedAt: $timestamp
        );

        return new TaxCalculationResult(
            taxableAmount: $taxableAmount,
            taxRate: $normalizedRate,
            taxAmount: $taxAmount,
            lineTotal: $lineTotal,
            snapshot: $snapshot
        );
    }

    /**
     * Calculate aggregated order-level totals strictly from individual line calculation results.
     * Ensures order tax total is the exact sum of rounded line taxes (preventing 1-cent invoice rounding discrepancies).
     *
     * @param  array<int, TaxCalculationResult>  $lineResults
     * @return array{taxable_total: string, tax_total: string, grand_total: string, line_count: int}
     */
    public function calculateOrderTotals(array $lineResults): array
    {
        $taxableTotal = '0.00';
        $taxTotal = '0.00';
        $grandTotal = '0.00';

        foreach ($lineResults as $line) {
            $taxableTotal = bcadd($taxableTotal, $line->taxableAmount, 2);
            $taxTotal = bcadd($taxTotal, $line->taxAmount, 2);
            $grandTotal = bcadd($grandTotal, $line->lineTotal, 2);
        }

        return [
            'taxable_total' => $taxableTotal,
            'tax_total' => $taxTotal,
            'grand_total' => $grandTotal,
            'line_count' => count($lineResults),
        ];
    }

    /**
     * Normalize quantity input.
     *
     * @throws ValidationException
     */
    protected function normalizeQuantity(mixed $quantity): string
    {
        if ($quantity === null || $quantity === '') {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity is required.',
            ]);
        }

        if (! is_int($quantity) && ! is_string($quantity)) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be a valid integer.',
            ]);
        }

        $strQty = trim((string) $quantity);

        if (! preg_match('/^\d+$/', $strQty) || (int) $strQty <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be a positive integer greater than zero.',
            ]);
        }

        return $strQty;
    }

    /**
     * Resolve tax profile metadata and rate from various input types.
     *
     * @param  Product|TaxProfile|array<string, mixed>|null  $input
     * @return array{0: ?int, 1: ?string, 2: ?string, 3: string}
     */
    protected function resolveTaxProfileMetadata(Product|TaxProfile|array|null $input): array
    {
        if ($input instanceof Product) {
            $profile = $input->relationLoaded('taxProfile')
                ? $input->taxProfile
                : ($input->tax_profile_id ? TaxProfile::find($input->tax_profile_id) : null);

            if ($profile instanceof TaxProfile) {
                return [
                    $profile->id,
                    $profile->code,
                    $profile->name,
                    (string) $profile->rate,
                ];
            }

            return [null, null, null, '0.0000'];
        }

        if ($input instanceof TaxProfile) {
            return [
                $input->id,
                $input->code,
                $input->name,
                (string) $input->rate,
            ];
        }

        if (is_array($input)) {
            $id = isset($input['tax_profile_id']) && $input['tax_profile_id'] !== '' && $input['tax_profile_id'] !== null
                ? (int) $input['tax_profile_id']
                : (isset($input['id']) ? (int) $input['id'] : null);

            $code = isset($input['code']) ? (string) $input['code'] : (isset($input['tax_profile_code']) ? (string) $input['tax_profile_code'] : null);
            $name = isset($input['name']) ? (string) $input['name'] : (isset($input['tax_profile_name']) ? (string) $input['tax_profile_name'] : null);
            $rate = isset($input['rate']) ? (string) $input['rate'] : (isset($input['tax_rate']) ? (string) $input['tax_rate'] : '0.0000');

            return [$id, $code, $name, $rate];
        }

        return [null, null, null, '0.0000'];
    }
}
