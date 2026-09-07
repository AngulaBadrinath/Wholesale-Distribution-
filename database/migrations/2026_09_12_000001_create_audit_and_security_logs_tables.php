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
        // 1. Business Audit Logs Table (Durable, authoritative append-only business record)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100)->index();
            $table->string('module', 50)->index();
            $table->string('action', 100);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete()->index();
            $table->string('actor_email', 255)->nullable();
            $table->string('actor_role_snapshot', 50)->nullable();
            $table->string('entity_type', 100)->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('reference_number', 100)->nullable()->index();
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // 2. Security Logs Table (Restricted channel for security events)
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100)->index();
            $table->string('severity', 20)->default('INFO')->index();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete()->index();
            $table->string('actor_email', 255)->nullable();
            $table->string('actor_role', 50)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('context')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_logs');
        Schema::dropIfExists('audit_logs');
    }
};
