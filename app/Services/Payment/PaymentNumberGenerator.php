<?php

namespace App\Services\Payment;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentNumberGenerator
{
    /**
     * Generate the next sequential payment number using database sequence.
     */
    public function generate(?int $year = null): string
    {
        $year ??= Carbon::now()->year;
        $connection = DB::connection();
        $isPgsql = $connection->getDriverName() === 'pgsql';

        if ($isPgsql) {
            $result = DB::selectOne("SELECT nextval('payment_number_seq') AS val");
            $sequence = (int) $result->val;
        } else {
            // Fallback for non-PostgreSQL (e.g. SQLite in unit test runner)
            $lastId = (int) DB::table('payments')->max('id');
            $sequence = $lastId + 1;
        }

        return $this->format($sequence, $year);
    }

    /**
     * Format a sequence number and year into the canonical payment number string.
     */
    public function format(int $sequence, ?int $year = null): string
    {
        $year ??= Carbon::now()->year;
        $padded = str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);

        return "PAY-{$year}-{$padded}";
    }
}
