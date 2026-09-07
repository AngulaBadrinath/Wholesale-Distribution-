<?php

declare(strict_types=1);

namespace App\Services\Payable;

use Illuminate\Support\Facades\DB;

class SupplierCodeGenerator
{
    /**
     * Generate the next sequential supplier code.
     * Format: SUP-{00000X}
     */
    public function generate(): string
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $seq = DB::selectOne("SELECT nextval('supplier_code_seq') AS val");
            $nextVal = (int) $seq->val;
        } else {
            $maxId = (int) (DB::table('suppliers')->max('id') ?? 0);
            $nextVal = $maxId + 1;
        }

        return sprintf('SUP-%06d', $nextVal);
    }
}
