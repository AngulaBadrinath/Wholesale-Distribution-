<?php

namespace App\Http\Controllers\Product;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Requests\Product\UpdateProductStatusRequest;
use App\Models\Product;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $productService
    ) {}

    /**
     * Display a paginated listing of products.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['search', 'status', 'category_id', 'sort_by', 'sort_order']);
        $perPage = max(1, min((int) $request->input('per_page', 15), 100));

        $products = $this->productService->list($filters, $perPage, $user);
        $categories = $this->productService->getActiveCategories();

        return Inertia::render('Product/Index', [
            'products' => $products,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', 'ALL'),
                'category_id' => $request->input('category_id', 'ALL'),
                'sort_by' => $request->input('sort_by', 'name'),
                'sort_order' => $request->input('sort_order', 'asc'),
            ],
            'statuses' => collect(ProductStatus::cases())->map(fn (ProductStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'badgeVariant' => $s->badgeVariant(),
            ]),
            'categories' => $categories,
            'can' => [
                'create' => $user?->can('create', Product::class),
                'update' => $user?->can('update', Product::class),
                'updatePrice' => $user?->can('updatePrice', Product::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();

        if ($user && ! $user->can('create', Product::class)) {
            abort(403, 'You are not authorized to create products.');
        }

        return Inertia::render('Product/Create', [
            'suggestedSku' => $this->productService->generateNextSku(),
            'categories' => $this->productService->getActiveCategories(),
            'taxProfiles' => $this->productService->getActiveTaxProfiles(),
            'statuses' => collect(ProductStatus::cases())->map(fn (ProductStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(StoreProductRequest $request): RedirectResponse|JsonResponse
    {
        $product = $this->productService->create(
            data: $request->toDto(),
            actor: $request->user(),
            ip: $request->ip()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Product created successfully.',
                'product' => $this->productService->formatProduct($product, $request->user()),
            ], 201);
        }

        return redirect()->route('products.show', $product)
            ->with('success', 'Product created successfully.');
    }

    /**
     * Display the specified product detail.
     */
    public function show(Request $request, Product $product): Response
    {
        $user = $request->user();

        if ($user && ! $user->can('view', $product)) {
            abort(403, 'You are not authorized to access this product.');
        }

        $formatted = $this->productService->findById($product->id, $user);

        return Inertia::render('Product/Show', [
            'product' => $formatted,
            'statuses' => collect(ProductStatus::cases())->map(fn (ProductStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'badgeVariant' => $s->badgeVariant(),
            ]),
            'can' => [
                'update' => $user?->can('update', $product),
                'updatePrice' => $user?->can('updatePrice', $product),
                'updateTax' => $user?->can('updateTax', $product),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Request $request, Product $product): Response
    {
        $user = $request->user();

        if ($user && ! $user->can('update', $product)) {
            abort(403, 'You are not authorized to edit this product.');
        }

        $formatted = $this->productService->findById($product->id, $user);

        return Inertia::render('Product/Edit', [
            'product' => $formatted,
            'categories' => $this->productService->getActiveCategories(),
            'taxProfiles' => $this->productService->getActiveTaxProfiles(),
            'statuses' => collect(ProductStatus::cases())->map(fn (ProductStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'can' => [
                'updatePrice' => $user?->can('updatePrice', $product),
                'updateTax' => $user?->can('updateTax', $product),
            ],
        ]);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse|JsonResponse
    {
        $updated = $this->productService->update(
            product: $product,
            data: $request->toDto(),
            actor: $request->user(),
            ip: $request->ip()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Product updated successfully.',
                'product' => $this->productService->formatProduct($updated, $request->user()),
            ]);
        }

        return redirect()->route('products.show', $updated)
            ->with('success', 'Product updated successfully.');
    }

    /**
     * Update product lifecycle status.
     */
    public function updateStatus(UpdateProductStatusRequest $request, Product $product): RedirectResponse|JsonResponse
    {
        $newStatus = ProductStatus::from($request->validated('status'));
        $reason = $request->validated('reason');

        $updated = $this->productService->updateStatus(
            product: $product,
            newStatus: $newStatus,
            actor: $request->user(),
            reason: $reason,
            ip: $request->ip()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Product status updated successfully.',
                'product' => $this->productService->formatProduct($updated, $request->user()),
            ]);
        }

        return redirect()->route('products.show', $updated)
            ->with('success', "Product status updated to {$newStatus->label()}.");
    }
}
