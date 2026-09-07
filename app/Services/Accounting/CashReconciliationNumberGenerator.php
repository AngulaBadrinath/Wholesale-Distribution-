<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use Illuminate\Support\Facades\DB;

class CashReconciliationNumberGenerator
{
    /**
     * Generate the next sequential reconciliation number.
     * Format: REC-{YYYY}-{00000X}
     */
    public function generate(): string
    {
        $year = date('Y');

        if (DB::connection()->getDriverName() === 'pgsql') {
            $seq = DB::selectOne("SELECT nextval('cash_reconciliation_number_seq') AS val");
            $nextVal = (int) $seq->val;
        } else {
            $maxId = (int) (DB::table('cash_reconciliations')->max('id') ?? 0);
            $nextVal = $maxId + 1;
        }

        return sprintf('REC-%s-%06d', $year, $nextVal);
    }
}
