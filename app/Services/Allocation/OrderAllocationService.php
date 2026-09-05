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
    public function __construct(
        protected ?OrderAllocationValidationService $validator = null
    ) {
        $this->validator ??= new OrderAllocationValidationService();
    }

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

            $this->validator->validateOrderLifecycleEligibility($lockedOrder);

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

                // Synchronize line item rollups authoritatively
                $this->syncOrderItemRollups($item);

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
        return DB::transaction(function () use ($item, $quantity, $actor, $warehouseCode, $notes) {
            // Lock Order -> OrderItem -> OrderItemAllocations
            /** @var Order $lockedOrder */
            $lockedOrder = Order::where('id', $item->order_id)->lockForUpdate()->firstOrFail();

            $this->validator->validateOrderLifecycleEligibility($lockedOrder);

            /** @var OrderItem $lockedItem */
            $lockedItem = OrderItem::where('id', $item->id)->lockForUpdate()->firstOrFail();

            // Lock existing allocations for item
            OrderItemAllocation::where('order_item_id', $lockedItem->id)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            $unallocated = $lockedItem->unallocatedQuantity();

            // Validate requested quantity against boundary rules and unallocated capacity
            $this->validator->validateAllocationQuantity($quantity, $unallocated);

            $orderNumClean = $lockedOrder->order_number ?: 'ORD-' . $lockedOrder->id;

            // Deterministic sequence generation: query maximum existing integer suffix across ALL allocations for this item
            $maxSequence = OrderItemAllocation::where('order_item_id', $lockedItem->id)
                ->pluck('allocation_number')
                ->map(function ($num) {
                    if (preg_match('/-(\d+)$/', (string) $num, $matches)) {
                        return (int) $matches[1];
                    }
                    return 0;
                })
                ->max() ?? 0;

            $nextSequence = sprintf('%02d', $maxSequence + 1);
            $allocationNumber = "ALC-{$orderNumClean}-{$lockedItem->id}-{$nextSequence}";

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

            // Centralized authoritative rollup synchronization
            $this->syncOrderItemRollups($lockedItem);

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
     * Release an allocation record, returning its quantity to the unallocated pool.
     * Preserves non-destructive history by soft-transitioning status to RELEASED.
     *
     * @throws ConflictHttpException
     */
    public function releaseAllocation(
        OrderItemAllocation $allocation,
        ?User $actor = null,
        ?string $reason = null
    ): OrderItemAllocation {
        return DB::transaction(function () use ($allocation, $actor, $reason) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::where('id', $allocation->order_id)->lockForUpdate()->firstOrFail();
            $this->validator->validateOrderLifecycleEligibility($lockedOrder);

            /** @var OrderItem $lockedItem */
            $lockedItem = OrderItem::where('id', $allocation->order_item_id)->lockForUpdate()->firstOrFail();

            /** @var OrderItemAllocation $lockedAllocation */
            $lockedAllocation = OrderItemAllocation::where('id', $allocation->id)->lockForUpdate()->firstOrFail();

            if (! $lockedAllocation->isReleasable()) {
                throw new ConflictHttpException("Allocation {$lockedAllocation->allocation_number} in status '{$lockedAllocation->status->value}' cannot be released.");
            }

            $releasedQty = $lockedAllocation->allocated_quantity;

            $lockedAllocation->status = AllocationStatus::RELEASED;
            $lockedAllocation->reserved_quantity = 0;
            if ($reason) {
                $lockedAllocation->notes = trim(($lockedAllocation->notes ? $lockedAllocation->notes . ' | ' : '') . 'Release reason: ' . $reason);
            }
            $lockedAllocation->save();

            // Synchronize line item rollups authoritatively
            $this->syncOrderItemRollups($lockedItem);

            Log::info('commerce.allocation_event', [
                'action' => 'ALLOCATION_RELEASED',
                'order_id' => $lockedOrder->id,
                'order_number' => $lockedOrder->order_number,
                'order_item_id' => $lockedItem->id,
                'allocation_id' => $lockedAllocation->id,
                'allocation_number' => $lockedAllocation->allocation_number,
                'released_quantity' => $releasedQty,
                'actor_id' => $actor?->id,
                'reason' => $reason,
                'timestamp' => Carbon::now()->toIso8601String(),
            ]);

            return $lockedAllocation;
        }, 3);
    }

    /**
     * Cancel an allocation record, voiding it prior to physical fulfillment.
     * Preserves non-destructive history by soft-transitioning status to CANCELLED.
     *
     * @throws ConflictHttpException
     */
    public function cancelAllocation(
        OrderItemAllocation $allocation,
        ?User $actor = null,
        ?string $reason = null
    ): OrderItemAllocation {
        return DB::transaction(function () use ($allocation, $actor, $reason) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::where('id', $allocation->order_id)->lockForUpdate()->firstOrFail();
            $this->validator->validateOrderLifecycleEligibility($lockedOrder);

            /** @var OrderItem $lockedItem */
            $lockedItem = OrderItem::where('id', $allocation->order_item_id)->lockForUpdate()->firstOrFail();

            /** @var OrderItemAllocation $lockedAllocation */
            $lockedAllocation = OrderItemAllocation::where('id', $allocation->id)->lockForUpdate()->firstOrFail();

            if (! $lockedAllocation->isCancellable()) {
                throw new ConflictHttpException("Allocation {$lockedAllocation->allocation_number} in status '{$lockedAllocation->status->value}' cannot be cancelled.");
            }

            $cancelledQty = $lockedAllocation->allocated_quantity;

            $lockedAllocation->status = AllocationStatus::CANCELLED;
            $lockedAllocation->reserved_quantity = 0;
            if ($reason) {
                $lockedAllocation->notes = trim(($lockedAllocation->notes ? $lockedAllocation->notes . ' | ' : '') . 'Cancellation reason: ' . $reason);
            }
            $lockedAllocation->save();

            // Synchronize line item rollups authoritatively
            $this->syncOrderItemRollups($lockedItem);

            Log::info('commerce.allocation_event', [
                'action' => 'ALLOCATION_CANCELLED',
                'order_id' => $lockedOrder->id,
                'order_number' => $lockedOrder->order_number,
                'order_item_id' => $lockedItem->id,
                'allocation_id' => $lockedAllocation->id,
                'allocation_number' => $lockedAllocation->allocation_number,
                'cancelled_quantity' => $cancelledQty,
                'actor_id' => $actor?->id,
                'reason' => $reason,
                'timestamp' => Carbon::now()->toIso8601String(),
            ]);

            return $lockedAllocation;
        }, 3);
    }

    /**
     * Authoritatively recalculate and synchronize order_items operational rollups from allocation rows.
     * Ensures single directional authority without circular synchronization loops.
     */
    public function syncOrderItemRollups(OrderItem $item): OrderItem
    {
        $allocations = OrderItemAllocation::where('order_item_id', $item->id)->get();

        $activeAllocations = $allocations->filter(
            fn (OrderItemAllocation $a) => ! in_array($a->status, [AllocationStatus::CANCELLED, AllocationStatus::RELEASED], true)
        );

        $item->reserved_quantity = (int) $activeAllocations->sum('reserved_quantity');
        $item->picked_quantity = (int) $allocations->sum('picked_quantity');
        $item->dispatched_quantity = (int) $allocations->sum('dispatched_quantity');
        $item->delivered_quantity = (int) $allocations->sum('delivered_quantity');
        $item->returned_quantity = (int) $allocations->sum('returned_quantity');
        $item->save();

        return $item;
    }

    /**
     * Check if a proposed reduction in fulfillable quantity violates active allocations.
     * Invariant: reduction <= unallocated_quantity
     */
    public function canReduceFulfillableQuantity(OrderItem $item, int $reductionQuantity): bool
    {
        return $this->validator->canReduceFulfillableQuantity($item, $reductionQuantity);
    }

    /**
     * Validate consistency of operational rollups against allocation rows, throwing on drift.
     *
     * @throws ValidationException
     */
    public function validateRollupConsistency(OrderItem $item): void
    {
        $drift = $this->validator->detectRollupDrift($item);

        if ($drift['has_drift']) {
            throw ValidationException::withMessages([
                'rollup_drift' => "Rollup drift detected for line item #{$item->id}: " . json_encode($drift['drift_details']),
            ]);
        }
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

                $this->syncOrderItemRollups($item);

                $created->push($allocation);
            }

            return $created;
        }, 3);
    }
}
