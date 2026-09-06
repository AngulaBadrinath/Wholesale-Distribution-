<?php

namespace App\Http\Requests\Delivery;

use App\Enums\DeliveryFailureReason;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Delivery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RecordDeliveryFailureRequest extends FormRequest
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
            'failure_reason' => ['required', new Enum(DeliveryFailureReason::class)],
            'driver_notes' => ['required', 'string', 'min:5', 'max:1000'],
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
            'failure_reason.required' => 'Please select an authoritative delivery failure reason.',
            'failure_reason.Illuminate\Validation\Rules\Enum' => 'Invalid delivery failure reason selected.',
            'driver_notes.required' => 'Detailed driver explanation notes are mandatory when reporting a failure.',
            'driver_notes.min' => 'Driver notes must be at least 5 characters.',
            'driver_notes.max' => 'Driver notes must not exceed 1000 characters.',
        ];
    }
}
