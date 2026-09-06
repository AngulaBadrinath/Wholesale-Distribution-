<?php

declare(strict_types=1);

namespace App\Services\Credit;

use App\Models\CreditNote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreditNoteNumberGenerator
{
    /**
     * Generate the next sequential, canonical credit note number.
     * Format: CR-{YEAR}-{00000X} (e.g. CR-2026-000001)
     */
    public function generate(?int $year = null): string
    {
        $year ??= (int) Carbon::now()->format('Y');

        if (DB::connection()->getDriverName() === 'pgsql') {
            $seqVal = DB::selectOne("SELECT nextval('credit_note_number_seq') AS val")->val;

            return sprintf('CR-%d-%06d', $year, $seqVal);
        }

        // Fallback for SQLite in test environments
        $maxId = (int) CreditNote::max('id');
        $nextNum = $maxId + 1;

        return sprintf('CR-%d-%06d', $year, $nextNum);
    }
}
