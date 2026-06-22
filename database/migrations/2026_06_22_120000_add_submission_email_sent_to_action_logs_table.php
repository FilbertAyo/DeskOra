<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('action_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('action_logs', 'submission_email_sent')) {
                $table->boolean('submission_email_sent')->default(false)->after('state');
            }
        });
    }

    public function down(): void
    {
        Schema::table('action_logs', function (Blueprint $table) {
            if (Schema::hasColumn('action_logs', 'submission_email_sent')) {
                $table->dropColumn('submission_email_sent');
            }
        });
    }
};
