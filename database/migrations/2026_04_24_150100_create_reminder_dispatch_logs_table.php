<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_dispatch_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('reminder_config_id');
            $table->foreign('reminder_config_id')->references('id')->on('reminder_configs')->cascadeOnDelete();
            $table->ulid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('activity_type');
            $table->string('window_key');
            $table->json('channels')->nullable();
            $table->string('status')->default('sent');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['reminder_config_id', 'user_id', 'activity_type', 'window_key'], 'reminder_dispatch_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_dispatch_logs');
    }
};
