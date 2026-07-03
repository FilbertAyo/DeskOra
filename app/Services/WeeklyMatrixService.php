<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use App\Models\WeeklyMatrix;
use App\Models\WeeklyMatrixEntry;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanActivity;
use App\Models\WorkPeriod;
use App\Models\YearPlanActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WeeklyMatrixService
{
    /**
     * @return Collection<int, array{
     *     period: WorkPeriod,
     *     matrix_count: int,
     *     sub_department_count: int,
     *     is_current: bool
     * }>
     */
    public function periodsForCalendar(int $year): Collection
    {
        $subDepartmentCount = $this->subDepartments()->count();
        $currentPeriodId = WorkPeriod::getCurrent()?->id;

        $periods = WorkPeriod::query()
            ->where('year', $year)
            ->whereIn('status', [WorkPeriod::STATUS_OPEN, WorkPeriod::STATUS_CLOSED])
            ->orderBy('week_number')
            ->get();

        $matrixCounts = WeeklyMatrix::query()
            ->whereIn('period_id', $periods->pluck('id'))
            ->selectRaw('period_id, COUNT(*) as aggregate')
            ->groupBy('period_id')
            ->pluck('aggregate', 'period_id');

        return $periods->map(function (WorkPeriod $period) use ($subDepartmentCount, $matrixCounts, $currentPeriodId): array {
            return [
                'period' => $period,
                'matrix_count' => (int) ($matrixCounts[(string) $period->id] ?? 0),
                'sub_department_count' => $subDepartmentCount,
                'is_current' => (string) $period->id === (string) $currentPeriodId,
            ];
        });
    }

    /**
     * @return Collection<int, array{
     *     department: Department,
     *     matrix: WeeklyMatrix|null,
     *     entry_count: int,
     *     year_plan_link_count: int
     * }>
     */
    public function subDepartmentsWithMatrixStatus(WorkPeriod $period): Collection
    {
        $subDepartments = $this->subDepartments();
        $matrices = WeeklyMatrix::query()
            ->where('period_id', $period->id)
            ->whereIn('department_id', $subDepartments->pluck('id'))
            ->withCount('entries')
            ->with(['entries.yearPlanActivities'])
            ->get()
            ->keyBy(fn (WeeklyMatrix $matrix) => (string) $matrix->department_id);

        return $subDepartments->map(function (Department $department) use ($matrices): array {
            /** @var WeeklyMatrix|null $matrix */
            $matrix = $matrices->get((string) $department->id);

            $yearPlanLinkCount = 0;
            if ($matrix) {
                $yearPlanLinkCount = $matrix->entries->sum(
                    fn ($entry) => $entry->yearPlanActivities->count()
                );
            }

            return [
                'department' => $department,
                'matrix' => $matrix,
                'entry_count' => $matrix?->entries_count ?? 0,
                'year_plan_link_count' => $yearPlanLinkCount,
            ];
        });
    }

    /**
     * Existing matrices for a work period (table listing).
     *
     * @return Collection<int, array{
     *     department: Department,
     *     matrix: WeeklyMatrix,
     *     entry: \App\Models\WeeklyMatrixEntry|null,
     *     year_plan_link_count: int
     * }>
     */
    public function matricesForPeriodTable(WorkPeriod $period): Collection
    {
        return WeeklyMatrix::query()
            ->where('period_id', $period->id)
            ->with(['department.parent', 'entries.yearPlanActivities'])
            ->get()
            ->sortBy(function (WeeklyMatrix $matrix) {
                $department = $matrix->department;

                return [
                    sprintf('%05d', $department?->plan_section_number ?? 99999),
                    $department?->name ?? '',
                ];
            })
            ->values()
            ->map(function (WeeklyMatrix $matrix): array {
                $entry = $matrix->entries->first();
                $yearPlanLinkCount = $entry
                    ? $entry->yearPlanActivities->count()
                    : 0;

                return [
                    'department' => $matrix->department,
                    'matrix' => $matrix,
                    'entry' => $entry,
                    'year_plan_link_count' => $yearPlanLinkCount,
                ];
            });
    }

    /**
     * Sub-departments that do not yet have a matrix for this period.
     *
     * @return Collection<int, Department>
     */
    public function subDepartmentsWithoutMatrix(WorkPeriod $period): Collection
    {
        $existingDepartmentIds = WeeklyMatrix::query()
            ->where('period_id', $period->id)
            ->pluck('department_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return $this->subDepartments()
            ->reject(fn (Department $department) => in_array((string) $department->id, $existingDepartmentIds, true))
            ->values();
    }

    /**
     * @return Collection<int, YearPlanActivity>
     */
    public function yearPlanOptionsForSubDepartment(WorkPeriod $period, Department $subDepartment): Collection
    {
        $this->ensureSubDepartment($subDepartment);

        $yearPlan = MonthlyReportYearPlanActivities::yearPlanForCalendarYear((int) $period->year);

        if (! $yearPlan) {
            return collect();
        }

        return YearPlanActivity::query()
            ->where('year_plan_id', $yearPlan->id)
            ->where('department_id', $subDepartment->id)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return Collection<int, array{
     *     id: string,
     *     activity: string,
     *     day: string|null,
     *     staff_name: string,
     *     weekly_plan_id: string
     * }>
     */
    public function suggestionsFromWeeklyPlans(WorkPeriod $period, Department $subDepartment): Collection
    {
        $this->ensureSubDepartment($subDepartment);

        $userIds = $this->userIdsForSubDepartment($subDepartment);

        if ($userIds === []) {
            return collect();
        }

        $plans = WeeklyPlan::query()
            ->where('period_id', $period->id)
            ->where('state', WeeklyPlan::STATE_SUBMITTED)
            ->whereIn('user_id', $userIds)
            ->with(['user:id,name', 'activities'])
            ->get();

        return $plans->flatMap(function (WeeklyPlan $plan) {
            return $plan->activities
                ->filter(fn (WeeklyPlanActivity $activity) => trim((string) $activity->activity) !== '')
                ->map(fn (WeeklyPlanActivity $activity) => [
                    'id' => (string) $activity->id,
                    'activity' => (string) $activity->activity,
                    'day' => $activity->day,
                    'staff_name' => (string) ($plan->user?->name ?? 'Unknown'),
                    'weekly_plan_id' => (string) $plan->id,
                ]);
        })->values();
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    public function syncEntries(WeeklyMatrix $matrix, array $entries, User $user): void
    {
        $period = $matrix->period()->first();
        $subDepartment = $matrix->department()->first();

        if (! $period || ! $subDepartment) {
            throw ValidationException::withMessages([
                'entries' => 'The matrix is missing a valid work period or sub-department.',
            ]);
        }

        $allowedYearPlanIds = $this->yearPlanOptionsForSubDepartment($period, $subDepartment)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $normalized = collect($entries)
            ->values()
            ->map(function (array $entry, int $index) use ($allowedYearPlanIds): array {
                $keyActivity = $this->preserveText($entry['key_activity'] ?? '');

                if ($keyActivity === '') {
                    throw ValidationException::withMessages([
                        "entries.{$index}.key_activity" => 'Key activity is required for each row.',
                    ]);
                }

                $yearPlanIds = collect($entry['year_plan_activity_ids'] ?? [])
                    ->map(fn ($id) => (string) $id)
                    ->unique()
                    ->values()
                    ->all();

                foreach ($yearPlanIds as $yearPlanId) {
                    if (! in_array($yearPlanId, $allowedYearPlanIds, true)) {
                        throw ValidationException::withMessages([
                            "entries.{$index}.year_plan_activity_ids" => 'One or more year plan activities are invalid for this sub-department.',
                        ]);
                    }
                }

                return [
                    'sort_order' => $index,
                    'key_activity' => $keyActivity,
                    'output' => $this->preserveText($entry['output'] ?? ''),
                    'outcome' => $this->preserveText($entry['outcome'] ?? ''),
                    'impact' => $this->preserveText($entry['impact'] ?? ''),
                    'indicators' => $this->preserveText($entry['indicators'] ?? ''),
                    'baseline' => $this->preserveText($entry['baseline'] ?? ''),
                    'target' => $this->preserveText($entry['target'] ?? ''),
                    'means_of_verification' => $this->preserveText($entry['means_of_verification'] ?? ''),
                    'year_plan_activity_ids' => $yearPlanIds,
                ];
            });

        if ($normalized->isEmpty()) {
            throw ValidationException::withMessages([
                'entries' => 'Add at least one key activity row.',
            ]);
        }

        if ($normalized->count() > 1) {
            throw ValidationException::withMessages([
                'entries' => 'Each sub-department can have only one key activity.',
            ]);
        }

        DB::transaction(function () use ($matrix, $normalized, $user): void {
            $matrix->entries()->delete();

            foreach ($normalized as $entryData) {
                $yearPlanIds = $entryData['year_plan_activity_ids'];
                unset($entryData['year_plan_activity_ids']);

                /** @var WeeklyMatrixEntry $entry */
                $entry = $matrix->entries()->create($entryData);

                if ($yearPlanIds !== []) {
                    $entry->yearPlanActivities()->sync($yearPlanIds);
                }
            }

            $matrix->update(['updated_by' => $user->id]);
        });
    }

    public function ensureSubDepartment(Department $department): void
    {
        if ($department->parent_id === null) {
            throw ValidationException::withMessages([
                'department' => 'Weekly matrices must be created for a sub-department.',
            ]);
        }
    }

    /**
     * @return Collection<int, Department>
     */
    public function subDepartments(): Collection
    {
        return Department::query()
            ->whereNotNull('parent_id')
            ->with('parent')
            ->get()
            ->sortBy(function (Department $department) {
                return [
                    sprintf('%05d', $department->plan_section_number ?? 99999),
                    $department->name,
                ];
            })
            ->values();
    }

    /**
     * @return list<string>
     */
    private function userIdsForSubDepartment(Department $subDepartment): array
    {
        $fromPrimary = User::query()
            ->where('department_id', $subDepartment->id)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $fromPivot = User::query()
            ->whereHas('subDepartments', fn ($query) => $query->where('departments.id', $subDepartment->id))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return array_values(array_unique([...$fromPrimary, ...$fromPivot]));
    }

    private function preserveText(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return str_replace(["\r\n", "\r"], "\n", (string) $value);
    }
}
