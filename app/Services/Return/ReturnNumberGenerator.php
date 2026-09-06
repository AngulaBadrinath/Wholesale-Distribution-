<?php

namespace App\Services\Return;

use App\Models\ReturnRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReturnNumberGenerator
{
    /**
     * Generate the next sequential, canonical return number.
     * Format: RET-{YEAR}-{00000X} (e.g. RET-2026-000001)
     */
    public function generate(?int $year = null): string
    {
        $year ??= (int) Carbon::now()->format('Y');

        if (DB::connection()->getDriverName() === 'pgsql') {
            $seqVal = DB::selectOne("SELECT nextval('return_number_seq') AS val")->val;

            return sprintf('RET-%d-%06d', $year, $seqVal);
        }

        // Fallback for SQLite in test environments
        $maxId = (int) ReturnRequest::max('id');
        $nextNum = $maxId + 1;

        return sprintf('RET-%d-%06d', $year, $nextNum);
    }
}
