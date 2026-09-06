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

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->string('payment_method', 30)->index();
            $table->string('status', 30)->default('PENDING_VERIFICATION')->index();
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');

            // Method-specific detail fields
            $table->string('cheque_number', 50)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('money_order_number', 50)->nullable();
            $table->string('issuer_name', 100)->nullable();
            $table->string('receipt_reference', 100)->nullable();

            // Secure private evidence metadata
            $table->string('evidence_object_key', 255)->nullable();
            $table->string('evidence_original_name', 255)->nullable();
            $table->string('evidence_mime_type', 50)->nullable();
            $table->unsignedBigInteger('evidence_size_bytes')->nullable();
            $table->timestampTz('evidence_uploaded_at')->nullable();

            // Workflow, notes and actor metadata
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('rejection_reason_code', 50)->nullable();
            $table->text('rejection_notes')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reversal_reason_code', 50)->nullable();
            $table->text('reversal_notes')->nullable();
            $table->timestampTz('reversed_at')->nullable();

            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();

            $table->index(['customer_id', 'payment_date'], 'idx_payments_customer_date');
            $table->index(['order_id', 'status'], 'idx_payments_order_status');
            $table->index(['status', 'payment_method'], 'idx_payments_status_method');
            $table->index(['recorded_by', 'created_at'], 'idx_payments_recorded_date');
            $table->index(['customer_id', 'cheque_number', 'bank_name'], 'idx_payments_cheque_dedup');
            $table->index(['customer_id', 'money_order_number', 'issuer_name'], 'idx_payments_mo_dedup');
        });

        if ($isPgsql) {
            DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payments_amount_positive CHECK (amount > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
