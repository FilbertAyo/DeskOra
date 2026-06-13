<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('weekly_report_activity_progress')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE weekly_report_activity_progress MODIFY progress ENUM('Not started', 'In progress', 'Done', 'Partially done', 'Not done') NULL");
    }

    public function down(): void
    {
        if (! Schema::hasTable('weekly_report_activity_progress')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE weekly_report_activity_progress MODIFY progress ENUM('Not started', 'In progress', 'Done', 'Partially done') NULL");
    }
};
