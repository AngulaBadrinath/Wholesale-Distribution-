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
            DB::statement('CREATE SEQUENCE IF NOT EXISTS invoice_number_seq START WITH 1 INCREMENT BY 1 NO CYCLE');
        }

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique()->index();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('ISSUED')->index();
            $table->date('invoice_date')->index();
            $table->date('due_date')->index();
            $table->string('payment_terms', 32)->default('NET_30');
            $table->string('currency', 3)->default('USD');

            // Financial Totals (DECIMAL 12,2)
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_total', 12, 2);
            $table->decimal('adjustment_total', 12, 2)->default(0.00);
            $table->decimal('grand_total', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0.00);
            $table->decimal('amount_due', 12, 2);
            $table->string('payment_status', 32)->default('UNPAID')->index();

            // Customer Profile Snapshot (RULE-DOM-001)
            $table->string('customer_name_snapshot', 255);
            $table->string('customer_code_snapshot', 64);
            $table->string('customer_contact_snapshot', 255)->nullable();
            $table->string('customer_email_snapshot', 255)->nullable();
            $table->string('customer_phone_snapshot', 64)->nullable();
            $table->string('customer_tax_id_snapshot', 64)->nullable();

            // Billing Address Snapshot
            $table->string('billing_address_line1_snapshot', 255);
            $table->string('billing_address_line2_snapshot', 255)->nullable();
            $table->string('billing_city_snapshot', 100);
            $table->string('billing_state_snapshot', 100);
            $table->string('billing_postal_code_snapshot', 20);
            $table->string('billing_country_snapshot', 100)->default('US');

            // Shipping Address Snapshot
            $table->string('shipping_address_line1_snapshot', 255);
            $table->string('shipping_address_line2_snapshot', 255)->nullable();
            $table->string('shipping_city_snapshot', 100);
            $table->string('shipping_state_snapshot', 100);
            $table->string('shipping_postal_code_snapshot', 20);
            $table->string('shipping_country_snapshot', 100)->default('US');

            // Company Legal Entity Snapshot
            $table->string('company_legal_name_snapshot', 255);
            $table->string('company_dba_name_snapshot', 255)->nullable();
            $table->text('company_address_snapshot');
            $table->string('company_phone_snapshot', 64)->nullable();
            $table->string('company_email_snapshot', 255)->nullable();
            $table->string('company_tax_id_snapshot', 64)->nullable();
            $table->string('company_state_tax_id_snapshot', 64)->nullable();
            $table->text('invoice_footer_note_snapshot')->nullable();

            // PDF Document Storage Caching
            $table->string('pdf_path', 500)->nullable();
            $table->timestampTz('pdf_generated_at')->nullable();

            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();

            // Product Master Snapshots
            $table->string('product_name_snapshot', 255);
            $table->string('sku_snapshot', 64);
            $table->string('unit_snapshot', 32);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);

            // Tax Profile Snapshots
            $table->string('tax_profile_code_snapshot', 64)->nullable();
            $table->string('tax_profile_name_snapshot', 255)->nullable();
            $table->decimal('tax_rate_snapshot', 6, 4)->default(0.0000);
            $table->decimal('taxable_amount', 12, 2);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('line_total', 12, 2);

            $table->timestamps();
        });

        // PostgreSQL Check Constraints
        if ($isPgsql) {
            DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoices_subtotal_non_negative CHECK (subtotal >= 0)');
            DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoices_tax_total_non_negative CHECK (tax_total >= 0)');
            DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoices_grand_total_non_negative CHECK (grand_total >= 0)');
            DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoices_amount_paid_non_negative CHECK (amount_paid >= 0)');
            DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoices_amount_due_non_negative CHECK (amount_due >= 0)');

            DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_invoice_items_quantity_positive CHECK (quantity > 0)');
            DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_invoice_items_unit_price_non_negative CHECK (unit_price >= 0)');
            DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_invoice_items_tax_rate_non_negative CHECK (tax_rate_snapshot >= 0)');
            DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_invoice_items_taxable_amount_non_negative CHECK (taxable_amount >= 0)');
            DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_invoice_items_tax_amount_non_negative CHECK (tax_amount >= 0)');
            DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT chk_invoice_items_line_total_non_negative CHECK (line_total >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP SEQUENCE IF EXISTS invoice_number_seq');
        }
    }
};
