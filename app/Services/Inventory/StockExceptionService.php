<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryStockState;
use App\Enums\Permission;
use App\Enums\StockExceptionSeverity;
use App\Enums\StockExceptionStatus;
use App\Enums\StockExceptionType;
use App\Models\InventoryBalance;
use App\Models\StockException;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class StockExceptionService
{
    public function __construct(
        protected PermissionService $permissionService,
        protected InventoryService $inventoryService,
        protected InventoryMovementService $movementService,
    ) {}

    /**
     * Generate unique deterministic sequential exception number.
     */
    public function generateExceptionNumber(): string
    {
        $datePrefix = Carbon::now()->format('Ymd');
        $randomSuffix = strtoupper(bin2hex(random_bytes(3)));
        $count = StockException::whereDate('created_at', Carbon::today())->count() + 1;
        $seq = str_pad((string) $count, 4, '0', STR_PAD_LEFT);

        $candidate = "EXC-{$datePrefix}-{$seq}-{$randomSuffix}";

        while (StockException::where('exception_number', $candidate)->exists()) {
            $randomSuffix = strtoupper(bin2hex(random_bytes(3)));
            $candidate = "EXC-{$datePrefix}-{$seq}-{$randomSuffix}";
        }

        return $candidate;
    }

    /**
     * Authoritatively report a stock exception and quarantine physical stock atomically.
     * Executes inside a PostgreSQL transaction with pessimistic row locking on inventory_balances.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function reportException(array $data, User $actor): StockException
    {
        if (! $this->permissionService->hasPermission($actor, Permission::INVENTORY_EXCEPTION_REPORT) &&
            ! $this->permissionService->hasPermission($actor, Permission::INVENTORY_ADJUST)) {
            $this->permissionService->authorize($actor, Permission::INVENTORY_EXCEPTION_REPORT);
        }

        $warehouseId = (int) $data['warehouse_id'];
        $productId = (int) $data['product_id'];
        $quantity = (int) $data['quantity'];

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Quarantined exception quantity must be strictly greater than zero.',
            ]);
        }

        $sourceState = isset($data['source_stock_state'])
            ? ($data['source_stock_state'] instanceof InventoryStockState ? $data['source_stock_state'] : InventoryStockState::from((string) $data['source_stock_state']))
            : InventoryStockState::AVAILABLE;

        $exceptionType = $data['exception_type'] instanceof StockExceptionType
            ? $data['exception_type']
            : StockExceptionType::from((string) $data['exception_type']);

        $severity = isset($data['severity'])
            ? ($data['severity'] instanceof StockExceptionSeverity ? $data['severity'] : StockExceptionSeverity::from((string) $data['severity']))
            : StockExceptionSeverity::MEDIUM;

        $description = trim((string) ($data['description'] ?? ''));
        if (mb_strlen($description) < 5) {
            throw ValidationException::withMessages([
                'description' => 'The exception description must be at least 5 characters.',
            ]);
        }

        return DB::transaction(function () use ($warehouseId, $productId, $quantity, $sourceState, $exceptionType, $severity, $description, $data, $actor) {
            // Acquire pessimistic row lock
            /** @var InventoryBalance|null $balance */
            $balance = $this->inventoryService->lockBalanceForUpdate($warehouseId, $productId);

            if (! $balance) {
                throw ValidationException::withMessages([
                    'product_id' => "No inventory balance record found for product ID {$productId} at warehouse ID {$warehouseId}.",
                ]);
            }

            $onHandBefore = (int) $balance->on_hand_quantity;
            $reservedBefore = (int) $balance->reserved_quantity;
            $availableBefore = (int) $balance->available_quantity;
            $damagedBefore = (int) $balance->damaged_quantity;

            // Validate source stock availability
            if ($sourceState === InventoryStockState::AVAILABLE) {
                if ($availableBefore < $quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => "Insufficient available stock to quarantine. Available: {$availableBefore}, Requested: {$quantity}.",
                    ]);
                }

                $balance->damaged_quantity += $quantity;
                $balance->available_quantity = $balance->calculateAvailableQuantity();
            } elseif ($sourceState === InventoryStockState::RESERVED) {
                if ($reservedBefore < $quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => "Insufficient reserved stock to quarantine. Reserved: {$reservedBefore}, Requested: {$quantity}.",
                    ]);
                }

                $balance->reserved_quantity = max(0, $balance->reserved_quantity - $quantity);
                $balance->damaged_quantity += $quantity;
                $balance->available_quantity = $balance->calculateAvailableQuantity();
            } else {
                throw ValidationException::withMessages([
                    'source_stock_state' => 'Source stock state must be either AVAILABLE or RESERVED.',
                ]);
            }

            $balance->version += 1;
            $balance->save();

            $exceptionNumber = $this->generateExceptionNumber();

            // Record immutable movement entry
            $this->movementService->recordMovement([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'inventory_balance_id' => $balance->id,
                'movement_type' => InventoryMovementType::DAMAGE_ISOLATION,
                'from_state' => $sourceState,
                'to_state' => InventoryStockState::DAMAGED,
                'quantity' => $quantity,
                'on_hand_before' => $onHandBefore,
                'on_hand_after' => (int) $balance->on_hand_quantity,
                'reserved_before' => $reservedBefore,
                'reserved_after' => (int) $balance->reserved_quantity,
                'available_before' => $availableBefore,
                'available_after' => (int) $balance->available_quantity,
                'damaged_before' => $damagedBefore,
                'damaged_after' => (int) $balance->damaged_quantity,
                'reference_type' => 'stock_exception',
                'reference_number' => $exceptionNumber,
                'notes' => "Stock quarantined via exception [{$exceptionNumber}]: {$description}",
                'actor_id' => $actor->id,
            ]);

            /** @var StockException $stockException */
            $stockException = StockException::create([
                'exception_number' => $exceptionNumber,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'inventory_balance_id' => $balance->id,
                'order_id' => $data['order_id'] ?? null,
                'order_item_allocation_id' => $data['order_item_allocation_id'] ?? null,
                'exception_type' => $exceptionType,
                'severity' => $severity,
                'source_stock_state' => $sourceState,
                'quantity' => $quantity,
                'status' => StockExceptionStatus::PENDING_REVIEW,
                'description' => $description,
                'reported_by' => $actor->id,
            ]);

            return $stockException;
        }, 3);
    }

    /**
     * Authoritatively resolve a stock exception with mandatory resolution notes.
     *
     * @throws AuthorizationException
     * @throws ConflictHttpException
     * @throws ValidationException
     */
    public function resolveException(StockException $exception, User $actor, string $resolutionNotes): StockException
    {
        if (! $actor->isActive()) {
            throw new AuthorizationException('Your account is not active.');
        }

        // Mutation requires inventory.adjust permission (Never inventory.view)
        $this->permissionService->authorize($actor, Permission::INVENTORY_ADJUST);

        $notes = trim($resolutionNotes);
        if (mb_strlen($notes) < 5) {
            throw ValidationException::withMessages([
                'resolution_notes' => 'Resolution notes must be at least 5 characters.',
            ]);
        }

        return DB::transaction(function () use ($exception, $actor, $notes) {
            /** @var StockException $locked */
            $locked = StockException::where('id', $exception->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== StockExceptionStatus::PENDING_REVIEW) {
                throw new ConflictHttpException("Stock exception {$locked->exception_number} has already been {$locked->status->label()} and cannot be resolved again.");
            }

            $locked->status = StockExceptionStatus::RESOLVED;
            $locked->resolved_by = $actor->id;
            $locked->resolution_notes = $notes;
            $locked->resolved_at = Carbon::now();
            $locked->save();

            return $locked;
        }, 3);
    }

    /**
     * Authoritatively dismiss a stock exception, optionally reverting the quarantined stock.
     *
     * @throws AuthorizationException
     * @throws ConflictHttpException
     * @throws ValidationException
     */
    public function dismissException(StockException $exception, User $actor, string $dismissalReason, bool $revertQuarantine = false): StockException
    {
        if (! $actor->isActive()) {
            throw new AuthorizationException('Your account is not active.');
        }

        // Mutation requires inventory.adjust permission (Never inventory.view)
        $this->permissionService->authorize($actor, Permission::INVENTORY_ADJUST);

        $reason = trim($dismissalReason);
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages([
                'dismissal_reason' => 'The dismissal reason must be at least 5 characters.',
            ]);
        }

        return DB::transaction(function () use ($exception, $actor, $reason, $revertQuarantine) {
            /** @var StockException $locked */
            $locked = StockException::where('id', $exception->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== StockExceptionStatus::PENDING_REVIEW) {
                throw new ConflictHttpException("Stock exception {$locked->exception_number} has already been {$locked->status->label()} and cannot be dismissed again.");
            }

            if ($revertQuarantine) {
                /** @var InventoryBalance|null $balance */
                $balance = $this->inventoryService->lockBalanceForUpdate($locked->warehouse_id, $locked->product_id);

                if ($balance) {
                    $onHandBefore = (int) $balance->on_hand_quantity;
                    $reservedBefore = (int) $balance->reserved_quantity;
                    $availableBefore = (int) $balance->available_quantity;
                    $damagedBefore = (int) $balance->damaged_quantity;

                    $actualRevert = min($damagedBefore, (int) $locked->quantity);

                    if ($actualRevert > 0) {
                        $balance->damaged_quantity = max(0, $balance->damaged_quantity - $actualRevert);

                        if ($locked->source_stock_state === InventoryStockState::RESERVED) {
                            $balance->reserved_quantity += $actualRevert;
                        }

                        $balance->available_quantity = $balance->calculateAvailableQuantity();
                        $balance->version += 1;
                        $balance->save();

                        // Record movement
                        $this->movementService->recordMovement([
                            'warehouse_id' => $locked->warehouse_id,
                            'product_id' => $locked->product_id,
                            'inventory_balance_id' => $balance->id,
                            'movement_type' => InventoryMovementType::DAMAGE_RELEASE,
                            'from_state' => InventoryStockState::DAMAGED,
                            'to_state' => $locked->source_stock_state,
                            'quantity' => $actualRevert,
                            'on_hand_before' => $onHandBefore,
                            'on_hand_after' => (int) $balance->on_hand_quantity,
                            'reserved_before' => $reservedBefore,
                            'reserved_after' => (int) $balance->reserved_quantity,
                            'available_before' => $availableBefore,
                            'available_after' => (int) $balance->available_quantity,
                            'damaged_before' => $damagedBefore,
                            'damaged_after' => (int) $balance->damaged_quantity,
                            'reference_type' => 'stock_exception',
                            'reference_number' => $locked->exception_number,
                            'notes' => "Quarantine reverted on dismissal of exception [{$locked->exception_number}]: {$reason}",
                            'actor_id' => $actor->id,
                        ]);
                    }
                }
            }

            $locked->status = StockExceptionStatus::DISMISSED;
            $locked->resolved_by = $actor->id;
            $locked->resolution_notes = $reason;
            $locked->resolved_at = Carbon::now();
            $locked->save();

            return $locked;
        }, 3);
    }

    /**
     * List stock exceptions with role-based scoping and filtering.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listExceptions(array $filters, int $perPage, User $actor): LengthAwarePaginator
    {
        $this->permissionService->authorize($actor, Permission::INVENTORY_VIEW);

        $query = StockException::query()
            ->with(['warehouse:id,code,name', 'product:id,sku,name,unit', 'reportedBy:id,name', 'resolvedBy:id,name']);

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['status']) && strtoupper((string) $filters['status']) !== 'ALL') {
            $query->where('status', strtoupper((string) $filters['status']));
        }

        if (! empty($filters['severity']) && strtoupper((string) $filters['severity']) !== 'ALL') {
            $query->where('severity', strtoupper((string) $filters['severity']));
        }

        if (! empty($filters['exception_type']) && strtoupper((string) $filters['exception_type']) !== 'ALL') {
            $query->where('exception_type', strtoupper((string) $filters['exception_type']));
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $isPgsql = DB::connection()->getDriverName() === 'pgsql';
            $like = $isPgsql ? 'ilike' : 'like';

            $query->where(function ($q) use ($search, $like) {
                $q->where('exception_number', $like, "%{$search}%")
                    ->orWhereHas('product', fn ($pq) => $pq->where('name', $like, "%{$search}%")->orWhere('sku', $like, "%{$search}%"));
            });
        }

        $boundedPerPage = max(1, min(100, $perPage));

        return $query->orderBy('id', 'desc')->paginate($boundedPerPage)->withQueryString();
    }
}
