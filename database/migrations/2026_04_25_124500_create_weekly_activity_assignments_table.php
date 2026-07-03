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

        Schema::create('weekly_activity_assignments', function (Blueprint $table) use ($isSqlite) {
            $table->ulid('id')->primary();
            if ($isSqlite) {
                // For SQLite-based tests, avoid FK constraints entirely.
                // These cutover-related domain tables are backed by SQL views and
                // nested transactions, and SQLite FK enforcement can fail due to
                // visibility/order-of-operations.
                $table->ulid('source_document_id')->nullable();
                $table->ulid('source_weekly_plan_id')->nullable();
                $table->ulid('source_user_id');
                $table->ulid('assignee_user_id');
                $table->ulid('period_id')->nullable();
            } else {
                $table->foreignUlid('source_document_id')->constrained('documents')->cascadeOnDelete();
                $table->ulid('source_weekly_plan_id')->nullable();
                $table->foreignUlid('source_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignUlid('assignee_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignUlid('period_id')->nullable()->constrained('work_periods')->nullOnDelete();
            }
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
