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

        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignId('order_item_allocation_id')->constrained('order_item_allocations')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            // Historical immutable snapshots (RULE-DOM-001)
            $table->string('product_name_snapshot', 255);
            $table->string('sku_snapshot', 100);

            // Quantities
            $table->unsignedInteger('deliverable_quantity');
            $table->unsignedInteger('delivered_quantity')->default(0);
            $table->unsignedInteger('returned_quantity')->default(0);

            $table->timestampsTz();

            // Operational indexes
            $table->index('delivery_id', 'idx_delivery_items_delivery_id');
            $table->index('order_item_id', 'idx_delivery_items_order_item_id');
            $table->index('order_item_allocation_id', 'idx_delivery_items_allocation_id');
        });

        if ($isPgsql) {
            DB::statement('ALTER TABLE delivery_items ADD CONSTRAINT chk_delivery_items_quantities CHECK (
                deliverable_quantity > 0 AND
                delivered_quantity <= deliverable_quantity AND
                returned_quantity <= delivered_quantity
            )');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_items');
    }
};
