<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\SupplierStatus;
use Illuminate\Foundation\Http\FormRequest;

class CreateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payable.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'supplier_code' => ['nullable', 'string', 'max:50', 'unique:suppliers,supplier_code'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:' . implode(',', SupplierStatus::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
