<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReconciliationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashReconciliation extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cash_reconciliations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'reconciliation_number',
        'account_id',
        'statement_date',
        'starting_balance',
        'ending_balance',
        'ledger_balance',
        'difference',
        'status',
        'notes',
        'reconciled_by',
        'reconciled_at',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => ReconciliationStatus::class,
        'statement_date' => 'date',
        'starting_balance' => 'decimal:2',
        'ending_balance' => 'decimal:2',
        'ledger_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'reconciled_at' => 'datetime',
        'account_id' => 'integer',
        'reconciled_by' => 'integer',
        'created_by' => 'integer',
    ];

    /**
     * Associated cash/bank account.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Reconciliation items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CashReconciliationItem::class, 'cash_reconciliation_id');
    }

    /**
     * User who marked the reconciliation as reconciled.
     */
    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    /**
     * User who created the reconciliation session.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
