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
        // 1. Immutable Delivery Events Ledger
        Schema::create('delivery_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->restrictOnDelete();
            $table->string('event_type', 50)->index();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->index();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['delivery_id', 'created_at'], 'idx_delivery_events_delivery_created');
        });

        // 2. Structured Delivery Failures Table
        Schema::create('delivery_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->restrictOnDelete();
            $table->string('failure_reason', 50)->index();
            $table->text('driver_notes');
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('reported_at')->useCurrent();
            $table->timestampTz('resolved_at')->nullable();
            $table->string('resolution_action', 50)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->index('delivery_id', 'idx_delivery_failures_delivery_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_failures');
        Schema::dropIfExists('delivery_events');
    }
};
