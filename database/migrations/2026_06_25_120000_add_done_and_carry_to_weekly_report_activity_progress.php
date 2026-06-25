<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE weekly_report_activity_progress MODIFY COLUMN progress ENUM(
            'Not started',
            'In progress',
            'Done',
            'Partially done',
            'Done and carry'
        ) NULL DEFAULT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE weekly_report_activity_progress MODIFY COLUMN progress ENUM(
            'Not started',
            'In progress',
            'Done',
            'Partially done'
        ) NULL DEFAULT NULL");
    }
};
