<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sku',
        'name',
        'description',
        'category_id',
        'unit',
        'status',
        'cost_price',
        'minimum_allowed_price',
        'default_selling_price',
        'mrp',
        'tax_profile_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => ProductStatus::class,
        'cost_price' => 'decimal:2',
        'minimum_allowed_price' => 'decimal:2',
        'default_selling_price' => 'decimal:2',
        'mrp' => 'decimal:2',
        'category_id' => 'integer',
        'tax_profile_id' => 'integer',
    ];

    /**
     * Get the category that this product belongs to.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Scope query to active products.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::ACTIVE);
    }

    /**
     * Scope query by search term across SKU, name, and description.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = trim($term);
        $isPgsql = $query->getConnection()->getDriverName() === 'pgsql';
        $like = $isPgsql ? 'ilike' : 'like';

        return $query->where(function (Builder $q) use ($term, $like) {
            $q->where('name', $like, "%{$term}%")
                ->orWhere('sku', $like, "%{$term}%")
                ->orWhere('description', $like, "%{$term}%");
        });
    }

    /**
     * Scope query by lifecycle status filter.
     */
    public function scopeFilterByStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status) || strtoupper($status) === 'ALL') {
            return $query;
        }

        $resolved = ProductStatus::tryFrom(strtoupper(trim($status)));

        if ($resolved) {
            return $query->where('status', $resolved);
        }

        return $query;
    }

    /**
     * Scope query by category ID filter.
     */
    public function scopeFilterByCategory(Builder $query, int|string|null $categoryId): Builder
    {
        if (blank($categoryId) || strtoupper((string) $categoryId) === 'ALL') {
            return $query;
        }

        if (strtoupper((string) $categoryId) === 'UNASSIGNED') {
            return $query->whereNull('category_id');
        }

        if (is_numeric($categoryId)) {
            return $query->where('category_id', (int) $categoryId);
        }

        return $query;
    }

    /**
     * Check if the product is active.
     */
    public function isActive(): bool
    {
        return $this->status === ProductStatus::ACTIVE;
    }

    /**
     * Check if the product can be added to new orders.
     */
    public function canOrder(): bool
    {
        return $this->status instanceof ProductStatus && $this->status->canOrder();
    }

    /**
     * Authoritatively assert that the product is eligible for order placement.
     * Future Phase 05 Orders domain consumes this validation contract.
     *
     * @throws ValidationException
     */
    public function ensureCanOrder(): void
    {
        if (! $this->canOrder()) {
            throw ValidationException::withMessages([
                'product_id' => "Product '{$this->name}' ({$this->sku}) is deactivated and cannot be added to new sales orders.",
            ]);
        }
    }
}
