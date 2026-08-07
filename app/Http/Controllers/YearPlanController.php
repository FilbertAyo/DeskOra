<?php

namespace App\Http\Controllers;

use App\Exports\YearPlanExport;
use App\Models\Department;
use App\Models\YearPlan;
use App\Models\YearPlanActivity;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class YearPlanController extends Controller
{
    public function index()
    {
        $monthLabels = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        $plans = YearPlan::query()
            ->with(['activities.department.parent'])
            ->withCount('activities')
            ->orderByDesc('year')
            ->get();

        return view('year-plan.index', compact('plans', 'monthLabels'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100', 'unique:year_plans,year'],
        ]);

        YearPlan::create([
            'year' => $validated['year'],
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('year-plan.show', $validated['year'])
            ->with('success', 'Year plan created.');
    }

    public function show(YearPlan $year)
    {
        $built = $this->buildSections($year);

        $subDepartments = Department::query()
            ->whereNotNull('parent_id')
            ->where('status', 'active')
            ->with('parent')
            ->orderBy('name')
            ->get();

        return view('year-plan.show', [
            'yearPlan' => $year,
            'sections' => $built['sections'],
            'grandTotal' => $built['grandTotal'],
            'subDepartments' => $subDepartments,
        ]);
    }

    public function download(Request $request, YearPlan $year): Response|BinaryFileResponse
    {
        $built = $this->buildSections($year);

        return $this->downloadExport(
            $request,
            $year,
            $built['sections'],
            $built['grandTotal'],
            "year_plan_{$year->year}",
            null,
        );
    }

    public function downloadDepartment(Request $request, YearPlan $year, Department $department): Response|BinaryFileResponse
    {
        abort_unless($department->parent_id !== null, 404);

        $built = $this->buildSections($year, $department);

        abort_if($built['sections']->isEmpty(), 404, 'No activities for this sub-department in the year plan.');

        $deptLabel = $department->parent
            ? $department->parent->name.' — '.$department->name
            : $department->name;

        $slug = Str::slug($department->name, '_');

        return $this->downloadExport(
            $request,
            $year,
            $built['sections'],
            $built['grandTotal'],
            "year_plan_{$year->year}_{$slug}",
            $deptLabel,
        );
    }

    public function storeActivity(Request $request, YearPlan $year)
    {
        $validated = $this->validateActivityPayload($request);

        $nextSort = YearPlanActivity::query()
            ->where('year_plan_id', $year->id)
            ->where('department_id', $validated['department_id'])
            ->max('sort_order');

        YearPlanActivity::create([
            'year_plan_id' => $year->id,
            'department_id' => $validated['department_id'],
            'sort_order' => (int) $nextSort + 1,
            'description' => $validated['description'],
            'frequency' => $validated['frequency'],
            'unit_count' => $validated['unit_count'],
            'unit_value_tzs' => $validated['unit_value_tzs'],
            'planned_months' => $validated['planned_months'] ?? [],
        ]);

        return redirect()
            ->route('year-plan.show', $year)
            ->with('success', 'Activity added.');
    }

    public function updateActivity(Request $request, YearPlan $year, YearPlanActivity $activity)
    {
        abort_unless($activity->year_plan_id === $year->id, 404);

        $validated = $this->validateActivityPayload($request, $year, $activity);

        $activity->update([
            'department_id' => $validated['department_id'],
            'sort_order' => $validated['sort_order'],
            'description' => $validated['description'],
            'frequency' => $validated['frequency'],
            'unit_count' => $validated['unit_count'],
            'unit_value_tzs' => $validated['unit_value_tzs'],
            'planned_months' => $validated['planned_months'] ?? [],
        ]);

        return redirect()
            ->route('year-plan.show', $year)
            ->with('success', 'Activity updated.');
    }

    public function destroyActivity(YearPlan $year, YearPlanActivity $activity)
    {
        abort_unless($activity->year_plan_id === $year->id, 404);

        $activity->delete();

        return redirect()
            ->route('year-plan.show', $year)
            ->with('success', 'Activity removed.');
    }

    /**
     * @return array{sections: Collection, grandTotal: float}
     */
    private function buildSections(YearPlan $year, ?Department $onlyDepartment = null): array
    {
        $query = $year->activities()->with(['department.parent']);

        if ($onlyDepartment !== null) {
            $query->where('department_id', $onlyDepartment->id);
        }

        $activities = $query->get();
        $groupedByDept = $activities->groupBy('department_id');

        $sortedDepartments = Department::query()
            ->whereIn('id', $groupedByDept->keys())
            ->with('parent')
            ->get()
            ->sortBy(function (Department $d) {
                return [
                    sprintf('%05d', $d->plan_section_number ?? 99999),
                    $d->name,
                ];
            })
            ->values();

        $sections = $sortedDepartments->map(function (Department $dept) use ($groupedByDept) {
            $rows = $groupedByDept->get($dept->id, collect())->sortBy('sort_order')->values();
            $subtotal = $rows->sum(fn (YearPlanActivity $a) => (float) (string) $a->total_cost_tzs);

            return [
                'department' => $dept,
                'activities' => $rows,
                'subtotal' => $subtotal,
            ];
        });

        $grandTotal = $activities->sum(fn (YearPlanActivity $a) => (float) (string) $a->total_cost_tzs);

        return [
            'sections' => $sections,
            'grandTotal' => $grandTotal,
        ];
    }

    private function downloadExport(
        Request $request,
        YearPlan $year,
        Collection $sections,
        float $grandTotal,
        string $filenameBase,
        ?string $departmentTitle,
    ): Response|BinaryFileResponse {
        $format = $request->get('format', 'pdf');

        if ($format === 'excel') {
            $rawTitle = $departmentTitle
                ? 'YP '.$year->year.' '.$departmentTitle
                : 'Year Plan '.$year->year;
            $sheetTitle = Str::limit(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '-', $rawTitle) ?? $rawTitle, 31, '');

            return Excel::download(
                new YearPlanExport($sections, (int) $year->year, $grandTotal, $sheetTitle),
                $filenameBase.'.xlsx'
            );
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('year-plan.pdf', [
                'yearPlan' => $year,
                'sections' => $sections,
                'grandTotal' => $grandTotal,
                'departmentTitle' => $departmentTitle,
            ])->setPaper('a4', 'landscape');

            return $pdf->download($filenameBase.'.pdf');
        }

        return back()->with('error', 'Invalid format requested.');
    }

    private function validateActivityPayload(Request $request, ?YearPlan $yearPlan = null, ?YearPlanActivity $existing = null): array
    {
        $subDeptRule = Rule::exists('departments', 'id')->where(function ($q) {
            $q->whereNotNull('parent_id');
        });

        $rules = [
            'department_id' => ['required', 'string', 'short_id', $subDeptRule],
            'description' => ['required', 'string', 'max:65535'],
            'frequency' => ['required', 'numeric', 'min:0'],
            'unit_count' => ['required', 'numeric', 'min:0'],
            'unit_value_tzs' => ['required', 'numeric', 'min:0'],
            'planned_months' => ['nullable', 'array'],
            'planned_months.*' => ['integer', 'min:1', 'max:12'],
        ];

        if ($yearPlan !== null && $existing !== null) {
            $sortUniqueRule = Rule::unique('year_plan_activities', 'sort_order')
                ->where(fn ($q) => $q
                    ->where('year_plan_id', $yearPlan->id)
                    ->where('department_id', (string) $request->input('department_id')))
                ->ignore($existing->getKey());

            $rules['sort_order'] = ['required', 'integer', 'min:1', 'max:9999', $sortUniqueRule];
        }

        return $request->validate($rules);
    }
}
