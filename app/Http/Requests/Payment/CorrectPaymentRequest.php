<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class CorrectPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999999.99'],
            'payment_date' => ['nullable', 'date', 'date_format:Y-m-d', 'before_or_equal:today'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'cheque_number' => ['nullable', 'string', 'max:50'],
            'cheque_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'issuer_name' => ['nullable', 'string', 'max:100'],
            'money_order_number' => ['nullable', 'string', 'max:50'],
            'receipt_reference' => ['nullable', 'string', 'max:100'],
            'evidence' => ['nullable', 'file'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
