<?php

namespace App\Services\Inventory;

use App\Enums\InventoryAdjustmentReason;
use App\Enums\InventoryAdjustmentType;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStockState;
use App\Enums\Permission;
use App\Models\InventoryAdjustment;
use App\Models\InventoryBalance;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class InventoryAdjustmentService
{
    public function __construct(
        protected PermissionService $permissionService,
        protected InventoryService $inventoryService,
        protected InventoryMovementService $movementService,
    ) {}

    /**
     * Generate unique sequential adjustment number.
     */
    public function generateAdjustmentNumber(): string
    {
        $datePrefix = Carbon::now()->format('Ymd');
        $randomSuffix = strtoupper(bin2hex(random_bytes(3)));
        $count = InventoryAdjustment::whereDate('created_at', Carbon::today())->count() + 1;
        $seq = str_pad((string) $count, 4, '0', STR_PAD_LEFT);

        $candidate = "ADJ-{$datePrefix}-{$seq}-{$randomSuffix}";

        while (InventoryAdjustment::where('adjustment_number', $candidate)->exists()) {
            $randomSuffix = strtoupper(bin2hex(random_bytes(3)));
            $candidate = "ADJ-{$datePrefix}-{$seq}-{$randomSuffix}";
        }

        return $candidate;
    }

    /**
     * Authoritatively adjust physical inventory balance with pessimistic row lock,
     * strict math boundaries, optimistic version checking, and immutable movement ledger recording.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ConflictHttpException
     * @throws ValidationException
     */
    public function adjustBalance(array $data, User $actor): InventoryAdjustment
    {
        if (! $actor->isActive()) {
            throw new AuthorizationException('Your account is not active.');
        }

        $this->permissionService->authorize($actor, Permission::INVENTORY_ADJUST);

        $warehouseId = (int) $data['warehouse_id'];
        $productId = (int) $data['product_id'];
        $quantity = (int) $data['quantity'];

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Adjustment quantity must be strictly greater than zero.',
            ]);
        }

        $adjustmentType = $data['adjustment_type'] instanceof InventoryAdjustmentType
            ? $data['adjustment_type']
            : InventoryAdjustmentType::from((string) $data['adjustment_type']);

        $reasonCode = $data['reason_code'] instanceof InventoryAdjustmentReason
            ? $data['reason_code']
            : InventoryAdjustmentReason::from((string) $data['reason_code']);

        $notes = trim((string) ($data['notes'] ?? ''));
        if (mb_strlen($notes) < 5) {
            throw ValidationException::withMessages([
                'notes' => 'Adjustment justification notes must be at least 5 characters.',
            ]);
        }

        $expectedVersion = isset($data['expected_version']) ? (int) $data['expected_version'] : null;

        return DB::transaction(function () use ($warehouseId, $productId, $quantity, $adjustmentType, $reasonCode, $notes, $expectedVersion, $actor) {
            /** @var InventoryBalance|null $balance */
            $balance = $this->inventoryService->lockBalanceForUpdate($warehouseId, $productId);

            if (! $balance) {
                throw ValidationException::withMessages([
                    'product_id' => "No inventory balance record found for product ID {$productId} at warehouse ID {$warehouseId}.",
                ]);
            }

            if ($expectedVersion !== null && (int) $balance->version !== $expectedVersion) {
                throw new ConflictHttpException("Inventory balance has been modified concurrently (expected version: {$expectedVersion}, actual: {$balance->version}). Please refresh and retry.");
            }

            $onHandBefore = (int) $balance->on_hand_quantity;
            $reservedBefore = (int) $balance->reserved_quantity;
            $availableBefore = (int) $balance->available_quantity;
            $damagedBefore = (int) $balance->damaged_quantity;

            $movementType = InventoryMovementType::ADJUSTMENT;
            $fromState = InventoryStockState::NONE;
            $toState = InventoryStockState::NONE;

            switch ($adjustmentType) {
                case InventoryAdjustmentType::INCREASE_ON_HAND:
                    $balance->on_hand_quantity += $quantity;
                    $balance->available_quantity = $balance->calculateAvailableQuantity();

                    $movementType = InventoryMovementType::INCREASE_ON_HAND;
                    $fromState = InventoryStockState::EXTERNAL;
                    $toState = InventoryStockState::AVAILABLE;
                    break;

                case InventoryAdjustmentType::DECREASE_ON_HAND:
                    if ($availableBefore < $quantity) {
                        throw ValidationException::withMessages([
                            'quantity' => "Insufficient available stock to decrease. Available: {$availableBefore}, Requested: {$quantity}.",
                        ]);
                    }

                    $balance->on_hand_quantity -= $quantity;
                    $balance->available_quantity = $balance->calculateAvailableQuantity();

                    $movementType = InventoryMovementType::DECREASE_ON_HAND;
                    $fromState = InventoryStockState::AVAILABLE;
                    $toState = InventoryStockState::NONE;
                    break;

                case InventoryAdjustmentType::TRANSFER_TO_DAMAGED:
                    if ($availableBefore < $quantity) {
                        throw ValidationException::withMessages([
                            'quantity' => "Insufficient available stock to transfer to damaged quarantine. Available: {$availableBefore}, Requested: {$quantity}.",
                        ]);
                    }

                    $balance->damaged_quantity += $quantity;
                    $balance->available_quantity = $balance->calculateAvailableQuantity();

                    $movementType = InventoryMovementType::DAMAGE_ISOLATION;
                    $fromState = InventoryStockState::AVAILABLE;
                    $toState = InventoryStockState::DAMAGED;
                    break;

                case InventoryAdjustmentType::DAMAGE_DISPOSAL:
                    if ($damagedBefore < $quantity) {
                        throw ValidationException::withMessages([
                            'quantity' => "Insufficient damaged stock to dispose. Damaged: {$damagedBefore}, Requested: {$quantity}.",
                        ]);
                    }

                    $balance->damaged_quantity -= $quantity;
                    $balance->on_hand_quantity -= $quantity;
                    $balance->available_quantity = $balance->calculateAvailableQuantity();

                    $movementType = InventoryMovementType::DAMAGE_RELEASE;
                    $fromState = InventoryStockState::DAMAGED;
                    $toState = InventoryStockState::NONE;
                    break;
            }

            $balance->version += 1;
            $balance->save();

            $adjustmentNumber = $this->generateAdjustmentNumber();

            // Record immutable ledger entry
            $this->movementService->recordMovement([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'inventory_balance_id' => $balance->id,
                'movement_type' => $movementType,
                'from_state' => $fromState,
                'to_state' => $toState,
                'quantity' => $quantity,
                'on_hand_before' => $onHandBefore,
                'on_hand_after' => (int) $balance->on_hand_quantity,
                'reserved_before' => $reservedBefore,
                'reserved_after' => (int) $balance->reserved_quantity,
                'available_before' => $availableBefore,
                'available_after' => (int) $balance->available_quantity,
                'damaged_before' => $damagedBefore,
                'damaged_after' => (int) $balance->damaged_quantity,
                'reference_type' => 'inventory_adjustment',
                'reference_number' => $adjustmentNumber,
                'notes' => "Adjustment [{$adjustmentNumber} / {$adjustmentType->value} / {$reasonCode->value}]: {$notes}",
                'actor_id' => $actor->id,
            ]);

            /** @var InventoryAdjustment $adjustment */
            $adjustment = InventoryAdjustment::create([
                'adjustment_number' => $adjustmentNumber,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'inventory_balance_id' => $balance->id,
                'adjustment_type' => $adjustmentType,
                'reason_code' => $reasonCode,
                'quantity' => $quantity,
                'on_hand_before' => $onHandBefore,
                'on_hand_after' => (int) $balance->on_hand_quantity,
                'reserved_before' => $reservedBefore,
                'reserved_after' => (int) $balance->reserved_quantity,
                'available_before' => $availableBefore,
                'available_after' => (int) $balance->available_quantity,
                'damaged_before' => $damagedBefore,
                'damaged_after' => (int) $balance->damaged_quantity,
                'notes' => $notes,
                'actor_id' => $actor->id,
            ]);

            return $adjustment;
        }, 3);
    }

    /**
     * List historical inventory adjustments.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listAdjustments(array $filters, int $perPage, User $actor): LengthAwarePaginator
    {
        $this->permissionService->authorize($actor, Permission::INVENTORY_VIEW);

        $query = InventoryAdjustment::query()
            ->with(['warehouse:id,code,name', 'product:id,sku,name,unit', 'actor:id,name']);

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }

        if (! empty($filters['adjustment_type']) && strtoupper((string) $filters['adjustment_type']) !== 'ALL') {
            $query->where('adjustment_type', strtoupper((string) $filters['adjustment_type']));
        }

        if (! empty($filters['reason_code']) && strtoupper((string) $filters['reason_code']) !== 'ALL') {
            $query->where('reason_code', strtoupper((string) $filters['reason_code']));
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $isPgsql = DB::connection()->getDriverName() === 'pgsql';
            $like = $isPgsql ? 'ilike' : 'like';

            $query->where(function ($q) use ($search, $like) {
                $q->where('adjustment_number', $like, "%{$search}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('name', $like, "%{$search}%")->orWhere('sku', $like, "%{$search}%"));
            });
        }

        $boundedPerPage = max(1, min(100, $perPage));

        return $query->orderBy('id', 'desc')->paginate($boundedPerPage)->withQueryString();
    }
}
