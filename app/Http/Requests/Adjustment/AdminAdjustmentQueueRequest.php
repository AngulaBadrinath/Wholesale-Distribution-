<?php

namespace App\Http\Requests\Adjustment;

use App\Enums\AdjustmentReasonCode;
use App\Enums\OrderAdjustmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminAdjustmentQueueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorized via controller / route permission middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'queue' => ['nullable', 'string', Rule::in([
                'attention',
                'pending',
                'ready_to_apply',
                'applied',
                'reversed',
                'closed',
                'all',
            ])],
            'exception_type' => ['nullable', 'string', Rule::in([
                'ALL',
                'CONFLICTED',
                'PICKED_ENCROACHMENT',
                'STALE',
                'INELIGIBLE_LIFECYCLE',
                'AGING',
            ])],
            'status' => ['nullable', 'string', Rule::in([
                'SUBMITTED',
                'APPROVED',
                'APPLIED',
                'REVERSED',
                'REJECTED',
                'CANCELLED',
                'ALL',
            ])],
            'impact_case' => ['nullable', 'string', Rule::in(['ALL', 'CASE_A', 'CASE_B'])],
            'reason_code' => ['nullable', 'string', Rule::in(array_column(AdjustmentReasonCode::cases(), 'value'))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'string', Rule::in([
                'requested_at',
                'adjustment_number',
                'projected_grand_total_reduction',
                'age',
            ])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50, 100])],
        ];
    }
}
