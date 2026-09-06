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

        if ($isPgsql) {
            DB::statement('CREATE SEQUENCE IF NOT EXISTS delivery_number_seq START WITH 1 INCREMENT BY 1');
        }

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number', 50)->unique()->index();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('PENDING_ASSIGNMENT')->index();

            // Address snapshot at assignment/dispatch time (RULE-DOM-001)
            $table->string('delivery_contact_name', 255)->nullable();
            $table->string('delivery_contact_phone', 50)->nullable();
            $table->string('delivery_address_line1', 255);
            $table->string('delivery_address_line2', 255)->nullable();
            $table->string('delivery_city', 100);
            $table->string('delivery_state', 100);
            $table->string('delivery_postal_code', 20);
            $table->string('delivery_country_code', 3)->default('USA');

            // Operational scheduling & driver notes
            $table->date('scheduled_date')->index();
            $table->string('delivery_window', 50)->nullable();
            $table->text('driver_instructions')->nullable();

            // Lifecycle milestone timestamps
            $table->timestampTz('assigned_at')->nullable();
            $table->timestampTz('picked_up_at')->nullable();
            $table->timestampTz('out_for_delivery_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('returned_at')->nullable();

            // Proof of Delivery (POD) fields
            $table->string('recipient_name', 255)->nullable();
            $table->string('recipient_signature_path', 255)->nullable();
            $table->string('pod_evidence_path', 255)->nullable();
            $table->text('pod_notes')->nullable();

            // Concurrency & Attribution
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            // Composite indexes for fast operational queries
            $table->index(['driver_id', 'status'], 'idx_deliveries_driver_status');
            $table->index(['scheduled_date', 'status'], 'idx_deliveries_scheduled_date');
            $table->index(['customer_id', 'status'], 'idx_deliveries_customer_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP SEQUENCE IF EXISTS delivery_number_seq');
        }
    }
};
