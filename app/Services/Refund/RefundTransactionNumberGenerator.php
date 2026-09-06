<?php

declare(strict_types=1);

namespace App\Services\Refund;

use App\Models\RefundTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RefundTransactionNumberGenerator
{
    /**
     * Generate the next sequential, canonical refund transaction number.
     * Format: TXN-REF-{YEAR}-{00000X} (e.g. TXN-REF-2026-000001)
     */
    public function generate(?int $year = null): string
    {
        $year ??= (int) Carbon::now()->format('Y');

        if (DB::connection()->getDriverName() === 'pgsql') {
            $seqVal = DB::selectOne("SELECT nextval('refund_txn_number_seq') AS val")->val;

            return sprintf('TXN-REF-%d-%06d', $year, $seqVal);
        }

        // Fallback for SQLite in test environments
        $maxId = (int) RefundTransaction::max('id');
        $nextNum = $maxId + 1;

        return sprintf('TXN-REF-%d-%06d', $year, $nextNum);
    }
}
