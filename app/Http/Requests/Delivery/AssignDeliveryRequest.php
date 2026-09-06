<?php

namespace App\Http\Requests\Delivery;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class AssignDeliveryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->canPermission(Permission::DELIVERY_ASSIGN);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'driver_id' => ['required', 'integer', 'exists:users,id'],
            'scheduled_date' => ['required', 'date'],
            'delivery_window' => ['nullable', 'string', 'max:50'],
            'driver_instructions' => ['nullable', 'string', 'max:1000'],
            'delivery_contact_name' => ['nullable', 'string', 'max:255'],
            'delivery_contact_phone' => ['nullable', 'string', 'max:50'],
            'delivery_address_line1' => ['nullable', 'string', 'max:255'],
            'delivery_address_line2' => ['nullable', 'string', 'max:255'],
            'delivery_city' => ['nullable', 'string', 'max:100'],
            'delivery_state' => ['nullable', 'string', 'max:100'],
            'delivery_postal_code' => ['nullable', 'string', 'max:20'],
            'delivery_country_code' => ['nullable', 'string', 'max:3'],
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
            'driver_id.required' => 'Please select a delivery driver.',
            'driver_id.exists' => 'The selected driver is invalid.',
            'scheduled_date.required' => 'Scheduled delivery date is required.',
            'scheduled_date.date' => 'Scheduled delivery date must be a valid date format.',
        ];
    }
}
