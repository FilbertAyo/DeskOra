<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_periods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->year('year');
            $table->unsignedTinyInteger('week_number');
            $table->date('week_start_date');
            $table->date('week_end_date');
            $table->date('plan_deadline');
            $table->date('report_deadline');
            $table->enum('status', ['open', 'closed', 'archived'])->default('open');
            $table->timestamps();
            $table->softDeletes();

            $table->index('year');
            $table->index('week_number');
            $table->index('status');
            $table->index('week_start_date');
            $table->index('week_end_date');
            $table->unique(['year', 'week_number']);
        });

        // Plan for week N is due on the Saturday before that week (week_start_date − 2 days).
        foreach (DB::table('work_periods')->select(['id', 'week_start_date'])->cursor() as $row) {
            if (empty($row->week_start_date)) {
                continue;
            }
            $planDeadline = Carbon::parse($row->week_start_date)->subDays(2)->format('Y-m-d');
            DB::table('work_periods')->where('id', $row->id)->update(['plan_deadline' => $planDeadline]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('work_periods');
    }
};
