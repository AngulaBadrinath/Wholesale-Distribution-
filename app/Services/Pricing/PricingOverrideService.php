<?php

namespace App\Services\Pricing;

use App\DTOs\Pricing\PriceOverrideDecision;
use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Enums\PriceOverrideDirection;
use App\Models\Product;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PricingOverrideService
{
    public function __construct(
        protected PermissionService $permissionService,
        protected PriceBoundaryService $priceBoundaryService
    ) {}

    /**
     * Evaluate a prospective transaction unit price against product master boundaries without authorization.
     * Useful for pre-flight boundary inspection and calculation.
     *
     * @throws ValidationException
     */
    public function evaluate(Product $product, mixed $unitPrice): PriceOverrideDecision
    {
        $normalizedPrice = PriceBoundaryService::normalize($unitPrice, 'unit_price', allowZero: false, allowNegative: false);
        $minPrice = PriceBoundaryService::normalize((string) $product->minimum_allowed_price, 'minimum_allowed_price');
        $defaultPrice = PriceBoundaryService::normalize((string) $product->default_selling_price, 'default_selling_price');
        $mrpPrice = PriceBoundaryService::normalize((string) $product->mrp, 'mrp');

        // Check min boundary: price < min
        if (bccomp($normalizedPrice, $minPrice, 2) === -1) {
            $variance = bcsub($minPrice, $normalizedPrice, 2);

            return new PriceOverrideDecision(
                isOverride: true,
                direction: PriceOverrideDirection::BELOW_MINIMUM,
                unitPrice: $normalizedPrice,
                minimumAllowedPrice: $minPrice,
                defaultSellingPrice: $defaultPrice,
                mrp: $mrpPrice,
                varianceAmount: $variance,
                reason: null,
                authorizedById: null,
                authorizedByEmail: null,
                authorizedAt: null,
                authorizationContext: []
            );
        }

        // Check max boundary: price > mrp
        if (bccomp($normalizedPrice, $mrpPrice, 2) === 1) {
            $variance = bcsub($normalizedPrice, $mrpPrice, 2);

            return new PriceOverrideDecision(
                isOverride: true,
                direction: PriceOverrideDirection::ABOVE_MRP,
                unitPrice: $normalizedPrice,
                minimumAllowedPrice: $minPrice,
                defaultSellingPrice: $defaultPrice,
                mrp: $mrpPrice,
                varianceAmount: $variance,
                reason: null,
                authorizedById: null,
                authorizedByEmail: null,
                authorizedAt: null,
                authorizationContext: []
            );
        }

        // Normal boundary: min <= price <= mrp (exact equality is normal pricing, NOT an override)
        return new PriceOverrideDecision(
            isOverride: false,
            direction: PriceOverrideDirection::NONE,
            unitPrice: $normalizedPrice,
            minimumAllowedPrice: $minPrice,
            defaultSellingPrice: $defaultPrice,
            mrp: $mrpPrice,
            varianceAmount: '0.00',
            reason: null,
            authorizedById: null,
            authorizedByEmail: null,
            authorizedAt: null,
            authorizationContext: []
        );
    }

    /**
     * Authoritatively validate and authorize prospective transaction unit price against product master boundaries.
     *
     * Invariants:
     * - Product master pricing fields remain 100% immutable.
     * - Normal prices (min <= price <= mrp) require no override and emit no override audit.
     * - Out-of-bound prices (price < min OR price > mrp) require pricing.override permission and mandatory reason (5-500 chars).
     * - Prices <= 0.00 are strictly rejected.
     * - Emits structured audit event PRODUCT_PRICE_OVERRIDE_AUTHORIZED for valid overrides.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws ValidationException
     * @throws AuthorizationException
     */
    public function authorizeOverride(
        Product $product,
        mixed $unitPrice,
        User $actor,
        ?string $reason = null,
        ?string $ip = null,
        array $context = []
    ): PriceOverrideDecision {
        $decision = $this->evaluate($product, $unitPrice);

        // 1. Normal pricing requires no override authority or reason
        if (! $decision->isOverride) {
            return $decision;
        }

        // 2. Validate mandatory override reason (5-500 chars, trimmed, non-whitespace-only)
        $trimmedReason = $this->validateOverrideReason($reason);

        // 3. Authenticate actor account state and evaluate pricing.override permission
        $resolvedIp = $ip ?? request()?->ip();
        $this->enforceOverrideAuthorization($actor, $product, $decision, $trimmedReason, $resolvedIp);

        // 4. Record structured audit event for authorized override
        Log::info('Authorized price override decision evaluated', [
            'event' => 'audit.pricing_event',
            'action' => 'PRODUCT_PRICE_OVERRIDE_AUTHORIZED',
            'actor_id' => $actor->id,
            'actor_email' => $actor->email,
            'actor_role' => $actor->role?->value,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'normal_bounds' => [
                'minimum_allowed_price' => $decision->minimumAllowedPrice,
                'default_selling_price' => $decision->defaultSellingPrice,
                'mrp' => $decision->mrp,
            ],
            'requested_price' => $decision->unitPrice,
            'direction' => $decision->direction->value,
            'variance_amount' => $decision->varianceAmount,
            'reason' => $trimmedReason,
            'ip_address' => $resolvedIp,
            'timestamp' => now()->toIso8601String(),
        ]);

        $authorizedAt = CarbonImmutable::now();
        $authContext = array_merge([
            'actor_role' => $actor->role?->value,
            'ip_address' => $resolvedIp,
        ], $context);

        return new PriceOverrideDecision(
            isOverride: true,
            direction: $decision->direction,
            unitPrice: $decision->unitPrice,
            minimumAllowedPrice: $decision->minimumAllowedPrice,
            defaultSellingPrice: $decision->defaultSellingPrice,
            mrp: $decision->mrp,
            varianceAmount: $decision->varianceAmount,
            reason: $trimmedReason,
            authorizedById: $actor->id,
            authorizedByEmail: $actor->email,
            authorizedAt: $authorizedAt,
            authorizationContext: $authContext
        );
    }

    /**
     * Validate the mandatory override reason string.
     *
     * @throws ValidationException
     */
    protected function validateOverrideReason(?string $reason): string
    {
        if ($reason === null || trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'An override reason is mandatory when transaction price is outside standard boundaries.',
            ]);
        }

        $trimmed = trim($reason);
        $length = mb_strlen($trimmed);

        if ($length < 5) {
            throw ValidationException::withMessages([
                'reason' => 'The override reason must be at least 5 characters.',
            ]);
        }

        if ($length > 500) {
            throw ValidationException::withMessages([
                'reason' => 'The override reason cannot exceed 500 characters.',
            ]);
        }

        return $trimmed;
    }

    /**
     * Enforce authorization boundary for price override.
     *
     * @throws AuthorizationException
     */
    protected function enforceOverrideAuthorization(
        User $actor,
        Product $product,
        PriceOverrideDecision $decision,
        string $reason,
        ?string $ip
    ): void {
        $isActive = ($actor->status instanceof AccountStatus)
            ? $actor->status === AccountStatus::ACTIVE
            : $actor->status === AccountStatus::ACTIVE->value;

        $hasPermission = $actor->exists && $isActive && $this->permissionService->has($actor, Permission::PRICING_OVERRIDE);

        if (! $hasPermission) {
            Log::warning('Unauthorized price override attempt rejected', [
                'event' => 'security.authorization_failure',
                'action' => 'PRICE_OVERRIDE_UNAUTHORIZED',
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role?->value,
                'actor_status' => $actor->status instanceof AccountStatus ? $actor->status->value : (string) $actor->status,
                'product_id' => $product->id,
                'sku' => $product->sku,
                'requested_price' => $decision->unitPrice,
                'direction' => $decision->direction->value,
                'variance_amount' => $decision->varianceAmount,
                'ip_address' => $ip,
                'timestamp' => now()->toIso8601String(),
            ]);

            throw new AuthorizationException('You do not have permission to authorize price overrides outside standard product boundaries.');
        }
    }
}
