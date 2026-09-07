<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class CreateManualJournalRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:500'],
            'accounting_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
