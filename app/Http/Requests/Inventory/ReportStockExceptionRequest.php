<?php

namespace App\Http\Requests\Inventory;

use App\Enums\InventoryStockState;
use App\Enums\Permission;
use App\Enums\StockExceptionSeverity;
use App\Enums\StockExceptionType;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportStockExceptionRequest extends FormRequest
{
    public function authorize(PermissionService $permissionService): bool
    {
        $user = $this->user();
        if (! $user || ! $user->isActive()) {
            return false;
        }

        return $permissionService->hasPermission($user, Permission::INVENTORY_EXCEPTION_REPORT)
            || $permissionService->hasPermission($user, Permission::INVENTORY_ADJUST);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'order_item_allocation_id' => ['nullable', 'integer', 'exists:order_item_allocations,id'],
            'exception_type' => ['required', 'string', Rule::in(StockExceptionType::values())],
            'severity' => ['nullable', 'string', Rule::in(StockExceptionSeverity::values())],
            'source_stock_state' => ['nullable', 'string', Rule::in([InventoryStockState::AVAILABLE->value, InventoryStockState::RESERVED->value])],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
