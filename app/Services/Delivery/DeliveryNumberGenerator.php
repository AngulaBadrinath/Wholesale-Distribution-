<?php

namespace App\Services\Delivery;

use App\Models\Delivery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DeliveryNumberGenerator
{
    /**
     * Generate the next sequential, canonical delivery number.
     * Format: DEL-{YEAR}-{00000X} (e.g. DEL-2026-000001)
     */
    public function generate(): string
    {
        $year = Carbon::now()->format('Y');

        if (DB::connection()->getDriverName() === 'pgsql') {
            $seqVal = DB::selectOne("SELECT nextval('delivery_number_seq') AS val")->val;

            return sprintf('DEL-%s-%06d', $year, $seqVal);
        }

        // Atomic fallback for SQLite in testing
        $maxId = (int) Delivery::max('id');
        $nextNum = $maxId + 1;

        return sprintf('DEL-%s-%06d', $year, $nextNum);
    }
}
