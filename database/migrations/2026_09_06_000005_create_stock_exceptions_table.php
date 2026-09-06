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

        Schema::create('stock_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('exception_number', 50)->unique();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_item_allocation_id')->nullable()->constrained('order_item_allocations')->nullOnDelete();
            $table->string('exception_type', 40)->index();
            $table->string('severity', 20)->default('MEDIUM')->index();
            $table->string('source_stock_state', 30)->default('AVAILABLE');
            $table->integer('quantity');
            $table->string('status', 30)->default('PENDING_REVIEW')->index();
            $table->text('description');
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->index(['warehouse_id', 'status'], 'idx_stock_exceptions_warehouse_status');
            $table->index(['product_id', 'status'], 'idx_stock_exceptions_product_status');
            $table->index(['inventory_balance_id', 'status'], 'idx_stock_exceptions_balance_status');
            $table->index('created_at', 'idx_stock_exceptions_created_at');
        });

        if ($isPgsql) {
            DB::statement('ALTER TABLE stock_exceptions ADD CONSTRAINT chk_stock_exceptions_quantity CHECK (quantity > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_exceptions');
    }
};
