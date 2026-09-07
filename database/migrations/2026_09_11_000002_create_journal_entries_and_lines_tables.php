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

        // 1. Create PostgreSQL sequence for sequential Journal Entry numbering
        if ($isPgsql) {
            DB::statement('CREATE SEQUENCE IF NOT EXISTS journal_number_seq START WITH 1 INCREMENT BY 1 NO CYCLE');
        }

        // 2. Create journal_entries table
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('journal_number', 50)->unique();
            $table->string('entry_type', 30)->default('SYSTEM')->index(); // SYSTEM, MANUAL, REVERSAL, CLOSING
            $table->string('source_type', 50)->nullable()->index(); // invoice, payment, credit_note, refund, supplier_bill, supplier_payment, delivery, inventory_adjustment
            $table->unsignedBigInteger('source_id')->nullable()->index();
            $table->string('source_number', 50)->nullable();
            $table->string('source_event', 50)->nullable()->index(); // INVOICE_ISSUED, PAYMENT_VERIFIED, PAYMENT_REVERSED, CREDIT_NOTE_ISSUED, REFUND_COMPLETED, BILL_POSTED, BILL_PAYMENT, BILL_PAYMENT_REVERSED, ORDER_DELIVERED_COGS, STOCK_ADJUSTMENT
            $table->string('status', 30)->default('POSTED')->index(); // DRAFT, POSTED, REVERSED
            $table->date('posting_date')->default(DB::raw('CURRENT_DATE'))->index();
            $table->date('accounting_date')->default(DB::raw('CURRENT_DATE'))->index();
            $table->decimal('total_debit', 15, 2)->default(0.00);
            $table->decimal('total_credit', 15, 2)->default(0.00);
            $table->string('description', 500);
            $table->text('notes')->nullable();
            $table->foreignId('reversal_journal_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('reversed_journal_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('reversal_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamps();

            // High performance query indices
            $table->index(['posting_date', 'status']);
            $table->index(['accounting_date', 'status']);
            $table->index(['source_type', 'source_id']);
            $table->index(['source_type', 'source_id', 'source_event']);
        });

        // 3. Create journal_lines table
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->unsignedInteger('line_number')->default(1);
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('debit', 15, 2)->default(0.00);
            $table->decimal('credit', 15, 2)->default(0.00);
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
            $table->index(['journal_entry_id', 'line_number']);
        });

        // 4. PostgreSQL Constraints & Triggers
        if ($isPgsql) {
            // Unique source event mapping constraint (prevents duplicate journal creation for same business event)
            DB::statement('
                CREATE UNIQUE INDEX uq_journal_source_event
                ON journal_entries (source_type, source_id, source_event)
                WHERE source_type IS NOT NULL AND source_id IS NOT NULL AND source_event IS NOT NULL
            ');

            // Line monetary checks: exactly one of debit/credit > 0, both >= 0
            DB::statement('
                ALTER TABLE journal_lines
                ADD CONSTRAINT chk_journal_line_debit_credit
                CHECK (
                    (debit > 0 AND credit = 0) OR
                    (credit > 0 AND debit = 0)
                )
            ');

            DB::statement('
                ALTER TABLE journal_lines
                ADD CONSTRAINT chk_journal_lines_positive
                CHECK (debit >= 0 AND credit >= 0)
            ');

            // Trigger on journal_entries: protect posted entries from deletion or financial mutation
            DB::statement('
                CREATE OR REPLACE FUNCTION protect_journal_entries()
                RETURNS TRIGGER AS $$
                BEGIN
                    IF TG_OP = \'DELETE\' THEN
                        IF OLD.status IN (\'POSTED\', \'REVERSED\') THEN
                            RAISE EXCEPTION \'Posted journal entries are permanent financial records and cannot be deleted.\';
                        END IF;
                    END IF;

                    IF TG_OP = \'UPDATE\' THEN
                        IF OLD.status IN (\'POSTED\', \'REVERSED\') THEN
                            -- Only allow linking reversal journal, changing status from POSTED to REVERSED with reversal reason, or updating timestamp
                            IF NEW.journal_number <> OLD.journal_number
                                OR NEW.entry_type <> OLD.entry_type
                                OR NEW.source_type IS DISTINCT FROM OLD.source_type
                                OR NEW.source_id IS DISTINCT FROM OLD.source_id
                                OR NEW.source_event IS DISTINCT FROM OLD.source_event
                                OR NEW.posting_date <> OLD.posting_date
                                OR NEW.accounting_date <> OLD.accounting_date
                                OR NEW.total_debit <> OLD.total_debit
                                OR NEW.total_credit <> OLD.total_credit THEN
                                RAISE EXCEPTION \'Posted journal entries are immutable. Core financial attributes cannot be altered.\';
                            END IF;

                            IF OLD.status = \'REVERSED\' AND NEW.status <> \'REVERSED\' THEN
                                RAISE EXCEPTION \'Reversed journal entries cannot be reverted to unreversed status.\';
                            END IF;
                        END IF;
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            ');

            DB::statement('
                CREATE TRIGGER trg_protect_journal_entries
                BEFORE UPDATE OR DELETE ON journal_entries
                FOR EACH ROW
                EXECUTE FUNCTION protect_journal_entries();
            ');

            // Trigger on journal_lines: protect lines belonging to posted/reversed journal entries
            DB::statement('
                CREATE OR REPLACE FUNCTION protect_journal_lines()
                RETURNS TRIGGER AS $$
                DECLARE
                    parent_status VARCHAR(30);
                BEGIN
                    IF TG_OP = \'DELETE\' THEN
                        SELECT status INTO parent_status FROM journal_entries WHERE id = OLD.journal_entry_id;
                        IF parent_status IN (\'POSTED\', \'REVERSED\') THEN
                            RAISE EXCEPTION \'Journal lines for posted journal entries are permanent and cannot be deleted.\';
                        END IF;
                    END IF;

                    IF TG_OP = \'UPDATE\' THEN
                        SELECT status INTO parent_status FROM journal_entries WHERE id = OLD.journal_entry_id;
                        IF parent_status IN (\'POSTED\', \'REVERSED\') THEN
                            RAISE EXCEPTION \'Journal lines for posted journal entries are immutable and cannot be updated.\';
                        END IF;
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            ');

            DB::statement('
                CREATE TRIGGER trg_protect_journal_lines
                BEFORE UPDATE OR DELETE ON journal_lines
                FOR EACH ROW
                EXECUTE FUNCTION protect_journal_lines();
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
            DB::statement('DROP TRIGGER IF EXISTS trg_protect_journal_lines ON journal_lines');
            DB::statement('DROP FUNCTION IF EXISTS protect_journal_lines()');
            DB::statement('DROP TRIGGER IF EXISTS trg_protect_journal_entries ON journal_entries');
            DB::statement('DROP FUNCTION IF EXISTS protect_journal_entries()');
        }

        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');

        if ($isPgsql) {
            DB::statement('DROP SEQUENCE IF EXISTS journal_number_seq');
        }
    }
};
