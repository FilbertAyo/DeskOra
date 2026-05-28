<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_share_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignUlid('weekly_plan_id')->nullable()->constrained('weekly_plans')->nullOnDelete();
            $table->foreignUlid('weekly_report_id')->nullable()->constrained('weekly_reports')->nullOnDelete();
            $table->foreignUlid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('event_type', ['added', 'removed']);
            $table->timestamps();

            $table->index(['target_user_id', 'created_at']);
            $table->index(['document_id', 'event_type']);
            $table->index(['weekly_plan_id', 'event_type'], 'document_share_events_weekly_plan_event_type_index');
            $table->index(['weekly_report_id', 'event_type'], 'document_share_events_weekly_report_event_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_share_events');
    }
};
