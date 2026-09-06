<?php

declare(strict_types=1);

namespace App\Services\Receivable;

use Illuminate\Support\Facades\DB;

class ReceivableTransactionNumberGenerator
{
    /**
     * Generate the next sequential receivable transaction number.
     * Format: AR-{YYYY}-{00000X}
     */
    public function generate(): string
    {
        $year = date('Y');

        if (DB::connection()->getDriverName() === 'pgsql') {
            $seq = DB::selectOne("SELECT nextval('receivable_transaction_number_seq') AS val");
            $nextVal = (int) $seq->val;
        } else {
            $maxId = (int) (DB::table('receivable_transactions')->max('id') ?? 0);
            $nextVal = $maxId + 1;
        }

        return sprintf('AR-%s-%06d', $year, $nextVal);
    }
}
