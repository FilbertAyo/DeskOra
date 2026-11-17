<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('in_house_meetings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('agenda')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('location')->nullable();
            $table->string('join_url')->nullable();
            $table->string('meeting_type', 20)->default('adhoc');
            $table->foreignUlid('period_id')->nullable()->constrained('work_periods')->nullOnDelete();
            $table->string('status', 20)->default('scheduled');
            $table->text('minutes_notes')->nullable();
            $table->text('discussion_points')->nullable();
            $table->text('decisions')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'status']);
            $table->index(['organizer_id', 'status']);
            $table->index(['meeting_type', 'period_id']);
        });

        Schema::create('in_house_meeting_participants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained('in_house_meetings')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('response', 20)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
            $table->index(['user_id', 'response']);
        });

        Schema::create('in_house_meeting_action_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained('in_house_meetings')->cascadeOnDelete();
            $table->string('title');
            $table->foreignUlid('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('open');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['meeting_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_house_meeting_action_items');
        Schema::dropIfExists('in_house_meeting_participants');
        Schema::dropIfExists('in_house_meetings');
    }
};
