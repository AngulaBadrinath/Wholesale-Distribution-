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
        // 1. Create PostgreSQL sequence for sequential Return numbers
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE SEQUENCE IF NOT EXISTS return_number_seq START WITH 1 INCREMENT BY 1 NO CYCLE');
        }

        // 2. Create return_requests header table
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->string('return_number', 50)->unique();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('salesman_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('status', 30)->default('REQUESTED')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->text('inspection_notes')->nullable();
            $table->json('evidence_photos')->nullable();
            $table->decimal('estimated_refund_subtotal', 15, 2)->default(0.00);
            $table->decimal('estimated_refund_tax', 15, 2)->default(0.00);
            $table->decimal('estimated_refund_total', 15, 2)->default(0.00);
            $table->boolean('is_credit_processed')->default(false)->index();
            $table->unsignedBigInteger('credit_note_id')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['order_id', 'status']);
            $table->index('created_at');
        });

        // 3. Create return_request_items table
        Schema::create('return_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained('return_requests')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('requested_quantity')->default(0);
            $table->integer('received_quantity')->default(0);
            $table->integer('accepted_good_quantity')->default(0);
            $table->integer('accepted_damaged_quantity')->default(0);
            $table->integer('rejected_quantity')->default(0);
            $table->decimal('unit_price_snapshot', 15, 2);
            $table->decimal('tax_rate_snapshot', 6, 4)->default(0.0000);
            $table->string('tax_profile_code_snapshot', 50)->nullable();
            $table->string('tax_profile_name_snapshot', 150)->nullable();
            $table->decimal('tax_amount_snapshot', 15, 2)->default(0.00);
            $table->decimal('line_total', 15, 2)->default(0.00);
            $table->string('reason_code', 50)->default('DEFECTIVE');
            $table->text('item_notes')->nullable();
            $table->timestamps();

            $table->index(['return_request_id', 'product_id']);
            $table->index(['order_item_id']);
        });

        // 4. Create return_request_events table
        Schema::create('return_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained('return_requests')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('event_type', 50);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['return_request_id', 'created_at']);
        });

        // 5. Add PostgreSQL / Database check constraints
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE return_request_items ADD CONSTRAINT chk_return_items_requested_positive CHECK (requested_quantity > 0)');
            DB::statement('ALTER TABLE return_request_items ADD CONSTRAINT chk_return_items_received_non_negative CHECK (received_quantity >= 0)');
            DB::statement('ALTER TABLE return_request_items ADD CONSTRAINT chk_return_items_accepted_good_non_negative CHECK (accepted_good_quantity >= 0)');
            DB::statement('ALTER TABLE return_request_items ADD CONSTRAINT chk_return_items_accepted_damaged_non_negative CHECK (accepted_damaged_quantity >= 0)');
            DB::statement('ALTER TABLE return_request_items ADD CONSTRAINT chk_return_items_rejected_non_negative CHECK (rejected_quantity >= 0)');
            DB::statement('ALTER TABLE return_request_items ADD CONSTRAINT chk_return_items_disposition_sum CHECK (accepted_good_quantity + accepted_damaged_quantity + rejected_quantity <= received_quantity)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_request_events');
        Schema::dropIfExists('return_request_items');
        Schema::dropIfExists('return_requests');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP SEQUENCE IF EXISTS return_number_seq');
        }
    }
};
