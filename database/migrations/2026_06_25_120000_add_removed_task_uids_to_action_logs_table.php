<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('action_logs', 'removed_task_uids')) {
                $table->json('removed_task_uids')->nullable()->after('submission_email_sent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('action_logs', function (Blueprint $table) {
            if (Schema::hasColumn('action_logs', 'removed_task_uids')) {
                $table->dropColumn('removed_task_uids');
            }
        });
    }
};
