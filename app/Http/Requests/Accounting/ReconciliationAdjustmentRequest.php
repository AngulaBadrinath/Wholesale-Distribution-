<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class ReconciliationAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && app(\App\Services\Auth\PermissionService::class)->has($this->user(), Permission::ACCOUNTING_POST);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
