<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Models\WeeklyDepartmentMatrix;
use App\Models\WeeklyPlan;
use App\Models\WorkPeriod;
use App\Models\YearPlan;
use App\Models\YearPlanActivity;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WeeklyObjectiveMatrixController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $parentDepartmentIds = $this->parentDepartmentIdsForUser($user);
        $parentDepartments = $this->parentDepartmentsForUser($user);

        $periods = WorkPeriod::query()
            ->whereIn('status', [WorkPeriod::STATUS_OPEN, WorkPeriod::STATUS_CLOSED])
            ->orderByDesc('year')
            ->orderByDesc('week_number')
            ->limit(24)
            ->get(['id', 'year', 'week_number', 'status', 'week_start_date', 'week_end_date']);

        $departmentMatrices = WeeklyDepartmentMatrix::query()
            ->with(['department:id,name,parent_id', 'period:id,week_number,year', 'creator:id,name', 'updater:id,name'])
            ->whereIn('department_id', $parentDepartmentIds)
            ->whereHas('department', fn ($query) => $query->whereNull('parent_id'))
            ->latest('updated_at')
            ->get();

        $matrixMeta = $departmentMatrices->mapWithKeys(function (WeeklyDepartmentMatrix $matrix): array {
            $subDepartmentIds = $this->subDepartmentIdsForParent((string) $matrix->department_id);

            return [
                (string) $matrix->id => [
                    'sub_department_count' => count($subDepartmentIds),
                    'submission_summary' => $this->departmentPlanSubmissionSummary($matrix->period, $subDepartmentIds, (string) $matrix->department_id),
                ],
            ];
        });

        return view('matrix-report.index', [
            'departmentMatrices' => $departmentMatrices,
            'matrixMeta' => $matrixMeta,
            'parentDepartments' => $parentDepartments,
            'periods' => $periods,
            'defaultDepartmentId' => old('department_id', $this->defaultParentDepartmentIdForUser($user)),
            'defaultPeriodId' => old('period_id', WorkPeriod::getCurrent()?->id),
        ]);
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $parentDepartmentIds = $this->parentDepartmentIdsForUser($user);

        $validated = $request->validate([
            'department_id' => ['required', 'string', 'ulid', Rule::in($parentDepartmentIds)],
            'period_id' => ['required', 'string', 'ulid', 'exists:work_periods,id'],
        ]);

        $parentDepartment = Department::query()
            ->whereNull('parent_id')
            ->whereKey($validated['department_id'])
            ->first();

        if (! $parentDepartment) {
            return back()
                ->withErrors(['department_id' => 'Select a valid parent department.'])
                ->withInput();
        }

        $matrix = WeeklyDepartmentMatrix::withTrashed()->firstOrNew([
            'period_id' => $validated['period_id'],
            'department_id' => $parentDepartment->id,
        ]);

        $wasRestored = false;
        if ($matrix->trashed()) {
            $matrix->restore();
            $matrix->entries = [];
            $matrix->overall_budget = null;
            $matrix->notes = null;
            $wasRestored = true;
        }

        if (! $matrix->exists || $wasRestored) {
            $matrix->created_by = $user->id;
            if (! $wasRestored) {
                $matrix->entries = [];
            }
        }

        $matrix->updated_by = $user->id;
        $matrix->save();

        $message = $wasRestored
            ? 'Parent department matrix recreated. You can now add activities from all sub-departments.'
            : ($matrix->wasRecentlyCreated
                ? 'Parent department matrix created. You can now add activities from all sub-departments.'
                : 'Parent department matrix already exists. You can continue editing it.');

        return redirect()
            ->route('matrix-report.edit', $matrix)
            ->with('success', $message);
    }

    public function edit(Request $request, WeeklyDepartmentMatrix $matrix)
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanManageMatrix($user, $matrix);

        $period = $matrix->period()->first();
        $subDepartmentIds = $this->subDepartmentIdsForParent((string) $matrix->department_id);
        $impactDepartmentIds = array_values(array_unique([
            ...$subDepartmentIds,
            (string) $matrix->department_id,
        ]));
        $availableActivities = $this->collectActivitiesForPeriodAndDepartments($period, $subDepartmentIds, (string) $matrix->department_id);
        $impactOptions = $this->impactOptionsForPeriodYearAndDepartments($period, $impactDepartmentIds);

        $entryDefaults = old('entries');
        if (! is_array($entryDefaults)) {
            $entryDefaults = collect($matrix->entries ?? [])
                ->map(fn (array $entry): array => $this->normalizeMatrixEntryForForm($entry))
                ->values()
                ->all();
        }

        if ($entryDefaults === []) {
            $entryDefaults = [['activity_key' => '', 'input' => '', 'impact_year_plan_activity_ids' => []]];
        }

        $planSubmissionSummary = $this->departmentPlanSubmissionSummary($period, $subDepartmentIds, (string) $matrix->department_id);
        $rolledUpBudget = $this->calculateDepartmentBudgetTotal($period, $subDepartmentIds, (string) $matrix->department_id);
        $budgetBreakdown = $this->departmentBudgetBreakdown($period, $subDepartmentIds, (string) $matrix->department_id);

        return view('matrix-report.edit', [
            'matrix' => $matrix->load(['department:id,name,parent_id', 'period:id,year,week_number']),
            'availableActivities' => $availableActivities,
            'impactOptions' => $impactOptions,
            'planSubmissionSummary' => $planSubmissionSummary,
            'rolledUpBudget' => $rolledUpBudget,
            'budgetBreakdown' => $budgetBreakdown,
            'subDepartmentCount' => count($subDepartmentIds),
            'entryDefaults' => $entryDefaults,
        ]);
    }

    public function update(Request $request, WeeklyDepartmentMatrix $matrix)
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanManageMatrix($user, $matrix);

        $period = $matrix->period()->first();
        if (! $period) {
            return back()->with('error', 'The associated work period for this matrix no longer exists.');
        }

        $validated = $request->validate([
            'overall_budget' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.activity_key' => ['required', 'string'],
            'entries.*.input' => ['required', 'string', 'max:2000'],
            'entries.*.impact_year_plan_activity_ids' => ['required', 'array', 'min:1'],
            'entries.*.impact_year_plan_activity_ids.*' => ['required', 'string', 'ulid'],
        ]);

        $subDepartmentIds = $this->subDepartmentIdsForParent((string) $matrix->department_id);
        $impactDepartmentIds = array_values(array_unique([
            ...$subDepartmentIds,
            (string) $matrix->department_id,
        ]));
        $activityMap = $this->collectActivitiesForPeriodAndDepartments($period, $subDepartmentIds, (string) $matrix->department_id)->keyBy('key');
        $impactMap = $this->impactOptionsForPeriodYearAndDepartments($period, $impactDepartmentIds)->keyBy('id');
        $entries = $this->hydrateValidatedEntries($validated['entries'], $activityMap, $impactMap);
        $rolledUpBudget = $this->calculateDepartmentBudgetTotal($period, $subDepartmentIds, (string) $matrix->department_id);

        $matrix->update([
            'overall_budget' => $validated['overall_budget'] ?? $rolledUpBudget,
            'notes' => $validated['notes'] ?? null,
            'entries' => $entries,
            'updated_by' => $user->id,
        ]);

        return redirect()
            ->route('matrix-report.index')
            ->with('success', 'Parent department matrix updated successfully.');
    }

    public function destroy(Request $request, WeeklyDepartmentMatrix $matrix)
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanManageMatrix($user, $matrix);

        $matrix->forceDelete();

        return redirect()
            ->route('matrix-report.index')
            ->with('success', 'Parent department matrix deleted successfully.');
    }

    public function download(Request $request)
    {
        $currentPeriod = WorkPeriod::getCurrent();
        if (! $currentPeriod) {
            return back()->with('error', 'No active work period found for weekly objective matrix.');
        }

        $orientation = strtolower((string) $request->query('orientation', 'portrait'));
        if (! in_array($orientation, ['portrait', 'landscape'], true)) {
            $orientation = 'portrait';
        }

        $matrixRows = WeeklyPlan::query()
            ->where('state', WeeklyPlan::STATE_SUBMITTED)
            ->where('period_id', $currentPeriod->id)
            ->whereNotNull('main_target')
            ->where('main_target', '!=', '')
            ->with(['user:id,name,department_id', 'user.department:id,name'])
            ->latest('updated_at')
            ->get(['id', 'user_id', 'main_target', 'objective_reflection_progress', 'updated_at'])
            ->groupBy('user_id')
            ->map(fn ($plans) => $plans->first())
            ->values()
            ->sortBy(fn ($plan) => strtolower($plan->user?->name ?? ''))
            ->values();

        $pdf = Pdf::loadView('documents.weekly-objective-matrix-pdf', [
            'currentPeriod' => $currentPeriod,
            'matrixRows' => $matrixRows,
            'orientation' => $orientation,
        ])->setPaper('a4', $orientation);

        $filename = sprintf(
            'weekly_objective_matrix_week_%s_%s.pdf',
            (string) $currentPeriod->week_number,
            $orientation
        );

        return $pdf->download($filename);
    }

    private function ensureCanManageMatrix(User $user, WeeklyDepartmentMatrix $matrix): void
    {
        abort_unless(
            in_array((string) $matrix->department_id, $this->parentDepartmentIdsForUser($user), true),
            403
        );
    }

    /**
     * @return list<string>
     */
    private function parentDepartmentIdsForUser(User $user): array
    {
        $scopeIds = $user->departmentScopeIds();

        return Department::query()
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->where(function ($query) use ($scopeIds): void {
                $query->whereIn('id', $scopeIds)
                    ->orWhereHas('children', function ($children) use ($scopeIds): void {
                        $children->whereIn('id', $scopeIds);
                    });
            })
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * @return EloquentCollection<int, Department>
     */
    private function parentDepartmentsForUser(User $user): EloquentCollection
    {
        $parentIds = $this->parentDepartmentIdsForUser($user);
        if ($parentIds === []) {
            return new EloquentCollection;
        }

        return Department::query()
            ->whereIn('id', $parentIds)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function defaultParentDepartmentIdForUser(User $user): ?string
    {
        if (! $user->department_id) {
            return null;
        }

        $department = Department::query()->find($user->department_id);
        if (! $department) {
            return null;
        }

        if ($department->parent_id === null) {
            return (string) $department->id;
        }

        return (string) $department->parent_id;
    }

    /**
     * Staff and year-plan data live on sub-departments under a parent department.
     *
     * @return list<string>
     */
    private function subDepartmentIdsForParent(string $parentDepartmentId): array
    {
        $parent = Department::query()->find($parentDepartmentId);
        if (! $parent) {
            return [$parentDepartmentId];
        }

        $childIds = $parent->children()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        return $childIds !== [] ? $childIds : [(string) $parent->id];
    }

    /**
     * @return list<string>
     */
    private function rollupDepartmentIds(array $subDepartmentIds, string $parentDepartmentId): array
    {
        return array_values(array_unique([
            ...$subDepartmentIds,
            $parentDepartmentId,
        ]));
    }

    /**
     * Active staff who belong to sub-departments (directly or via sub_department_user).
     *
     * @return list<string>
     */
    private function activeStaffIdsForDepartments(array $subDepartmentIds, string $parentDepartmentId): array
    {
        $departmentIds = $this->rollupDepartmentIds($subDepartmentIds, $parentDepartmentId);
        if ($departmentIds === []) {
            return [];
        }

        $baseQuery = User::query()
            ->where('status', 'active')
            ->where(function ($query) use ($departmentIds, $subDepartmentIds): void {
                $query->whereIn('department_id', $departmentIds);

                if (Schema::hasTable('sub_department_user') && $subDepartmentIds !== []) {
                    $query->orWhereHas('subDepartments', function ($subQuery) use ($subDepartmentIds): void {
                        $subQuery->whereIn('departments.id', $subDepartmentIds);
                    });
                }
            });

        $staffIds = (clone $baseQuery)
            ->permission('create weekly plan')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($staffIds !== []) {
            return $staffIds;
        }

        return $baseQuery
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * Submitted weekly plans for staff in scope (domain tables only).
     *
     * @param  list<string>  $staffIds
     * @return Collection<int, WeeklyPlan>
     */
    private function submittedPlansForPeriodAndStaff(WorkPeriod $period, array $staffIds): Collection
    {
        if ($staffIds === []) {
            return collect();
        }

        /** @var EloquentCollection<int, WeeklyPlan> $domainPlans */
        $domainPlans = WeeklyPlan::query()
            ->where('period_id', $period->id)
            ->where('state', WeeklyPlan::STATE_SUBMITTED)
            ->whereIn('user_id', $staffIds)
            ->with([
                'activities' => fn ($query) => $query->orderBy('sort_order'),
                'budgetItems' => fn ($query) => $query->orderBy('sort_order'),
                'user:id,name,department_id',
                'user.department:id,name',
            ])
            ->latest('updated_at')
            ->get()
            ->unique('user_id')
            ->values();

        return $domainPlans;
    }

    private function collectActivitiesForPeriodAndDepartments(?WorkPeriod $period, array $subDepartmentIds, string $parentDepartmentId): Collection
    {
        if (! $period) {
            return collect();
        }

        $staffIds = $this->activeStaffIdsForDepartments($subDepartmentIds, $parentDepartmentId);
        if ($staffIds === []) {
            return collect();
        }

        $rows = collect();

        foreach ($this->submittedPlansForPeriodAndStaff($period, $staffIds) as $plan) {
            $sourceDocumentId = (string) $plan->id;
            $sourceWeeklyPlanId = (string) $plan->id;
            $staffName = $plan->user?->name ?? 'Unknown Staff';
            $subDepartmentName = $plan->user?->department?->name ?? 'Unknown Sub-department';

            foreach ($plan->activities as $index => $activity) {
                $activityText = trim((string) ($activity->activity ?? ''));
                if ($activityText === '') {
                    continue;
                }

                $activityUid = trim((string) ($activity->activity_uid ?? ''));
                if ($activityUid === '') {
                    $activityUid = 'row_'.$index;
                }

                $key = $sourceDocumentId.'::'.$activityUid;
                $rows->push([
                    'key' => $key,
                    'source_document_id' => $sourceDocumentId,
                    'source_weekly_plan_id' => $sourceWeeklyPlanId,
                    'activity_uid' => $activityUid,
                    'activity' => $activityText,
                    'staff_name' => $staffName,
                    'department_name' => $subDepartmentName,
                    'label' => sprintf('%s (%s) - %s', $staffName, $subDepartmentName, $activityText),
                ]);
            }
        }

        return $rows
            ->unique('key')
            ->sortBy(fn (array $row) => strtolower($row['staff_name'].' '.$row['activity']))
            ->values();
    }

    private function impactOptionsForPeriodYearAndDepartments(?WorkPeriod $period, array $departmentIds): Collection
    {
        if (! $period || $departmentIds === []) {
            return collect();
        }

        $yearPlan = YearPlan::query()->where('year', $period->year)->first();
        if (! $yearPlan) {
            return collect();
        }

        /** @var EloquentCollection<int, YearPlanActivity> $activities */
        $activities = YearPlanActivity::query()
            ->with('department:id,name,plan_section_number')
            ->where('year_plan_id', $yearPlan->id)
            ->whereIn('department_id', $departmentIds)
            ->orderBy('department_id')
            ->orderBy('sort_order')
            ->get();

        return $activities->map(function (YearPlanActivity $activity): array {
            $deptName = $activity->department?->name;

            return [
                'id' => (string) $activity->id,
                'label' => trim($activity->displayCode().' '.$activity->description.($deptName ? ' ['.$deptName.']' : '')),
                'department_name' => $deptName,
            ];
        })->values();
    }

    private function calculateDepartmentBudgetTotal(?WorkPeriod $period, array $subDepartmentIds, string $parentDepartmentId): float
    {
        if (! $period) {
            return 0.0;
        }

        $staffIds = $this->activeStaffIdsForDepartments($subDepartmentIds, $parentDepartmentId);
        if ($staffIds === []) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($this->submittedPlansForPeriodAndStaff($period, $staffIds) as $plan) {
            foreach ($plan->budgetItems as $item) {
                $total += (float) ($item->amount_numeric ?? 0);
            }
        }

        return round($total, 2);
    }

    /**
     * @return array{
     *   departments: array<int, array{
     *      name: string,
     *      total: float,
     *      members: array<int, array{name: string, total: float}>
     *   }>,
     *   overall_total: float
     * }
     */
    private function departmentBudgetBreakdown(?WorkPeriod $period, array $subDepartmentIds, string $parentDepartmentId): array
    {
        $empty = [
            'departments' => [],
            'overall_total' => 0.0,
        ];

        if (! $period) {
            return $empty;
        }

        $staffIds = $this->activeStaffIdsForDepartments($subDepartmentIds, $parentDepartmentId);
        if ($staffIds === []) {
            return $empty;
        }

        $plans = $this->submittedPlansForPeriodAndStaff($period, $staffIds);
        $departments = [];
        $overallTotal = 0.0;

        foreach ($plans as $plan) {
            $departmentName = (string) ($plan->user?->department?->name ?? 'Unknown Sub-department');
            $staffName = (string) ($plan->user?->name ?? 'Unknown Staff');
            $staffTotal = 0.0;

            foreach ($plan->budgetItems as $item) {
                $staffTotal += (float) ($item->amount_numeric ?? 0);
            }
            $staffTotal = round($staffTotal, 2);

            if (! isset($departments[$departmentName])) {
                $departments[$departmentName] = [
                    'name' => $departmentName,
                    'total' => 0.0,
                    'members' => [],
                ];
            }

            $departments[$departmentName]['members'][] = [
                'name' => $staffName,
                'total' => $staffTotal,
            ];
            $departments[$departmentName]['total'] += $staffTotal;
            $overallTotal += $staffTotal;
        }

        $departmentRows = collect($departments)
            ->map(function (array $department): array {
                $members = collect($department['members'])
                    ->sortBy(fn (array $member) => strtolower($member['name']))
                    ->values()
                    ->all();

                return [
                    'name' => $department['name'],
                    'total' => round((float) $department['total'], 2),
                    'members' => $members,
                ];
            })
            ->sortBy(fn (array $department) => strtolower($department['name']))
            ->values()
            ->all();

        return [
            'departments' => $departmentRows,
            'overall_total' => round($overallTotal, 2),
        ];
    }

    private function normalizeMatrixEntryForForm(array $entry): array
    {
        $impactIds = $entry['impact_year_plan_activity_ids'] ?? null;
        if (! is_array($impactIds) || $impactIds === []) {
            $legacyImpactId = trim((string) ($entry['impact_year_plan_activity_id'] ?? ''));
            $impactIds = $legacyImpactId !== '' ? [$legacyImpactId] : [];
        }

        return [
            'activity_key' => (string) ($entry['activity_key'] ?? ''),
            'input' => (string) ($entry['input'] ?? ''),
            'impact_year_plan_activity_ids' => array_values(array_map(
                fn ($id) => (string) $id,
                $impactIds
            )),
        ];
    }

    private function hydrateValidatedEntries(array $entries, Collection $activityMap, Collection $impactMap): array
    {
        $result = [];

        foreach ($entries as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $activityKey = trim((string) ($entry['activity_key'] ?? ''));
            $input = trim((string) ($entry['input'] ?? ''));
            $impactIds = collect($entry['impact_year_plan_activity_ids'] ?? [])
                ->map(fn ($id) => trim((string) $id))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($activityKey === '' || $input === '' || $impactIds === []) {
                continue;
            }

            $activity = $activityMap->get($activityKey);
            if (! is_array($activity)) {
                throw ValidationException::withMessages([
                    'entries.'.$index.'.activity_key' => 'Selected activity is invalid or no longer available.',
                ]);
            }

            $impactLabels = [];
            foreach ($impactIds as $impactIndex => $impactId) {
                $impact = $impactMap->get($impactId);
                if (! is_array($impact)) {
                    throw ValidationException::withMessages([
                        'entries.'.$index.'.impact_year_plan_activity_ids.'.$impactIndex => 'One or more selected impacts are invalid for this department year plan.',
                    ]);
                }
                $impactLabels[] = $impact['label'];
            }

            $result[] = [
                'activity_key' => $activityKey,
                'source_document_id' => $activity['source_document_id'],
                'source_weekly_plan_id' => $activity['source_weekly_plan_id'] ?? null,
                'activity_uid' => $activity['activity_uid'],
                'activity' => $activity['activity'],
                'activity_label' => $activity['label'],
                'input' => $input,
                'impact_year_plan_activity_ids' => $impactIds,
                'impact_labels' => $impactLabels,
            ];
        }

        if ($result === []) {
            throw ValidationException::withMessages([
                'entries' => 'Add at least one valid matrix activity row.',
            ]);
        }

        return array_values($result);
    }

    /**
     * @return array{expected_staff: int, submitted_staff: int, pending_staff: int, all_submitted: bool}
     */
    private function departmentPlanSubmissionSummary(?WorkPeriod $period, array $subDepartmentIds, string $parentDepartmentId): array
    {
        $empty = [
            'expected_staff' => 0,
            'submitted_staff' => 0,
            'pending_staff' => 0,
            'all_submitted' => false,
        ];

        if (! $period) {
            return $empty;
        }

        $expectedStaffIds = $this->activeStaffIdsForDepartments($subDepartmentIds, $parentDepartmentId);
        $expectedCount = count($expectedStaffIds);
        if ($expectedCount === 0) {
            return $empty;
        }

        $submittedStaffIds = $this->submittedPlansForPeriodAndStaff($period, $expectedStaffIds)
            ->map(function (WeeklyPlan $plan): string {
                return (string) $plan->user_id;
            })
            ->unique()
            ->values()
            ->all();

        $submittedCount = count($submittedStaffIds);

        return [
            'expected_staff' => $expectedCount,
            'submitted_staff' => $submittedCount,
            'pending_staff' => max(0, $expectedCount - $submittedCount),
            'all_submitted' => $submittedCount >= $expectedCount,
        ];
    }
}
