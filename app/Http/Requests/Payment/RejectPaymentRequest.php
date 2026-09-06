<?php

namespace App\Http\Requests\Payment;

use App\Enums\PaymentRejectionReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RejectPaymentRequest extends FormRequest
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
            'rejection_reason_code' => ['required', new Enum(PaymentRejectionReason::class)],
            'rejection_notes' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
