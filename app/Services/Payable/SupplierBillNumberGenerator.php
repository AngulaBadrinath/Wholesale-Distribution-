<?php

declare(strict_types=1);

namespace App\Services\Payable;

use Illuminate\Support\Facades\DB;

class SupplierBillNumberGenerator
{
    /**
     * Generate the next sequential supplier bill number.
     * Format: BILL-{YYYY}-{00000X}
     */
    public function generate(): string
    {
        $year = date('Y');

        if (DB::connection()->getDriverName() === 'pgsql') {
            $seq = DB::selectOne("SELECT nextval('supplier_bill_number_seq') AS val");
            $nextVal = (int) $seq->val;
        } else {
            $maxId = (int) (DB::table('supplier_bills')->max('id') ?? 0);
            $nextVal = $maxId + 1;
        }

        return sprintf('BILL-%s-%06d', $year, $nextVal);
    }
}
