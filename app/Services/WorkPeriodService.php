<?php

namespace App\Services;

use App\Models\WorkPeriod;
use Carbon\Carbon;

class WorkPeriodService
{
    /**
     * Create the next work period automatically
     * This creates only the immediate next week from "now"
     *
     * @return WorkPeriod|null
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

        // Calculate deadlines
        // Plan deadline: Friday of the week (4 days after Monday = Friday)
        // Report deadline: Saturday of the week (5 days after Monday = Saturday)
        $planDeadline = $nextWeekStart->copy()->addDays(4); // Friday
        $reportDeadline = $nextWeekStart->copy()->addDays(5); // Saturday

        // Ensure deadlines are correct days of week
        if ($planDeadline->dayOfWeek !== Carbon::FRIDAY) {
            $planDeadline = $nextWeekStart->copy()->next(Carbon::FRIDAY);
            if ($planDeadline->gt($nextWeekEnd)) {
                $planDeadline = $nextWeekStart->copy()->previous(Carbon::FRIDAY);
            }
        }

        if ($reportDeadline->dayOfWeek !== Carbon::SATURDAY) {
            $reportDeadline = $nextWeekStart->copy()->next(Carbon::SATURDAY);
            if ($reportDeadline->gt($nextWeekEnd)) {
                $reportDeadline = $nextWeekStart->copy()->previous(Carbon::SATURDAY);
            }
        }

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
     *
     * @return bool
     */
    public function closeCurrentWeek(): bool
    {
        // Get the period that ended yesterday (Saturday)
        $yesterday = Carbon::yesterday();
        
        $period = WorkPeriod::where('week_end_date', $yesterday->format('Y-m-d'))
            ->where('status', WorkPeriod::STATUS_OPEN)
            ->first();

        if ($period) {
            return $period->close();
        }

        return false;
    }

    /**
     * Get current period
     *
     * @return WorkPeriod|null
     */
    public function getCurrentPeriod(): ?WorkPeriod
    {
        return WorkPeriod::getCurrent();
    }

    /**
     * Get period by date
     *
     * @param Carbon|string $date
     * @return WorkPeriod|null
     */
    public function getPeriodByDate($date): ?WorkPeriod
    {
        return WorkPeriod::getPeriodByDate($date);
    }

    /**
     * Process weekly automation
     * This method handles both closing the current week and creating the next week
     * Called by scheduler (creation should run every Thursday)
     *
     * @return array
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

        // Close the week that just ended (Saturday)
        $closed = $this->closeCurrentWeek();
        if ($closed) {
            $results['closed'] = true;
            $results['closed_period'] = WorkPeriod::where('week_end_date', Carbon::yesterday()->format('Y-m-d'))
                ->where('status', WorkPeriod::STATUS_CLOSED)
                ->first();
            $results['messages'][] = "Closed week: " . ($results['closed_period'] ? $results['closed_period']->display_name : 'Unknown');
        }

        // Create the next week
        $newPeriod = $this->createNextWeek();
        if ($newPeriod) {
            $results['created'] = true;
            $results['created_period'] = $newPeriod;
            $results['messages'][] = "Created new week: " . $newPeriod->display_name;
        } else {
            $results['messages'][] = "Could not create next week. No previous period found or period already exists.";
        }

        return $results;
    }
}

