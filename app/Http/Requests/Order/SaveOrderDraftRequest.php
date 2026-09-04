<?php

namespace App\Http\Requests\Order;

use App\DTOs\Order\SaveOrderDraftDTO;
use Illuminate\Foundation\Http\FormRequest;

class SaveOrderDraftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authoritative domain authorization is executed in OrderPolicy and OrderService
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'expected_version' => ['nullable', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'items' => ['nullable', 'array', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999999'],
            'items.*.unit_price' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,2})?$/'],
        ];
    }

    /**
     * Custom validation messages for commercial clarity.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Please select a customer before saving a draft.',
            'items.*.product_id.required' => 'Each draft item must reference a valid product.',
            'items.*.quantity.required' => 'Please specify a quantity for each draft item.',
            'items.*.quantity.min' => 'Draft item quantity must be at least 1.',
            'items.*.quantity.max' => 'Draft item quantity cannot exceed 999,999.',
            'items.*.quantity.integer' => 'Draft item quantity must be a whole number.',
            'items.*.unit_price.regex' => 'Unit price must be a valid monetary decimal with up to 2 decimal places.',
        ];
    }

    /**
     * Build strongly-typed SaveOrderDraftDTO.
     */
    public function toDTO(): SaveOrderDraftDTO
    {
        return SaveOrderDraftDTO::fromArray($this->validated());
    }
}
