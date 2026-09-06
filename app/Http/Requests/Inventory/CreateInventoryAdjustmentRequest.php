<?php

namespace App\Http\Requests\Inventory;

use App\Enums\InventoryAdjustmentReason;
use App\Enums\InventoryAdjustmentType;
use App\Enums\Permission;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateInventoryAdjustmentRequest extends FormRequest
{
    public function authorize(PermissionService $permissionService): bool
    {
        $user = $this->user();
        if (! $user || ! $user->isActive()) {
            return false;
        }

        return $permissionService->hasPermission($user, Permission::INVENTORY_ADJUST);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'adjustment_type' => ['required', 'string', Rule::in(InventoryAdjustmentType::values())],
            'reason_code' => ['required', 'string', Rule::in(InventoryAdjustmentReason::values())],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'expected_version' => ['nullable', 'integer', 'min:0'],
            'notes' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
