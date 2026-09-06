<?php

namespace App\Services\Delivery;

use App\Enums\AllocationStatus;
use App\Enums\DeliveryEventType;
use App\Enums\DeliveryFailureReason;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Delivery;
use App\Models\DeliveryEvent;
use App\Models\DeliveryFailure;
use App\Models\DeliveryItem;
use App\Models\InventoryBalance;
use App\Models\Order;
use App\Models\OrderItemAllocation;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\InventoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeliveryWorkflowService
{
    public function __construct(
        protected PermissionService $permissionService,
        protected DeliveryEvidenceService $evidenceService,
    ) {}

    /**
     * Authoritative Warehouse Pickup Confirmation (FEAT-DEL-003).
     *
     * In Model B, pickup transitions transit custody to the driver.
     * Allocation progression: dispatched_quantity is set to picked_quantity.
     * Order fulfillment_status transitions to DISPATCHED.
     * Physical inventory remains in warehouse reserved balance until DELIVERED.
     *
     * @throws AuthorizationException
     * @throws ConflictHttpException
     * @throws NotFoundHttpException
     */
    public function confirmPickup(Delivery $delivery, User $actor, array $options = []): Delivery
    {
        $this->authorizeDeliveryUpdate($delivery, $actor);

        // Deterministic Lock Hierarchy: Order -> Delivery -> OrderItemAllocations
        return DB::transaction(function () use ($delivery, $actor, $options) {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::where('id', $delivery->order_id)->lockForUpdate()->firstOrFail();

            /** @var Delivery $lockedDelivery */
            $lockedDelivery = Delivery::where('id', $delivery->id)->lockForUpdate()->firstOrFail();

            // Idempotency check: if already PICKED_UP, return latest delivery state cleanly
            if ($lockedDelivery->status === DeliveryStatus::PICKED_UP) {
                return $lockedDelivery->load(['items', 'order', 'customer', 'driver']);
            }

            if (! $lockedDelivery->canBePickedUp()) {
                throw new ConflictHttpException("Delivery {$lockedDelivery->delivery_number} is in '{$lockedDelivery->status->value}' status and cannot be picked up.");
            }

            $now = Carbon::now();

            // Lock and update OrderItemAllocations: synchronize dispatched_quantity = picked_quantity
            $allocations = OrderItemAllocation::where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->orderBy('id', 'asc')
                ->get();

            foreach ($allocations as $alloc) {
                // Invariant: 0 <= dispatched <= picked <= allocated
                if ($alloc->picked_quantity > 0) {
                    $alloc->dispatched_quantity = $alloc->picked_quantity;
                    $alloc->status = AllocationStatus::DISPATCHED;
                    $alloc->save();
                }
            }

            // Update delivery state
            $previousStatus = $lockedDelivery->status;
            $lockedDelivery->status = DeliveryStatus::PICKED_UP;
            $lockedDelivery->picked_up_at = $now;
            $lockedDelivery->updated_by = $actor->id;
            $lockedDelivery->version += 1;
            $lockedDelivery->save();

            // Record immutable event
            DeliveryEvent::create([
                'delivery_id' => $lockedDelivery->id,
                'event_type' => DeliveryEventType::PICKED_UP,
                'from_status' => $previousStatus->value,
                'to_status' => DeliveryStatus::PICKED_UP->value,
                'actor_id' => $actor->id,
                'notes' => $options['notes'] ?? 'Goods picked up from warehouse and custody accepted by driver',
                'metadata' => [
                    'picked_up_at' => $now->toIso8601String(),
                    'driver_id' => $lockedDelivery->driver_id,
                ],
                'created_at' => $now,
            ]);

            // Synchronize order fulfillment status to DISPATCHED
            $lockedOrder->fulfillment_status = FulfillmentStatus::DISPATCHED;
            $lockedOrder->delivery_status = DeliveryStatus::PICKED_UP;
            $lockedOrder->save();

            Log::info('logistics.delivery_pickup', [
                'delivery_id' => $lockedDelivery->id,
                'delivery_number' => $lockedDelivery->delivery_number,
                'order_id' => $lockedOrder->id,
                'order_number' => $lockedOrder->order_number,
                'driver_id' => $lockedDelivery->driver_id,
                'actor_id' => $actor->id,
                'timestamp' => $now->toIso8601String(),
            ]);

            return $lockedDelivery->load(['items', 'order', 'customer', 'driver']);
        }, 3);
    }

    /**
     * Authorize that the actor is either the assigned delivery driver or a privileged admin.
     *
     * @throws AuthorizationException
     * @throws NotFoundHttpException
     */
    protected function authorizeDeliveryUpdate(Delivery $delivery, User $actor): void
    {
        if (! $actor->isActive()) {
            throw new AuthorizationException('Your account is not active.');
        }

        // Fail-Closed Anti-IDOR for Delivery Partners
        if ($actor->role === UserRole::DELIVERY_PARTNER) {
            if ($delivery->driver_id !== $actor->id) {
                throw new NotFoundHttpException('Delivery mission not found.');
            }
        } else {
            // Admin / Privileged role requires delivery.update permission
            $this->permissionService->authorize($actor, Permission::DELIVERY_UPDATE);
        }
    }
}
