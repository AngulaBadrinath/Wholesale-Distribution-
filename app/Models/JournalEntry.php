<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JournalEntryType;
use App\Enums\JournalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class JournalEntry extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'journal_entries';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'journal_number',
        'entry_type',
        'source_type',
        'source_id',
        'source_number',
        'source_event',
        'status',
        'posting_date',
        'accounting_date',
        'total_debit',
        'total_credit',
        'description',
        'notes',
        'reversal_journal_id',
        'reversed_journal_id',
        'reversal_reason',
        'created_by',
        'posted_by',
        'posted_at',
        'idempotency_key',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'entry_type' => JournalEntryType::class,
        'status' => JournalStatus::class,
        'posting_date' => 'date',
        'accounting_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'posted_at' => 'datetime',
        'reversal_journal_id' => 'integer',
        'reversed_journal_id' => 'integer',
        'created_by' => 'integer',
        'posted_by' => 'integer',
    ];

    /**
     * The "booted" method of the model.
     * Enforces dual-layer immutability on posted financial journal records.
     */
    protected static function booted(): void
    {
        static::updating(function (JournalEntry $journal) {
            $originalStatus = $journal->getOriginal('status');
            $originalStatusValue = $originalStatus instanceof JournalStatus ? $originalStatus->value : $originalStatus;

            if (in_array($originalStatusValue, [JournalStatus::POSTED->value, JournalStatus::REVERSED->value], true)) {
                $dirty = array_keys($journal->getDirty());
                $allowedOperationalFields = [
                    'status',
                    'reversal_journal_id',
                    'reversal_reason',
                    'notes',
                    'updated_at',
                ];

                $disallowed = array_diff($dirty, $allowedOperationalFields);

                if (! empty($disallowed)) {
                    throw new LogicException(sprintf(
                        'Posted journal entries are immutable financial records. Cannot modify fields: [%s].',
                        implode(', ', $disallowed)
                    ));
                }

                if ($originalStatusValue === JournalStatus::REVERSED->value && $journal->isDirty('status')) {
                    throw new LogicException('Reversed journal entries cannot be reverted to unreversed status.');
                }
            }
        });

        static::deleting(function (JournalEntry $journal) {
            $status = $journal->status instanceof JournalStatus ? $journal->status->value : $journal->status;
            if (in_array($status, [JournalStatus::POSTED->value, JournalStatus::REVERSED->value], true)) {
                throw new LogicException('Posted journal entries are permanent financial records and cannot be deleted.');
            }
        });
    }

    /**
     * Line items comprising this journal entry.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'journal_entry_id')->orderBy('line_number');
    }

    /**
     * Reversal journal created to offset this journal (if reversed).
     */
    public function reversalJournal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_journal_id');
    }

    /**
     * Original journal that this reversal entry offsets (if this is a reversal).
     */
    public function reversedJournal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_journal_id');
    }

    /**
     * User who created the journal entry.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who posted the journal entry.
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * Scope query to posted journals only.
     */
    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', JournalStatus::POSTED);
    }
}
