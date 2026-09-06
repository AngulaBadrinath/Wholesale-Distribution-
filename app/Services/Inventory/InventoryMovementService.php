<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\InventoryStockState;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryMovementService
{
    /**
     * Generate the next deterministic sequential movement number.
     */
    public function generateMovementNumber(): string
    {
        $datePrefix = Carbon::now()->format('Ymd');
        $randomSuffix = strtoupper(bin2hex(random_bytes(3)));
        $count = InventoryMovement::whereDate('created_at', Carbon::today())->count() + 1;
        $seq = str_pad((string) $count, 4, '0', STR_PAD_LEFT);

        $candidate = "MOV-{$datePrefix}-{$seq}-{$randomSuffix}";

        while (InventoryMovement::where('movement_number', $candidate)->exists()) {
            $randomSuffix = strtoupper(bin2hex(random_bytes(3)));
            $candidate = "MOV-{$datePrefix}-{$seq}-{$randomSuffix}";
        }

        return $candidate;
    }

    /**
     * Record an immutable inventory movement entry atomically.
     * Must be called within an active DB transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordMovement(array $data): InventoryMovement
    {
        $quantity = (int) ($data['quantity'] ?? 0);
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Movement quantity must be strictly positive.');
        }

        $movementNumber = $data['movement_number'] ?? $this->generateMovementNumber();

        $movementType = $data['movement_type'] instanceof InventoryMovementType
            ? $data['movement_type']
            : InventoryMovementType::from((string) $data['movement_type']);

        $fromState = $data['from_state'] instanceof InventoryStockState
            ? $data['from_state']
            : InventoryStockState::from((string) $data['from_state']);

        $toState = $data['to_state'] instanceof InventoryStockState
            ? $data['to_state']
            : InventoryStockState::from((string) $data['to_state']);

        /** @var InventoryMovement $movement */
        $movement = InventoryMovement::create([
            'movement_number' => $movementNumber,
            'warehouse_id' => $data['warehouse_id'],
            'product_id' => $data['product_id'],
            'inventory_balance_id' => $data['inventory_balance_id'],
            'movement_type' => $movementType,
            'from_state' => $fromState,
            'to_state' => $toState,
            'quantity' => $quantity,
            'on_hand_before' => (int) $data['on_hand_before'],
            'on_hand_after' => (int) $data['on_hand_after'],
            'reserved_before' => (int) $data['reserved_before'],
            'reserved_after' => (int) $data['reserved_after'],
            'available_before' => (int) $data['available_before'],
            'available_after' => (int) $data['available_after'],
            'damaged_before' => (int) $data['damaged_before'],
            'damaged_after' => (int) $data['damaged_after'],
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'actor_id' => $data['actor_id'],
        ]);

        return $movement;
    }

    /**
     * Retrieve paginated immutable movement ledger for a specific inventory balance row.
     */
    public function getMovementsForBalance(int $balanceId, int $perPage = 25): LengthAwarePaginator
    {
        return InventoryMovement::query()
            ->with(['actor', 'product', 'warehouse'])
            ->where('inventory_balance_id', $balanceId)
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Retrieve recent movements across the warehouse or organization.
     *
     * @return Collection<int, InventoryMovement>
     */
    public function getRecentMovements(?int $warehouseId = null, int $limit = 50): Collection
    {
        $query = InventoryMovement::query()
            ->with(['actor', 'product', 'warehouse'])
            ->orderBy('id', 'desc')
            ->limit($limit);

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->get();
    }
}
