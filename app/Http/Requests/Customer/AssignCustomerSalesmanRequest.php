<?php

namespace App\Http\Requests\Customer;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

class AssignCustomerSalesmanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(PermissionService $permissionService): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $isActive = ($user->status instanceof AccountStatus)
            ? $user->status === AccountStatus::ACTIVE
            : $user->status === AccountStatus::ACTIVE->value;

        if (! $isActive) {
            return false;
        }

        return $permissionService->has($user, Permission::CUSTOMER_UPDATE);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'salesman_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Prepare inputs for validation (trim whitespace and normalize null).
     */
    protected function prepareForValidation(): void
    {
        $salesmanId = $this->input('salesman_id');

        if ($salesmanId === '' || $salesmanId === 'null' || $salesmanId === '0') {
            $salesmanId = null;
        } elseif ($salesmanId !== null) {
            $salesmanId = (int) $salesmanId;
        }

        $this->merge([
            'salesman_id' => $salesmanId,
            'reason' => $this->filled('reason') ? trim((string) $this->input('reason')) : null,
        ]);
    }
}
