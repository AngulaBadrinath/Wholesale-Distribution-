<?php

namespace App\Services\Category;

use App\DTOs\Category\CategoryData;
use App\Enums\AccountStatus;
use App\Enums\CategoryStatus;
use App\Enums\Permission;
use App\Models\Category;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function __construct(
        protected PermissionService $permissionService
    ) {}

    /**
     * Retrieve paginated, searchable, filterable category list.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = [], int $perPage = 15, ?User $actor = null): LengthAwarePaginator
    {
        $query = Category::query()
            ->with(['parent:id,name,code'])
            ->withCount(['products', 'children']);

        // 1. Search filter across code, name, description
        if (! empty($filters['search'])) {
            $query->search((string) $filters['search']);
        }

        // 2. Lifecycle status filter
        if (! empty($filters['status'])) {
            $query->filterByStatus((string) $filters['status']);
        }

        // 3. Root-only filter
        if (isset($filters['root_only']) && filter_var($filters['root_only'], FILTER_VALIDATE_BOOLEAN)) {
            $query->root();
        }

        // 4. Parent ID filter
        if (isset($filters['parent_id']) && is_numeric($filters['parent_id'])) {
            $query->where('parent_id', (int) $filters['parent_id']);
        }

        // 5. Sorting
        $allowedSorts = ['name', 'code', 'sort_order', 'status', 'created_at', 'products_count'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts, true) ? $filters['sort_by'] : 'sort_order';
        $sortOrder = strtolower((string) ($filters['sort_order'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $paginator = $query->orderBy($sortBy, $sortOrder)->orderBy('name', 'asc')->paginate($perPage)->withQueryString();

        $paginator->through(fn (Category $cat) => $this->formatCategory($cat, $actor));

        return $paginator;
    }

    /**
     * Retrieve full hierarchical tree of categories.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTree(?User $actor = null, bool $activeOnly = false): array
    {
        $query = Category::query()
            ->withCount(['products', 'children'])
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');

        if ($activeOnly) {
            $query->active();
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Category> $allCategories */
        $allCategories = $query->get();

        return $this->buildTreeArray($allCategories, null);
    }

    /**
     * Helper to recursively build nested tree array from flat collection.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Category>  $categories
     * @return array<int, array<string, mixed>>
     */
    protected function buildTreeArray($categories, ?int $parentId): array
    {
        $branch = [];

        foreach ($categories as $category) {
            if ($category->parent_id === $parentId) {
                $children = $this->buildTreeArray($categories, $category->id);

                $branch[] = [
                    'id' => $category->id,
                    'code' => $category->code,
                    'name' => $category->name,
                    'description' => $category->description,
                    'parent_id' => $category->parent_id,
                    'sort_order' => $category->sort_order,
                    'status' => $category->status instanceof CategoryStatus ? $category->status->value : (string) $category->status,
                    'status_label' => $category->status instanceof CategoryStatus ? $category->status->label() : (string) $category->status,
                    'status_badge_variant' => $category->status instanceof CategoryStatus ? $category->status->badgeVariant() : 'secondary',
                    'products_count' => (int) ($category->products_count ?? 0),
                    'children_count' => (int) ($category->children_count ?? count($children)),
                    'children' => $children,
                ];
            }
        }

        return $branch;
    }

    /**
     * Retrieve flat selectable hierarchy list for parent selection dropdowns.
     * Excludes self and all descendants to prevent circular hierarchy.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSelectableTree(?int $excludeId = null, bool $activeOnly = true): array
    {
        $query = Category::query()
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');

        if ($activeOnly) {
            $query->active();
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Category> $allCategories */
        $allCategories = $query->get();

        $excludedIds = [];
        if ($excludeId !== null) {
            $excludedIds[] = $excludeId;
            $target = $allCategories->firstWhere('id', $excludeId);
            if ($target) {
                $excludedIds = array_merge($excludedIds, $target->allDescendantIds());
            }
        }

        $result = [];
        $this->flattenSelectableTree($allCategories, null, 0, '', $excludedIds, $result);

        return $result;
    }

    /**
     * Helper to recursively flatten category hierarchy for dropdown presentation.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Category>  $categories
     * @param  array<int, int>  $excludedIds
     * @param  array<int, array<string, mixed>>  $result
     */
    protected function flattenSelectableTree(
        $categories,
        ?int $parentId,
        int $depth,
        string $parentPath,
        array $excludedIds,
        array &$result
    ): void {
        foreach ($categories as $category) {
            if ($category->parent_id === $parentId && ! in_array($category->id, $excludedIds, true)) {
                $currentPath = $parentPath ? "{$parentPath} > {$category->name}" : $category->name;

                $result[] = [
                    'id' => $category->id,
                    'code' => $category->code,
                    'name' => $category->name,
                    'hierarchy_path' => $currentPath,
                    'depth' => $depth,
                    'status' => $category->status instanceof CategoryStatus ? $category->status->value : (string) $category->status,
                ];

                $this->flattenSelectableTree($categories, $category->id, $depth + 1, $currentPath, $excludedIds, $result);
            }
        }
    }

    /**
     * Retrieve category by ID formatted for presentation.
     */
    public function findById(int $id, ?User $actor = null): array
    {
        /** @var Category $category */
        $category = Category::query()
            ->with(['parent:id,name,code', 'children' => fn ($q) => $q->withCount('products')])
            ->withCount(['products', 'children'])
            ->findOrFail($id);

        return $this->formatCategory($category, $actor);
    }

    /**
     * Format category model into structured presentation array.
     *
     * @return array<string, mixed>
     */
    public function formatCategory(Category $category, ?User $actor = null): array
    {
        $category->loadMissing(['parent:id,name,code']);

        $productsCount = (int) ($category->products_count ?? $category->products()->count());
        $childrenCount = (int) ($category->children_count ?? $category->children()->count());

        return [
            'id' => $category->id,
            'code' => $category->code,
            'name' => $category->name,
            'description' => $category->description,
            'parent_id' => $category->parent_id,
            'parent' => $category->parent ? [
                'id' => $category->parent->id,
                'name' => $category->parent->name,
                'code' => $category->parent->code,
            ] : null,
            'sort_order' => $category->sort_order,
            'status' => $category->status instanceof CategoryStatus ? $category->status->value : (string) $category->status,
            'status_label' => $category->status instanceof CategoryStatus ? $category->status->label() : (string) $category->status,
            'status_badge_variant' => $category->status instanceof CategoryStatus ? $category->status->badgeVariant() : 'secondary',
            'products_count' => $productsCount,
            'children_count' => $childrenCount,
            'hierarchy_path' => $category->getHierarchyPath(),
            'can_delete' => ($productsCount === 0 && $childrenCount === 0),
            'children' => $category->relationLoaded('children')
                ? $category->children->map(fn ($c) => [
                    'id' => $c->id,
                    'code' => $c->code,
                    'name' => $c->name,
                    'sort_order' => $c->sort_order,
                    'status' => $c->status instanceof CategoryStatus ? $c->status->value : (string) $c->status,
                    'products_count' => (int) ($c->products_count ?? 0),
                ])->values()->all()
                : [],
            'created_at' => $category->created_at?->toIso8601String(),
            'updated_at' => $category->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Generate the next deterministic sequential Category code.
     */
    public function generateNextCode(): string
    {
        $maxNum = 0;
        $codes = Category::query()->whereNotNull('code')->pluck('code');

        foreach ($codes as $code) {
            if (preg_match('/^CAT-(\d+)$/i', trim($code), $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }

        if ($maxNum === 0) {
            $maxId = (int) Category::max('id');
            $maxNum = $maxId;
        }

        return sprintf('CAT-%05d', $maxNum + 1);
    }

    /**
     * Validate that a category cannot become a child of itself or one of its descendants.
     *
     * @throws ValidationException
     */
    public function validateCycle(Category $category, ?int $newParentId): void
    {
        if ($newParentId === null) {
            return; // Moving to root is always cycle-free
        }

        if ($newParentId === $category->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be set as its own parent.',
            ]);
        }

        $descendantIds = $category->allDescendantIds();

        if (in_array($newParentId, $descendantIds, true)) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be moved under one of its own subcategories.',
            ]);
        }
    }

    /**
     * Validate sibling name uniqueness (unique name among categories sharing same parent).
     *
     * @throws ValidationException
     */
    public function validateSiblingNameUniqueness(string $name, ?int $parentId, ?int $ignoreId = null): void
    {
        $query = Category::query()
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($name))]);

        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => 'A category with this name already exists in the same hierarchy level.',
            ]);
        }
    }

    /**
     * Create a new category record.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function create(CategoryData $data, User $actor, ?string $ip = null): Category
    {
        $this->ensureActorIsActiveAndAuthorized($actor, Permission::PRODUCT_CREATE);

        return DB::transaction(function () use ($data, $actor, $ip) {
            $attributes = $data->toArray();

            // Auto-generate code if omitted
            if (empty($attributes['code'])) {
                $attributes['code'] = $this->generateNextCode();
            } else {
                $attributes['code'] = strtoupper(trim((string) $attributes['code']));
            }

            // Defensive unique check
            if (Category::where('code', $attributes['code'])->exists()) {
                throw ValidationException::withMessages([
                    'code' => 'The category code has already been taken.',
                ]);
            }

            // Sibling name uniqueness
            $this->validateSiblingNameUniqueness($data->name, $data->parent_id);

            // If parent_id specified, verify parent exists
            if ($data->parent_id !== null) {
                $parentExists = Category::where('id', $data->parent_id)->exists();
                if (! $parentExists) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'The selected parent category does not exist.',
                    ]);
                }
            }

            $category = Category::create($attributes);

            Log::info('Category master record created', [
                'event' => 'audit.category_event',
                'action' => 'CATEGORY_CREATED',
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role?->value,
                'category_id' => $category->id,
                'code' => $category->code,
                'name' => $category->name,
                'parent_id' => $category->parent_id,
                'sort_order' => $category->sort_order,
                'status' => $category->status?->value ?? (string) $category->status,
                'ip_address' => $ip,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $category->load('parent:id,name,code');
        });
    }

    /**
     * Update an existing category record.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(Category $category, CategoryData $data, User $actor, ?string $ip = null): Category
    {
        $this->ensureActorIsActiveAndAuthorized($actor, Permission::PRODUCT_UPDATE);

        return DB::transaction(function () use ($category, $data, $actor, $ip) {
            /** @var Category $lockedCategory */
            $lockedCategory = Category::query()
                ->where('id', $category->id)
                ->lockForUpdate()
                ->firstOrFail();

            $newCode = strtoupper(trim($data->code));

            // Check code uniqueness
            if ($newCode !== $lockedCategory->code && Category::where('code', $newCode)->where('id', '!=', $lockedCategory->id)->exists()) {
                throw ValidationException::withMessages([
                    'code' => 'The category code has already been taken.',
                ]);
            }

            // Cycle prevention
            $this->validateCycle($lockedCategory, $data->parent_id);

            // Sibling name uniqueness
            $this->validateSiblingNameUniqueness($data->name, $data->parent_id, $lockedCategory->id);

            // Check if parent_id changed (reparenting)
            $previousParentId = $lockedCategory->parent_id;
            $parentChanged = ($previousParentId !== $data->parent_id);

            // Check if status changed
            $previousStatus = $lockedCategory->status;
            $newStatus = $data->status;
            $statusChanged = ($previousStatus !== $newStatus);

            if ($statusChanged) {
                if (! $previousStatus->canTransitionTo($newStatus)) {
                    throw ValidationException::withMessages([
                        'status' => "Cannot transition category status from {$previousStatus->label()} to {$newStatus->label()}.",
                    ]);
                }
            }

            $original = $lockedCategory->only(['code', 'name', 'description', 'parent_id', 'sort_order', 'status']);
            $newValues = $data->toArray();
            $newValues['code'] = $newCode;

            $changedFields = [];
            foreach ($newValues as $key => $val) {
                $origVal = $original[$key] ?? null;
                if ($origVal instanceof CategoryStatus) {
                    $origVal = $origVal->value;
                }
                if ($origVal != $val) {
                    $changedFields[] = $key;
                }
            }

            $lockedCategory->fill($newValues);
            $lockedCategory->save();

            Log::info('Category master record updated', [
                'event' => 'audit.category_event',
                'action' => 'CATEGORY_UPDATED',
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role?->value,
                'category_id' => $lockedCategory->id,
                'code' => $lockedCategory->code,
                'changed_fields' => $changedFields,
                'ip_address' => $ip,
                'timestamp' => now()->toIso8601String(),
            ]);

            if ($parentChanged) {
                Log::info('Category reparented in hierarchy', [
                    'event' => 'audit.category_event',
                    'action' => 'CATEGORY_REPARENTED',
                    'actor_id' => $actor->id,
                    'actor_email' => $actor->email,
                    'actor_role' => $actor->role?->value,
                    'category_id' => $lockedCategory->id,
                    'code' => $lockedCategory->code,
                    'previous_parent_id' => $previousParentId,
                    'new_parent_id' => $data->parent_id,
                    'ip_address' => $ip,
                    'timestamp' => now()->toIso8601String(),
                ]);
            }

            if ($statusChanged) {
                $statusAction = ($newStatus === CategoryStatus::ACTIVE) ? 'CATEGORY_ACTIVATED' : 'CATEGORY_DEACTIVATED';
                Log::info("Category status transitioned: {$statusAction}", [
                    'event' => 'audit.category_event',
                    'action' => $statusAction,
                    'actor_id' => $actor->id,
                    'actor_email' => $actor->email,
                    'actor_role' => $actor->role?->value,
                    'category_id' => $lockedCategory->id,
                    'code' => $lockedCategory->code,
                    'previous_status' => $previousStatus?->value ?? (string) $previousStatus,
                    'new_status' => $newStatus->value,
                    'reason' => 'Updated via category edit',
                    'ip_address' => $ip,
                    'timestamp' => now()->toIso8601String(),
                ]);
            }

            return $lockedCategory->load('parent:id,name,code');
        });
    }

    /**
     * Transition category lifecycle status.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function updateStatus(
        Category $category,
        CategoryStatus $newStatus,
        User $actor,
        ?string $reason = null,
        ?string $ip = null
    ): Category {
        $this->ensureActorIsActiveAndAuthorized($actor, Permission::PRODUCT_UPDATE);

        return DB::transaction(function () use ($category, $newStatus, $actor, $reason, $ip) {
            /** @var Category $lockedCategory */
            $lockedCategory = Category::query()
                ->where('id', $category->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStatus = $lockedCategory->status;

            // No-op check: identical status returns without duplicate writes or audits
            if ($previousStatus === $newStatus) {
                return $lockedCategory;
            }

            if (! $previousStatus->canTransitionTo($newStatus)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot transition category status from {$previousStatus->label()} to {$newStatus->label()}.",
                ]);
            }

            $action = ($newStatus === CategoryStatus::ACTIVE) ? 'CATEGORY_ACTIVATED' : 'CATEGORY_DEACTIVATED';

            $lockedCategory->status = $newStatus;
            $lockedCategory->save();

            Log::info("Category status transitioned: {$action}", [
                'event' => 'audit.category_event',
                'action' => $action,
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role?->value,
                'category_id' => $lockedCategory->id,
                'code' => $lockedCategory->code,
                'previous_status' => $previousStatus?->value ?? (string) $previousStatus,
                'new_status' => $newStatus->value,
                'reason' => $reason,
                'ip_address' => $ip,
                'timestamp' => now()->toIso8601String(),
            ]);

            return $lockedCategory;
        });
    }

    /**
     * Delete an empty leaf category.
     * Deletion is permitted ONLY when category has zero attached products and zero child categories.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function delete(Category $category, User $actor, ?string $ip = null): void
    {
        $this->ensureActorIsActiveAndAuthorized($actor, Permission::PRODUCT_UPDATE);

        DB::transaction(function () use ($category, $actor, $ip) {
            /** @var Category $lockedCategory */
            $lockedCategory = Category::query()
                ->where('id', $category->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Constraint check 1: Attached products
            if ($lockedCategory->products()->exists()) {
                throw ValidationException::withMessages([
                    'category' => 'Cannot delete category: it has products associated with it. Deactivate the category instead.',
                ]);
            }

            // Constraint check 2: Child subcategories
            if ($lockedCategory->children()->exists()) {
                throw ValidationException::withMessages([
                    'category' => 'Cannot delete category: it has child subcategories. Delete or reassign child subcategories first.',
                ]);
            }

            $categorySnapshot = [
                'id' => $lockedCategory->id,
                'code' => $lockedCategory->code,
                'name' => $lockedCategory->name,
                'parent_id' => $lockedCategory->parent_id,
            ];

            $lockedCategory->delete();

            Log::info('Category master record deleted', [
                'event' => 'audit.category_event',
                'action' => 'CATEGORY_DELETED',
                'actor_id' => $actor->id,
                'actor_email' => $actor->email,
                'actor_role' => $actor->role?->value,
                'category_id' => $categorySnapshot['id'],
                'code' => $categorySnapshot['code'],
                'name' => $categorySnapshot['name'],
                'ip_address' => $ip,
                'timestamp' => now()->toIso8601String(),
            ]);
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
            throw new AuthorizationException('Inactive accounts are not authorized to perform category operations.');
        }

        $this->permissionService->authorize($actor, $permission);
    }
}
