<?php

namespace App\Http\Controllers\Category;

use App\Enums\CategoryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryStatusRequest;
use App\Models\Category;
use App\Services\Category\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryService $categoryService
    ) {}

    /**
     * Display a listing and hierarchy of categories.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['search', 'status', 'root_only', 'sort_by', 'sort_order']);
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));

        $categories = $this->categoryService->list($filters, $perPage, $user);
        $tree = $this->categoryService->getTree($user);

        return Inertia::render('Category/Index', [
            'categories' => $categories,
            'tree' => $tree,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', 'ALL'),
                'root_only' => filter_var($request->input('root_only', false), FILTER_VALIDATE_BOOLEAN),
                'sort_by' => $request->input('sort_by', 'sort_order'),
                'sort_order' => $request->input('sort_order', 'asc'),
            ],
            'statuses' => collect(CategoryStatus::cases())->map(fn (CategoryStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'badgeVariant' => $s->badgeVariant(),
            ]),
            'can' => [
                'create' => $user?->can('create', Category::class),
                'update' => $user?->can('update', Category::class),
                'delete' => $user?->can('delete', Category::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new category.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();

        if ($user && ! $user->can('create', Category::class)) {
            abort(403, 'You are not authorized to create categories.');
        }

        return Inertia::render('Category/Create', [
            'suggestedCode' => $this->categoryService->generateNextCode(),
            'selectableParents' => $this->categoryService->getSelectableTree(null, true),
            'statuses' => collect(CategoryStatus::cases())->map(fn (CategoryStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse|JsonResponse
    {
        $category = $this->categoryService->create(
            data: $request->toDto(),
            actor: $request->user(),
            ip: $request->ip()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Category created successfully.',
                'category' => $this->categoryService->formatCategory($category, $request->user()),
            ], 201);
        }

        return redirect()->route('categories.show', $category)
            ->with('success', 'Category created successfully.');
    }

    /**
     * Display the specified category detail and subcategory structure.
     */
    public function show(Request $request, Category $category): Response
    {
        $user = $request->user();

        if ($user && ! $user->can('view', $category)) {
            abort(403, 'You are not authorized to access this category.');
        }

        $formatted = $this->categoryService->findById($category->id, $user);

        return Inertia::render('Category/Show', [
            'category' => $formatted,
            'statuses' => collect(CategoryStatus::cases())->map(fn (CategoryStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'badgeVariant' => $s->badgeVariant(),
            ]),
            'can' => [
                'create' => $user?->can('create', Category::class),
                'update' => $user?->can('update', $category),
                'delete' => $user?->can('delete', $category),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(Request $request, Category $category): Response
    {
        $user = $request->user();

        if ($user && ! $user->can('update', $category)) {
            abort(403, 'You are not authorized to edit this category.');
        }

        $formatted = $this->categoryService->findById($category->id, $user);
        $selectableParents = $this->categoryService->getSelectableTree($category->id, true);

        return Inertia::render('Category/Edit', [
            'category' => $formatted,
            'selectableParents' => $selectableParents,
            'statuses' => collect(CategoryStatus::cases())->map(fn (CategoryStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    /**
     * Update the specified category in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse|JsonResponse
    {
        $updated = $this->categoryService->update(
            category: $category,
            data: $request->toDto(),
            actor: $request->user(),
            ip: $request->ip()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Category updated successfully.',
                'category' => $this->categoryService->formatCategory($updated, $request->user()),
            ]);
        }

        return redirect()->route('categories.show', $updated)
            ->with('success', 'Category updated successfully.');
    }

    /**
     * Update category lifecycle status.
     */
    public function updateStatus(UpdateCategoryStatusRequest $request, Category $category): RedirectResponse|JsonResponse
    {
        $newStatus = CategoryStatus::from($request->validated('status'));
        $reason = $request->validated('reason');

        $updated = $this->categoryService->updateStatus(
            category: $category,
            newStatus: $newStatus,
            actor: $request->user(),
            reason: $reason,
            ip: $request->ip()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Category status updated successfully.',
                'category' => $this->categoryService->formatCategory($updated, $request->user()),
            ]);
        }

        return redirect()->route('categories.show', $updated)
            ->with('success', "Category status updated to {$newStatus->label()}.");
    }

    /**
     * Remove the specified empty leaf category from storage.
     */
    public function destroy(Request $request, Category $category): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if ($user && ! $user->can('delete', $category)) {
            abort(403, 'You are not authorized to delete categories.');
        }

        $this->categoryService->delete(
            category: $category,
            actor: $user,
            ip: $request->ip()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Category deleted successfully.',
            ]);
        }

        return redirect()->route('categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
