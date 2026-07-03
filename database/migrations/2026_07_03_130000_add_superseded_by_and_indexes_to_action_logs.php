<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_logs', function (Blueprint $table) {
            $table->foreignUlid('superseded_by_action_log_id')
                ->nullable()
                ->after('removed_task_uids')
                ->constrained('action_logs')
                ->nullOnDelete();
        });

        Schema::table('action_log_tasks', function (Blueprint $table) {
            $table->index('task_uid', 'action_log_tasks_task_uid_index');
        });

        Schema::table('action_log_task_history', function (Blueprint $table) {
            $table->index(['period_id', 'action_log_task_id'], 'action_log_task_history_period_task_index');
        });

        Schema::table('action_logs', function (Blueprint $table) {
            $table->index(['period_id', 'state'], 'action_logs_period_state_index');
        });
    }

    public function down(): void
    {
        Schema::table('action_logs', function (Blueprint $table) {
            $table->dropIndex('action_logs_period_state_index');
            $table->dropConstrainedForeignId('superseded_by_action_log_id');
        });

        Schema::table('action_log_task_history', function (Blueprint $table) {
            $table->dropIndex('action_log_task_history_period_task_index');
        });

        Schema::table('action_log_tasks', function (Blueprint $table) {
            $table->dropIndex('action_log_tasks_task_uid_index');
        });
    }
};
