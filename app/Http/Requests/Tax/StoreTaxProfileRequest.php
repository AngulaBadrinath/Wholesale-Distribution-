<?php

namespace App\Http\Requests\Tax;

use App\DTOs\Tax\TaxProfileData;
use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Enums\TaxProfileStatus;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxProfileRequest extends FormRequest
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

        return $permissionService->has($user, Permission::PRODUCT_TAX_UPDATE);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'unique:tax_profiles,code'],
            'rate' => ['required', 'regex:/^\d+(\.\d{1,4})?$/', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', Rule::in(TaxProfileStatus::values())],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'This tax profile code is already in use.',
            'rate.regex' => 'The tax rate must be a valid non-negative number with at most 4 decimal places.',
            'rate.min' => 'The tax rate cannot be negative.',
            'rate.max' => 'The tax rate cannot exceed 100.0000%.',
        ];
    }

    /**
     * Prepare inputs for validation (trim whitespace and uppercase code).
     */
    protected function prepareForValidation(): void
    {
        $sanitized = [];

        foreach ($this->all() as $key => $value) {
            if (is_string($value)) {
                $trimmed = trim($value);
                $sanitized[$key] = $trimmed === '' ? null : $trimmed;
            } elseif (is_numeric($value) && $key === 'rate') {
                $sanitized[$key] = (string) $value;
            } else {
                $sanitized[$key] = $value;
            }
        }

        if (isset($sanitized['code']) && is_string($sanitized['code'])) {
            $sanitized['code'] = strtoupper(trim((string) $sanitized['code']));
        }

        $this->merge($sanitized);
    }

    /**
     * Convert validated request to DTO.
     */
    public function toDto(): TaxProfileData
    {
        return TaxProfileData::fromArray($this->validated());
    }
}
