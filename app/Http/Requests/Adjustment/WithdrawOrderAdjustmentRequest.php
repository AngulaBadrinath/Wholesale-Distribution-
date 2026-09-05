<?php

namespace App\Http\Requests\Adjustment;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawOrderAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authoritative domain authorization is enforced in PermissionService and OrderAdjustmentService
    }

    /**
     * Prepare data for validation by trimming strings.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => is_string($this->reason) ? trim($this->reason) : $this->reason,
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
            'reason' => ['nullable', 'string', 'max:1000'],
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
            'reason' => 'withdrawal reason',
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
            'reason.required' => 'A reason for withdrawing the adjustment request is required.',
            'reason.min' => 'The withdrawal reason must be at least 5 characters.',
            'reason.max' => 'The withdrawal reason may not exceed 1000 characters.',
        ];
    }
}
