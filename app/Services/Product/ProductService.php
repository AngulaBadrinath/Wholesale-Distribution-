<?php

namespace App\Services\Product;

use App\DTOs\Product\ProductData;
use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Retrieve paginated, searchable, filterable product list.
     * Mask sensitive cost_price for non-administrative roles.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = [], int $perPage = 15, ?User $actor = null): LengthAwarePaginator
    {
        $query = Product::query()->with('category:id,name,code');

        // 1. Search filter across SKU, name, description
        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        // 2. Lifecycle Status filter
        if (! empty($filters['status'])) {
            $query->filterByStatus((string) $filters['status']);
        }

        // 3. Category filter
        if (! empty($filters['category_id'])) {
            $query->filterByCategory($filters['category_id']);
        }

        // 4. Sorting (allow-listed fields only)
        $allowedSorts = ['name', 'sku', 'default_selling_price', 'mrp', 'status', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts, true) ? $filters['sort_by'] : 'name';
        $sortOrder = strtolower((string) ($filters['sort_order'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $paginator = $query->orderBy($sortBy, $sortOrder)->paginate($perPage)->withQueryString();

        // 5. Transform collection to shape sensitive fields based on actor role
        $paginator->through(fn (Product $product) => $this->formatProduct($product, $actor));

        return $paginator;
    }

    /**
     * Retrieve active categories for selection dropdowns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveCategories(): array
    {
        return \App\Models\Category::active()
            ->orderBy('name', 'asc')
            ->get(['id', 'code', 'name'])
            ->toArray();
    }

    /**
     * Retrieve product by ID formatted for presentation.
     */
    public function findById(int $id, ?User $actor = null): array
    {
        /** @var Product $product */
        $product = Product::with('category:id,name,code')->findOrFail($id);

        return $this->formatProduct($product, $actor);
    }

    /**
     * Format product model into structured presentation array with sensitive cost masking.
     *
     * @return array<string, mixed>
     */
    public function formatProduct(Product $product, ?User $actor = null): array
    {
        $product->loadMissing('category:id,name,code');

        $isAdmin = $actor && in_array($actor->role, [UserRole::SUPER_ADMIN, UserRole::ADMIN], true);

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'description' => $product->description,
            'category_id' => $product->category_id,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'code' => $product->category->code,
            ] : null,
            'unit' => $product->unit,
            'status' => $product->status instanceof ProductStatus ? $product->status->value : (string) $product->status,
            'status_label' => $product->status instanceof ProductStatus ? $product->status->label() : (string) $product->status,
            'status_badge_variant' => $product->status instanceof ProductStatus ? $product->status->badgeVariant() : 'secondary',
            'cost_price' => $isAdmin ? (float) $product->cost_price : null,
            'minimum_allowed_price' => (float) $product->minimum_allowed_price,
            'default_selling_price' => (float) $product->default_selling_price,
            'mrp' => (float) $product->mrp,
            'can_order' => $product->canOrder(),
            'tax_profile_id' => $product->tax_profile_id,
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Generate the next deterministic sequential SKU.
     */
    public function generateNextSku(): string
    {
        $maxNum = 0;
        $skus = Product::query()->whereNotNull('sku')->pluck('sku');

        foreach ($skus as $sku) {
            if (preg_match('/^PROD-(\d+)$/i', trim($sku), $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }

        if ($maxNum === 0) {
            $maxId = (int) Product::max('id');
            $maxNum = $maxId;
        }

        return sprintf('PROD-%05d', $maxNum + 1);
    }

    /**
     * Validate authoritative commercial pricing hierarchy.
     *
     * Invariant:
     * 0 <= cost_price
     * 0 < minimum_allowed_price <= default_selling_price <= mrp
     *
     * @throws ValidationException
     */
    public function validatePricingHierarchy(float $costPrice, float $minPrice, float $defaultPrice, float $mrp): void
    {
        $errors = [];

        if ($costPrice < 0) {
            $errors['cost_price'] = 'Cost price cannot be negative.';
        }

        if ($minPrice <= 0) {
            $errors['minimum_allowed_price'] = 'Minimum allowed price must be greater than zero.';
        }

        if ($minPrice > $mrp) {
            $errors['minimum_allowed_price'] = 'Minimum allowed price cannot exceed the MRP / list price.';
        }

        if ($defaultPrice < $minPrice) {
            $errors['default_selling_price'] = 'Default selling price cannot be less than the minimum allowed price.';
        }

        if ($defaultPrice > $mrp) {
            $errors['default_selling_price'] = 'Default selling price cannot exceed the MRP / list price.';
        }

        if ($mrp < $defaultPrice) {
            $errors['mrp'] = 'MRP / list price cannot be less than the default selling price.';
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Create a new product master record.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function create(ProductData $data, User $actor, ?string $ip = null): Product
    {
        $this->ensureActorIsActiveAndAuthorized($actor, Permission::PRODUCT_CREATE);

        $this->validatePricingHierarchy(
            $data->cost_price,
            $data->minimum_allowed_price,
            $data->default_selling_price,
            $data->mrp
        );

        return DB::transaction(function () use ($data, $actor, $ip) {
            $attributes = $data->toArray();

            // Auto-generate SKU if omitted
            if (empty($attributes['sku'])) {
                $attributes['sku'] = $this->generateNextSku();
            } else {
                $attributes['sku'] = strtoupper(trim((string) $attributes['sku']));
            }

            // Check SKU uniqueness defensively
            if (Product::where('sku', $attributes['sku'])->exists()) {
                throw ValidationException::withMessages([
                    'sku' => 'The product SKU has already been taken.',
                ]);
            }

            $product = Product::create($attributes);

            Log::info('Product master record created', [
                'event' => 'audit.product_event',
                'action' => 'PRODUCT_CREATED',
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role?->value,
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'unit' => $product->unit,
                'cost_price' => $product->cost_price,
                'minimum_allowed_price' => $product->minimum_allowed_price,
                'default_selling_price' => $product->default_selling_price,
                'mrp' => $product->mrp,
                'status' => $product->status?->value ?? (string) $product->status,
                'ip_address' => $ip,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $product->load('category:id,name,code');
        });
    }

    /**
     * Update an existing product master record.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(Product $product, ProductData $data, User $actor, ?string $ip = null): Product
    {
        $this->ensureActorIsActiveAndAuthorized($actor, Permission::PRODUCT_UPDATE);

        $this->validatePricingHierarchy(
            $data->cost_price,
            $data->minimum_allowed_price,
            $data->default_selling_price,
            $data->mrp
        );

        return DB::transaction(function () use ($product, $data, $actor, $ip) {
            /** @var Product $lockedProduct */
            $lockedProduct = Product::query()
                ->where('id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $newSku = strtoupper(trim($data->sku));

            // Check SKU uniqueness if changed
            if ($newSku !== $lockedProduct->sku && Product::where('sku', $newSku)->where('id', '!=', $lockedProduct->id)->exists()) {
                throw ValidationException::withMessages([
                    'sku' => 'The product SKU has already been taken.',
                ]);
            }

            $previousPrices = [
                'cost_price' => (float) $lockedProduct->cost_price,
                'minimum_allowed_price' => (float) $lockedProduct->minimum_allowed_price,
                'default_selling_price' => (float) $lockedProduct->default_selling_price,
                'mrp' => (float) $lockedProduct->mrp,
            ];

            $newPrices = [
                'cost_price' => $data->cost_price,
                'minimum_allowed_price' => $data->minimum_allowed_price,
                'default_selling_price' => $data->default_selling_price,
                'mrp' => $data->mrp,
            ];

            $pricingChanged = ($previousPrices != $newPrices);

            // If pricing changed, verify actor possesses PRODUCT_PRICE_UPDATE
            if ($pricingChanged) {
                $this->ensureActorIsActiveAndAuthorized($actor, Permission::PRODUCT_PRICE_UPDATE);
            }

            $original = $lockedProduct->only(array_keys($data->toArray()));
            $newValues = $data->toArray();
            $newValues['sku'] = $newSku;

            $changedFields = [];
            foreach ($newValues as $key => $val) {
                if (($original[$key] ?? null) != $val) {
                    $changedFields[] = $key;
                }
            }

            $lockedProduct->fill($newValues);
            $lockedProduct->save();

            Log::info('Product master record updated', [
                'event' => 'audit.product_event',
                'action' => 'PRODUCT_UPDATED',
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role?->value,
                'product_id' => $lockedProduct->id,
                'sku' => $lockedProduct->sku,
                'changed_fields' => $changedFields,
                'ip_address' => $ip,
                'timestamp' => now()->toIso8601String(),
            ]);

            if ($pricingChanged) {
                Log::info('Product commercial pricing updated', [
                    'event' => 'audit.product_event',
                    'action' => 'PRODUCT_PRICING_UPDATED',
                    'actor_id' => $actor->id,
                    'actor_email' => $actor->email,
                    'actor_role' => $actor->role?->value,
                    'product_id' => $lockedProduct->id,
                    'sku' => $lockedProduct->sku,
                    'previous_prices' => $previousPrices,
                    'new_prices' => $newPrices,
                    'ip_address' => $ip,
                    'timestamp' => now()->toIso8601String(),
                ]);
            }

            return $lockedProduct->load('category:id,name,code');
        });
    }

    /**
     * Transition product lifecycle status.
     *
     * @throws AuthorizationException
     */
    public function updateStatus(
        Product $product,
        ProductStatus $newStatus,
        User $actor,
        ?string $reason = null,
        ?string $ip = null
    ): Product {
        $this->ensureActorIsActiveAndAuthorized($actor, Permission::PRODUCT_UPDATE);

        return DB::transaction(function () use ($product, $newStatus, $actor, $reason, $ip) {
            /** @var Product $lockedProduct */
            $lockedProduct = Product::query()
                ->where('id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStatus = $lockedProduct->status;

            // No-op check: identical status returns without duplicate writes or audits
            if ($previousStatus === $newStatus) {
                return $lockedProduct;
            }

            $action = ($newStatus === ProductStatus::ACTIVE) ? 'PRODUCT_ACTIVATED' : 'PRODUCT_DEACTIVATED';

            $lockedProduct->status = $newStatus;
            $lockedProduct->save();

            Log::info("Product status transitioned: {$action}", [
                'event' => 'audit.product_event',
                'action' => $action,
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role?->value,
                'product_id' => $lockedProduct->id,
                'sku' => $lockedProduct->sku,
                'previous_status' => $previousStatus?->value ?? (string) $previousStatus,
                'new_status' => $newStatus->value,
                'reason' => $reason,
                'ip_address' => $ip,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $lockedProduct;
        });
    }

    /**
     * Ensure actor has an active account status and required permission.
     *
     * @throws AuthorizationException
     */
    protected function ensureActorIsActiveAndAuthorized(User $actor, Permission $permission): void
    {
        $isActive = ($actor->status instanceof AccountStatus)
            ? $actor->status === AccountStatus::ACTIVE
            : $actor->status === AccountStatus::ACTIVE->value;

        if (! $isActive) {
            throw new AuthorizationException('Inactive accounts are not authorized to perform product operations.');
        }

        $this->permissionService->authorize($actor, $permission);
    }
}
