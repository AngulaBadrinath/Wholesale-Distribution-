<?php

namespace App\Models;

use App\Enums\CategoryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'parent_id',
        'sort_order',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'parent_id' => 'integer',
        'sort_order' => 'integer',
        'status' => CategoryStatus::class,
    ];

    /**
     * Get the parent category.
     *
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the immediate child subcategories.
     *
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');
    }

    /**
     * Get the products belonging to this category.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Scope query to root categories (those with no parent).
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope query to active categories.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CategoryStatus::ACTIVE);
    }

    /**
     * Scope query by lifecycle status filter.
     */
    public function scopeFilterByStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status) || strtoupper((string) $status) === 'ALL') {
            return $query;
        }

        $resolved = CategoryStatus::tryFrom(strtoupper(trim((string) $status)));

        if ($resolved) {
            return $query->where('status', $resolved);
        }

        return $query;
    }

    /**
     * Scope query by search term across code, name, and description.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = trim((string) $term);
        $isPgsql = $query->getConnection()->getDriverName() === 'pgsql';
        $like = $isPgsql ? 'ilike' : 'like';

        return $query->where(function (Builder $q) use ($term, $like) {
            $q->where('name', $like, "%{$term}%")
                ->orWhere('code', $like, "%{$term}%")
                ->orWhere('description', $like, "%{$term}%");
        });
    }

    /**
     * Check if the category is active.
     */
    public function isActive(): bool
    {
        return $this->status === CategoryStatus::ACTIVE;
    }

    /**
     * Recursively retrieve all descendant category IDs of this category.
     *
     * @return array<int, int>
     */
    public function allDescendantIds(): array
    {
        $descendantIds = [];
        $children = Category::query()->where('parent_id', $this->id)->get(['id']);

        foreach ($children as $child) {
            $descendantIds[] = (int) $child->id;
            $descendantIds = array_merge($descendantIds, $child->allDescendantIds());
        }

        return array_values(array_unique($descendantIds));
    }

    /**
     * Retrieve all ancestor categories from top root down to immediate parent.
     *
     * @return Collection<int, Category>
     */
    public function ancestors(): Collection
    {
        $ancestors = new Collection();
        $current = $this->parent;

        while ($current !== null) {
            $ancestors->prepend($current);
            $current = $current->parent;
        }

        return $ancestors;
    }

    /**
     * Get the full breadcrumb hierarchy path string (e.g. "Industrial > Fasteners > Hex Bolts").
     */
    public function getHierarchyPath(string $separator = ' > '): string
    {
        $ancestorNames = $this->ancestors()->pluck('name')->all();
        $ancestorNames[] = $this->name;

        return implode($separator, $ancestorNames);
    }
}
