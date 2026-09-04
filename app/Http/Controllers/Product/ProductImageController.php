<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\UploadProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Product\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductImageController extends Controller
{
    public function __construct(
        protected ProductImageService $productImageService
    ) {}

    /**
     * Store a newly uploaded image for a product.
     */
    public function store(UploadProductImageRequest $request, Product $product): RedirectResponse|JsonResponse
    {
        $image = $this->productImageService->upload(
            product: $product,
            file: $request->file('image'),
            actor: $request->user(),
            isPrimaryRequested: (bool) $request->input('is_primary', false),
            sortOrder: (int) $request->input('sort_order', 0),
            ip: $request->ip()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Product image uploaded successfully.',
                'image' => $this->productImageService->formatImage($image),
            ], 201);
        }

        return redirect()->back()->with('success', 'Product image uploaded successfully.');
    }

    /**
     * Set a product image as the primary image.
     */
    public function setPrimary(Request $request, Product $product, ProductImage $image): RedirectResponse|JsonResponse
    {
        $updated = $this->productImageService->setPrimary(
            product: $product,
            image: $image,
            actor: $request->user(),
            ip: $request->ip()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Primary product image updated successfully.',
                'image' => $this->productImageService->formatImage($updated),
            ]);
        }

        return redirect()->back()->with('success', 'Primary product image updated successfully.');
    }

    /**
     * Delete an image from a product.
     */
    public function destroy(Request $request, Product $product, ProductImage $image): RedirectResponse|JsonResponse
    {
        $this->productImageService->delete(
            product: $product,
            image: $image,
            actor: $request->user(),
            ip: $request->ip()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Product image deleted successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Product image deleted successfully.');
    }
}
