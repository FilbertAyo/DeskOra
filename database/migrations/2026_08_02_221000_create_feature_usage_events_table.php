<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_usage_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('feature_key');
            $table->string('event_type', 32);
            $table->string('route_name')->nullable();
            $table->string('path', 512)->nullable();
            $table->string('module');
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('feature_key');
            $table->index('module');
            $table->index('event_type');
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['feature_key', 'created_at']);
            $table->index(['user_id', 'feature_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_usage_events');
    }
};
