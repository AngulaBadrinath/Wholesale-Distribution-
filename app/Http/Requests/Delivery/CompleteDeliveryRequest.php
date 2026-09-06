<?php

namespace App\Http\Requests\Delivery;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Delivery;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CompleteDeliveryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user || ! $user->isActive()) {
            return false;
        }

        /** @var Delivery|null $delivery */
        $delivery = $this->route('delivery');
        if ($delivery && $user->role === UserRole::DELIVERY_PARTNER && $delivery->driver_id !== $user->id) {
            throw new NotFoundHttpException('Delivery mission not found.');
        }

        return $user->canPermission(Permission::DELIVERY_UPDATE);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:255'],
            'pod_notes' => ['nullable', 'string', 'max:1000'],
            'pod_evidence' => ['nullable', 'file', 'max:5120'], // Max 5MB
            'recipient_signature' => ['nullable', 'file', 'max:5120'],
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
            'recipient_name.required' => 'Recipient name is strictly required to complete delivery.',
            'recipient_name.max' => 'Recipient name must not exceed 255 characters.',
            'pod_evidence.max' => 'Proof of delivery evidence must not exceed 5MB.',
        ];
    }
}
