<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_call_peers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained('in_house_meetings')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
            $table->index(['meeting_id', 'last_seen_at']);
        });

        Schema::create('meeting_call_signals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained('in_house_meetings')->cascadeOnDelete();
            $table->foreignUlid('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);
            $table->json('payload');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['meeting_id', 'to_user_id', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_call_signals');
        Schema::dropIfExists('meeting_call_peers');
    }
};
