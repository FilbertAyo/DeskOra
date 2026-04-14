<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\YearPlan;
use App\Models\YearPlanActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class YearPlanController extends Controller
{
    public function index()
    {
        $plans = YearPlan::query()
            ->withCount('activities')
            ->orderByDesc('year')
            ->get();

        return view('year-plan.index', compact('plans'));
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
        $activities = $year->activities()
            ->with(['department.parent'])
            ->get();

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

        $subDepartments = Department::query()
            ->whereNotNull('parent_id')
            ->where('status', 'active')
            ->with('parent')
            ->orderBy('name')
            ->get();

        return view('year-plan.show', [
            'yearPlan' => $year,
            'sections' => $sections,
            'grandTotal' => $grandTotal,
            'subDepartments' => $subDepartments,
        ]);
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

    private function validateActivityPayload(Request $request, ?YearPlan $yearPlan = null, ?YearPlanActivity $existing = null): array
    {
        $subDeptRule = Rule::exists('departments', 'id')->where(function ($q) {
            $q->whereNotNull('parent_id');
        });

        $rules = [
            'department_id' => ['required', 'string', 'ulid', $subDeptRule],
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
