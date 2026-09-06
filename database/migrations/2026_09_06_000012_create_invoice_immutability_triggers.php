<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // 1. Invoice Header Immutability Trigger Function
        DB::unprepared("
            CREATE OR REPLACE FUNCTION protect_invoice_immutability()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Issued invoices are permanent financial records and cannot be deleted.';
                END IF;

                IF TG_OP = 'UPDATE' THEN
                    -- Block mutation of any immutable commercial facts
                    IF (OLD.invoice_number <> NEW.invoice_number OR
                        OLD.order_id <> NEW.order_id OR
                        OLD.customer_id <> NEW.customer_id OR
                        OLD.invoice_date <> NEW.invoice_date OR
                        OLD.due_date <> NEW.due_date OR
                        OLD.currency <> NEW.currency OR
                        OLD.subtotal <> NEW.subtotal OR
                        OLD.tax_total <> NEW.tax_total OR
                        OLD.adjustment_total <> NEW.adjustment_total OR
                        OLD.grand_total <> NEW.grand_total OR
                        OLD.customer_name_snapshot <> NEW.customer_name_snapshot OR
                        OLD.customer_code_snapshot <> NEW.customer_code_snapshot OR
                        OLD.billing_address_line1_snapshot <> NEW.billing_address_line1_snapshot OR
                        OLD.billing_city_snapshot <> NEW.billing_city_snapshot OR
                        OLD.shipping_address_line1_snapshot <> NEW.shipping_address_line1_snapshot OR
                        OLD.company_legal_name_snapshot <> NEW.company_legal_name_snapshot) THEN
                        RAISE EXCEPTION 'Issued invoices are immutable financial records and commercial snapshot fields cannot be modified.';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_protect_invoices ON invoices;
            CREATE TRIGGER trg_protect_invoices
            BEFORE UPDATE OR DELETE ON invoices
            FOR EACH ROW
            EXECUTE FUNCTION protect_invoice_immutability();
        ");

        // 2. Invoice Item Immutability Trigger Function
        DB::unprepared("
            CREATE OR REPLACE FUNCTION protect_invoice_item_immutability()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Invoice items are permanent financial records and cannot be deleted.';
                END IF;

                IF TG_OP = 'UPDATE' THEN
                    RAISE EXCEPTION 'Invoice items are immutable historical records and cannot be modified.';
                END IF;

                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_protect_invoice_items ON invoice_items;
            CREATE TRIGGER trg_protect_invoice_items
            BEFORE UPDATE OR DELETE ON invoice_items
            FOR EACH ROW
            EXECUTE FUNCTION protect_invoice_item_immutability();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_protect_invoices ON invoices;');
        DB::unprepared('DROP FUNCTION IF EXISTS protect_invoice_immutability();');

        DB::unprepared('DROP TRIGGER IF EXISTS trg_protect_invoice_items ON invoice_items;');
        DB::unprepared('DROP FUNCTION IF EXISTS protect_invoice_item_immutability();');
    }
};
