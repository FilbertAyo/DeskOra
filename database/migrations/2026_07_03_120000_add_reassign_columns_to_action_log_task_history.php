<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_log_task_history', function (Blueprint $table) {
            $table->json('reassigned_from_user_ids')->nullable()->after('source');
            $table->json('reassigned_to_user_ids')->nullable()->after('reassigned_from_user_ids');
        });
    }

    public function down(): void
    {
        Schema::table('action_log_task_history', function (Blueprint $table) {
            $table->dropColumn(['reassigned_from_user_ids', 'reassigned_to_user_ids']);
        });
    }
};
