<?php

namespace App\Http\Requests\Order;

use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderHistoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authoritative domain authorization is enforced in PermissionService and OrderPolicy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $statusValues = array_merge(OrderStatus::values(), ['ALL', 'all']);
        $fulfillmentValues = array_merge(FulfillmentStatus::values(), ['ALL', 'all']);
        $paymentValues = array_merge(PaymentStatus::values(), ['ALL', 'all']);
        $deliveryValues = array_merge(DeliveryStatus::values(), ['ALL', 'all']);

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in($statusValues)],
            'fulfillment_status' => ['nullable', 'string', Rule::in($fulfillmentValues)],
            'payment_status' => ['nullable', 'string', Rule::in($paymentValues)],
            'delivery_status' => ['nullable', 'string', Rule::in($deliveryValues)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Custom error messages for clear API / filter validation feedback.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'Search term cannot exceed 100 characters.',
            'status.in' => 'Selected order status filter is invalid.',
            'fulfillment_status.in' => 'Selected fulfillment status filter is invalid.',
            'payment_status.in' => 'Selected payment status filter is invalid.',
            'delivery_status.in' => 'Selected delivery status filter is invalid.',
            'date_from.date_format' => 'Start date must be in YYYY-MM-DD format.',
            'date_to.date_format' => 'End date must be in YYYY-MM-DD format.',
            'date_to.after_or_equal' => 'End date must be on or after the start date.',
            'page.min' => 'Page number must be a positive integer.',
        ];
    }
}
