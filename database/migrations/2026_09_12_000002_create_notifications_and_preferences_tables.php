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
        // 1. In-App Notifications Table (User-facing operational notifications)
        Schema::create('in_app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->index();
            $table->string('notification_type', 100)->index();
            $table->string('category', 50)->index();
            $table->string('severity', 20)->default('info');
            $table->string('title', 255);
            $table->text('message');
            $table->string('entity_type', 100)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('action_url', 500)->nullable();
            $table->boolean('is_read')->default(false)->index();
            $table->timestamp('read_at')->nullable();
            $table->string('deduplication_key', 150)->nullable()->index();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Composite index for efficient unread count and user feed queries
            $table->index(['user_id', 'is_read', 'created_at']);
        });

        // 2. Notification Preferences Table (User configuration of operational categories)
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->index();
            $table->string('category', 50);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('in_app_notifications');
    }
};
