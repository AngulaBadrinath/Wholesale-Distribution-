<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_adjustments', function (Blueprint $table) {
            $table->foreignId('reversed_by')
                ->nullable()
                ->after('reversed_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->text('reversal_reason')
                ->nullable()
                ->after('reversed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_adjustments', function (Blueprint $table) {
            $table->dropForeign(['reversed_by']);
            $table->dropColumn(['reversed_by', 'reversal_reason']);
        });
    }
};
