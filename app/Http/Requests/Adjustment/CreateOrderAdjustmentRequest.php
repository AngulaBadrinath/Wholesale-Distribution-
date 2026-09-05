<?php

namespace App\Http\Requests\Adjustment;

use App\Enums\AdjustmentReasonCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrderAdjustmentRequest extends FormRequest
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
        $items = $this->items;
        if (is_array($items)) {
            foreach ($items as $idx => $item) {
                if (is_array($item)) {
                    if (isset($item['requested_quantity_reduction']) && ! isset($item['reduction_quantity'])) {
                        $items[$idx]['reduction_quantity'] = $item['requested_quantity_reduction'];
                    } elseif (isset($item['reduction_quantity']) && ! isset($item['requested_quantity_reduction'])) {
                        $items[$idx]['requested_quantity_reduction'] = $item['reduction_quantity'];
                    }
                }
            }
        }

        $this->merge([
            'notes' => is_string($this->notes) ? trim($this->notes) : $this->notes,
            'reason_code' => is_string($this->reason_code) ? trim($this->reason_code) : $this->reason_code,
            'idempotency_key' => is_string($this->idempotency_key) ? trim($this->idempotency_key) : $this->idempotency_key,
            'items' => $items,
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
            'reason_code' => ['required', 'string', Rule::in(AdjustmentReasonCode::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'items.*.reduction_quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'items.*.requested_quantity_reduction' => ['nullable', 'integer', 'min:1', 'max:999999'],
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
            'reason_code' => 'adjustment reason',
            'notes' => 'adjustment notes',
            'idempotency_key' => 'idempotency key',
            'items' => 'adjusted items',
            'items.*.order_item_id' => 'line item',
            'items.*.reduction_quantity' => 'reduction quantity',
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
            'reason_code.in' => 'The selected adjustment reason is invalid.',
            'notes.min' => 'Adjustment notes must be at least 5 characters.',
            'notes.max' => 'Adjustment notes may not exceed 2000 characters.',
            'items.required' => 'At least one line item must be selected for adjustment.',
            'items.*.reduction_quantity.min' => 'Reduction quantity must be at least 1 unit.',
        ];
    }
}
