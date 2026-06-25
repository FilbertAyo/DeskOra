<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        Schema::create('weekly_plans', function (Blueprint $table) use ($isSqlite) {
            $table->ulid('id')->primary();
            if ($isSqlite) {
                // `documents` is a SQL view in production; SQLite foreign keys can't reference views.
                $table->ulid('legacy_document_id')->nullable()->unique();
            } else {
                $table->foreignUlid('legacy_document_id')->unique()->constrained('documents')->cascadeOnDelete();
            }
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('period_id')->nullable()->constrained('work_periods')->nullOnDelete();
            $table->string('title')->nullable();
            $table->enum('state', ['draft', 'submitted'])->default('draft');
            $table->text('main_target')->nullable();
            $table->text('objective_reflection_progress')->nullable();
            $table->text('review_rejection_reason')->nullable();
            $table->enum('budget_review_status', ['pending', 'approved', 'rejected'])->nullable();
            $table->foreignUlid('budget_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('budget_reviewed_at')->nullable();
            $table->decimal('budget_provided_amount', 14, 2)->nullable();
            $table->text('budget_review_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'period_id']);
            $table->index(['period_id', 'state']);
            $table->index('budget_review_status');
            $table->unique(['user_id', 'period_id'], 'weekly_plans_user_period_unique');
        });

        Schema::create('weekly_plan_activities', function (Blueprint $table) use ($isSqlite) {
            $table->ulid('id')->primary();
            $table->foreignUlid('weekly_plan_id')->constrained('weekly_plans')->cascadeOnDelete();
            $table->string('activity_uid', 64);
            $table->enum('day', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'])->nullable();
            $table->text('activity')->nullable();
            $table->string('location')->nullable();
            $table->text('notes_inclusion')->nullable();
            if ($isSqlite) {
                $table->ulid('carry_over_source_report_id')->nullable();
            } else {
                $table->foreignUlid('carry_over_source_report_id')->nullable()->constrained('documents')->nullOnDelete();
            }
            $table->foreignUlid('carry_over_source_weekly_report_id')->nullable()->constrained('weekly_reports')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['weekly_plan_id', 'activity_uid'], 'weekly_plan_activities_uid_unique');
            $table->index(['weekly_plan_id', 'sort_order']);
            $table->index(
                'carry_over_source_weekly_report_id',
                'weekly_plan_activities_carry_over_weekly_report_index'
            );
        });

        Schema::create('weekly_plan_activity_assignees', function (Blueprint $table) {
            $table->id();
            $table->ulid('weekly_plan_activity_id');
            $table->ulid('user_id');
            $table->timestamps();

            $table->unique(['weekly_plan_activity_id', 'user_id'], 'weekly_plan_activity_assignees_unique');
            $table->index('user_id');
            $table->foreign('weekly_plan_activity_id', 'wp_activity_assignees_activity_fk')
                ->references('id')
                ->on('weekly_plan_activities')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'wp_activity_assignees_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::create('weekly_plan_meetings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('weekly_plan_id')->constrained('weekly_plans')->cascadeOnDelete();
            $table->string('meeting_uid', 64);
            $table->date('date')->nullable();
            $table->string('meeting')->nullable();
            $table->string('status')->nullable();
            $table->string('location')->nullable();
            $table->text('reactions_notes')->nullable();
            $table->string('source_type')->nullable();
            $table->foreignUlid('source_plan_id')->nullable()->constrained('weekly_plans')->nullOnDelete();
            $table->foreignUlid('source_owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_readonly_for_current_user')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['weekly_plan_id', 'meeting_uid'], 'weekly_plan_meetings_uid_unique');
            $table->index(['weekly_plan_id', 'sort_order']);
        });

        Schema::create('weekly_plan_meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->ulid('weekly_plan_meeting_id');
            $table->ulid('user_id');
            $table->timestamps();

            $table->unique(['weekly_plan_meeting_id', 'user_id'], 'weekly_plan_meeting_participants_unique');
            $table->index('user_id');
            $table->foreign('weekly_plan_meeting_id', 'wp_meeting_participants_meeting_fk')
                ->references('id')
                ->on('weekly_plan_meetings')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'wp_meeting_participants_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::create('weekly_plan_budget_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('weekly_plan_id')->constrained('weekly_plans')->cascadeOnDelete();
            $table->string('budget_uid', 64);
            $table->string('item')->nullable();
            $table->text('description_purpose')->nullable();
            $table->string('amount_raw', 60)->nullable();
            $table->decimal('amount_numeric', 14, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['weekly_plan_id', 'budget_uid'], 'weekly_plan_budget_items_uid_unique');
            $table->index(['weekly_plan_id', 'sort_order']);
        });

        Schema::create('weekly_plan_budget_reviews', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('weekly_plan_budget_item_id')->unique()->constrained('weekly_plan_budget_items')->cascadeOnDelete();
            $table->enum('decision', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('approval_stage', ['pending', 'reviewed', 'approved', 'authorized', 'rejected'])->default('pending');
            $table->decimal('provided_amount', 14, 2)->default(0);
            $table->text('note')->nullable();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUlid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamps();

            $table->index('decision');
            $table->index('approval_stage');
            $table->index('reviewed_by');
        });

        Schema::create('weekly_reports', function (Blueprint $table) use ($isSqlite) {
            $table->ulid('id')->primary();
            if ($isSqlite) {
                $table->ulid('legacy_document_id')->nullable()->unique();
            } else {
                $table->foreignUlid('legacy_document_id')->unique()->constrained('documents')->cascadeOnDelete();
            }
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('period_id')->nullable()->constrained('work_periods')->nullOnDelete();
            $table->string('title')->nullable();
            $table->enum('state', ['draft', 'submitted'])->default('draft');
            $table->text('summary')->nullable();
            $table->boolean('target_accomplished')->nullable();
            $table->string('target_accomplished_status')->nullable();
            $table->text('target_explanation')->nullable();
            $table->text('review_rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'period_id']);
            $table->index(['period_id', 'state']);
            $table->unique(['user_id', 'period_id'], 'weekly_reports_user_period_unique');
        });

        Schema::create('weekly_report_activity_progress', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('weekly_report_id')->constrained('weekly_reports')->cascadeOnDelete();
            $table->string('activity_uid', 64)->nullable();
            $table->text('activity_output')->nullable();
            $table->text('off_plan_activity')->nullable();
            $table->enum('progress', ['Not started', 'In progress', 'Done', 'Partially done', 'Done and carry'])->nullable();
            $table->text('remarks_challenges')->nullable();
            $table->text('next_step')->nullable();
            $table->boolean('carry_to_next_plan')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['weekly_report_id', 'sort_order'], 'weekly_report_activity_progress_order_idx');
        });

        Schema::create('weekly_report_meetings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('weekly_report_id')->constrained('weekly_reports')->cascadeOnDelete();
            $table->string('meeting_uid', 64)->nullable();
            $table->date('date')->nullable();
            $table->string('meeting')->nullable();
            $table->string('status')->nullable();
            $table->string('location')->nullable();
            $table->text('reactions_notes')->nullable();
            $table->string('source_type')->nullable();
            $table->foreignUlid('source_plan_id')->nullable()->constrained('weekly_plans')->nullOnDelete();
            $table->foreignUlid('source_owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_readonly_for_current_user')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['weekly_report_id', 'sort_order'], 'weekly_report_meetings_order_idx');
        });

        Schema::create('weekly_report_meeting_participants', function (Blueprint $table) {
            $table->id();
            $table->ulid('weekly_report_meeting_id');
            $table->ulid('user_id');
            $table->timestamps();

            $table->unique(['weekly_report_meeting_id', 'user_id'], 'weekly_report_meeting_participants_unique');
            $table->index('user_id');
            $table->foreign('weekly_report_meeting_id', 'wr_meeting_participants_meeting_fk')
                ->references('id')
                ->on('weekly_report_meetings')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'wr_meeting_participants_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });

        Schema::create('weekly_report_achievements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('weekly_report_id')->constrained('weekly_reports')->cascadeOnDelete();
            $table->text('achievement');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['weekly_report_id', 'sort_order'], 'weekly_report_achievements_order_idx');
        });

        Schema::create('weekly_report_challenges', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('weekly_report_id')->constrained('weekly_reports')->cascadeOnDelete();
            $table->text('challenge')->nullable();
            $table->text('action_taken_planned')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['weekly_report_id', 'sort_order'], 'weekly_report_challenges_order_idx');
        });

        Schema::create('weekly_report_budget_statuses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('weekly_report_id')->constrained('weekly_reports')->cascadeOnDelete();
            $table->enum('status', ['Not started', 'Pending', 'Received', 'Paid'])->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['weekly_report_id', 'sort_order'], 'weekly_report_budget_statuses_order_idx');
        });

        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('legacy_document_id')->unique()->constrained('documents')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('title')->nullable();
            $table->enum('state', ['draft', 'submitted'])->default('draft');
            $table->string('file_path')->nullable();
            $table->string('attachment')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('original_filename')->nullable();
            $table->text('month_summary')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'year', 'month']);
            $table->unique(['user_id', 'year', 'month'], 'monthly_reports_user_year_month_unique');
        });

        Schema::create('monthly_report_planned_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('monthly_report_id')->constrained('monthly_reports')->cascadeOnDelete();
            $table->foreignUlid('year_plan_activity_id')->nullable()->constrained('year_plan_activities')->nullOnDelete();
            $table->string('implementation_status')->nullable();
            $table->text('achievements')->nullable();
            $table->text('challenges')->nullable();
            $table->text('next_steps')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['monthly_report_id', 'sort_order'], 'monthly_report_planned_items_order_idx');
        });

        Schema::create('monthly_report_off_plan_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('monthly_report_id')->constrained('monthly_reports')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['monthly_report_id', 'sort_order'], 'monthly_report_off_plan_items_order_idx');
        });

        Schema::create('action_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('legacy_document_id')->unique()->constrained('documents')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('period_id')->nullable()->constrained('work_periods')->nullOnDelete();
            $table->string('title')->nullable();
            $table->enum('state', ['draft', 'submitted'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['period_id', 'state']);
            $table->index('user_id');
        });

        Schema::create('action_log_tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('action_log_id')->constrained('action_logs')->cascadeOnDelete();
            $table->string('task_uid', 80);
            $table->unsignedInteger('task_no')->default(0);
            $table->text('task')->nullable();
            $table->enum('progress', ['Not Started', 'In Progress', 'Done', 'Cancelled'])->default('Not Started');
            $table->date('due_date')->nullable();
            $table->date('compilation_date')->nullable();
            $table->text('notes_progress')->nullable();
            $table->text('status_reason')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['action_log_id', 'task_uid'], 'action_log_tasks_uid_unique');
            $table->index(['action_log_id', 'sort_order']);
        });

        Schema::create('action_log_task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('action_log_task_id')->constrained('action_log_tasks')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['action_log_task_id', 'user_id'], 'action_log_task_assignees_unique');
            $table->index('user_id');
        });

        Schema::create('action_log_task_history', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('action_log_task_id')->constrained('action_log_tasks')->cascadeOnDelete();
            $table->foreignUlid('period_id')->nullable()->constrained('work_periods')->nullOnDelete();
            $table->string('period_label')->nullable();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_progress')->nullable();
            $table->string('to_progress')->nullable();
            $table->text('notes_progress')->nullable();
            $table->text('status_reason')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();

            $table->index(['action_log_task_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_log_task_history');
        Schema::dropIfExists('action_log_task_assignees');
        Schema::dropIfExists('action_log_tasks');
        Schema::dropIfExists('action_logs');
        Schema::dropIfExists('monthly_report_off_plan_items');
        Schema::dropIfExists('monthly_report_planned_items');
        Schema::dropIfExists('monthly_reports');
        Schema::dropIfExists('weekly_report_budget_statuses');
        Schema::dropIfExists('weekly_report_challenges');
        Schema::dropIfExists('weekly_report_achievements');
        Schema::dropIfExists('weekly_report_meeting_participants');
        Schema::dropIfExists('weekly_report_meetings');
        Schema::dropIfExists('weekly_report_activity_progress');
        Schema::dropIfExists('weekly_reports');
        Schema::dropIfExists('weekly_plan_budget_reviews');
        Schema::dropIfExists('weekly_plan_budget_items');
        Schema::dropIfExists('weekly_plan_meeting_participants');
        Schema::dropIfExists('weekly_plan_meetings');
        Schema::dropIfExists('weekly_plan_activity_assignees');
        Schema::dropIfExists('weekly_plan_activities');
        Schema::dropIfExists('weekly_plans');
    }
};
