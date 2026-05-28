<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_activity_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignUlid('source_weekly_plan_id')->nullable()->constrained('weekly_plans')->nullOnDelete();
            $table->foreignUlid('source_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('assignee_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('period_id')->nullable()->constrained('work_periods')->nullOnDelete();
            $table->string('activity_uid', 64);
            $table->string('status', 20)->default('preview');
            $table->json('activity_payload');
            $table->timestamps();

            $table->unique(
                ['source_document_id', 'assignee_user_id', 'activity_uid'],
                'weekly_activity_assignments_source_assignee_uid_unique'
            );
            $table->unique(
                ['source_weekly_plan_id', 'assignee_user_id', 'activity_uid'],
                'weekly_activity_assignments_plan_assignee_uid_unique'
            );
            $table->index(['assignee_user_id', 'status'], 'weekly_activity_assignments_assignee_status_index');
            $table->index(['period_id', 'assignee_user_id'], 'weekly_activity_assignments_period_assignee_index');
            $table->index(
                ['source_weekly_plan_id', 'period_id'],
                'weekly_activity_assignments_plan_period_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_activity_assignments');
    }
};
