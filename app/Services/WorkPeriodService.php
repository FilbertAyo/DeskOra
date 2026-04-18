<?php

namespace App\Services;

use App\Models\WorkPeriod;
use Carbon\Carbon;

class WorkPeriodService
{
    /**
     * Resolve period that weekly plans should target (next week).
     */
    public function periodForWeeklyPlanSubmission(): ?WorkPeriod
    {
        $current = $this->getCurrentPeriod();
        if (! $current) {
            return null;
        }

        return $current->getNextPeriod();
    }

    /**
     * Resolve period that weekly reports should target (current week).
     */
    public function periodForWeeklyReportSubmission(): ?WorkPeriod
    {
        return $this->getCurrentPeriod();
    }

    /**
     * Create the next work period automatically
     * This creates only the immediate next week from "now"
     */
    public function createNextWeek(): ?WorkPeriod
    {
        // Calculate immediate next week from current date (prevents creating two+ weeks ahead)
        $nextWeekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeek(); // Next Monday
        $nextWeekEnd = $nextWeekStart->copy()->addDays(6); // Next Sunday

        // Determine ISO week year and number
        $nextYear = $nextWeekStart->isoWeekYear();
        $nextWeekNumber = $nextWeekStart->isoWeek();

        // Check if period already exists
        $exists = WorkPeriod::where('year', $nextYear)
            ->where('week_number', $nextWeekNumber)
            ->exists();

        if ($exists) {
            // Period already exists, return it
            return WorkPeriod::where('year', $nextYear)
                ->where('week_number', $nextWeekNumber)
                ->first();
        }

        // Calculate deadlines based on business rule:
        // - Plan deadline for Week N is Saturday of Week N-1
        // - Report deadline for Week N is Saturday of Week N
        $planDeadline = $nextWeekStart->copy()->subDays(2); // previous Saturday
        $reportDeadline = $nextWeekStart->copy()->addDays(5); // current Saturday

        // Create the new period
        $period = WorkPeriod::create([
            'year' => $nextYear,
            'week_number' => $nextWeekNumber,
            'week_start_date' => $nextWeekStart->format('Y-m-d'),
            'week_end_date' => $nextWeekEnd->format('Y-m-d'),
            'plan_deadline' => $planDeadline->format('Y-m-d'),
            'report_deadline' => $reportDeadline->format('Y-m-d'),
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        return $period;
    }

    /**
     * Close the current week period
     * This is called on Sunday to close the week that just ended
     */
    public function closeCurrentWeek(): bool
    {
        $period = WorkPeriod::where('week_end_date', '<', Carbon::today()->format('Y-m-d'))
            ->where('status', WorkPeriod::STATUS_OPEN)
            ->orderBy('week_end_date', 'desc')
            ->first();

        if ($period) {
            return $period->close();
        }

        return false;
    }

    /**
     * Close all open periods that have already ended before today.
     *
     * @return array{count:int, periods:\Illuminate\Support\Collection<int,WorkPeriod>}
     */
    public function closeEndedWeeks(): array
    {
        $periods = WorkPeriod::where('status', WorkPeriod::STATUS_OPEN)
            ->where('week_end_date', '<', Carbon::today()->format('Y-m-d'))
            ->orderBy('week_end_date', 'asc')
            ->get();

        $closed = 0;
        foreach ($periods as $period) {
            if ($period->close()) {
                $closed++;
            }
        }

        return [
            'count' => $closed,
            'periods' => $periods,
        ];
    }

    /**
     * Get current period
     */
    public function getCurrentPeriod(): ?WorkPeriod
    {
        return WorkPeriod::getCurrent();
    }

    /**
     * Badge/text/deadline dates for UI: plan targets next week; report targets current week.
     *
     * @return array<string, mixed>|null
     */
    public function deadlineStatusesForUi(?WorkPeriod $current, ?WorkPeriod $next): ?array
    {
        if (! $current && ! $next) {
            return null;
        }

        $row = [];
        if ($next) {
            $row['plan_badge'] = $next->getPlanDeadlineBadgeClass();
            $row['plan_text'] = $next->getDeadlineStatusText('plan');
            $row['plan_due'] = $next->getPlanDeadlineCutoff();
        }
        if ($current) {
            $row['report_badge'] = $current->getReportDeadlineBadgeClass();
            $row['report_text'] = $current->getDeadlineStatusText('report');
            $row['report_due'] = $current->getReportDeadlineCutoff();
        }

        return $row;
    }

    /**
     * Get period by date
     *
     * @param  Carbon|string  $date
     */
    public function getPeriodByDate($date): ?WorkPeriod
    {
        return WorkPeriod::getPeriodByDate($date);
    }

    /**
     * Process weekly automation
     * This method handles both closing the current week and creating the next week
     * Called by scheduler (creation should run every Thursday)
     */
    public function processWeeklyAutomation(): array
    {
        $results = [
            'closed' => false,
            'created' => false,
            'closed_period' => null,
            'created_period' => null,
            'messages' => [],
        ];

        // Close all ended weeks
        $closedResult = $this->closeEndedWeeks();
        if ($closedResult['count'] > 0) {
            $results['closed'] = true;
            $results['closed_period'] = $closedResult['periods']->last();
            $results['messages'][] = "Closed {$closedResult['count']} ended week(s).";
        }

        // Create the next week
        $newPeriod = $this->createNextWeek();
        if ($newPeriod) {
            $results['created'] = true;
            $results['created_period'] = $newPeriod;
            $results['messages'][] = 'Created new week: '.$newPeriod->display_name;
        } else {
            $results['messages'][] = 'Could not create next week. No previous period found or period already exists.';
        }

        return $results;
    }
}
