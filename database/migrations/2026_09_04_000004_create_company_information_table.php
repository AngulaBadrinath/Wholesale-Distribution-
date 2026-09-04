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
        Schema::create('company_information', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name', 255);
            $table->string('dba_name', 255)->nullable();
            $table->string('address_line1', 255);
            $table->string('address_line2', 255)->nullable();
            $table->string('city', 100);
            $table->string('state', 100);
            $table->string('postal_code', 20);
            $table->string('country', 2)->default('US');
            $table->string('phone', 50);
            $table->string('email', 255);
            $table->string('website', 255)->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->string('state_tax_id', 50)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('timezone', 50)->default('America/New_York');
            $table->text('invoice_footer_note')->nullable();
            $table->boolean('is_singleton')->default(true)->unique();
            $table->timestamps();
        });

        // Seed initial authoritative default record
        DB::table('company_information')->insert([
            'legal_name' => 'Wholesale Distribution Inc.',
            'dba_name' => 'Apex Wholesale Distribution',
            'address_line1' => '100 Distribution Blvd',
            'address_line2' => 'Suite 400',
            'city' => 'Atlanta',
            'state' => 'GA',
            'postal_code' => '30301',
            'country' => 'US',
            'phone' => '+1 (800) 555-0199',
            'email' => 'support@example.com',
            'website' => 'https://example.com',
            'tax_id' => '12-3456789',
            'state_tax_id' => 'GA-987654',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'invoice_footer_note' => 'Thank you for your business. Invoices are payable within 30 days.',
            'is_singleton' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_information');
    }
};
