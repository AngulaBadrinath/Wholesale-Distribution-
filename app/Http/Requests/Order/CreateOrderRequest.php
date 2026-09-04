<?php

namespace App\Http\Requests\Order;

use App\DTOs\Order\CreateOrderDTO;
use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
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
            'idempotency_key' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
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
            'customer_id.required' => 'Please select a customer for this order.',
            'idempotency_key.required' => 'An idempotency key is required to prevent duplicate order submissions.',
            'items.required' => 'An order must contain at least one product line item.',
            'items.min' => 'An order must contain at least one product line item.',
            'items.*.product_id.required' => 'Each line item must reference a valid product.',
            'items.*.quantity.required' => 'Please specify a quantity for each line item.',
            'items.*.quantity.min' => 'Ordered quantity must be at least 1.',
            'items.*.quantity.max' => 'Ordered quantity cannot exceed 999,999.',
            'items.*.quantity.integer' => 'Ordered quantity must be a whole number.',
            'items.*.unit_price.regex' => 'Unit price must be a valid monetary decimal with up to 2 decimal places.',
        ];
    }

    /**
     * Build the strongly-typed CreateOrderDTO from validated data.
     */
    public function toDTO(): CreateOrderDTO
    {
        return CreateOrderDTO::fromArray($this->validated());
    }
}
