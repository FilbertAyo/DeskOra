<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('action_log_task_history')) {
            return;
        }

        Schema::table('action_log_task_history', function (Blueprint $table) {
            if (! Schema::hasColumn('action_log_task_history', 'reassigned_from_user_ids')) {
                $table->json('reassigned_from_user_ids')->nullable()->after('source');
            }
            if (! Schema::hasColumn('action_log_task_history', 'reassigned_to_user_ids')) {
                $table->json('reassigned_to_user_ids')->nullable()->after('reassigned_from_user_ids');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('action_log_task_history')) {
            return;
        }

        Schema::table('action_log_task_history', function (Blueprint $table) {
            if (Schema::hasColumn('action_log_task_history', 'reassigned_to_user_ids')) {
                $table->dropColumn('reassigned_to_user_ids');
            }
            if (Schema::hasColumn('action_log_task_history', 'reassigned_from_user_ids')) {
                $table->dropColumn('reassigned_from_user_ids');
            }
        });
    }
};
