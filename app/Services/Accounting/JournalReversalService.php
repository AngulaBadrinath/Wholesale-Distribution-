<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\JournalEntryType;
use App\Enums\JournalStatus;
use App\Models\JournalEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalReversalService
{
    public function __construct(
        protected JournalService $journalService
    ) {}

    /**
     * Authoritatively reverse a posted journal entry with an offsetting compensating journal entry.
     *
     * @throws ValidationException
     */
    public function reverseJournal(JournalEntry|int $journalInput, string $reason, ?User $actor = null): JournalEntry
    {
        $journalId = $journalInput instanceof JournalEntry ? $journalInput->id : (int) $journalInput;

        $cleanReason = trim($reason);
        if (empty($cleanReason)) {
            throw ValidationException::withMessages([
                'reason' => 'A valid reversal reason is required.',
            ]);
        }

        return DB::transaction(function () use ($journalId, $cleanReason, $actor) {
            /** @var JournalEntry|null $originalJournal */
            $originalJournal = JournalEntry::query()
                ->where('id', $journalId)
                ->with(['lines.account'])
                ->lockForUpdate()
                ->first();

            if (! $originalJournal) {
                throw ValidationException::withMessages([
                    'journal' => 'The specified journal entry does not exist.',
                ]);
            }

            // Invariant Check 1: Journal must be in POSTED status
            if ($originalJournal->status === JournalStatus::REVERSED || $originalJournal->reversal_journal_id !== null) {
                throw ValidationException::withMessages([
                    'journal' => "Journal #{$originalJournal->journal_number} has already been reversed and cannot be reversed again.",
                ]);
            }

            if ($originalJournal->status !== JournalStatus::POSTED) {
                throw ValidationException::withMessages([
                    'journal' => "Only posted journal entries can be reversed. Current status: {$originalJournal->status->value}.",
                ]);
            }

            // Invariant Check 2: Cannot reverse a reversal journal
            if ($originalJournal->entry_type === JournalEntryType::REVERSAL) {
                throw ValidationException::withMessages([
                    'journal' => 'Reversal journal entries cannot be directly reversed. Post a new correcting manual journal instead.',
                ]);
            }

            // 1. Build Offsetting Reversal Lines (All Debits become Credits, All Credits become Debits)
            $reversalLines = [];
            foreach ($originalJournal->lines as $line) {
                $reversalLines[] = [
                    'account_id' => $line->account_id,
                    'debit' => $line->credit, // Invert
                    'credit' => $line->debit, // Invert
                    'description' => "Reversal of #{$originalJournal->journal_number} line: " . ($line->description ?: $originalJournal->description),
                ];
            }

            // 2. Post Offsetting Reversal Journal
            $reversalHeader = [
                'entry_type' => JournalEntryType::REVERSAL,
                'source_type' => $originalJournal->source_type,
                'source_id' => $originalJournal->source_id,
                'source_number' => $originalJournal->source_number,
                'source_event' => $originalJournal->source_event ? "REVERSAL_{$originalJournal->source_event}" : null,
                'posting_date' => Carbon::now()->toDateString(),
                'accounting_date' => Carbon::now()->toDateString(),
                'description' => "Reversal of Journal #{$originalJournal->journal_number}: {$cleanReason}",
                'notes' => "Reversal reason: {$cleanReason}",
                'reversed_journal_id' => $originalJournal->id,
                'reversal_reason' => $cleanReason,
            ];

            $reversalJournal = $this->journalService->createAndPostJournal($reversalHeader, $reversalLines, $actor);

            // 3. Mark Original Journal as REVERSED and link reversal_journal_id
            $originalJournal->status = JournalStatus::REVERSED;
            $originalJournal->reversal_journal_id = $reversalJournal->id;
            $originalJournal->reversal_reason = $cleanReason;
            $originalJournal->save();

            return $reversalJournal->fresh(['lines.account', 'reversedJournal', 'creator']);
        });
    }
}
