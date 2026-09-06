<?php

namespace App\Http\Requests\Inventory;

use App\Enums\Permission;
use App\Enums\StockExceptionSeverity;
use App\Enums\StockExceptionStatus;
use App\Enums\StockExceptionType;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminStockExceptionIndexRequest extends FormRequest
{
    public function authorize(PermissionService $permissionService): bool
    {
        $user = $this->user();
        if (! $user || ! $user->isActive()) {
            return false;
        }

        return $permissionService->hasPermission($user, Permission::INVENTORY_VIEW);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'status' => ['nullable', 'string', Rule::in(array_merge(['ALL'], StockExceptionStatus::values()))],
            'severity' => ['nullable', 'string', Rule::in(array_merge(['ALL'], StockExceptionSeverity::values()))],
            'exception_type' => ['nullable', 'string', Rule::in(array_merge(['ALL'], StockExceptionType::values()))],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
