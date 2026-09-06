<?php

namespace App\Services\Return;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryStockState;
use App\Models\InventoryBalance;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\User;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReturnInventoryService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected InventoryMovementService $movementService,
    ) {}

    /**
     * Execute physical inventory balance mutations, movement logging, and allocation synchronization.
     * MUST be executed inside an active DB transaction with appropriate row locks.
     *
     * @param  Collection<int, ReturnRequestItem>  $items
     *
     * @throws ValidationException
     */
    public function executeDisposition(ReturnRequest $returnRequest, Collection $items, User $actor): void
    {
        $warehouseId = $returnRequest->warehouse_id;

        foreach ($items as $item) {
            $goodQty = (int) $item->accepted_good_quantity;
            $damagedQty = (int) $item->accepted_damaged_quantity;
            $approvedQty = $goodQty + $damagedQty;

            if ($approvedQty <= 0) {
                continue;
            }

            // 1. Get or create locked InventoryBalance for product and warehouse
            /** @var InventoryBalance|null $balance */
            $balance = InventoryBalance::where('warehouse_id', $warehouseId)
                ->where('product_id', $item->product_id)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = InventoryBalance::create([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $item->product_id,
                    'on_hand_quantity' => 0,
                    'reserved_quantity' => 0,
                    'available_quantity' => 0,
                    'damaged_quantity' => 0,
                    'is_active' => true,
                ]);
                $balance = InventoryBalance::where('id', $balance->id)->lockForUpdate()->firstOrFail();
            }

            // 2. Process Good Stock Disposition (Restock to sellable available inventory)
            if ($goodQty > 0) {
                $onHandBefore = (int) $balance->on_hand_quantity;
                $availableBefore = (int) $balance->available_quantity;
                $reservedBefore = (int) $balance->reserved_quantity;
                $damagedBefore = (int) $balance->damaged_quantity;

                $onHandAfter = $onHandBefore + $goodQty;
                $availableAfter = $availableBefore + $goodQty;
                $reservedAfter = $reservedBefore;
                $damagedAfter = $damagedBefore;

                $balance->update([
                    'on_hand_quantity' => $onHandAfter,
                    'available_quantity' => $availableAfter,
                ]);

                $this->movementService->recordMovement([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $item->product_id,
                    'inventory_balance_id' => $balance->id,
                    'movement_type' => InventoryMovementType::RETURN,
                    'from_state' => InventoryStockState::EXTERNAL,
                    'to_state' => InventoryStockState::AVAILABLE,
                    'quantity' => $goodQty,
                    'on_hand_before' => $onHandBefore,
                    'on_hand_after' => $onHandAfter,
                    'reserved_before' => $reservedBefore,
                    'reserved_after' => $reservedAfter,
                    'available_before' => $availableBefore,
                    'available_after' => $availableAfter,
                    'damaged_before' => $damagedBefore,
                    'damaged_after' => $damagedAfter,
                    'reference_type' => ReturnRequest::class,
                    'reference_id' => $returnRequest->id,
                    'reference_number' => $returnRequest->return_number,
                    'notes' => "Customer Return Restock (Good): {$returnRequest->return_number}",
                    'actor_id' => $actor->id,
                ]);

                // Refresh balance state for potential damaged execution
                $balance = $balance->fresh();
            }

            // 3. Process Damaged Stock Disposition (Quarantine to damaged stock)
            if ($damagedQty > 0) {
                $onHandBefore = (int) $balance->on_hand_quantity;
                $availableBefore = (int) $balance->available_quantity;
                $reservedBefore = (int) $balance->reserved_quantity;
                $damagedBefore = (int) $balance->damaged_quantity;

                $onHandAfter = $onHandBefore + $damagedQty;
                $damagedAfter = $damagedBefore + $damagedQty;
                $availableAfter = $availableBefore; // available unchanged as on_hand - reserved - damaged remains same
                $reservedAfter = $reservedBefore;

                $balance->update([
                    'on_hand_quantity' => $onHandAfter,
                    'damaged_quantity' => $damagedAfter,
                ]);

                $this->movementService->recordMovement([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $item->product_id,
                    'inventory_balance_id' => $balance->id,
                    'movement_type' => InventoryMovementType::RETURN,
                    'from_state' => InventoryStockState::EXTERNAL,
                    'to_state' => InventoryStockState::DAMAGED,
                    'quantity' => $damagedQty,
                    'on_hand_before' => $onHandBefore,
                    'on_hand_after' => $onHandAfter,
                    'reserved_before' => $reservedBefore,
                    'reserved_after' => $reservedAfter,
                    'available_before' => $availableBefore,
                    'available_after' => $availableAfter,
                    'damaged_before' => $damagedBefore,
                    'damaged_after' => $damagedAfter,
                    'reference_type' => ReturnRequest::class,
                    'reference_id' => $returnRequest->id,
                    'reference_number' => $returnRequest->return_number,
                    'notes' => "Customer Return Quarantine (Damaged): {$returnRequest->return_number}",
                    'actor_id' => $actor->id,
                ]);
            }

            // 4. Synchronize OrderItem returned_quantity
            /** @var OrderItem $orderItem */
            $orderItem = OrderItem::where('id', $item->order_item_id)->lockForUpdate()->firstOrFail();
            $newReturnedQuantity = (int) $orderItem->returned_quantity + $approvedQty;

            if ($newReturnedQuantity > (int) $orderItem->delivered_quantity) {
                throw ValidationException::withMessages([
                    'items' => "Cumulative returned quantity ({$newReturnedQuantity}) cannot exceed delivered quantity ({$orderItem->delivered_quantity}) for item #{$orderItem->id}.",
                ]);
            }

            $orderItem->update([
                'returned_quantity' => $newReturnedQuantity,
            ]);

            // 5. Synchronize OrderItemAllocation returned_quantity
            $allocations = OrderItemAllocation::where('order_item_id', $orderItem->id)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            $remainingToAllocate = $approvedQty;
            foreach ($allocations as $allocation) {
                if ($remainingToAllocate <= 0) {
                    break;
                }
                $allocDelivered = (int) $allocation->delivered_quantity;
                $allocReturned = (int) $allocation->returned_quantity;
                $allocCapacity = max(0, $allocDelivered - $allocReturned);

                $allocIncrement = min($remainingToAllocate, $allocCapacity);
                if ($allocIncrement > 0) {
                    $allocation->update([
                        'returned_quantity' => $allocReturned + $allocIncrement,
                    ]);
                    $remainingToAllocate -= $allocIncrement;
                }
            }
        }
    }
}
