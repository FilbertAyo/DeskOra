<?php

namespace App\Services;

use App\Models\User;
use App\Models\YearPlan;
use App\Models\YearPlanActivity;
use Illuminate\Support\Collection;

class MonthlyReportYearPlanActivities
{
    /**
     * Sub-department IDs the user is explicitly linked to for year-plan reporting.
     *
     * @return list<string>
     */
    public static function subDepartmentIdsForUser(User $user): array
    {
        return $user->subDepartments()->pluck('departments.id')->map(fn ($id) => (string) $id)->unique()->values()->all();
    }

    public static function yearPlanForCalendarYear(int $year): ?YearPlan
    {
        return YearPlan::query()->where('year', $year)->first();
    }

    /**
     * Planned activities for the user's sub-departments in the given calendar month.
     *
     * @return Collection<int, YearPlanActivity>
     */
    public static function plannedForUserMonth(User $user, int $calendarYear, int $month): Collection
    {
        $subDeptIds = self::subDepartmentIdsForUser($user);

        if ($subDeptIds === []) {
            return collect();
        }

        $yearPlan = self::yearPlanForCalendarYear($calendarYear);

        if (! $yearPlan) {
            return collect();
        }

        return YearPlanActivity::query()
            ->where('year_plan_id', $yearPlan->id)
            ->whereIn('department_id', $subDeptIds)
            ->with(['department.parent'])
            ->orderBy('department_id')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (YearPlanActivity $a) => $a->isPlannedInMonth($month))
            ->values();
    }

    /**
     * @return array<string, true>
     */
    public static function allowedActivityIdMap(User $user, int $calendarYear, int $month): array
    {
        $ids = self::plannedForUserMonth($user, $calendarYear, $month)->pluck('id')->all();

        return array_fill_keys($ids, true);
    }
}
