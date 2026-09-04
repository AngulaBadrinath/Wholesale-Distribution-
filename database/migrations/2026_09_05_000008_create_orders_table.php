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
            DB::statement('CREATE SEQUENCE IF NOT EXISTS order_number_seq START WITH 1 INCREMENT BY 1');
        }

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique()->index();
            $table->string('idempotency_key', 64)->unique()->index();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('salesman_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('status', 30)->default('SUBMITTED')->index();
            $table->string('fulfillment_status', 30)->default('UNALLOCATED')->index();
            $table->string('payment_status', 30)->default('UNPAID')->index();
            $table->string('delivery_status', 30)->default('PENDING_ASSIGNMENT')->index();
            $table->string('adjustment_status', 30)->default('NONE')->index();

            $table->string('currency', 3)->default('USD');
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('tax_total', 12, 2)->default(0.00);
            $table->decimal('adjustment_total', 12, 2)->default(0.00);
            $table->decimal('grand_total', 12, 2)->default(0.00);

            $table->text('notes')->nullable();

            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();
        });

        if ($isPgsql) {
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_subtotal_check CHECK (subtotal >= 0)');
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_tax_total_check CHECK (tax_total >= 0)');
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_grand_total_check CHECK (grand_total >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP SEQUENCE IF EXISTS order_number_seq');
        }
    }
};
