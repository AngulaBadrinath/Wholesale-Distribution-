<?php

namespace App\Http\Requests\Delivery;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Delivery;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReturnToWarehouseRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
