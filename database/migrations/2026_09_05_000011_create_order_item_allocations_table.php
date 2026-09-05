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

        Schema::create('order_item_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('allocation_number', 50)->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            // Quantity tracking per allocation unit
            $table->integer('allocated_quantity');
            $table->integer('reserved_quantity')->default(0);
            $table->integer('picked_quantity')->default(0);
            $table->integer('dispatched_quantity')->default(0);
            $table->integer('delivered_quantity')->default(0);
            $table->integer('returned_quantity')->default(0);

            // Lifecycle & Warehouse metadata
            $table->string('status', 30)->default('ALLOCATED');
            $table->string('warehouse_code', 30)->default('MAIN');
            $table->text('notes')->nullable();

            // Attribution & Timestamps
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('allocated_at')->useCurrent();
            $table->timestamps();

            // Indexes for operational queries
            $table->index(['order_id', 'status']);
            $table->index(['order_item_id', 'status']);
            $table->index(['product_id', 'status']);
            $table->index(['warehouse_code', 'status']);
        });

        if ($isPgsql) {
            DB::statement('ALTER TABLE order_item_allocations ADD CONSTRAINT order_item_allocations_allocated_quantity_check CHECK (allocated_quantity > 0)');
            DB::statement('ALTER TABLE order_item_allocations ADD CONSTRAINT order_item_allocations_reserved_quantity_check CHECK (reserved_quantity >= 0 AND reserved_quantity <= allocated_quantity)');
            DB::statement('ALTER TABLE order_item_allocations ADD CONSTRAINT order_item_allocations_picked_quantity_check CHECK (picked_quantity >= 0 AND picked_quantity <= allocated_quantity)');
            DB::statement('ALTER TABLE order_item_allocations ADD CONSTRAINT order_item_allocations_dispatched_quantity_check CHECK (dispatched_quantity >= 0 AND dispatched_quantity <= allocated_quantity)');
            DB::statement('ALTER TABLE order_item_allocations ADD CONSTRAINT order_item_allocations_delivered_quantity_check CHECK (delivered_quantity >= 0 AND delivered_quantity <= allocated_quantity)');
            DB::statement('ALTER TABLE order_item_allocations ADD CONSTRAINT order_item_allocations_returned_quantity_check CHECK (returned_quantity >= 0 AND returned_quantity <= delivered_quantity)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item_allocations');
    }
};
