<?php

namespace App\Http\Requests\Category;

use App\DTOs\Category\CategoryData;
use App\Enums\AccountStatus;
use App\Enums\CategoryStatus;
use App\Enums\Permission;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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

        return $permissionService->has($user, Permission::PRODUCT_CREATE);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:32', 'unique:categories,code'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'status' => ['required', 'string', Rule::in(CategoryStatus::values())],
        ];
    }

    /**
     * Prepare inputs for validation.
     */
    protected function prepareForValidation(): void
    {
        $sanitized = [];

        foreach ($this->all() as $key => $value) {
            if (is_string($value)) {
                $trimmed = trim($value);
                $sanitized[$key] = $trimmed === '' ? null : $trimmed;
            } else {
                $sanitized[$key] = $value;
            }
        }

        if (isset($sanitized['code'])) {
            $sanitized['code'] = strtoupper((string) $sanitized['code']);
        }

        if (! isset($sanitized['status']) || blank($sanitized['status'])) {
            $sanitized['status'] = CategoryStatus::ACTIVE->value;
        }

        if (! isset($sanitized['sort_order']) || blank($sanitized['sort_order'])) {
            $sanitized['sort_order'] = 0;
        }

        $this->merge($sanitized);
    }

    /**
     * Convert validated request to DTO.
     */
    public function toDto(): CategoryData
    {
        return CategoryData::fromArray($this->validated());
    }
}
