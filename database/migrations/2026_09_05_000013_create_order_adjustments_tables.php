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

        // 1. Create order_adjustments table (Parent Aggregate)
        Schema::create('order_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number', 50)->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();

            // Historical Order Snapshots
            $table->string('order_number_snapshot', 50);
            $table->unsignedInteger('order_version_snapshot')->default(1);
            $table->string('order_status_snapshot', 30);
            $table->decimal('order_subtotal_snapshot', 12, 2);
            $table->decimal('order_tax_total_snapshot', 12, 2);
            $table->decimal('order_grand_total_snapshot', 12, 2);

            // Adjustment Type & Lifecycle
            $table->string('type', 30)->default('QUANTITY_REDUCTION');
            $table->string('status', 30)->default('SUBMITTED')->index();
            $table->string('reason_code', 50);
            $table->text('notes');

            // Audit Attribution & Timestamps
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('requested_at')->useCurrent();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestampTz('applied_at')->nullable();
            $table->timestampTz('reversed_at')->nullable();

            // Projected Financial Reductions (Informational / Auditable snapshots)
            $table->decimal('projected_subtotal_reduction', 12, 2)->default(0.00);
            $table->decimal('projected_tax_reduction', 12, 2)->default(0.00);
            $table->decimal('projected_grand_total_reduction', 12, 2)->default(0.00);

            // Idempotency & Concurrency
            $table->string('idempotency_key', 64)->unique();
            $table->string('request_fingerprint', 64)->index();

            $table->timestamps();

            // Operational Indexes
            $table->index(['order_id', 'status'], 'idx_order_adjustments_order_status');
            $table->index(['requested_by', 'status'], 'idx_order_adjustments_requester_status');
        });

        // 2. Create order_adjustment_items table (Child Line Items)
        Schema::create('order_adjustment_items', function (Blueprint $table) {
            $table->id();
            // Invariant: Non-destructive history; restrict deletion of parent adjustment when items exist
            $table->foreignId('adjustment_id')->constrained('order_adjustments')->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            // Product Master Snapshots
            $table->string('product_name_snapshot', 255);
            $table->string('sku_snapshot', 50);
            $table->decimal('unit_price_snapshot', 12, 2);
            $table->decimal('tax_rate_snapshot', 7, 4)->default(0.0000);
            $table->string('tax_profile_code_snapshot', 50)->nullable();

            // Baseline Quantity Snapshots at Request Time
            $table->integer('ordered_quantity_snapshot');
            $table->integer('cancelled_quantity_snapshot');
            $table->integer('fulfillable_quantity_snapshot');
            $table->integer('allocated_quantity_snapshot');
            $table->integer('unallocated_quantity_snapshot');

            // Requested & Projected Quantities
            $table->integer('requested_quantity_reduction');
            $table->integer('projected_fulfillable_quantity');
            $table->integer('projected_cancelled_quantity');
            $table->integer('affected_allocation_quantity')->default(0);

            // Projected Line-Level Financial Reductions
            $table->decimal('projected_taxable_amount_reduction', 12, 2)->default(0.00);
            $table->decimal('projected_tax_amount_reduction', 12, 2)->default(0.00);
            $table->decimal('projected_line_total_reduction', 12, 2)->default(0.00);

            $table->timestamps();

            // Indexes
            $table->index('adjustment_id', 'idx_order_adj_items_adj_id');
            $table->index('order_item_id', 'idx_order_adj_items_item_id');
            $table->index('product_id', 'idx_order_adj_items_product_id');
        });

        // 3. PostgreSQL Authoritative Constraints & Partial Unique Index
        if ($isPgsql) {
            // Partial unique index enforcing single open adjustment request per order
            DB::statement("CREATE UNIQUE INDEX idx_order_adjustments_single_open ON order_adjustments (order_id) WHERE status = 'SUBMITTED'");

            // order_adjustments check constraints
            DB::statement('ALTER TABLE order_adjustments ADD CONSTRAINT order_adjustments_projected_subtotal_reduction_check CHECK (projected_subtotal_reduction >= 0)');
            DB::statement('ALTER TABLE order_adjustments ADD CONSTRAINT order_adjustments_projected_tax_reduction_check CHECK (projected_tax_reduction >= 0)');
            DB::statement('ALTER TABLE order_adjustments ADD CONSTRAINT order_adjustments_projected_grand_total_reduction_check CHECK (projected_grand_total_reduction >= 0)');

            // order_adjustment_items check constraints
            DB::statement('ALTER TABLE order_adjustment_items ADD CONSTRAINT order_adj_items_requested_qty_check CHECK (requested_quantity_reduction > 0)');
            DB::statement('ALTER TABLE order_adjustment_items ADD CONSTRAINT order_adj_items_projected_fulfillable_check CHECK (projected_fulfillable_quantity >= 0)');
            DB::statement('ALTER TABLE order_adjustment_items ADD CONSTRAINT order_adj_items_projected_cancelled_check CHECK (projected_cancelled_quantity >= 0)');
            DB::statement('ALTER TABLE order_adjustment_items ADD CONSTRAINT order_adj_items_affected_allocation_check CHECK (affected_allocation_quantity >= 0)');
            DB::statement('ALTER TABLE order_adjustment_items ADD CONSTRAINT order_adj_items_taxable_reduction_check CHECK (projected_taxable_amount_reduction >= 0)');
            DB::statement('ALTER TABLE order_adjustment_items ADD CONSTRAINT order_adj_items_tax_reduction_check CHECK (projected_tax_amount_reduction >= 0)');
            DB::statement('ALTER TABLE order_adjustment_items ADD CONSTRAINT order_adj_items_line_total_reduction_check CHECK (projected_line_total_reduction >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('DROP INDEX IF EXISTS idx_order_adjustments_single_open');
        }

        Schema::dropIfExists('order_adjustment_items');
        Schema::dropIfExists('order_adjustments');
    }
};
