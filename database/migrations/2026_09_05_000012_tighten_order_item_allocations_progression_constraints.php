<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        if ($isPgsql) {
            // Drop older relaxed upper-bound progression constraints
            DB::statement('ALTER TABLE order_item_allocations DROP CONSTRAINT IF EXISTS order_item_allocations_dispatched_quantity_check');
            DB::statement('ALTER TABLE order_item_allocations DROP CONSTRAINT IF EXISTS order_item_allocations_delivered_quantity_check');

            // Add strict unidirectional progression constraints:
            // dispatched <= picked and delivered <= dispatched
            DB::statement('ALTER TABLE order_item_allocations ADD CONSTRAINT order_item_allocations_dispatched_quantity_check CHECK (dispatched_quantity >= 0 AND dispatched_quantity <= picked_quantity)');
            DB::statement('ALTER TABLE order_item_allocations ADD CONSTRAINT order_item_allocations_delivered_quantity_check CHECK (delivered_quantity >= 0 AND delivered_quantity <= dispatched_quantity)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('ALTER TABLE order_item_allocations DROP CONSTRAINT IF EXISTS order_item_allocations_dispatched_quantity_check');
            DB::statement('ALTER TABLE order_item_allocations DROP CONSTRAINT IF EXISTS order_item_allocations_delivered_quantity_check');

            // Restore baseline progression constraints (<= allocated_quantity)
            DB::statement('ALTER TABLE order_item_allocations ADD CONSTRAINT order_item_allocations_dispatched_quantity_check CHECK (dispatched_quantity >= 0 AND dispatched_quantity <= allocated_quantity)');
            DB::statement('ALTER TABLE order_item_allocations ADD CONSTRAINT order_item_allocations_delivered_quantity_check CHECK (delivered_quantity >= 0 AND delivered_quantity <= allocated_quantity)');
        }
    }
};
