<?php

namespace App\Services\Inventory;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class InventoryInitializationService
{
    /**
     * Resolve or deterministically initialize the canonical default warehouse.
     */
    public function getDefaultWarehouse(): Warehouse
    {
        /** @var Warehouse|null $warehouse */
        $warehouse = Warehouse::getDefault();

        if ($warehouse) {
            return $warehouse;
        }

        return Warehouse::firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Distribution Center',
                'country_code' => 'US',
                'is_active' => true,
                'is_default' => true,
            ]
        );
    }

    /**
     * Atomically and idempotently initialize a baseline stock balance for a product at a warehouse.
     * Protected by the UNIQUE(warehouse_id, product_id) database constraint.
     */
    public function initializeProduct(Product $product, ?Warehouse $warehouse = null): InventoryBalance
    {
        $warehouse = $warehouse ?? $this->getDefaultWarehouse();

        InventoryBalance::query()->insertOrIgnore([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'bin_location' => null,
            'reorder_point' => 0,
            'safety_stock' => 0,
            'on_hand_quantity' => 0,
            'reserved_quantity' => 0,
            'available_quantity' => 0,
            'damaged_quantity' => 0,
            'is_active' => true,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return InventoryBalance::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->firstOrFail();
    }

    /**
     * Idempotently backfill baseline inventory records for all catalog products at the default warehouse.
     *
     * @return array<string, mixed>
     */
    public function initializeCatalog(?Warehouse $warehouse = null): array
    {
        $warehouse = $warehouse ?? $this->getDefaultWarehouse();

        $productIds = Product::query()->pluck('id');
        $total = $productIds->count();
        $initialized = 0;
        $alreadyExisted = 0;

        foreach ($productIds as $productId) {
            $exists = InventoryBalance::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('product_id', $productId)
                ->exists();

            if ($exists) {
                $alreadyExisted++;
                continue;
            }

            $affected = InventoryBalance::query()->insertOrIgnore([
                'warehouse_id' => $warehouse->id,
                'product_id' => $productId,
                'bin_location' => null,
                'reorder_point' => 0,
                'safety_stock' => 0,
                'on_hand_quantity' => 0,
                'reserved_quantity' => 0,
                'available_quantity' => 0,
                'damaged_quantity' => 0,
                'is_active' => true,
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($affected > 0) {
                $initialized++;
            } else {
                $alreadyExisted++;
            }
        }

        return [
            'total_products' => $total,
            'initialized' => $initialized,
            'already_existed' => $alreadyExisted,
            'warehouse_id' => $warehouse->id,
            'warehouse_code' => $warehouse->code,
        ];
    }
}
