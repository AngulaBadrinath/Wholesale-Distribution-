<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 255)->index();
            $table->string('contact_name', 255);
            $table->string('email', 255)->nullable()->index();
            $table->string('phone', 50);

            // Physical & Billing Address
            $table->string('billing_address_line1', 255);
            $table->string('billing_address_line2', 255)->nullable();
            $table->string('billing_city', 100);
            $table->string('billing_state', 100);
            $table->string('billing_postal_code', 20);
            $table->string('billing_country', 2)->default('US');

            // Shipping & Delivery Address
            $table->string('shipping_address_line1', 255)->nullable();
            $table->string('shipping_address_line2', 255)->nullable();
            $table->string('shipping_city', 100)->nullable();
            $table->string('shipping_state', 100)->nullable();
            $table->string('shipping_postal_code', 20)->nullable();
            $table->string('shipping_country', 2)->default('US');

            // Business & Credit Terms
            $table->string('tax_id', 50)->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0.00);
            $table->string('payment_terms', 50)->default('NET_30');
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
