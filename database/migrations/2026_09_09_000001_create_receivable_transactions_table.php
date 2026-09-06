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

        // 1. Create PostgreSQL sequence for sequential AR transaction number generation
        if ($isPgsql) {
            DB::statement('CREATE SEQUENCE IF NOT EXISTS receivable_transaction_number_seq START WITH 1 INCREMENT BY 1 NO CYCLE');
        }

        // 2. Create receivable_transactions table
        Schema::create('receivable_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->foreignId('credit_note_id')->nullable()->constrained('credit_notes')->restrictOnDelete();

            $table->string('source_type', 50);
            $table->unsignedBigInteger('source_id');
            $table->string('source_number', 50);
            $table->string('type', 30)->index();

            $table->decimal('amount', 15, 2)->default(0.00);
            $table->decimal('debit_amount', 15, 2)->default(0.00);
            $table->decimal('credit_amount', 15, 2)->default(0.00);
            $table->decimal('running_balance', 15, 2)->nullable();

            $table->date('transaction_date');
            $table->date('posting_date')->default(DB::raw('CURRENT_DATE'));
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('description', 500);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();

            // High performance query indices
            $table->index(['customer_id', 'transaction_date']);
            $table->index(['customer_id', 'due_date']);
            $table->index(['customer_id', 'type']);
            $table->index(['source_type', 'source_id']);

            // Idempotency: Prevent duplicate posting of the exact same source event and type
            $table->unique(['source_type', 'source_id', 'type'], 'uq_ar_source_type_id_type');
        });

        // 3. PostgreSQL Immutability Triggers (Append-Only Financial Ledger)
        if ($isPgsql) {
            DB::statement('
                CREATE OR REPLACE FUNCTION protect_receivable_transactions()
                RETURNS TRIGGER AS $$
                BEGIN
                    IF TG_OP = \'DELETE\' THEN
                        RAISE EXCEPTION \'Receivable transactions are permanent immutable financial ledger records and cannot be deleted.\';
                    END IF;

                    IF TG_OP = \'UPDATE\' THEN
                        -- Allow updating ONLY running_balance or updated_at if running balance is recalculated
                        IF NEW.transaction_number <> OLD.transaction_number
                            OR NEW.customer_id <> OLD.customer_id
                            OR NEW.source_type <> OLD.source_type
                            OR NEW.source_id <> OLD.source_id
                            OR NEW.source_number <> OLD.source_number
                            OR NEW.type <> OLD.type
                            OR NEW.amount <> OLD.amount
                            OR NEW.debit_amount <> OLD.debit_amount
                            OR NEW.credit_amount <> OLD.credit_amount
                            OR NEW.transaction_date <> OLD.transaction_date
                            OR NEW.currency <> OLD.currency THEN
                            RAISE EXCEPTION \'Receivable transactions are immutable financial records. Core financial attributes cannot be altered.\';
                        END IF;
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            ');

            DB::statement('
                CREATE TRIGGER trg_protect_receivable_transactions
                BEFORE UPDATE OR DELETE ON receivable_transactions
                FOR EACH ROW
                EXECUTE FUNCTION protect_receivable_transactions();
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::statement('DROP TRIGGER IF EXISTS trg_protect_receivable_transactions ON receivable_transactions');
            DB::statement('DROP FUNCTION IF EXISTS protect_receivable_transactions()');
        }

        Schema::dropIfExists('receivable_transactions');

        if ($isPgsql) {
            DB::statement('DROP SEQUENCE IF EXISTS receivable_transaction_number_seq');
        }
    }
};
