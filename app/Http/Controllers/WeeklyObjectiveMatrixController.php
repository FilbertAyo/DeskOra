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
use Illuminate\Validation\ValidationException;

class WeeklyObjectiveMatrixController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $currentPeriod = WorkPeriod::getCurrent();
        $departmentScopeIds = $this->resolveDepartmentIdsForMatrix($user);
        $activeDepartmentId = $user->department_id;
        $matrixPeriod = $currentPeriod;
        $planSubmissionSummary = $this->departmentPlanSubmissionSummary($matrixPeriod, $departmentScopeIds);

        $matrixRows = collect();
        if ($currentPeriod) {
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
        }

        $departmentMatrices = WeeklyDepartmentMatrix::query()
            ->with(['department:id,name', 'period:id,week_number,year', 'creator:id,name', 'updater:id,name'])
            ->whereIn('department_id', $departmentScopeIds)
            ->when(
                $matrixPeriod,
                fn ($query) => $query->where('period_id', $matrixPeriod->id),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->latest('updated_at')
            ->get();

        $availableActivities = $this->collectActivitiesForPeriodAndDepartments(
            $matrixPeriod,
            $departmentScopeIds
        );
        $impactOptions = $this->impactOptionsForPeriodYearAndDepartments(
            $matrixPeriod,
            $departmentScopeIds
        );

        return view('matrix-report.index', [
            'currentPeriod' => $currentPeriod,
            'matrixRows' => $matrixRows,
            'departmentMatrices' => $departmentMatrices,
            'availableActivities' => $availableActivities,
            'impactOptions' => $impactOptions,
            'activeDepartmentId' => $activeDepartmentId,
            'matrixPeriod' => $matrixPeriod,
            'planSubmissionSummary' => $planSubmissionSummary,
            'entryDefaults' => old('entries', [['activity_key' => '', 'input' => '', 'output' => '', 'impact_year_plan_activity_id' => '']]),
        ]);
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $departmentId = $user->department_id;
        if (! $departmentId) {
            return back()->withErrors([
                'entries' => 'Your account is not assigned to a department.',
            ])->withInput();
        }

        $departmentScopeIds = $this->resolveDepartmentIdsForMatrix($user);
        $currentPeriod = WorkPeriod::getCurrent();

        $validated = $request->validate([
            'period_id' => ['nullable', 'string', 'ulid', 'exists:work_periods,id'],
            'overall_budget' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.activity_key' => ['required', 'string'],
            'entries.*.input' => ['required', 'string', 'max:2000'],
            'entries.*.output' => ['required', 'string', 'max:2000'],
            'entries.*.impact_year_plan_activity_id' => ['required', 'string', 'ulid'],
        ]);

        $targetPeriod = null;
        if (! empty($validated['period_id'])) {
            $targetPeriod = WorkPeriod::query()->find($validated['period_id']);
        }
        if (! $targetPeriod) {
            $targetPeriod = $currentPeriod;
        }
        if (! $targetPeriod) {
            return back()->with('error', 'No active work period found for matrix creation.')->withInput();
        }

        $activityMap = $this->collectActivitiesForPeriodAndDepartments($targetPeriod, $departmentScopeIds)->keyBy('key');
        if ($activityMap->isEmpty()) {
            return back()
                ->withErrors(['entries' => 'No submitted weekly-plan activities are available yet. Wait for staff to submit plans, or add late submissions later via Edit.'])
                ->withInput();
        }
        $impactMap = $this->impactOptionsForPeriodYearAndDepartments($targetPeriod, $departmentScopeIds)->keyBy('id');
        $entries = $this->hydrateValidatedEntries($validated['entries'], $activityMap, $impactMap);

        $matrix = WeeklyDepartmentMatrix::query()->firstOrNew([
            'period_id' => $targetPeriod->id,
            'department_id' => $departmentId,
        ]);

        if (! $matrix->exists) {
            $matrix->created_by = $user->id;
        }

        $matrix->overall_budget = $validated['overall_budget'] ?? null;
        $matrix->notes = $validated['notes'] ?? null;
        $matrix->entries = $this->mergeMatrixEntries(is_array($matrix->entries) ? $matrix->entries : [], $entries);
        $matrix->updated_by = $user->id;
        $matrix->save();

        return redirect()
            ->route('matrix-report.index')
            ->with('success', 'Department matrix saved successfully.');
    }

    public function edit(Request $request, WeeklyDepartmentMatrix $matrix)
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanManageMatrix($user, $matrix);

        $period = $matrix->period()->first();
        $departmentIds = $this->resolveDepartmentIdsFromDepartmentId($matrix->department_id);
        $availableActivities = $this->collectActivitiesForPeriodAndDepartments($period, $departmentIds);
        $impactOptions = $this->impactOptionsForPeriodYearAndDepartments($period, $departmentIds);

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

        $planSubmissionSummary = $this->departmentPlanSubmissionSummary($period, $departmentIds);

        return view('matrix-report.edit', [
            'matrix' => $matrix->load(['department:id,name', 'period:id,year,week_number']),
            'availableActivities' => $availableActivities,
            'impactOptions' => $impactOptions,
            'planSubmissionSummary' => $planSubmissionSummary,
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

        $departmentIds = $this->resolveDepartmentIdsFromDepartmentId($matrix->department_id);
        $activityMap = $this->collectActivitiesForPeriodAndDepartments($period, $departmentIds)->keyBy('key');
        $impactMap = $this->impactOptionsForPeriodYearAndDepartments($period, $departmentIds)->keyBy('id');
        $entries = $this->hydrateValidatedEntries($validated['entries'], $activityMap, $impactMap);

        $matrix->update([
            'overall_budget' => $validated['overall_budget'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'entries' => $entries,
            'updated_by' => $user->id,
        ]);

        return redirect()
            ->route('matrix-report.index')
            ->with('success', 'Department matrix updated successfully.');
    }

    public function destroy(Request $request, WeeklyDepartmentMatrix $matrix)
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanManageMatrix($user, $matrix);

        $matrix->delete();

        return redirect()
            ->route('matrix-report.index')
            ->with('success', 'Department matrix deleted successfully.');
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
        abort_unless(in_array($matrix->department_id, $user->departmentScopeIds(), true), 403);
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

                $key = $plan->id.'::'.$activityUid;
                $rows->push([
                    'key' => $key,
                    'source_document_id' => (string) $plan->id,
                    'activity_uid' => $activityUid,
                    'activity' => $activityText,
                    'staff_name' => $plan->user?->name ?? 'Unknown Staff',
                    'department_name' => $plan->user?->department?->name ?? 'Unknown Department',
                    'label' => ($plan->user?->name ?? 'Unknown Staff').' - '.$activityText,
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
            return [
                'id' => (string) $activity->id,
                'label' => $activity->displayCode().' '.$activity->description,
                'department_name' => $activity->department?->name,
            ];
        })->values();
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
                    'entries.'.$index.'.impact_year_plan_activity_id' => 'Selected impact is invalid for your department plan.',
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

    private function resolveDepartmentIdsForMatrix(User $user): array
    {
        $scopeIds = $user->departmentScopeIds();
        if (! $user->department_id) {
            return $scopeIds;
        }

        $ownDepartment = Department::query()->find($user->department_id);
        if (! $ownDepartment) {
            return [$user->department_id];
        }

        $departmentIds = $ownDepartment->selfAndDescendantIds();
        if ($scopeIds !== []) {
            $departmentIds = array_values(array_intersect($departmentIds, $scopeIds));
        }

        return $departmentIds !== [] ? $departmentIds : [$user->department_id];
    }

    private function resolveDepartmentIdsFromDepartmentId(string $departmentId): array
    {
        $department = Department::query()->find($departmentId);
        if (! $department) {
            return [$departmentId];
        }

        return $department->selfAndDescendantIds();
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

    /**
     * Keep existing rows and add/update by activity_key (for late staff submissions).
     *
     * @param  list<array<string, mixed>>  $existing
     * @param  list<array<string, mixed>>  $incoming
     * @return list<array<string, mixed>>
     */
    private function mergeMatrixEntries(array $existing, array $incoming): array
    {
        $byKey = [];
        foreach ($existing as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['activity_key'] ?? ''));
            if ($key !== '') {
                $byKey[$key] = $row;
            }
        }

        foreach ($incoming as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['activity_key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $byKey[$key] = $row;
        }

        return array_values($byKey);
    }
}
