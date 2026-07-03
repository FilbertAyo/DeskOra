<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Models\WeeklyMatrix;
use App\Models\WorkPeriod;
use App\Services\WeeklyMatrixService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WeeklyMatrixController extends Controller
{
    public function __construct(
        private readonly WeeklyMatrixService $weeklyMatrixService,
    ) {}

    public function index(Request $request)
    {
        $year = (int) $request->query('year', (int) date('Y'));
        $availableYears = WorkPeriod::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($y) => (int) $y)
            ->all();

        if ($availableYears === []) {
            $availableYears = [$year];
        }

        if (! in_array($year, $availableYears, true)) {
            $year = $availableYears[0];
        }

        $periodCards = $this->weeklyMatrixService->periodsForCalendar($year);

        return view('weekly-matrix.index', [
            'year' => $year,
            'availableYears' => $availableYears,
            'periodCards' => $periodCards,
        ]);
    }

    public function period(WorkPeriod $period)
    {
        $subDepartments = $this->weeklyMatrixService->subDepartmentsWithMatrixStatus($period);

        return view('weekly-matrix.period', [
            'period' => $period,
            'subDepartments' => $subDepartments,
        ]);
    }

    public function edit(WorkPeriod $period, Department $department)
    {
        if ($department->parent_id === null) {
            abort(404);
        }

        $matrix = WeeklyMatrix::query()
            ->where('period_id', $period->id)
            ->where('department_id', $department->id)
            ->with(['entries.yearPlanActivities.department'])
            ->first();

        $yearPlanOptions = $this->weeklyMatrixService->yearPlanOptionsForSubDepartment($period, $department);
        $suggestions = $this->weeklyMatrixService->suggestionsFromWeeklyPlans($period, $department);

        $entryDefaults = old('entries');
        if (! is_array($entryDefaults)) {
            $entryDefaults = $matrix
                ? $matrix->entries->map(fn ($entry) => [
                    'key_activity' => $entry->key_activity,
                    'output' => $entry->output ?? '',
                    'outcome' => $entry->outcome ?? '',
                    'impact' => $entry->impact ?? '',
                    'indicators' => $entry->indicators ?? '',
                    'baseline' => $entry->baseline ?? '',
                    'target' => $entry->target ?? '',
                    'means_of_verification' => $entry->means_of_verification ?? '',
                    'year_plan_activity_ids' => $entry->yearPlanActivities->pluck('id')->all(),
                ])->values()->all()
                : [];
        }

        if ($entryDefaults === []) {
            $entryDefaults = [[
                'key_activity' => '',
                'output' => '',
                'outcome' => '',
                'impact' => '',
                'indicators' => '',
                'baseline' => '',
                'target' => '',
                'means_of_verification' => '',
                'year_plan_activity_ids' => [],
            ]];
        }

        $viewMode = request()->boolean('view');

        return view('weekly-matrix.edit', [
            'period' => $period,
            'department' => $department->load('parent'),
            'matrix' => $matrix,
            'entryDefaults' => $entryDefaults,
            'yearPlanOptions' => $yearPlanOptions,
            'suggestions' => $suggestions,
            'viewMode' => $viewMode,
        ]);
    }

    public function store(Request $request, WorkPeriod $period, Department $department)
    {
        return $this->saveMatrix($request, $period, $department, isCreate: true);
    }

    public function update(Request $request, WorkPeriod $period, Department $department)
    {
        return $this->saveMatrix($request, $period, $department, isCreate: false);
    }

    public function destroy(WorkPeriod $period, Department $department)
    {
        $this->weeklyMatrixService->ensureSubDepartment($department);

        $matrix = WeeklyMatrix::query()
            ->where('period_id', $period->id)
            ->where('department_id', $department->id)
            ->first();

        if ($matrix) {
            $matrix->delete();
        }

        return redirect()
            ->route('weekly-matrix.period', $period)
            ->with('success', 'Weekly matrix deleted.');
    }

    public function suggestions(WorkPeriod $period, Department $department)
    {
        $this->weeklyMatrixService->ensureSubDepartment($department);

        return response()->json([
            'suggestions' => $this->weeklyMatrixService
                ->suggestionsFromWeeklyPlans($period, $department)
                ->values(),
        ]);
    }

    private function saveMatrix(Request $request, WorkPeriod $period, Department $department, bool $isCreate)
    {
        /** @var User $user */
        $user = $request->user();
        $this->weeklyMatrixService->ensureSubDepartment($department);

        $validated = $request->validate([
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.key_activity' => ['required', 'string', 'max:20000'],
            'entries.*.output' => ['nullable', 'string', 'max:20000'],
            'entries.*.outcome' => ['nullable', 'string', 'max:20000'],
            'entries.*.impact' => ['nullable', 'string', 'max:20000'],
            'entries.*.indicators' => ['nullable', 'string', 'max:20000'],
            'entries.*.baseline' => ['nullable', 'string', 'max:20000'],
            'entries.*.target' => ['nullable', 'string', 'max:20000'],
            'entries.*.means_of_verification' => ['nullable', 'string', 'max:20000'],
            'entries.*.year_plan_activity_ids' => ['nullable', 'array'],
            'entries.*.year_plan_activity_ids.*' => ['string', 'short_id', 'exists:year_plan_activities,id'],
        ]);

        $existing = WeeklyMatrix::query()
            ->where('period_id', $period->id)
            ->where('department_id', $department->id)
            ->first();

        if ($isCreate && $existing) {
            throw ValidationException::withMessages([
                'entries' => 'A matrix already exists for this sub-department and work period. Use update instead.',
            ]);
        }

        if (! $isCreate && ! $existing) {
            throw ValidationException::withMessages([
                'entries' => 'No matrix exists yet for this sub-department and work period.',
            ]);
        }

        $matrix = $existing ?? WeeklyMatrix::create([
            'period_id' => $period->id,
            'department_id' => $department->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->weeklyMatrixService->syncEntries($matrix, $validated['entries'], $user);

        return redirect()
            ->route('weekly-matrix.edit', [$period, $department])
            ->with('success', $existing ? 'Weekly matrix updated.' : 'Weekly matrix created.');
    }
}
