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
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number', 50)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->string('adjustment_type', 40)->index();
            $table->string('reason_code', 50)->index();
            $table->integer('quantity');
            $table->integer('on_hand_before');
            $table->integer('on_hand_after');
            $table->integer('reserved_before');
            $table->integer('reserved_after');
            $table->integer('available_before');
            $table->integer('available_after');
            $table->integer('damaged_before');
            $table->integer('damaged_after');
            $table->text('notes');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['warehouse_id', 'created_at'], 'idx_inv_adj_warehouse_date');
            $table->index(['product_id', 'created_at'], 'idx_inv_adj_product_date');
            $table->index(['inventory_balance_id', 'created_at'], 'idx_inv_adj_balance_date');
            $table->index('reason_code', 'idx_inv_adj_reason');
        });

        if ($isPgsql) {
            DB::statement('ALTER TABLE inventory_adjustments ADD CONSTRAINT chk_inv_adjustments_quantity CHECK (quantity > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
