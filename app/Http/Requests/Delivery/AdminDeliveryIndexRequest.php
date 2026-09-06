<?php

namespace App\Http\Requests\Delivery;

use App\Enums\DeliveryStatus;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminDeliveryIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->canPermission(Permission::DELIVERY_VIEW);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $statusValues = array_merge(DeliveryStatus::values(), ['ALL', 'all']);

        return [
            'status' => ['nullable', 'string', Rule::in($statusValues)],
            'tab' => ['nullable', 'string', Rule::in(['all', 'pending', 'assigned', 'active_route', 'delivered', 'failed', 'rescheduled', 'returned'])],
            'driver_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'scheduled_date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string', Rule::in(['scheduled_date', 'created_at', 'delivery_number', 'status'])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}
