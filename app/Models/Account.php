<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\BalanceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Account extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'accounts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_code',
        'name',
        'type',
        'category',
        'normal_balance',
        'parent_id',
        'is_active',
        'is_system',
        'is_reconcilable',
        'description',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'type' => AccountType::class,
        'category' => AccountCategory::class,
        'normal_balance' => BalanceType::class,
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'is_reconcilable' => 'boolean',
        'parent_id' => 'integer',
        'created_by' => 'integer',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::updating(function (Account $account) {
            if ($account->is_system && $account->isDirty('account_code')) {
                throw new LogicException('System account codes cannot be modified.');
            }
        });

        static::deleting(function (Account $account) {
            if ($account->is_system) {
                throw new LogicException('System accounts are permanent and cannot be deleted.');
            }

            if ($account->journalLines()->exists()) {
                throw new LogicException('Accounts with posted journal transactions cannot be deleted. Archive the account by setting is_active to false instead.');
            }
        });
    }

    /**
     * Parent account relationship.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    /**
     * Sub-accounts relationship.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id')->orderBy('account_code');
    }

    /**
     * Journal lines referencing this account.
     */
    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'account_id');
    }

    /**
     * Cash reconciliations for this account.
     */
    public function reconciliations(): HasMany
    {
        return $this->hasMany(CashReconciliation::class, 'account_id');
    }

    /**
     * Creator user relationship.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope query to active accounts only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query to reconcilable cash/bank accounts.
     */
    public function scopeReconcilable(Builder $query): Builder
    {
        return $query->where('is_reconcilable', true);
    }
}
