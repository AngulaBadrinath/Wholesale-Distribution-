<?php

namespace App\Exceptions\Inventory;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InsufficientStockException extends Exception
{
    public function __construct(
        public readonly int $productId,
        public readonly string $sku,
        public readonly int $requestedQuantity,
        public readonly int $availableQuantity,
        public readonly ?int $warehouseId = null,
        string $message = '',
        int $code = 422
    ) {
        $message = $message ?: "Insufficient physical stock for SKU [{$sku}]. Requested: {$requestedQuantity}, Available: {$availableQuantity}.";
        parent::__construct($message, $code);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $this->getMessage(),
                'errors' => [
                    'inventory' => [$this->getMessage()],
                ],
                'product_id' => $this->productId,
                'sku' => $this->sku,
                'requested_quantity' => $this->requestedQuantity,
                'available_quantity' => $this->availableQuantity,
                'warehouse_id' => $this->warehouseId,
            ], 422);
        }

        return redirect()->back()->withErrors([
            'inventory' => $this->getMessage(),
            'error' => $this->getMessage(),
        ]);
    }
}
