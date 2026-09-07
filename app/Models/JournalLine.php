<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JournalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class JournalLine extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'journal_lines';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'journal_entry_id',
        'line_number',
        'account_id',
        'debit',
        'credit',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'line_number' => 'integer',
        'journal_entry_id' => 'integer',
        'account_id' => 'integer',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::updating(function (JournalLine $line) {
            $parent = $line->journalEntry;
            if ($parent && in_array($parent->status, [JournalStatus::POSTED, JournalStatus::REVERSED], true)) {
                throw new LogicException('Journal lines for posted journal entries are immutable and cannot be updated.');
            }
        });

        static::deleting(function (JournalLine $line) {
            $parent = $line->journalEntry;
            if ($parent && in_array($parent->status, [JournalStatus::POSTED, JournalStatus::REVERSED], true)) {
                throw new LogicException('Journal lines for posted journal entries are permanent and cannot be deleted.');
            }
        });
    }

    /**
     * Parent journal entry.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * Referenced account in the Chart of Accounts.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
