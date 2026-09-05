<?php

namespace App\Services\Allocation;

use App\Enums\AllocationStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemAllocation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OrderAllocationService
{
    /**
     * Create baseline initial allocations for all fulfillable items of an approved order.
     * Must be called inside a PostgreSQL transaction (or will start one).
     * Enforces deterministic row locking and idempotency.
     *
     * @return Collection<int, OrderItemAllocation>
     *
     * @throws ConflictHttpException
     * @throws ValidationException
     */
    public function createInitialAllocationsForOrder(Order $order, ?User $actor = null): Collection
    {
        return DB::transaction(function () use ($order, $actor) {
            // Lock target order
            /** @var Order $lockedOrder */
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedOrder->status, [OrderStatus::APPROVED, OrderStatus::PROCESSING], true)) {
                $statusLabel = $lockedOrder->status instanceof OrderStatus ? $lockedOrder->status->label() : (string) $lockedOrder->status;
                throw new ConflictHttpException("Order {$lockedOrder->order_number} is in '{$statusLabel}' status and cannot receive allocations.");
            }

            // Lock order items ordered by ascending ID
            $lockedItems = $lockedOrder->items()->lockForUpdate()->orderBy('id', 'asc')->get();

            $allocations = new Collection();
            $totalAllocatedUnits = 0;

            foreach ($lockedItems as $item) {
                $fulfillable = $item->fulfillableQuantity();

                if ($fulfillable <= 0) {
                    continue;
                }

                // Deterministic baseline allocation number for line item
                $orderNumClean = $lockedOrder->order_number ?: 'ORD-' . $lockedOrder->id;
                $allocationNumber = "ALC-{$orderNumClean}-{$item->id}-01";

                // Lock and check if baseline allocation already exists (exact-once idempotency)
                /** @var OrderItemAllocation|null $existingAllocation */
                $existingAllocation = OrderItemAllocation::where('order_item_id', $item->id)
                    ->where('allocation_number', $allocationNumber)
                    ->lockForUpdate()
                    ->first();

                if ($existingAllocation) {
                    $allocations->push($existingAllocation);
                    $totalAllocatedUnits += $existingAllocation->allocated_quantity;
                    continue;
                }

                // Check cross-row total constraint before creating new allocation
                $currentAllocated = $item->allocatedQuantity();
                if (($currentAllocated + $fulfillable) > $fulfillable) {
                    // Already partially or fully allocated via other records
                    $remaining = max(0, $fulfillable - $currentAllocated);
                    if ($remaining <= 0) {
                        continue;
                    }
                    $allocationQty = $remaining;
                } else {
                    $allocationQty = $fulfillable;
                }

                /** @var OrderItemAllocation $allocation */
                $allocation = OrderItemAllocation::create([
                    'allocation_number' => $allocationNumber,
                    'order_id' => $lockedOrder->id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'allocated_quantity' => $allocationQty,
                    'reserved_quantity' => $allocationQty,
                    'picked_quantity' => 0,
                    'dispatched_quantity' => 0,
                    'delivered_quantity' => 0,
                    'returned_quantity' => 0,
                    'status' => AllocationStatus::ALLOCATED,
                    'warehouse_code' => 'MAIN',
                    'notes' => 'Initial baseline order allocation upon approval',
                    'allocated_by' => $actor?->id,
                    'allocated_at' => Carbon::now(),
                ]);

                $allocations->push($allocation);
                $totalAllocatedUnits += $allocationQty;
            }

            // Post-commit structured observability
            Log::info('commerce.allocation_event', [
                'action' => 'ORDER_ALLOCATED',
                'order_id' => $lockedOrder->id,
                'order_number' => $lockedOrder->order_number,
                'actor_id' => $actor?->id,
                'actor_name' => $actor?->name,
                'allocations_count' => $allocations->count(),
                'total_allocated_units' => $totalAllocatedUnits,
                'timestamp' => Carbon::now()->toIso8601String(),
            ]);

            return $allocations;
        }, 3);
    }

    /**
     * Allocate a specific quantity for an order item.
     * Enforces strict cross-row conservation and lock ordering.
     *
     * @throws ConflictHttpException
     * @throws ValidationException
     */
    public function allocateItemQuantity(
        OrderItem $item,
        int $quantity,
        ?User $actor = null,
        string $warehouseCode = 'MAIN',
        ?string $notes = null
    ): OrderItemAllocation {
        if ($quantity <= 0 || $quantity > 999999) {
            throw ValidationException::withMessages([
                'quantity' => 'Allocation quantity must be between 1 and 999,999.',
            ]);
        }

        return DB::transaction(function () use ($item, $quantity, $actor, $warehouseCode, $notes) {
            // Lock Order -> OrderItem -> OrderItemAllocations
            /** @var Order $lockedOrder */
            $lockedOrder = Order::where('id', $item->order_id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedOrder->status, [OrderStatus::APPROVED, OrderStatus::PROCESSING], true)) {
                $statusLabel = $lockedOrder->status instanceof OrderStatus ? $lockedOrder->status->label() : (string) $lockedOrder->status;
                throw new ConflictHttpException("Order {$lockedOrder->order_number} is in '{$statusLabel}' status and cannot receive allocations.");
            }

            /** @var OrderItem $lockedItem */
            $lockedItem = OrderItem::where('id', $item->id)->lockForUpdate()->firstOrFail();

            // Lock existing allocations for item
            $lockedAllocations = OrderItemAllocation::where('order_item_id', $lockedItem->id)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            $unallocated = $lockedItem->unallocatedQuantity();

            if ($quantity > $unallocated) {
                throw ValidationException::withMessages([
                    'quantity' => "Cannot allocate {$quantity} units. Only {$unallocated} fulfillable units remain unallocated.",
                ]);
            }

            $orderNumClean = $lockedOrder->order_number ?: 'ORD-' . $lockedOrder->id;
            $sequence = sprintf('%02d', $lockedAllocations->count() + 1);
            $allocationNumber = "ALC-{$orderNumClean}-{$lockedItem->id}-{$sequence}";

            /** @var OrderItemAllocation $allocation */
            $allocation = OrderItemAllocation::create([
                'allocation_number' => $allocationNumber,
                'order_id' => $lockedOrder->id,
                'order_item_id' => $lockedItem->id,
                'product_id' => $lockedItem->product_id,
                'allocated_quantity' => $quantity,
                'reserved_quantity' => $quantity,
                'picked_quantity' => 0,
                'dispatched_quantity' => 0,
                'delivered_quantity' => 0,
                'returned_quantity' => 0,
                'status' => AllocationStatus::ALLOCATED,
                'warehouse_code' => $warehouseCode,
                'notes' => $notes,
                'allocated_by' => $actor?->id,
                'allocated_at' => Carbon::now(),
            ]);

            Log::info('commerce.allocation_event', [
                'action' => 'ITEM_ALLOCATION_CREATED',
                'order_id' => $lockedOrder->id,
                'order_number' => $lockedOrder->order_number,
                'order_item_id' => $lockedItem->id,
                'allocation_id' => $allocation->id,
                'allocation_number' => $allocation->allocation_number,
                'allocated_quantity' => $quantity,
                'actor_id' => $actor?->id,
                'timestamp' => Carbon::now()->toIso8601String(),
            ]);

            return $allocation;
        }, 3);
    }

    /**
     * Calculate summary metrics of allocations for an order.
     *
     * @return array{
     *     total_allocated_units: int,
     *     total_fulfillable_units: int,
     *     total_unallocated_units: int,
     *     allocations_count: int,
     *     has_allocations: bool
     * }
     */
    public function calculateAllocationBreakdown(Order $order): array
    {
        $items = $order->relationLoaded('items')
            ? $order->items
            : $order->items()->with('allocations')->get();

        $totalAllocatedUnits = 0;
        $totalFulfillableUnits = 0;
        $allocationsCount = 0;

        foreach ($items as $item) {
            $fulfillable = $item->fulfillableQuantity();
            $allocated = $item->allocatedQuantity();

            $totalFulfillableUnits += $fulfillable;
            $totalAllocatedUnits += $allocated;

            if ($item->relationLoaded('allocations')) {
                $allocationsCount += $item->allocations->count();
            } else {
                $allocationsCount += $item->allocations()->count();
            }
        }

        $totalUnallocatedUnits = max(0, $totalFulfillableUnits - $totalAllocatedUnits);

        return [
            'total_allocated_units' => $totalAllocatedUnits,
            'total_fulfillable_units' => $totalFulfillableUnits,
            'total_unallocated_units' => $totalUnallocatedUnits,
            'allocations_count' => $allocationsCount,
            'has_allocations' => $allocationsCount > 0,
        ];
    }

    /**
     * Safe, idempotent backfill helper for historical approved orders created before
     * this model existed that have reserved_quantity > 0 but zero allocation records.
     * Preserves original approved_at and approved_by timestamps where available.
     *
     * @return Collection<int, OrderItemAllocation>
     */
    public function backfillApprovedOrderAllocations(?Order $order = null): Collection
    {
        if ($order !== null) {
            return $this->backfillSingleOrder($order);
        }

        $eligibleOrders = Order::whereIn('status', [OrderStatus::APPROVED, OrderStatus::PROCESSING])
            ->whereDoesntHave('allocations')
            ->orderBy('id', 'asc')
            ->get();

        $allCreated = new Collection();
        foreach ($eligibleOrders as $ord) {
            $allCreated = $allCreated->concat($this->backfillSingleOrder($ord));
        }

        return $allCreated;
    }

    /**
     * Internal helper to backfill allocations for a single order.
     *
     * @return Collection<int, OrderItemAllocation>
     */
    protected function backfillSingleOrder(Order $order): Collection
    {
        if (! in_array($order->status, [OrderStatus::APPROVED, OrderStatus::PROCESSING], true)) {
            return new Collection();
        }

        return DB::transaction(function () use ($order) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();
            $lockedItems = $lockedOrder->items()->lockForUpdate()->orderBy('id', 'asc')->get();

            $created = new Collection();

            foreach ($lockedItems as $item) {
                // If item already has allocations, skip
                if ($item->allocations()->exists()) {
                    continue;
                }

                $fulfillable = $item->fulfillableQuantity();
                if ($fulfillable <= 0) {
                    continue;
                }

                $orderNumClean = $lockedOrder->order_number ?: 'ORD-' . $lockedOrder->id;
                $allocationNumber = "ALC-{$orderNumClean}-{$item->id}-01";

                /** @var OrderItemAllocation $allocation */
                $allocation = OrderItemAllocation::create([
                    'allocation_number' => $allocationNumber,
                    'order_id' => $lockedOrder->id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'allocated_quantity' => $fulfillable,
                    'reserved_quantity' => $item->reserved_quantity > 0 ? min($item->reserved_quantity, $fulfillable) : $fulfillable,
                    'picked_quantity' => $item->picked_quantity,
                    'dispatched_quantity' => $item->dispatched_quantity,
                    'delivered_quantity' => $item->delivered_quantity,
                    'returned_quantity' => $item->returned_quantity,
                    'status' => AllocationStatus::ALLOCATED,
                    'warehouse_code' => 'MAIN',
                    'notes' => 'Controlled backfill of baseline allocation for historical approved order',
                    'allocated_by' => $lockedOrder->approved_by,
                    'allocated_at' => $lockedOrder->approved_at ?: Carbon::now(),
                ]);

                $created->push($allocation);
            }

            return $created;
        }, 3);
    }
}
