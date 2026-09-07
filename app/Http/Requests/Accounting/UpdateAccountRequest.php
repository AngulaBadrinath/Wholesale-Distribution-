<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
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
        $accountId = $this->route('account') instanceof \App\Models\Account ? $this->route('account')->id : $this->route('account');

        return [
            'account_code' => ['sometimes', 'string', 'max:32', "unique:accounts,account_code,{$accountId}"],
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['sometimes', 'boolean'],
            'is_reconcilable' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
