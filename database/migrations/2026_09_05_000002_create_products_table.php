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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique()->index();
            $table->string('name', 255)->index();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('unit', 30)->default('UNIT');
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->decimal('cost_price', 12, 2)->default(0.00);
            $table->decimal('minimum_allowed_price', 12, 2);
            $table->decimal('default_selling_price', 12, 2);
            $table->decimal('mrp', 12, 2);
            $table->unsignedBigInteger('tax_profile_id')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
