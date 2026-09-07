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

        // 1. Create PostgreSQL sequences for sequential number generation
        if ($isPgsql) {
            DB::statement('CREATE SEQUENCE IF NOT EXISTS supplier_code_seq START WITH 1 INCREMENT BY 1 NO CYCLE');
            DB::statement('CREATE SEQUENCE IF NOT EXISTS supplier_bill_number_seq START WITH 1 INCREMENT BY 1 NO CYCLE');
            DB::statement('CREATE SEQUENCE IF NOT EXISTS supplier_payment_number_seq START WITH 1 INCREMENT BY 1 NO CYCLE');
            DB::statement('CREATE SEQUENCE IF NOT EXISTS payable_transaction_number_seq START WITH 1 INCREMENT BY 1 NO CYCLE');
        }

        // 2. Create suppliers table
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code', 50)->unique();
            $table->string('name', 255);
            $table->string('contact_person', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->unsignedInteger('payment_terms_days')->default(30);
            $table->string('tax_id', 50)->nullable();
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'name']);
        });

        // 3. Create supplier_bills table
        Schema::create('supplier_bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number', 50)->unique();
            $table->string('supplier_invoice_number', 100)->nullable();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->date('bill_date');
            $table->date('due_date');
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('tax_total', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->decimal('amount_paid', 15, 2)->default(0.00);
            $table->decimal('amount_due', 15, 2)->default(0.00);
            $table->string('status', 30)->default('DRAFT')->index();
            $table->string('description', 500)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index(['supplier_id', 'due_date']);
        });

        // 4. Create supplier_payments table
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 50)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('supplier_bill_id')->nullable()->constrained('supplier_bills')->restrictOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 30)->default('BANK_TRANSFER');
            $table->string('reference_number', 100)->nullable();
            $table->string('status', 30)->default('COMPLETED')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reversal_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index(['supplier_bill_id', 'status']);
        });

        // 5. Create payable_transactions table
        Schema::create('payable_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 50)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('supplier_bill_id')->nullable()->constrained('supplier_bills')->restrictOnDelete();
            $table->foreignId('supplier_payment_id')->nullable()->constrained('supplier_payments')->restrictOnDelete();

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
            $table->index(['supplier_id', 'transaction_date']);
            $table->index(['supplier_id', 'due_date']);
            $table->index(['supplier_id', 'type']);
            $table->index(['source_type', 'source_id']);

            // Idempotency: Prevent duplicate posting of the exact same source event and type
            $table->unique(['source_type', 'source_id', 'type'], 'uq_ap_source_type_id_type');
        });

        // 6. PostgreSQL Immutability Triggers (Append-Only Financial AP Ledger)
        if ($isPgsql) {
            DB::statement('
                CREATE OR REPLACE FUNCTION protect_payable_transactions()
                RETURNS TRIGGER AS $$
                BEGIN
                    IF TG_OP = \'DELETE\' THEN
                        RAISE EXCEPTION \'Payable transactions are permanent immutable financial ledger records and cannot be deleted.\';
                    END IF;

                    IF TG_OP = \'UPDATE\' THEN
                        -- Allow updating ONLY running_balance or updated_at if running balance is recalculated
                        IF NEW.transaction_number <> OLD.transaction_number
                            OR NEW.supplier_id <> OLD.supplier_id
                            OR NEW.source_type <> OLD.source_type
                            OR NEW.source_id <> OLD.source_id
                            OR NEW.source_number <> OLD.source_number
                            OR NEW.type <> OLD.type
                            OR NEW.amount <> OLD.amount
                            OR NEW.debit_amount <> OLD.debit_amount
                            OR NEW.credit_amount <> OLD.credit_amount
                            OR NEW.transaction_date <> OLD.transaction_date
                            OR NEW.currency <> OLD.currency THEN
                            RAISE EXCEPTION \'Payable transactions are immutable financial records. Core financial attributes cannot be altered.\';
                        END IF;
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            ');

            DB::statement('
                CREATE TRIGGER trg_protect_payable_transactions
                BEFORE UPDATE OR DELETE ON payable_transactions
                FOR EACH ROW
                EXECUTE FUNCTION protect_payable_transactions();
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
            DB::statement('DROP TRIGGER IF EXISTS trg_protect_payable_transactions ON payable_transactions');
            DB::statement('DROP FUNCTION IF EXISTS protect_payable_transactions()');
        }

        Schema::dropIfExists('payable_transactions');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_bills');
        Schema::dropIfExists('suppliers');

        if ($isPgsql) {
            DB::statement('DROP SEQUENCE IF EXISTS payable_transaction_number_seq');
            DB::statement('DROP SEQUENCE IF EXISTS supplier_payment_number_seq');
            DB::statement('DROP SEQUENCE IF EXISTS supplier_bill_number_seq');
            DB::statement('DROP SEQUENCE IF EXISTS supplier_code_seq');
        }
    }
};
