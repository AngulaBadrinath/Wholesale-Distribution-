<?php

namespace App\Http\Requests\Inventory;

use App\Enums\StockStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminInventoryIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authoritative domain authorization is enforced in PermissionService
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $statusValues = array_merge(StockStatus::values(), ['ALL', 'all', 'in_stock', 'low_stock', 'out_of_stock']);
        $sortByValues = [
            'id',
            'on_hand_quantity',
            'available_quantity',
            'reserved_quantity',
            'damaged_quantity',
            'reorder_point',
            'safety_stock',
            'bin_location',
            'last_counted_at',
            'created_at',
        ];

        return [
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'stock_status' => ['nullable', 'string', Rule::in($statusValues)],
            'search' => ['nullable', 'string', 'max:100'],
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
            'warehouse_id.exists' => 'The selected warehouse is invalid.',
            'stock_status.in' => 'Selected stock status filter is invalid.',
            'search.max' => 'Search term cannot exceed 100 characters.',
            'sort_by.in' => 'Selected sort column is invalid.',
            'sort_direction.in' => 'Sort direction must be either asc or desc.',
            'per_page.min' => 'Page size must be at least 1.',
            'per_page.max' => 'Page size cannot exceed 100.',
            'page.min' => 'Page number must be a positive integer.',
        ];
    }
}
