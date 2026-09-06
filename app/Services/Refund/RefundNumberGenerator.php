<?php

declare(strict_types=1);

namespace App\Services\Refund;

use App\Models\RefundRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RefundNumberGenerator
{
    /**
     * Generate the next sequential, canonical refund request number.
     * Format: REF-{YEAR}-{00000X} (e.g. REF-2026-000001)
     */
    public function generate(?int $year = null): string
    {
        $year ??= (int) Carbon::now()->format('Y');

        if (DB::connection()->getDriverName() === 'pgsql') {
            $seqVal = DB::selectOne("SELECT nextval('refund_number_seq') AS val")->val;

            return sprintf('REF-%d-%06d', $year, $seqVal);
        }

        // Fallback for SQLite in test environments
        $maxId = (int) RefundRequest::max('id');
        $nextNum = $maxId + 1;

        return sprintf('REF-%d-%06d', $year, $nextNum);
    }
}
