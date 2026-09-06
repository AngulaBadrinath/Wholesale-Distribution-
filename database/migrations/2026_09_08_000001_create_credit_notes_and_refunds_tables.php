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
            DB::statement('CREATE SEQUENCE IF NOT EXISTS credit_note_number_seq START WITH 1 INCREMENT BY 1 NO CYCLE');
            DB::statement('CREATE SEQUENCE IF NOT EXISTS refund_number_seq START WITH 1 INCREMENT BY 1 NO CYCLE');
            DB::statement('CREATE SEQUENCE IF NOT EXISTS refund_txn_number_seq START WITH 1 INCREMENT BY 1 NO CYCLE');
        }

        // 2. Create credit_notes table (Header)
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('credit_number', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('return_request_id')->nullable()->constrained('return_requests')->restrictOnDelete();
            $table->string('status', 30)->default('ISSUED')->index();
            $table->string('currency', 3)->default('USD');
            $table->decimal('subtotal', 15, 2)->default(0.00);
            $table->decimal('tax_total', 15, 2)->default(0.00);
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->decimal('allocated_to_refunds', 15, 2)->default(0.00);
            $table->decimal('remaining_balance', 15, 2)->default(0.00);
            $table->text('reason');
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('issued_at');

            // Snapshot attributes
            $table->string('customer_name_snapshot', 255);
            $table->string('customer_code_snapshot', 50);
            $table->string('customer_contact_snapshot', 255)->nullable();
            $table->string('customer_email_snapshot', 255)->nullable();
            $table->string('customer_phone_snapshot', 50)->nullable();
            $table->string('billing_address_line1_snapshot', 255)->nullable();
            $table->string('billing_city_snapshot', 100)->nullable();
            $table->string('billing_state_snapshot', 100)->nullable();
            $table->string('billing_postal_code_snapshot', 20)->nullable();
            $table->string('billing_country_snapshot', 10)->nullable();
            $table->string('company_legal_name_snapshot', 255)->nullable();
            $table->text('company_address_snapshot')->nullable();
            $table->string('company_tax_id_snapshot', 50)->nullable();

            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['order_id', 'status']);
            $table->index(['return_request_id']);
        });

        // 3. Create credit_note_items table (Line Items)
        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignId('return_request_item_id')->nullable()->constrained('return_request_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name_snapshot', 255);
            $table->string('sku_snapshot', 50);
            $table->integer('quantity');
            $table->decimal('unit_price_snapshot', 15, 2);
            $table->decimal('tax_rate_snapshot', 7, 4)->default(0.0000);
            $table->decimal('tax_amount_snapshot', 15, 2)->default(0.00);
            $table->decimal('line_subtotal', 15, 2);
            $table->decimal('line_total', 15, 2);
            $table->timestamps();

            $table->index('credit_note_id');
            $table->index('product_id');
        });

        // 4. Create refund_requests table
        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->restrictOnDelete();
            $table->string('status', 30)->default('REQUESTED')->index();
            $table->string('payment_method', 30)->default('CASH');
            $table->decimal('amount', 15, 2);
            $table->text('reason');

            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('requested_at');

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();

            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index(['credit_note_id', 'status']);
        });

        // 5. Create refund_request_events table (Append-only lifecycle stream)
        Schema::create('refund_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_request_id')->constrained('refund_requests')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['refund_request_id', 'created_at']);
        });

        // 6. Create refund_transactions table (Authoritative Settlement Boundary)
        Schema::create('refund_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 50)->unique();
            $table->foreignId('refund_request_id')->constrained('refund_requests')->restrictOnDelete();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 30);
            $table->string('reference_number', 100)->nullable();
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('processed_at');
            $table->string('status', 30)->default('COMPLETED')->index();
            $table->text('failure_reason')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();

            $table->index(['customer_id', 'processed_at']);
            $table->index('credit_note_id');
        });

        // 7. PostgreSQL-specific check constraints & immutability triggers
        if ($isPgsql) {
            DB::statement('ALTER TABLE credit_notes ADD CONSTRAINT chk_credit_notes_positive_subtotal CHECK (subtotal >= 0)');
            DB::statement('ALTER TABLE credit_notes ADD CONSTRAINT chk_credit_notes_positive_tax CHECK (tax_total >= 0)');
            DB::statement('ALTER TABLE credit_notes ADD CONSTRAINT chk_credit_notes_positive_total CHECK (total_amount > 0)');
            DB::statement('ALTER TABLE credit_notes ADD CONSTRAINT chk_credit_notes_allocated_bounds CHECK (allocated_to_refunds >= 0 AND allocated_to_refunds <= total_amount)');
            DB::statement('ALTER TABLE credit_notes ADD CONSTRAINT chk_credit_notes_balance_conservation CHECK (remaining_balance = total_amount - allocated_to_refunds)');

            DB::statement('ALTER TABLE credit_note_items ADD CONSTRAINT chk_credit_note_items_quantity_positive CHECK (quantity > 0)');
            DB::statement('ALTER TABLE credit_note_items ADD CONSTRAINT chk_credit_note_items_unit_price_non_negative CHECK (unit_price_snapshot >= 0)');
            DB::statement('ALTER TABLE credit_note_items ADD CONSTRAINT chk_credit_note_items_line_total_non_negative CHECK (line_total >= 0)');

            DB::statement('ALTER TABLE refund_requests ADD CONSTRAINT chk_refund_requests_amount_positive CHECK (amount > 0)');
            DB::statement('ALTER TABLE refund_transactions ADD CONSTRAINT chk_refund_transactions_amount_positive CHECK (amount > 0)');

            // Credit Note Header Immutability Trigger Function
            DB::unprepared("
                CREATE OR REPLACE FUNCTION protect_credit_note_immutability()
                RETURNS TRIGGER AS \$\$
                BEGIN
                    IF TG_OP = 'DELETE' THEN
                        RAISE EXCEPTION 'Issued credit notes are permanent financial records and cannot be deleted.';
                    END IF;

                    IF TG_OP = 'UPDATE' THEN
                        IF (OLD.credit_number <> NEW.credit_number OR
                            OLD.customer_id <> NEW.customer_id OR
                            OLD.order_id <> NEW.order_id OR
                            OLD.return_request_id <> NEW.return_request_id OR
                            OLD.currency <> NEW.currency OR
                            OLD.subtotal <> NEW.subtotal OR
                            OLD.tax_total <> NEW.tax_total OR
                            OLD.total_amount <> NEW.total_amount OR
                            OLD.issued_by <> NEW.issued_by OR
                            OLD.issued_at <> NEW.issued_at OR
                            OLD.customer_name_snapshot <> NEW.customer_name_snapshot OR
                            OLD.customer_code_snapshot <> NEW.customer_code_snapshot OR
                            OLD.company_legal_name_snapshot <> NEW.company_legal_name_snapshot) THEN
                            RAISE EXCEPTION 'Issued credit notes are immutable financial records and commercial snapshot fields cannot be modified.';
                        END IF;
                    END IF;

                    RETURN NEW;
                END;
                \$\$ LANGUAGE plpgsql;
            ");

            DB::unprepared("
                DROP TRIGGER IF EXISTS trg_protect_credit_notes ON credit_notes;
                CREATE TRIGGER trg_protect_credit_notes
                BEFORE UPDATE OR DELETE ON credit_notes
                FOR EACH ROW
                EXECUTE FUNCTION protect_credit_note_immutability();
            ");

            // Credit Note Item Immutability Trigger Function
            DB::unprepared("
                CREATE OR REPLACE FUNCTION protect_credit_note_item_immutability()
                RETURNS TRIGGER AS \$\$
                BEGIN
                    IF TG_OP = 'DELETE' THEN
                        RAISE EXCEPTION 'Credit note items are permanent financial records and cannot be deleted.';
                    END IF;

                    IF TG_OP = 'UPDATE' THEN
                        RAISE EXCEPTION 'Credit note items are immutable historical records and cannot be modified.';
                    END IF;

                    RETURN NEW;
                END;
                \$\$ LANGUAGE plpgsql;
            ");

            DB::unprepared("
                DROP TRIGGER IF EXISTS trg_protect_credit_note_items ON credit_note_items;
                CREATE TRIGGER trg_protect_credit_note_items
                BEFORE UPDATE OR DELETE ON credit_note_items
                FOR EACH ROW
                EXECUTE FUNCTION protect_credit_note_item_immutability();
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        if ($isPgsql) {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_protect_credit_note_items ON credit_note_items;');
            DB::unprepared('DROP FUNCTION IF EXISTS protect_credit_note_item_immutability();');

            DB::unprepared('DROP TRIGGER IF EXISTS trg_protect_credit_notes ON credit_notes;');
            DB::unprepared('DROP FUNCTION IF EXISTS protect_credit_note_immutability();');
        }

        Schema::dropIfExists('refund_transactions');
        Schema::dropIfExists('refund_request_events');
        Schema::dropIfExists('refund_requests');
        Schema::dropIfExists('credit_note_items');
        Schema::dropIfExists('credit_notes');

        if ($isPgsql) {
            DB::statement('DROP SEQUENCE IF EXISTS refund_txn_number_seq');
            DB::statement('DROP SEQUENCE IF EXISTS refund_number_seq');
            DB::statement('DROP SEQUENCE IF EXISTS credit_note_number_seq');
        }
    }
};
