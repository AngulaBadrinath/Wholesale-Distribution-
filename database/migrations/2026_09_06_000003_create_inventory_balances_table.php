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

        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('bin_location', 50)->nullable();
            $table->integer('reorder_point')->default(0);
            $table->integer('safety_stock')->default(0);
            $table->integer('on_hand_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('available_quantity')->default(0);
            $table->integer('damaged_quantity')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestampTz('last_counted_at')->nullable();
            $table->timestampsTz();

            $table->unique(['warehouse_id', 'product_id'], 'uq_inventory_warehouse_product');
            $table->index('product_id', 'idx_inventory_product_lookup');
            $table->index('warehouse_id', 'idx_inventory_warehouse_lookup');
            $table->index(['warehouse_id', 'available_quantity'], 'idx_inventory_available');
            $table->index(['warehouse_id', 'on_hand_quantity', 'reorder_point'], 'idx_inventory_reorder');
        });

        if ($isPgsql) {
            DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT chk_inventory_balances_quantities CHECK (
                on_hand_quantity >= 0 AND
                reserved_quantity >= 0 AND
                available_quantity >= 0 AND
                damaged_quantity >= 0 AND
                reorder_point >= 0 AND
                safety_stock >= 0
            )');
            DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT chk_inventory_balances_bounds CHECK (
                reserved_quantity + damaged_quantity <= on_hand_quantity
            )');
            DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT chk_inventory_balances_math CHECK (
                available_quantity = (on_hand_quantity - reserved_quantity - damaged_quantity)
            )');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_balances');
    }
};
