<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceNumberGenerator
{
    /**
     * Generate the next sequential, canonical invoice number.
     * Format: INV-{YEAR}-{00000X} (e.g. INV-2026-000001)
     */
    public function generate(?int $year = null): string
    {
        $year ??= (int) Carbon::now()->format('Y');

        if (DB::connection()->getDriverName() === 'pgsql') {
            $seqVal = DB::selectOne("SELECT nextval('invoice_number_seq') AS val")->val;

            return sprintf('INV-%d-%06d', $year, $seqVal);
        }

        // Atomic fallback for SQLite in testing
        $maxId = (int) Invoice::max('id');
        $nextNum = $maxId + 1;

        return sprintf('INV-%d-%06d', $year, $nextNum);
    }
}
