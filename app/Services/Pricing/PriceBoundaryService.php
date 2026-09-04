<?php

namespace App\Services\Pricing;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class PriceBoundaryService
{
    /**
     * Normalize a financial decimal string to exact 2-decimal scale.
     * Rejects values with > 2 decimal places, negatives (unless allowed), scientific notation, or non-numeric strings.
     *
     * @throws ValidationException
     */
    public static function normalize(mixed $value, string $fieldName = 'price', bool $allowZero = true, bool $allowNegative = false): string
    {
        if ($value === null || $value === '') {
            if ($allowZero) {
                return '0.00';
            }
            throw ValidationException::withMessages([
                $fieldName => ucfirst(str_replace('_', ' ', $fieldName)).' is required.',
            ]);
        }

        // Must be string or integer (no floats in authoritative financial path)
        if (! is_string($value) && ! is_int($value)) {
            throw ValidationException::withMessages([
                $fieldName => ucfirst(str_replace('_', ' ', $fieldName)).' must be a valid numeric value.',
            ]);
        }

        $strVal = trim((string) $value);

        // Reject scientific notation (e.g. 1e3) and non-standard characters
        if (! preg_match('/^-?\d+(\.\d+)?$/', $strVal)) {
            throw ValidationException::withMessages([
                $fieldName => ucfirst(str_replace('_', ' ', $fieldName)).' has an invalid numeric format.',
            ]);
        }

        // Disallow negative if not permitted
        if (! $allowNegative && str_starts_with($strVal, '-')) {
            throw ValidationException::withMessages([
                $fieldName => ucfirst(str_replace('_', ' ', $fieldName)).' cannot be negative.',
            ]);
        }

        // Check decimal places: strictly <= 2 decimal digits allowed
        $parts = explode('.', $strVal);
        if (isset($parts[1]) && strlen($parts[1]) > 2) {
            throw ValidationException::withMessages([
                $fieldName => ucfirst(str_replace('_', ' ', $fieldName)).' cannot have more than 2 decimal places.',
            ]);
        }

        // Format to exact 2 decimal places using bcadd
        $normalized = bcadd($strVal, '0', 2);

        if (! $allowZero && bccomp($normalized, '0.00', 2) === 0) {
            throw ValidationException::withMessages([
                $fieldName => ucfirst(str_replace('_', ' ', $fieldName)).' must be greater than zero.',
            ]);
        }

        return $normalized;
    }

    /**
     * Authoritatively validate Product Master pricing hierarchy.
     *
     * Invariant:
     * 0 <= cost_price
     * 0 < minimum_allowed_price <= default_selling_price <= mrp
     *
     * Equalities (min = default = mrp) are valid.
     *
     * @throws ValidationException
     */
    public function validateProductMasterPricing(
        mixed $costPrice,
        mixed $minPrice,
        mixed $defaultPrice,
        mixed $mrp
    ): void {
        $cost = self::normalize($costPrice, 'cost_price', allowZero: true, allowNegative: false);
        $min = self::normalize($minPrice, 'minimum_allowed_price', allowZero: false, allowNegative: false);
        $default = self::normalize($defaultPrice, 'default_selling_price', allowZero: false, allowNegative: false);
        $mrpVal = self::normalize($mrp, 'mrp', allowZero: false, allowNegative: false);

        $errors = [];

        // 1. cost_price >= 0.00
        if (bccomp($cost, '0.00', 2) === -1) {
            $errors['cost_price'] = 'Cost price cannot be negative.';
        }

        // 2. minimum_allowed_price > 0.00
        if (bccomp($min, '0.00', 2) <= 0) {
            $errors['minimum_allowed_price'] = 'Minimum allowed price must be greater than zero.';
        }

        // 3. minimum_allowed_price <= mrp
        if (bccomp($min, $mrpVal, 2) === 1) {
            $errors['minimum_allowed_price'] = 'Minimum allowed price cannot exceed the MRP / list price.';
        }

        // 4. default_selling_price >= minimum_allowed_price
        if (bccomp($default, $min, 2) === -1) {
            $errors['default_selling_price'] = 'Default selling price cannot be less than the minimum allowed price.';
        }

        // 5. default_selling_price <= mrp
        if (bccomp($default, $mrpVal, 2) === 1) {
            $errors['default_selling_price'] = 'Default selling price cannot exceed the MRP / list price.';
        }

        // 6. mrp >= default_selling_price
        if (bccomp($mrpVal, $default, 2) === -1) {
            $errors['mrp'] = 'MRP / list price cannot be less than the default selling price.';
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Authoritatively validate prospective order line unit selling price against Product pricing bounds.
     *
     * Invariant (RULE-PRI-002):
     * minimum_allowed_price <= order_unit_price <= mrp
     *
     * Reusable for future Phase 05 Order placement and line-level pricing.
     *
     * @param  Product|array<string, mixed>  $productOrPricing
     *
     * @throws ValidationException
     */
    public function validateOrderUnitPrice(Product|array $productOrPricing, mixed $orderUnitPrice): string
    {
        $normalizedOrderPrice = self::normalize($orderUnitPrice, 'unit_price', allowZero: false, allowNegative: false);

        if ($productOrPricing instanceof Product) {
            $minPrice = self::normalize((string) $productOrPricing->minimum_allowed_price, 'minimum_allowed_price');
            $mrp = self::normalize((string) $productOrPricing->mrp, 'mrp');
            $productLabel = "'{$productOrPricing->name}' ({$productOrPricing->sku})";
        } else {
            $minPrice = self::normalize((string) ($productOrPricing['minimum_allowed_price'] ?? '0.00'), 'minimum_allowed_price');
            $mrp = self::normalize((string) ($productOrPricing['mrp'] ?? '0.00'), 'mrp');
            $productLabel = 'The product';
        }

        // Check min boundary: order_price >= minPrice
        if (bccomp($normalizedOrderPrice, $minPrice, 2) === -1) {
            throw ValidationException::withMessages([
                'unit_price' => "{$productLabel} order unit price ({$normalizedOrderPrice}) cannot be less than the minimum allowed price ({$minPrice}).",
            ]);
        }

        // Check max boundary: order_price <= mrp
        if (bccomp($normalizedOrderPrice, $mrp, 2) === 1) {
            throw ValidationException::withMessages([
                'unit_price' => "{$productLabel} order unit price ({$normalizedOrderPrice}) cannot exceed the MRP / list price ({$mrp}).",
            ]);
        }

        return $normalizedOrderPrice;
    }

    /**
     * Check if prospective order line unit price is within Product pricing bounds without throwing exceptions.
     *
     * @param  Product|array<string, mixed>  $productOrPricing
     */
    public function isOrderUnitPriceWithinBounds(Product|array $productOrPricing, mixed $orderUnitPrice): bool
    {
        try {
            $this->validateOrderUnitPrice($productOrPricing, $orderUnitPrice);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }
}
