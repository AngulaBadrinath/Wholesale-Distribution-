<?php

namespace App\Http\Requests\Inventory;

use App\Enums\Permission;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

class DismissStockExceptionRequest extends FormRequest
{
    public function authorize(PermissionService $permissionService): bool
    {
        $user = $this->user();
        if (! $user || ! $user->isActive()) {
            return false;
        }

        // Mutation requires authoritative inventory.adjust permission (Never view-only)
        return $permissionService->hasPermission($user, Permission::INVENTORY_ADJUST);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dismissal_reason' => ['required', 'string', 'min:5', 'max:2000'],
            'revert_quarantine' => ['nullable', 'boolean'],
        ];
    }
}
