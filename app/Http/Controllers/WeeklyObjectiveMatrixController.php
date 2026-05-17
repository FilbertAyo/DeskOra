<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Department;
use App\Models\User;
use App\Models\WeeklyDepartmentMatrix;
use App\Models\WorkPeriod;
use App\Models\YearPlan;
use App\Models\YearPlanActivity;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
                    'submission_summary' => $this->departmentPlanSubmissionSummary($matrix->period, $subDepartmentIds),
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

        $matrix = WeeklyDepartmentMatrix::query()->firstOrNew([
            'period_id' => $validated['period_id'],
            'department_id' => $parentDepartment->id,
        ]);

        if (! $matrix->exists) {
            $matrix->created_by = $user->id;
            $matrix->entries = [];
        }

        $matrix->updated_by = $user->id;
        $matrix->save();

        $message = $matrix->wasRecentlyCreated
            ? 'Parent department matrix created. You can now add activities from all sub-departments.'
            : 'Parent department matrix already exists. You can continue editing it.';

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
        $availableActivities = $this->collectActivitiesForPeriodAndDepartments($period, $subDepartmentIds);
        $impactOptions = $this->impactOptionsForPeriodYearAndDepartments($period, $subDepartmentIds);

        $entryDefaults = old('entries');
        if (! is_array($entryDefaults)) {
            $entryDefaults = collect($matrix->entries ?? [])
                ->map(function (array $entry): array {
                    return [
                        'activity_key' => (string) ($entry['activity_key'] ?? ''),
                        'input' => (string) ($entry['input'] ?? ''),
                        'output' => (string) ($entry['output'] ?? ''),
                        'impact_year_plan_activity_id' => (string) ($entry['impact_year_plan_activity_id'] ?? ''),
                    ];
                })
                ->values()
                ->all();
        }

        if ($entryDefaults === []) {
            $entryDefaults = [['activity_key' => '', 'input' => '', 'output' => '', 'impact_year_plan_activity_id' => '']];
        }

        $planSubmissionSummary = $this->departmentPlanSubmissionSummary($period, $subDepartmentIds);
        $rolledUpBudget = $this->calculateDepartmentBudgetTotal($period, $subDepartmentIds);

        return view('matrix-report.edit', [
            'matrix' => $matrix->load(['department:id,name,parent_id', 'period:id,year,week_number']),
            'availableActivities' => $availableActivities,
            'impactOptions' => $impactOptions,
            'planSubmissionSummary' => $planSubmissionSummary,
            'rolledUpBudget' => $rolledUpBudget,
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
            'entries.*.output' => ['required', 'string', 'max:2000'],
            'entries.*.impact_year_plan_activity_id' => ['required', 'string', 'ulid'],
        ]);

        $subDepartmentIds = $this->subDepartmentIdsForParent((string) $matrix->department_id);
        $activityMap = $this->collectActivitiesForPeriodAndDepartments($period, $subDepartmentIds)->keyBy('key');
        $impactMap = $this->impactOptionsForPeriodYearAndDepartments($period, $subDepartmentIds)->keyBy('id');
        $entries = $this->hydrateValidatedEntries($validated['entries'], $activityMap, $impactMap);

        $matrix->update([
            'overall_budget' => $validated['overall_budget'] ?? null,
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

        $matrix->delete();

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

        $matrixRows = Document::query()
            ->where('type', Document::TYPE_WEEKLY_PLAN)
            ->where('state', Document::STATE_SUBMITTED)
            ->where('period_id', $currentPeriod->id)
            ->whereNotNull('main_target')
            ->where('main_target', '!=', '')
            ->with(['user:id,name,department_id', 'user.department:id,name'])
            ->latest('updated_at')
            ->get(['user_id', 'main_target', 'content', 'updated_at'])
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

    private function collectActivitiesForPeriodAndDepartments(?WorkPeriod $period, array $departmentIds): Collection
    {
        if (! $period || $departmentIds === []) {
            return collect();
        }

        /** @var EloquentCollection<int, Document> $plans */
        $plans = Document::query()
            ->where('type', Document::TYPE_WEEKLY_PLAN)
            ->where('state', Document::STATE_SUBMITTED)
            ->where('period_id', $period->id)
            ->whereHas('user', function ($query) use ($departmentIds): void {
                $query->whereIn('department_id', $departmentIds);
            })
            ->with(['user:id,name,department_id', 'user.department:id,name'])
            ->latest('updated_at')
            ->get(['id', 'user_id', 'state', 'content', 'updated_at'])
            ->groupBy('user_id')
            ->map(fn ($userPlans) => $userPlans->first())
            ->values();

        $rows = collect();
        foreach ($plans as $plan) {
            $planContent = is_array($plan->content) ? $plan->content : [];
            $activities = is_array($planContent['planned_activities'] ?? null) ? $planContent['planned_activities'] : [];

            foreach ($activities as $index => $activity) {
                if (! is_array($activity)) {
                    continue;
                }

                $activityText = trim((string) ($activity['activity'] ?? ''));
                if ($activityText === '') {
                    continue;
                }

                $activityUid = trim((string) ($activity['activity_uid'] ?? ''));
                if ($activityUid === '') {
                    $activityUid = 'row_'.$index;
                }

                $staffName = $plan->user?->name ?? 'Unknown Staff';
                $subDepartmentName = $plan->user?->department?->name ?? 'Unknown Sub-department';

                $key = $plan->id.'::'.$activityUid;
                $rows->push([
                    'key' => $key,
                    'source_document_id' => (string) $plan->id,
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

    private function calculateDepartmentBudgetTotal(?WorkPeriod $period, array $subDepartmentIds): float
    {
        if (! $period || $subDepartmentIds === []) {
            return 0.0;
        }

        $plans = Document::query()
            ->where('type', Document::TYPE_WEEKLY_PLAN)
            ->where('state', Document::STATE_SUBMITTED)
            ->where('period_id', $period->id)
            ->whereHas('user', function ($query) use ($subDepartmentIds): void {
                $query->whereIn('department_id', $subDepartmentIds);
            })
            ->get();

        $total = 0.0;
        foreach ($plans as $plan) {
            foreach ($plan->budgetItems() as $item) {
                $total += (float) ($item['amount_numeric'] ?? 0);
            }
        }

        return round($total, 2);
    }

    private function hydrateValidatedEntries(array $entries, Collection $activityMap, Collection $impactMap): array
    {
        $result = [];

        foreach ($entries as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $activityKey = trim((string) ($entry['activity_key'] ?? ''));
            $impactId = trim((string) ($entry['impact_year_plan_activity_id'] ?? ''));
            $input = trim((string) ($entry['input'] ?? ''));
            $output = trim((string) ($entry['output'] ?? ''));

            if ($activityKey === '' || $input === '' || $output === '' || $impactId === '') {
                continue;
            }

            $activity = $activityMap->get($activityKey);
            if (! is_array($activity)) {
                throw ValidationException::withMessages([
                    'entries.'.$index.'.activity_key' => 'Selected activity is invalid or no longer available.',
                ]);
            }

            $impact = $impactMap->get($impactId);
            if (! is_array($impact)) {
                throw ValidationException::withMessages([
                    'entries.'.$index.'.impact_year_plan_activity_id' => 'Selected impact is invalid for this department year plan.',
                ]);
            }

            $result[] = [
                'activity_key' => $activityKey,
                'source_document_id' => $activity['source_document_id'],
                'activity_uid' => $activity['activity_uid'],
                'activity' => $activity['activity'],
                'activity_label' => $activity['label'],
                'input' => $input,
                'output' => $output,
                'impact_year_plan_activity_id' => $impactId,
                'impact_label' => $impact['label'],
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
    private function departmentPlanSubmissionSummary(?WorkPeriod $period, array $departmentIds): array
    {
        $empty = [
            'expected_staff' => 0,
            'submitted_staff' => 0,
            'pending_staff' => 0,
            'all_submitted' => false,
        ];

        if (! $period || $departmentIds === []) {
            return $empty;
        }

        $expectedStaffIds = User::query()
            ->where('status', 'active')
            ->whereIn('department_id', $departmentIds)
            ->permission('create weekly plan')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($expectedStaffIds === []) {
            $expectedStaffIds = User::query()
                ->where('status', 'active')
                ->whereIn('department_id', $departmentIds)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();
        }

        $expectedCount = count($expectedStaffIds);
        if ($expectedCount === 0) {
            return $empty;
        }

        $submittedStaffIds = Document::query()
            ->where('type', Document::TYPE_WEEKLY_PLAN)
            ->where('state', Document::STATE_SUBMITTED)
            ->where('period_id', $period->id)
            ->whereIn('user_id', $expectedStaffIds)
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
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
