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

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            // Product Master Snapshots
            $table->string('product_name_snapshot', 255);
            $table->string('sku_snapshot', 50);
            $table->string('unit_snapshot', 30);

            // Quantities
            $table->integer('ordered_quantity');
            $table->integer('cancelled_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('picked_quantity')->default(0);
            $table->integer('dispatched_quantity')->default(0);
            $table->integer('delivered_quantity')->default(0);
            $table->integer('returned_quantity')->default(0);

            // Pricing & Overrides
            $table->decimal('unit_price', 12, 2);
            $table->boolean('is_price_overridden')->default(false);
            $table->text('price_override_reason')->nullable();
            $table->foreignId('price_override_approved_by')->nullable()->constrained('users')->nullOnDelete();

            // Tax Snapshots
            $table->foreignId('tax_profile_id')->nullable()->constrained('tax_profiles')->restrictOnDelete();
            $table->string('tax_profile_code_snapshot', 50)->nullable();
            $table->string('tax_profile_name_snapshot', 100)->nullable();
            $table->decimal('tax_rate_snapshot', 7, 4)->default(0.0000);
            $table->decimal('taxable_amount', 12, 2)->default(0.00);
            $table->decimal('tax_amount', 12, 2)->default(0.00);
            $table->decimal('line_total', 12, 2)->default(0.00);

            $table->timestamps();
        });

        if ($isPgsql) {
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_ordered_quantity_check CHECK (ordered_quantity > 0)');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_cancelled_quantity_check CHECK (cancelled_quantity >= 0 AND cancelled_quantity <= ordered_quantity)');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_reserved_quantity_check CHECK (reserved_quantity >= 0)');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_picked_quantity_check CHECK (picked_quantity >= 0)');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_dispatched_quantity_check CHECK (dispatched_quantity >= 0)');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_delivered_quantity_check CHECK (delivered_quantity >= 0)');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_returned_quantity_check CHECK (returned_quantity >= 0)');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_unit_price_check CHECK (unit_price > 0)');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_tax_rate_check CHECK (tax_rate_snapshot >= 0)');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_taxable_amount_check CHECK (taxable_amount >= 0)');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_tax_amount_check CHECK (tax_amount >= 0)');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_line_total_check CHECK (line_total >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
