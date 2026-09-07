<?php

declare(strict_types=1);

namespace App\Services\Payable;

use Illuminate\Support\Facades\DB;

class SupplierPaymentNumberGenerator
{
    /**
     * Generate the next sequential supplier payment number.
     * Format: SP-{YYYY}-{00000X}
     */
    public function generate(): string
    {
        $year = date('Y');

        if (DB::connection()->getDriverName() === 'pgsql') {
            $seq = DB::selectOne("SELECT nextval('supplier_payment_number_seq') AS val");
            $nextVal = (int) $seq->val;
        } else {
            $maxId = (int) (DB::table('supplier_payments')->max('id') ?? 0);
            $nextVal = $maxId + 1;
        }

        return sprintf('SP-%s-%06d', $year, $nextVal);
    }
}
