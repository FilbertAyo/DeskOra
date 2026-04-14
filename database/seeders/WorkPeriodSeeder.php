<?php

namespace Database\Seeders;

use App\Models\WorkPeriod;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class WorkPeriodSeeder extends Seeder
{
    /**
     * Seed an initial current work period so staff can start submitting documents.
     */
    public function run(): void
    {
        $startOfCurrentWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $startOfNextWeek = $startOfCurrentWeek->copy()->addWeek();

        $this->upsertPeriodForWeekStart($startOfCurrentWeek);
        $this->upsertPeriodForWeekStart($startOfNextWeek);
    }

    private function upsertPeriodForWeekStart(Carbon $weekStart): void
    {
        $weekEnd = $weekStart->copy()->addDays(6);
        $year = $weekStart->isoWeekYear();
        $weekNumber = $weekStart->isoWeek();

        // Plan for Week N is due on Saturday of Week N-1.
        $planDeadline = $weekStart->copy()->subDays(2);
        $reportDeadline = $weekStart->copy()->addDays(5);

        WorkPeriod::updateOrCreate(
            [
                'year' => $year,
                'week_number' => $weekNumber,
            ],
            [
                'week_start_date' => $weekStart->format('Y-m-d'),
                'week_end_date' => $weekEnd->format('Y-m-d'),
                'plan_deadline' => $planDeadline->format('Y-m-d'),
                'report_deadline' => $reportDeadline->format('Y-m-d'),
                'status' => WorkPeriod::STATUS_OPEN,
            ]
        );
    }
}
