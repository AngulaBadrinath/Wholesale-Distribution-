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

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->string('movement_number', 50)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->string('movement_type', 40)->index();
            $table->string('from_state', 30);
            $table->string('to_state', 30);
            $table->integer('quantity');
            $table->integer('on_hand_before');
            $table->integer('on_hand_after');
            $table->integer('reserved_before');
            $table->integer('reserved_after');
            $table->integer('available_before');
            $table->integer('available_after');
            $table->integer('damaged_before');
            $table->integer('damaged_after');
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 60)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index(['inventory_balance_id', 'created_at'], 'idx_inv_movements_balance_date');
            $table->index(['product_id', 'created_at'], 'idx_inv_movements_product_date');
            $table->index(['warehouse_id', 'movement_type', 'created_at'], 'idx_inv_movements_type_date');
            $table->index(['reference_type', 'reference_id'], 'idx_inv_movements_reference');
            $table->index('reference_number', 'idx_inv_movements_ref_number');
        });

        if ($isPgsql) {
            DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT chk_inv_movements_quantity CHECK (quantity > 0)');
            DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT chk_inv_movements_snapshots CHECK (
                on_hand_before >= 0 AND on_hand_after >= 0 AND
                reserved_before >= 0 AND reserved_after >= 0 AND
                available_before >= 0 AND available_after >= 0 AND
                damaged_before >= 0 AND damaged_after >= 0
            )');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
