<?php

declare(strict_types=1);

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

        // 1. Create PostgreSQL sequence for sequential Reconciliation numbering
        if ($isPgsql) {
            DB::statement('CREATE SEQUENCE IF NOT EXISTS cash_reconciliation_number_seq START WITH 1 INCREMENT BY 1 NO CYCLE');
        }

        // 2. Create cash_reconciliations table
        Schema::create('cash_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->string('reconciliation_number', 50)->unique();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->date('statement_date')->index();
            $table->decimal('starting_balance', 15, 2)->default(0.00);
            $table->decimal('ending_balance', 15, 2)->default(0.00);
            $table->decimal('ledger_balance', 15, 2)->default(0.00);
            $table->decimal('difference', 15, 2)->default(0.00);
            $table->string('status', 30)->default('IN_PROGRESS')->index(); // IN_PROGRESS, RECONCILED, DISCREPANCY
            $table->text('notes')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'statement_date']);
            $table->index(['account_id', 'status']);
        });

        // 3. Create cash_reconciliation_items table
        Schema::create('cash_reconciliation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_reconciliation_id')->constrained('cash_reconciliations')->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('supplier_payment_id')->nullable()->constrained('supplier_payments')->nullOnDelete();
            $table->date('item_date');
            $table->decimal('amount', 15, 2);
            $table->string('type', 30); // RECEIPT, DISBURSEMENT, ADJUSTMENT
            $table->boolean('is_cleared')->default(false)->index();
            $table->timestamp('cleared_at')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['cash_reconciliation_id', 'is_cleared']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        Schema::dropIfExists('cash_reconciliation_items');
        Schema::dropIfExists('cash_reconciliations');

        if ($isPgsql) {
            DB::statement('DROP SEQUENCE IF EXISTS cash_reconciliation_number_seq');
        }
    }
};
