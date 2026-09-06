<?php

namespace App\Http\Requests\Adjustment;

use Illuminate\Foundation\Http\FormRequest;

class ReverseOrderAdjustmentRequest extends FormRequest
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
            'reason' => is_string($this->reason) ? trim($this->reason) : $this->reason,
            'emergency_override_reason' => is_string($this->emergency_override_reason) ? trim($this->emergency_override_reason) : $this->emergency_override_reason,
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
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
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
            'reason' => 'reversal reason',
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
            'reason.required' => 'A reason for reversing the adjustment is mandatory.',
            'reason.min' => 'The reversal reason must be at least 10 characters to provide adequate audit documentation.',
            'reason.max' => 'The reversal reason cannot exceed 1000 characters.',
            'emergency_override_reason.min' => 'The emergency override reason must be at least 10 characters.',
            'emergency_override_reason.max' => 'The emergency override reason cannot exceed 1000 characters.',
        ];
    }
}
