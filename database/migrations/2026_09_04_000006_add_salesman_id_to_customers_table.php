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
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('salesman_id')
                ->nullable()
                ->after('notes')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('salesman_id', 'idx_customers_salesman_id');
            $table->index(['salesman_id', 'status'], 'idx_customers_salesman_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['salesman_id']);
            $table->dropIndex('idx_customers_salesman_status');
            $table->dropIndex('idx_customers_salesman_id');
            $table->dropColumn('salesman_id');
        });
    }
};
