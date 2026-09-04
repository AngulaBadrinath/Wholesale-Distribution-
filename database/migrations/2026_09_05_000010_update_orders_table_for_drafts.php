<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        // 1. Add draft_token as nullable first to allow safe backfilling of existing rows
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('draft_token')->nullable()->after('idempotency_key');
            $table->unsignedInteger('version')->default(1)->after('draft_token');
        });

        // 2. Backfill existing orders with unique UUIDs for draft_token
        $existingOrders = DB::table('orders')->whereNull('draft_token')->get(['id']);
        foreach ($existingOrders as $existing) {
            DB::table('orders')->where('id', $existing->id)->update([
                'draft_token' => (string) Str::uuid(),
                'version' => 1,
            ]);
        }

        // 3. Make draft_token NOT NULL and UNIQUE, make order_number nullable for DRAFT status
        if ($isPgsql) {
            DB::statement('ALTER TABLE orders ALTER COLUMN draft_token SET NOT NULL');
            DB::statement('ALTER TABLE orders ALTER COLUMN order_number DROP NOT NULL');
        } else {
            Schema::table('orders', function (Blueprint $table) {
                $table->uuid('draft_token')->nullable(false)->change();
                $table->string('order_number', 50)->nullable()->change();
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->unique('draft_token');
            $table->index(['salesman_id', 'status', 'updated_at'], 'idx_orders_salesman_status_updated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_salesman_status_updated');
            $table->dropUnique(['draft_token']);
            $table->dropColumn(['draft_token', 'version']);
        });

        if ($isPgsql) {
            DB::statement('ALTER TABLE orders ALTER COLUMN order_number SET NOT NULL');
        } else {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('order_number', 50)->nullable(false)->change();
            });
        }
    }
};
