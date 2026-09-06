<?php

namespace App\Http\Requests\Adjustment;

use Illuminate\Foundation\Http\FormRequest;

class ApproveOrderAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authoritative domain authorization is enforced in Policy and WorkflowService
    }

    /**
     * Prepare data for validation by trimming strings.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'emergency_override_reason' => is_string($this->emergency_override_reason) ? trim($this->emergency_override_reason) : $this->emergency_override_reason,
            'acknowledge_allocation_impact' => filter_var($this->acknowledge_allocation_impact, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'acknowledge_allocation_impact' => ['sometimes', 'boolean'],
            'emergency_override_reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'acknowledge_allocation_impact' => 'allocation impact acknowledgment',
            'emergency_override_reason' => 'emergency override reason',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'emergency_override_reason.min' => 'The emergency override reason must be at least 10 characters.',
            'emergency_override_reason.max' => 'The emergency override reason may not exceed 1000 characters.',
        ];
    }
}
