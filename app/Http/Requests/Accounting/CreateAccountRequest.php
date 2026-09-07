<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\BalanceType;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAccountRequest extends FormRequest
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
            'account_code' => ['required', 'string', 'max:32', 'unique:accounts,account_code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'category' => ['required', Rule::enum(AccountCategory::class)],
            'normal_balance' => ['nullable', Rule::enum(BalanceType::class)],
            'parent_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'is_active' => ['sometimes', 'boolean'],
            'is_reconcilable' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
