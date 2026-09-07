<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use Illuminate\Support\Facades\DB;

class JournalNumberGenerator
{
    /**
     * Generate the next sequential journal entry number.
     * Format: JE-{YYYY}-{00000X}
     */
    public function generate(): string
    {
        $year = date('Y');

        if (DB::connection()->getDriverName() === 'pgsql') {
            $seq = DB::selectOne("SELECT nextval('journal_number_seq') AS val");
            $nextVal = (int) $seq->val;
        } else {
            $maxId = (int) (DB::table('journal_entries')->max('id') ?? 0);
            $nextVal = $maxId + 1;
        }

        return sprintf('JE-%s-%06d', $year, $nextVal);
    }
}
