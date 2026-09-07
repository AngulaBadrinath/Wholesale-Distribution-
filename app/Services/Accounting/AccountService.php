<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\BalanceType;
use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class AccountService
{
    /**
     * Create a new account in the Chart of Accounts.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createAccount(array $data, ?User $actor = null): Account
    {
        $code = trim((string) ($data['account_code'] ?? ''));
        if (empty($code)) {
            throw ValidationException::withMessages([
                'account_code' => 'Account code is required.',
            ]);
        }

        if (Account::where('account_code', $code)->exists()) {
            throw ValidationException::withMessages([
                'account_code' => "Account code '{$code}' already exists.",
            ]);
        }

        $rawType = $data['type'] ?? null;
        $type = $rawType instanceof AccountType
            ? $rawType
            : AccountType::tryFrom((string) $rawType);

        if (! $type) {
            throw ValidationException::withMessages([
                'type' => 'A valid account type (ASSET, LIABILITY, EQUITY, REVENUE, EXPENSE) is required.',
            ]);
        }

        $rawCategory = $data['category'] ?? null;
        $category = $rawCategory instanceof AccountCategory
            ? $rawCategory
            : AccountCategory::tryFrom((string) $rawCategory);

        if (! $category) {
            throw ValidationException::withMessages([
                'category' => 'A valid account category is required.',
            ]);
        }

        $rawNormal = $data['normal_balance'] ?? null;
        $normalBalance = $rawNormal instanceof BalanceType
            ? $rawNormal
            : (BalanceType::tryFrom((string) $rawNormal) ?? $type->normalBalance());

        $parentId = ! empty($data['parent_id']) ? (int) $data['parent_id'] : null;
        if ($parentId) {
            $parent = Account::find($parentId);
            if (! $parent) {
                throw ValidationException::withMessages([
                    'parent_id' => 'The selected parent account does not exist.',
                ]);
            }

            if ($parent->type !== $type) {
                throw ValidationException::withMessages([
                    'parent_id' => "Parent account type ({$parent->type->value}) must match child account type ({$type->value}).",
                ]);
            }
        }

        return Account::create([
            'account_code' => $code,
            'name' => trim((string) ($data['name'] ?? '')),
            'type' => $type,
            'category' => $category,
            'normal_balance' => $normalBalance,
            'parent_id' => $parentId,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_system' => false,
            'is_reconcilable' => (bool) ($data['is_reconcilable'] ?? false),
            'description' => ! empty($data['description']) ? trim((string) $data['description']) : null,
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * Update an existing account.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateAccount(Account $account, array $data, ?User $actor = null): Account
    {
        if ($account->is_system && isset($data['account_code']) && $data['account_code'] !== $account->account_code) {
            throw ValidationException::withMessages([
                'account_code' => 'System account codes cannot be modified.',
            ]);
        }

        if (isset($data['account_code']) && $data['account_code'] !== $account->account_code) {
            $code = trim((string) $data['account_code']);
            if (Account::where('account_code', $code)->where('id', '<>', $account->id)->exists()) {
                throw ValidationException::withMessages([
                    'account_code' => "Account code '{$code}' is already in use by another account.",
                ]);
            }
            $account->account_code = $code;
        }

        if (isset($data['name'])) {
            $account->name = trim((string) $data['name']);
        }

        if (isset($data['parent_id'])) {
            $parentId = ! empty($data['parent_id']) ? (int) $data['parent_id'] : null;

            if ($parentId === $account->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'An account cannot be its own parent.',
                ]);
            }

            if ($parentId) {
                // Prevent circular hierarchy
                if ($this->isDescendant($parentId, $account->id)) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Cannot set a sub-account as parent (circular hierarchy detected).',
                    ]);
                }

                $parent = Account::find($parentId);
                if (! $parent) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'The selected parent account does not exist.',
                    ]);
                }

                if ($parent->type !== $account->type) {
                    throw ValidationException::withMessages([
                        'parent_id' => "Parent account type ({$parent->type->value}) must match account type ({$account->type->value}).",
                    ]);
                }
            }

            $account->parent_id = $parentId;
        }

        if (isset($data['is_active'])) {
            $account->is_active = (bool) $data['is_active'];
        }

        if (isset($data['is_reconcilable'])) {
            $account->is_reconcilable = (bool) $data['is_reconcilable'];
        }

        if (isset($data['description'])) {
            $account->description = ! empty($data['description']) ? trim((string) $data['description']) : null;
        }

        $account->save();

        return $account->fresh(['parent', 'children']);
    }

    /**
     * Delete an account from the Chart of Accounts.
     *
     * @throws ValidationException
     */
    public function deleteAccount(Account $account): bool
    {
        if ($account->is_system) {
            throw ValidationException::withMessages([
                'account' => 'System accounts are permanent and cannot be deleted.',
            ]);
        }

        if ($account->journalLines()->exists()) {
            throw ValidationException::withMessages([
                'account' => 'Accounts with posted journal transactions cannot be deleted. Deactivate the account instead.',
            ]);
        }

        if ($account->children()->exists()) {
            throw ValidationException::withMessages([
                'account' => 'Cannot delete an account that has sub-accounts. Reassign or delete sub-accounts first.',
            ]);
        }

        return (bool) $account->delete();
    }

    /**
     * Resolve account by account code.
     *
     * @throws LogicException
     */
    public function resolveAccount(string $accountCode): Account
    {
        /** @var Account|null $account */
        $account = Account::where('account_code', $accountCode)->first();

        if (! $account) {
            throw new LogicException("Required Chart of Accounts code '{$accountCode}' is not configured in the system.");
        }

        return $account;
    }

    /**
     * Retrieve the hierarchical Chart of Accounts tree.
     *
     * @return Collection<int, Account>
     */
    public function getHierarchy(): Collection
    {
        return Account::whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->orderBy('account_code')])
            ->orderBy('account_code')
            ->get();
    }

    /**
     * Check whether a candidate parent is a descendant of the target account.
     */
    protected function isDescendant(int $candidateId, int $targetId): bool
    {
        $current = Account::find($candidateId);
        while ($current && $current->parent_id) {
            if ($current->parent_id === $targetId) {
                return true;
            }
            $current = Account::find($current->parent_id);
        }

        return false;
    }
}
