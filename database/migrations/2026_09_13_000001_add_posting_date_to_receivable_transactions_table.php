<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('receivable_transactions', 'posting_date')) {
            Schema::table('receivable_transactions', function (Blueprint $table) {
                $table->date('posting_date')->nullable()->after('transaction_date');
            });

            DB::statement('UPDATE receivable_transactions SET posting_date = transaction_date WHERE posting_date IS NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('receivable_transactions', 'posting_date')) {
            Schema::table('receivable_transactions', function (Blueprint $table) {
                $table->dropColumn('posting_date');
            });
        }
    }
};
