<?php

namespace App\Http\Requests\Order;

use App\Enums\AdjustmentStatus;
use App\Enums\DeliveryStatus;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminOrderQueueRequest extends FormRequest
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
        $queueValues = ['new', 'attention', 'processing', 'delivery', 'adjustments', 'completed', 'cancelled', 'all'];
        $statusValues = array_merge(OrderStatus::values(), ['ALL', 'all']);
        $fulfillmentValues = array_merge(FulfillmentStatus::values(), ['ALL', 'all']);
        $paymentValues = array_merge(PaymentStatus::values(), ['ALL', 'all']);
        $deliveryValues = array_merge(DeliveryStatus::values(), ['ALL', 'all']);
        $adjustmentValues = array_merge(AdjustmentStatus::values(), ['ALL', 'all']);
        $sortByValues = ['submitted_at', 'order_number', 'customer_name', 'grand_total', 'status'];

        return [
            'queue' => ['nullable', 'string', Rule::in($queueValues)],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in($statusValues)],
            'fulfillment_status' => ['nullable', 'string', Rule::in($fulfillmentValues)],
            'payment_status' => ['nullable', 'string', Rule::in($paymentValues)],
            'delivery_status' => ['nullable', 'string', Rule::in($deliveryValues)],
            'adjustment_status' => ['nullable', 'string', Rule::in($adjustmentValues)],
            'salesman_id' => ['nullable'],
            'customer_id' => ['nullable'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort_by' => ['nullable', 'string', Rule::in($sortByValues)],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc', 'ASC', 'DESC'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
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
            'queue.in' => 'Selected queue view is invalid.',
            'search.max' => 'Search term cannot exceed 100 characters.',
            'status.in' => 'Selected order status filter is invalid.',
            'fulfillment_status.in' => 'Selected fulfillment status filter is invalid.',
            'payment_status.in' => 'Selected payment status filter is invalid.',
            'delivery_status.in' => 'Selected delivery status filter is invalid.',
            'adjustment_status.in' => 'Selected adjustment status filter is invalid.',
            'date_from.date_format' => 'Start date must be in YYYY-MM-DD format.',
            'date_to.date_format' => 'End date must be in YYYY-MM-DD format.',
            'date_to.after_or_equal' => 'End date must be on or after the start date.',
            'sort_by.in' => 'Selected sort column is invalid.',
            'sort_direction.in' => 'Sort direction must be either asc or desc.',
            'per_page.min' => 'Page size must be at least 1.',
            'per_page.max' => 'Page size cannot exceed 100.',
            'page.min' => 'Page number must be a positive integer.',
        ];
    }
}
