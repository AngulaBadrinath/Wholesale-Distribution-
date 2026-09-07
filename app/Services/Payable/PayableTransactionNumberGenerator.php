<?php

declare(strict_types=1);

namespace App\Services\Payable;

use Illuminate\Support\Facades\DB;

class PayableTransactionNumberGenerator
{
    /**
     * Generate the next sequential payable transaction number.
     * Format: AP-{YYYY}-{00000X}
     */
    public function generate(): string
    {
        $year = date('Y');

        if (DB::connection()->getDriverName() === 'pgsql') {
            $seq = DB::selectOne("SELECT nextval('payable_transaction_number_seq') AS val");
            $nextVal = (int) $seq->val;
        } else {
            $maxId = (int) (DB::table('payable_transactions')->max('id') ?? 0);
            $nextVal = $maxId + 1;
        }

        return sprintf('AP-%s-%06d', $year, $nextVal);
    }
}
