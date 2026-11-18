<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Models\WeeklyMatrix;
use App\Models\WorkPeriod;
use App\Services\WeeklyMatrixService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

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

        return Inertia::render('WeeklyMatrix/Index', [
            'year' => $year,
            'availableYears' => $availableYears,
            'periodCards' => $periodCards->map(fn (array $card) => [
                'period' => $this->periodPayload($card['period']),
                'matrixCount' => $card['matrix_count'],
                'subDepartmentCount' => $card['sub_department_count'],
                'isCurrent' => $card['is_current'],
                'url' => route('weekly-matrix.period', $card['period']),
            ])->values(),
        ]);
    }

    public function period(Request $request, WorkPeriod $period)
    {
        $matrices = $this->weeklyMatrixService->matricesForPeriodTable($period);
        $availableSubDepartments = $this->weeklyMatrixService->subDepartmentsWithoutMatrix($period);
        $canCreate = $availableSubDepartments->isNotEmpty();

        $modal = (string) $request->query('modal', '');
        if (! in_array($modal, ['create', 'edit', 'view'], true)) {
            $modal = '';
        }

        $modalDepartmentId = (string) $request->query(
            'department',
            (string) $request->query('department_id', '')
        );
        if ($modal === 'create' && ! $canCreate) {
            $modal = '';
            $modalDepartmentId = '';
        }
        if ($modal === 'create' && $modalDepartmentId !== '') {
            $hasOpenDepartment = $availableSubDepartments->contains(
                fn (Department $department) => (string) $department->id === $modalDepartmentId
            );
            if (! $hasOpenDepartment) {
                $modalDepartmentId = '';
            }
        }
        if (in_array($modal, ['edit', 'view'], true)) {
            $hasDepartment = $matrices->contains(
                fn (array $row) => (string) ($row['department']?->id ?? '') === $modalDepartmentId
            );
            if (! $hasDepartment) {
                $modal = '';
                $modalDepartmentId = '';
            }
        }

        return Inertia::render('WeeklyMatrix/Period', [
            'period' => $this->periodPayload($period),
            'matrices' => $matrices->map(fn (array $row) => [
                'department' => $row['department'] ? [
                    'id' => (string) $row['department']->id,
                    'name' => $row['department']->name,
                    'parentName' => $row['department']->parent?->name,
                ] : null,
                'entry' => $this->entryPayload($row['entry']),
                'yearPlanLinkCount' => $row['year_plan_link_count'],
                'updatedAt' => $row['matrix']->updated_at?->diffForHumans(),
                'updateUrl' => $row['department']
                    ? route('weekly-matrix.update', [$period, $row['department']])
                    : null,
                'destroyUrl' => $row['department']
                    ? route('weekly-matrix.destroy', [$period, $row['department']])
                    : null,
            ])->values(),
            'availableSubDepartments' => $availableSubDepartments->map(fn (Department $department) => [
                'id' => (string) $department->id,
                'name' => $department->name,
                'parentName' => $department->parent?->name,
            ])->values(),
            'canCreate' => $canCreate,
            'modal' => $modal !== '' ? $modal : null,
            'modalDepartmentId' => $modalDepartmentId !== '' ? $modalDepartmentId : null,
            'indexUrl' => route('weekly-matrix.index', ['year' => $period->year]),
            'createSubmitUrl' => route('weekly-matrix.create.store', $period),
            'downloadUrl' => route('weekly-matrix.download', $period),
            'tableUrl' => route('weekly-matrix.period.table', $period),
        ]);
    }

    public function periodTable(WorkPeriod $period)
    {
        $matrices = $this->weeklyMatrixService->matricesForPeriodTable($period);

        $html = view('weekly-matrix.partials.matrix-table', [
            'period' => $period,
            'matrices' => $matrices,
        ])->render();

        return response()->json([
            'html' => $html,
            'week_number' => $period->week_number,
            'year' => $period->year,
            'date_range' => $period->date_range,
        ]);
    }

    public function download(WorkPeriod $period)
    {
        $matrices = $this->weeklyMatrixService->matricesForPeriodTable($period);

        $pdf = Pdf::loadView('weekly-matrix.pdf', [
            'period' => $period,
            'matrices' => $matrices,
        ])->setPaper('a4', 'landscape');

        $filename = 'weekly_matrix_week_'.$period->week_number.'_'.$period->year.'.pdf';

        return $pdf->download($filename);
    }

    public function create(Request $request, WorkPeriod $period)
    {
        $availableSubDepartments = $this->weeklyMatrixService->subDepartmentsWithoutMatrix($period);

        if ($availableSubDepartments->isEmpty()) {
            return redirect()
                ->route('weekly-matrix.period', $period)
                ->with('error', 'All sub-departments already have a matrix for this work period.');
        }

        return redirect()->route('weekly-matrix.period', [
            'period' => $period,
            'modal' => 'create',
            'department_id' => $request->query('department_id'),
        ]);
    }

    public function storeFromCreate(Request $request, WorkPeriod $period)
    {
        /** @var User $user */
        $user = $request->user();

        $availableIds = $this->weeklyMatrixService
            ->subDepartmentsWithoutMatrix($period)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $validated = $request->validate([
            'department_id' => ['required', 'string', 'short_id', Rule::in($availableIds)],
            'entry' => ['required', 'array'],
            'entry.key_activity' => ['required', 'string', 'max:20000'],
            'entry.output' => ['nullable', 'string', 'max:20000'],
            'entry.outcome' => ['nullable', 'string', 'max:20000'],
            'entry.impact' => ['nullable', 'string', 'max:20000'],
            'entry.indicators' => ['nullable', 'string', 'max:20000'],
            'entry.baseline' => ['nullable', 'string', 'max:20000'],
            'entry.target' => ['nullable', 'string', 'max:20000'],
            'entry.means_of_verification' => ['nullable', 'string', 'max:20000'],
            'entry.year_plan_activity_ids' => ['nullable', 'array'],
            'entry.year_plan_activity_ids.*' => ['string', 'short_id', 'exists:year_plan_activities,id'],
        ]);

        $department = Department::query()->findOrFail($validated['department_id']);
        $this->weeklyMatrixService->ensureSubDepartment($department);

        $matrix = WeeklyMatrix::create([
            'period_id' => $period->id,
            'department_id' => $department->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->weeklyMatrixService->syncEntries($matrix, [$validated['entry']], $user);

        return redirect()
            ->route('weekly-matrix.period', $period)
            ->with('success', 'Weekly matrix created for '.$department->name.'.');
    }

    public function edit(WorkPeriod $period, Department $department)
    {
        if ($department->parent_id === null) {
            abort(404);
        }

        $matrix = WeeklyMatrix::query()
            ->where('period_id', $period->id)
            ->where('department_id', $department->id)
            ->first();

        if (! $matrix) {
            return redirect()
                ->route('weekly-matrix.period', ['period' => $period, 'modal' => 'create'])
                ->with('error', 'Create a matrix for this sub-department first.');
        }

        return redirect()->route('weekly-matrix.period', [
            'period' => $period,
            'modal' => request()->boolean('view') ? 'view' : 'edit',
            'department' => $department->id,
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
            'entries' => ['required', 'array', 'size:1'],
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
            ->route('weekly-matrix.period', $period)
            ->with('success', $existing ? 'Weekly matrix updated.' : 'Weekly matrix created.');
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyEntry(): array
    {
        return [
            'key_activity' => '',
            'output' => '',
            'outcome' => '',
            'impact' => '',
            'indicators' => '',
            'baseline' => '',
            'target' => '',
            'means_of_verification' => '',
            'year_plan_activity_ids' => [],
        ];
    }

    /**
     * @param  \App\Models\WeeklyMatrixEntry|null  $entry
     * @return array{
     *     key_activity: string,
     *     output: string,
     *     outcome: string,
     *     impact: string,
     *     indicators: string,
     *     baseline: string,
     *     target: string,
     *     means_of_verification: string,
     *     year_plan_activity_ids: list<string>
     * }
     */
    private function entryPayload($entry): array
    {
        if (! $entry) {
            return $this->emptyEntry();
        }

        return [
            'key_activity' => (string) ($entry->key_activity ?? ''),
            'output' => (string) ($entry->output ?? ''),
            'outcome' => (string) ($entry->outcome ?? ''),
            'impact' => (string) ($entry->impact ?? ''),
            'indicators' => (string) ($entry->indicators ?? ''),
            'baseline' => (string) ($entry->baseline ?? ''),
            'target' => (string) ($entry->target ?? ''),
            'means_of_verification' => (string) ($entry->means_of_verification ?? ''),
            'year_plan_activity_ids' => $entry->yearPlanActivities
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all(),
        ];
    }

    private function periodPayload(WorkPeriod $period): array
    {
        return [
            'id' => $period->id,
            'weekNumber' => $period->week_number,
            'year' => $period->year,
            'dateRange' => $period->date_range,
            'status' => $period->status,
        ];
    }
}
