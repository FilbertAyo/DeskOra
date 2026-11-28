<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('management_assigned_tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('assigned_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('period_id')->constrained('work_periods')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('deadline')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['period_id', 'assigned_by_user_id']);
            $table->index('created_at');
        });

        Schema::create('management_assigned_task_recipients', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('management_assigned_task_id')
                ->constrained('management_assigned_tasks')
                ->cascadeOnDelete();
            $table->foreignUlid('assignee_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('progress', 40)->nullable();
            $table->text('report_feedback')->nullable();
            $table->ulid('weekly_report_id')->nullable();
            $table->timestamps();

            $table->unique(
                ['management_assigned_task_id', 'assignee_user_id'],
                'mgmt_task_recipients_task_assignee_unique'
            );
            $table->index(['assignee_user_id', 'status'], 'mgmt_task_recipients_assignee_status_index');
            $table->index('weekly_report_id', 'mgmt_task_recipients_weekly_report_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_assigned_task_recipients');
        Schema::dropIfExists('management_assigned_tasks');
    }
};
