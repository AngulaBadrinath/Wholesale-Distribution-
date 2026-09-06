<?php

namespace App\Http\Requests\Adjustment;

use Illuminate\Foundation\Http\FormRequest;

class RejectOrderAdjustmentRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
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
            'reason' => 'rejection reason',
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
            'reason.required' => 'A reason for rejecting the adjustment request is required.',
            'reason.min' => 'The rejection reason must be at least 5 characters.',
            'reason.max' => 'The rejection reason may not exceed 1000 characters.',
            'emergency_override_reason.min' => 'The emergency override reason must be at least 10 characters.',
            'emergency_override_reason.max' => 'The emergency override reason may not exceed 1000 characters.',
        ];
    }
}
