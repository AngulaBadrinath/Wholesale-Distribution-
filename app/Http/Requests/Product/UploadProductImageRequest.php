<?php

namespace App\Http\Requests\Product;

use App\Enums\AccountStatus;
use App\Enums\Permission;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Http\FormRequest;

class UploadProductImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(PermissionService $permissionService): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $isActive = ($user->status instanceof AccountStatus)
            ? $user->status === AccountStatus::ACTIVE
            : $user->status === AccountStatus::ACTIVE->value;

        if (! $isActive) {
            return false;
        }

        return $permissionService->has($user, Permission::PRODUCT_UPDATE);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'max:5120', // Max 5MB (5120 KB)
                'mimes:jpeg,jpg,png,webp',
            ],
            'is_primary' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'An image file is required for upload.',
            'image.file' => 'The uploaded asset must be a valid file.',
            'image.max' => 'The image file size must not exceed 5MB.',
            'image.mimes' => 'The image must be a file of type: JPEG, PNG, or WebP.',
        ];
    }
}
