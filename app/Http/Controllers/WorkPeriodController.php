<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Models\WorkPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WorkPeriodController extends Controller
{
    private function authenticatedUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403, 'Unauthorized access. Only users with work period permissions can manage work periods.');
        }

        return $user;
    }

    private function requestUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403, 'Unauthorized.');
        }

        return $user;
    }

    /**
     * Check if user has admin permissions (for create/edit/delete operations)
     */
    private function checkAdmin(): void
    {
        if (! $this->authenticatedUser()->hasPermissionTo('manage work periods')) {
            abort(403, 'Unauthorized access. Only users with work period permissions can manage work periods.');
        }
    }

    /**
     * Display a listing of the resource.
     * Anyone can view work periods
     */
    public function index(Request $request)
    {
        if (! $this->requestUser($request)->hasPermissionTo('manage work periods')) {
            abort(403, 'Unauthorized access.');
        }

        $query = WorkPeriod::query();

        // Filter by year if provided
        if ($request->has('year') && $request->year) {
            $query->byYear($request->year);
        } else {
            // Default to current year
            $query->byYear(Carbon::now()->year);
        }

        // Filter by status if provided
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $periods = $query->orderByDesc('week_start_date')
            ->paginate(20)
            ->withQueryString();

        // Get available years for filter
        $years = WorkPeriod::select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        // Get current period
        $currentPeriod = WorkPeriod::getCurrent();

        // Get current year for modal
        $currentYear = Carbon::now()->year;

        // Only allow creating the immediate next week (one week ahead)
        $nextWeekStartDate = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeek();
        $nextWeekYear = $nextWeekStartDate->isoWeekYear();
        $nextWeekNumber = $nextWeekStartDate->isoWeek();

        return view('settings.work-periods.index', compact(
            'periods',
            'years',
            'currentPeriod',
            'currentYear',
            'nextWeekStartDate',
            'nextWeekYear',
            'nextWeekNumber'
        ));
    }

    /**
     * Show the form for creating a new resource.
     * Only admin can create work periods
     */
    public function create()
    {
        $this->checkAdmin();

        // Get the last period to suggest next week
        $lastPeriod = WorkPeriod::orderBy('year', 'desc')
            ->orderBy('week_number', 'desc')
            ->first();

        // Get current year
        $currentYear = Carbon::now()->year;
        $currentWeek = Carbon::now()->week;

        return view('settings.work-periods.create', compact('lastPeriod', 'currentYear', 'currentWeek'));
    }

    /**
     * Store a newly created resource in storage.
     * Only admin can create work periods
     */
    public function store(Request $request)
    {
        $this->checkAdmin();

        abort_unless($this->authenticatedUser()->hasPermissionTo('manage work periods'), 403, 'Unauthorized.');

        $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'week_number' => ['required', 'integer', 'min:1', 'max:53'],
            'week_start_date' => ['required', 'date'],
        ]);

        // Enforce "next week only" creation rule
        $expectedStartDate = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeek();
        $expectedYear = $expectedStartDate->isoWeekYear();
        $expectedWeekNumber = $expectedStartDate->isoWeek();
        $submittedStartDate = Carbon::parse($request->week_start_date)->startOfDay();

        if (
            (int) $request->year !== $expectedYear ||
            (int) $request->week_number !== $expectedWeekNumber ||
            ! $submittedStartDate->isSameDay($expectedStartDate)
        ) {
            return redirect()->route('work-periods.index')
                ->withInput()
                ->withErrors([
                    'week_start_date' => "You can only create the immediate next week: Week {$expectedWeekNumber} of {$expectedYear} ({$expectedStartDate->format('Y-m-d')}).",
                ]);
        }

        // Check if period already exists
        $exists = WorkPeriod::where('year', $request->year)
            ->where('week_number', $request->week_number)
            ->exists();

        if ($exists) {
            return redirect()->route('work-periods.index')
                ->withInput()
                ->withErrors([
                    'week_number' => "Week {$request->week_number} of {$request->year} already exists.",
                ]);
        }

        // Calculate week end date (6 days after start = Sunday to Saturday)
        $startDate = Carbon::parse($request->week_start_date);
        $endDate = $startDate->copy()->addDays(6);

        // Calculate deadlines based on business rule:
        // - Plan deadline for Week N is Saturday of Week N-1
        // - Report deadline for Week N is Saturday of Week N
        $planDeadline = $startDate->copy()->subDays(2); // previous Saturday
        $reportDeadline = $startDate->copy()->addDays(5); // current Saturday

        $period = WorkPeriod::create([
            'year' => $request->year,
            'week_number' => $request->week_number,
            'week_start_date' => $startDate->format('Y-m-d'),
            'week_end_date' => $endDate->format('Y-m-d'),
            'plan_deadline' => $planDeadline->format('Y-m-d'),
            'report_deadline' => $reportDeadline->format('Y-m-d'),
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        return redirect()->route('work-periods.index')
            ->with('success', "Work period created successfully: Week {$period->week_number} of {$period->year}");
    }

    /**
     * Display the specified resource.
     * Anyone can view work periods
     */
    public function show(WorkPeriod $work_period)
    {
        abort_unless($this->authenticatedUser()->hasPermissionTo('manage work periods'), 403, 'Unauthorized.');

        $period = $work_period->load(['documents.user', 'weeklyPlans.user', 'weeklyReports.user']);

        // Get statistics
        $stats = [
            'total_documents' => $period->documents()->count(),
            'weekly_plans' => $period->weeklyPlans()->count(),
            'weekly_reports' => $period->weeklyReports()->count(),
            'monthly_reports' => $period->monthlyReports()->count(),
        ];

        return view('settings.work-periods.show', compact('period', 'stats'));
    }

    /**
     * Show the form for editing the specified resource.
     * Only admin can edit work periods
     */
    public function edit(WorkPeriod $work_period)
    {
        $this->checkAdmin();

        abort_unless($this->authenticatedUser()->hasPermissionTo('manage work periods'), 403, 'Unauthorized.');

        $period = $work_period;

        return view('settings.work-periods.edit', compact('period'));
    }

    /**
     * Update the specified resource in storage.
     * Only admin can update work periods
     */
    public function update(Request $request, WorkPeriod $work_period)
    {
        $this->checkAdmin();

        abort_unless($this->authenticatedUser()->hasPermissionTo('manage work periods'), 403, 'Unauthorized.');

        $period = $work_period;

        $request->validate([
            'week_start_date' => ['required', 'date'],
            'plan_deadline' => ['required', 'date'],
            'report_deadline' => ['required', 'date'],
            'status' => ['required', 'in:open,closed,archived'],
        ]);

        // Calculate week end date
        $startDate = Carbon::parse($request->week_start_date);
        $endDate = $startDate->copy()->addDays(6);

        $period->update([
            'week_start_date' => $startDate->format('Y-m-d'),
            'week_end_date' => $endDate->format('Y-m-d'),
            'plan_deadline' => Carbon::parse($request->plan_deadline)->format('Y-m-d'),
            'report_deadline' => Carbon::parse($request->report_deadline)->format('Y-m-d'),
            'status' => $request->status,
        ]);

        return redirect()->route('work-periods.index')
            ->with('success', 'Work period updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     * Only admin can delete work periods
     */
    public function destroy(WorkPeriod $work_period)
    {
        $this->checkAdmin();

        abort_unless($this->authenticatedUser()->hasPermissionTo('manage work periods'), 403, 'Unauthorized.');

        $period = $work_period;

        // Check if period has documents
        if ($period->documents()->count() > 0) {
            return redirect()->back()
                ->with('error', 'Cannot delete work period that has associated documents. Please archive it instead.');
        }

        $period->delete();

        return redirect()->route('work-periods.index')
            ->with('success', 'Work period deleted successfully.');
    }

    /**
     * Close a work period
     * Only admin can close work periods
     */
    public function close(WorkPeriod $work_period)
    {
        $this->checkAdmin();

        $work_period->close();

        return redirect()->back()
            ->with('success', 'Work period closed successfully.');
    }

    /**
     * Archive a work period
     * Only admin can archive work periods
     */
    public function archive(WorkPeriod $work_period)
    {
        $this->checkAdmin();

        $work_period->archive();

        return redirect()->back()
            ->with('success', 'Work period archived successfully.');
    }

    /**
     * Reopen selected closed work periods and reset selected weekly plans/reports to draft.
     */
    public function reopenAndReset(Request $request)
    {
        $this->checkAdmin();
        abort_unless($this->requestUser($request)->hasPermissionTo('manage work periods'), 403, 'Unauthorized.');

        $validated = $request->validate([
            'period_ids' => ['required', 'array', 'min:1'],
            'period_ids.*' => ['required', 'string', 'exists:work_periods,id'],
        ], [
            'period_ids.required' => 'Please select at least one work period.',
            'period_ids.min' => 'Please select at least one work period.',
        ]);

        $selectedPeriodIds = array_values(array_unique($validated['period_ids']));

        $counts = DB::transaction(function () use ($selectedPeriodIds): array {
            $reopenedPeriods = WorkPeriod::query()
                ->whereIn('id', $selectedPeriodIds)
                ->where('status', WorkPeriod::STATUS_CLOSED)
                ->update(['status' => WorkPeriod::STATUS_OPEN]);

            $weeklyPlanDocumentIds = Document::query()
                ->whereIn('period_id', $selectedPeriodIds)
                ->where('type', Document::TYPE_WEEKLY_PLAN)
                ->pluck('id');

            $updatedWeeklyPlans = Document::query()
                ->whereIn('id', $weeklyPlanDocumentIds)
                ->update([
                    'state' => Document::STATE_DRAFT,
                    'review_rejection_reason' => null,
                    'budget_review_status' => null,
                    'budget_reviewed_by' => null,
                    'budget_reviewed_at' => null,
                    'budget_provided_amount' => null,
                    'budget_review_note' => null,
                ]);

            WeeklyPlan::query()
                ->whereIn('id', $weeklyPlanDocumentIds)
                ->update([
                    'state' => Document::STATE_DRAFT,
                    'review_rejection_reason' => null,
                    'budget_review_status' => null,
                    'budget_reviewed_by' => null,
                    'budget_reviewed_at' => null,
                    'budget_provided_amount' => null,
                    'budget_review_note' => null,
                ]);

            $updatedWeeklyReports = Document::query()
                ->whereIn('period_id', $selectedPeriodIds)
                ->where('type', Document::TYPE_WEEKLY_REPORT)
                ->update([
                    'state' => Document::STATE_DRAFT,
                    'review_rejection_reason' => null,
                ]);

            return [
                'selected_periods' => count($selectedPeriodIds),
                'reopened_periods' => $reopenedPeriods,
                'updated_weekly_plans' => $updatedWeeklyPlans,
                'updated_weekly_reports' => $updatedWeeklyReports,
                'updated_documents' => $updatedWeeklyPlans + $updatedWeeklyReports,
            ];
        });

        return redirect()->route('work-periods.index')->with(
            'success',
            "Bulk reset completed for {$counts['selected_periods']} selected period(s): {$counts['reopened_periods']} closed period(s) reopened, {$counts['updated_documents']} weekly document(s) moved to draft ({$counts['updated_weekly_plans']} plans, {$counts['updated_weekly_reports']} reports)."
        );
    }
}
