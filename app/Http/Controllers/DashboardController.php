<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\Petty;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\WorkPeriodService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function __construct(private readonly WorkPeriodService $workPeriodService) {}

    public function redirect()
    {

        // This is very important but later should be moved to Console/Command for automatic update
        DB::table('petties')
            ->where('status', 'resubmission')
            ->where('updated_at', '<', now()->subDay())
            ->update(['status' => 'rejected']);

        Log::info('Dashboard-triggered auto-reject of stale petty cash requests.');

        // End of it
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();
        $departmentScopeIds = $authUser->departmentScopeIds();

        $userNo = User::count();
        $pettyNo = Petty::whereIn('department_id', $departmentScopeIds)->count();
        $paidAmount = Petty::whereIn('department_id', $departmentScopeIds)->where('status', 'paid')->sum('amount');
        $myExpense = Petty::where('user_id', Auth::user()->id)->where('status', 'paid')->sum('amount');

        $departmentNo = Department::query()
            ->where('status', 'active')
            ->whereNull('parent_id')
            ->count();

        // Status breakdown for pie chart
        $statusTotals = Petty::query()
            ->whereIn('department_id', $departmentScopeIds)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $statusBreakdown = [
            'pending' => (int) ($statusTotals['pending'] ?? 0),
            'approved' => (int) ($statusTotals['approved'] ?? 0),
            'paid' => (int) ($statusTotals['paid'] ?? 0),
            'rejected' => (int) ($statusTotals['rejected'] ?? 0),
            'resubmission' => (int) ($statusTotals['resubmission'] ?? 0),
        ];

        // Monthly expense trend (last 6 months)
        $monthlyExpenses = [];
        $monthLabels = [];
        $monthlyExpenseByKey = Petty::query()
            ->where('status', 'paid')
            ->whereIn('department_id', $departmentScopeIds)
            ->whereDate('paid_date', '>=', Carbon::now()->subMonths(5)->startOfMonth())
            ->selectRaw('YEAR(paid_date) as y, MONTH(paid_date) as m, SUM(amount) as total')
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($row) => $row->y.'-'.$row->m);
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthLabels[] = $date->format('M Y');
            $key = $date->year.'-'.$date->month;
            $monthlyExpenses[] = (float) ($monthlyExpenseByKey->get($key)->total ?? 0);
        }

        // Top 5 departments by spending
        $topDepartments = Petty::where('petties.status', 'paid')
            ->whereIn('petties.department_id', $departmentScopeIds)
            ->join('departments', 'petties.department_id', '=', 'departments.id')
            ->select('departments.name', DB::raw('SUM(petties.amount) as total'))
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Recent requests (last 5)
        $recentRequests = Petty::with(['user', 'department'])
            ->whereIn('department_id', $departmentScopeIds)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Current month statistics
        $currentMonthTotal = Petty::where('status', 'paid')
            ->whereIn('department_id', $departmentScopeIds)
            ->whereYear('paid_date', Carbon::now()->year)
            ->whereMonth('paid_date', Carbon::now()->month)
            ->sum('amount');

        $lastMonthTotal = Petty::where('status', 'paid')
            ->whereIn('department_id', $departmentScopeIds)
            ->whereYear('paid_date', Carbon::now()->subMonth()->year)
            ->whereMonth('paid_date', Carbon::now()->subMonth()->month)
            ->sum('amount');

        $percentageChange = $lastMonthTotal > 0
            ? (($currentMonthTotal - $lastMonthTotal) / $lastMonthTotal) * 100
            : 0;

        // Active users (users who made requests this month)
        $activeUsers = Petty::whereYear('created_at', Carbon::now()->year)
            ->whereIn('department_id', $departmentScopeIds)
            ->whereMonth('created_at', Carbon::now()->month)
            ->distinct('user_id')
            ->count('user_id');

        // Individual user analytics
        $myTotalRequests = Petty::where('user_id', Auth::user()->id)->count();
        $myStatusTotals = Petty::query()
            ->where('user_id', Auth::user()->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $myApprovedRequests = (int) ($myStatusTotals['approved'] ?? 0);
        $myPendingRequests = (int) ($myStatusTotals['pending'] ?? 0);

        // My status breakdown
        $myStatusBreakdown = [
            'pending' => (int) ($myStatusTotals['pending'] ?? 0),
            'approved' => (int) ($myStatusTotals['approved'] ?? 0),
            'paid' => (int) ($myStatusTotals['paid'] ?? 0),
            'rejected' => (int) ($myStatusTotals['rejected'] ?? 0),
            'resubmission' => (int) ($myStatusTotals['resubmission'] ?? 0),
        ];

        // My monthly request trend (last 6 months)
        $myMonthlyRequests = [];
        $myMonthLabels = [];
        $myMonthlyRequestsByKey = Petty::query()
            ->where('user_id', Auth::user()->id)
            ->whereDate('created_at', '>=', Carbon::now()->subMonths(5)->startOfMonth())
            ->selectRaw('YEAR(created_at) as y, MONTH(created_at) as m, COUNT(*) as total')
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($row) => $row->y.'-'.$row->m);
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $myMonthLabels[] = $date->format('M Y');
            $key = $date->year.'-'.$date->month;
            $myMonthlyRequests[] = (int) ($myMonthlyRequestsByKey->get($key)->total ?? 0);
        }

        // My recent requests (last 10)
        $myRecentRequests = Petty::where('user_id', Auth::user()->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Document/Work Period related data for dashboard
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $currentPeriod = $this->workPeriodService->periodForWeeklyReportSubmission();
        $nextPeriod = $this->workPeriodService->periodForWeeklyPlanSubmission();

        // Plan deadline applies to next week; report deadline to current week
        $periodDeadlineStatuses = $this->workPeriodService->deadlineStatusesForUi($currentPeriod, $nextPeriod);

        // Get available document types for user
        $dashboardAvailableTypes = Document::getAvailableTypesForUser($user);

        // Get pending document counts by type
        $pendingDocuments = Document::forUser($user->id)
            ->drafts()
            ->get();

        $dashboardPendingDocs = [
            'weekly_plan' => $pendingDocuments->where('type', Document::TYPE_WEEKLY_PLAN)->count(),
            'weekly_report' => $pendingDocuments->where('type', Document::TYPE_WEEKLY_REPORT)->count(),
            'monthly_report' => $pendingDocuments->where('type', Document::TYPE_MONTHLY_REPORT)->count(),
        ];

        // Check if user can see weekly dashboard
        // Users who can create documents can see the dashboard
        $canSeeWeeklyDashboard = $user->hasAnyPermission([
            'create weekly plan',
            'create weekly report',
            'create monthly report',
            'create action logs',
            'approve documents',
        ]);

        // Prepare weekly dashboard data if applicable
        $weeklyDashboardData = null;
        if ($canSeeWeeklyDashboard) {
            $periodOptions = WorkPeriod::orderBy('year', 'desc')
                ->orderBy('week_number', 'desc')
                ->take(20)
                ->get();

            $allDocuments = Document::query()
                ->forUser($user->id)
                ->latest()
                ->get();

            $timelinePeriods = WorkPeriod::orderBy('year', 'desc')
                ->orderBy('week_number', 'desc')
                ->take(6)
                ->get();
            $timelineDocs = Document::query()
                ->forUser($user->id)
                ->whereIn('period_id', $timelinePeriods->pluck('id'))
                ->whereIn('type', [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT])
                ->get()
                ->groupBy(fn ($doc) => $doc->period_id.'-'.$doc->type);

            $timelineData = $timelinePeriods
                ->map(function ($period) use ($timelineDocs) {
                    $plan = $timelineDocs->get($period->id.'-'.Document::TYPE_WEEKLY_PLAN)?->first();
                    $report = $timelineDocs->get($period->id.'-'.Document::TYPE_WEEKLY_REPORT)?->first();

                    return [
                        'period' => $period,
                        'plan' => $plan,
                        'report' => $report,
                        'plan_status' => $plan?->state ?? 'missing',
                        'report_status' => $report?->state ?? 'missing',
                    ];
                })
                ->sortByDesc(fn ($entry) => $entry['period']->week_start_date)
                ->values();

            $stats = [
                'total_pending' => $pendingDocuments->count(),
                'total_submitted' => $allDocuments->where('state', Document::STATE_SUBMITTED)->count(),
                'weekly_plan_pending' => $dashboardPendingDocs['weekly_plan'],
                'weekly_report_pending' => $dashboardPendingDocs['weekly_report'],
                'monthly_report_pending' => $dashboardPendingDocs['monthly_report'],
            ];

            $weeklyDashboardData = [
                'pendingDocuments' => $pendingDocuments,
                'allDocuments' => $allDocuments,
                'stats' => $stats,
                'periodInfo' => [
                    'current' => $currentPeriod,
                    'next' => $nextPeriod,
                ],
                'deadlineStatuses' => $periodDeadlineStatuses,
                'availableTypes' => $dashboardAvailableTypes,
                'periodOptions' => $periodOptions,
                'timelineData' => $timelineData,
            ];
        }

        $actionLogProgress = $this->buildActionLogProgressForUser($user);

        return view('dashboard', compact(
            'userNo',
            'pettyNo',
            'paidAmount',
            'myExpense',
            'departmentNo',
            'statusBreakdown',
            'monthlyExpenses',
            'monthLabels',
            'topDepartments',
            'recentRequests',
            'currentMonthTotal',
            'percentageChange',
            'activeUsers',
            'myTotalRequests',
            'myApprovedRequests',
            'myPendingRequests',
            'myStatusBreakdown',
            'myMonthlyRequests',
            'myMonthLabels',
            'myRecentRequests',
            // Document/Work Period data
            'currentPeriod',
            'nextPeriod',
            'periodDeadlineStatuses',
            'dashboardAvailableTypes',
            'dashboardPendingDocs',
            'canSeeWeeklyDashboard',
            'weeklyDashboardData',
            'actionLogProgress'
        ));
    }

    private function buildActionLogProgressForUser(User $user): array
    {
        $isActionLogManager = $user->hasAnyPermission(['create action logs', 'approve documents']);
        $documentsQuery = Document::with('period')
            ->where('type', Document::TYPE_ACTION_LOG)
            ->whereNotNull('period_id');

        $documents = $isActionLogManager
            ? $documentsQuery->forUser($user->id)->get()
            : $documentsQuery->get()->filter(fn (Document $document) => $this->isUserAssignedToActionLog($user, $document))->values();

        if ($documents->isEmpty()) {
            return [
                'activeTasks' => [],
                'currentDraftId' => null,
                'showNotifications' => false,
            ];
        }

        // Keep one document per period: submitted first, otherwise latest draft.
        $selectedByPeriod = $documents
            ->groupBy('period_id')
            ->map(function ($periodDocs) {
                return $periodDocs
                    ->sortByDesc(function ($doc) {
                        $isSubmitted = $doc->state === Document::STATE_SUBMITTED ? 1 : 0;

                        return [$isSubmitted, $doc->id];
                    })
                    ->first();
            })
            ->filter()
            ->sortBy(fn ($doc) => optional($doc->period)->week_start_date?->timestamp ?? 0)
            ->values();

        $currentPeriod = WorkPeriod::getCurrent();
        $currentDraftDoc = $currentPeriod
            ? $selectedByPeriod->first(fn ($doc) => $doc->period_id === $currentPeriod->id && $doc->isDraft())
            : null;

        $taskTimeline = [];
        foreach ($selectedByPeriod as $document) {
            if (! $document || ! $document->period) {
                continue;
            }

            $content = is_array($document->content) ? $document->content : [];
            $tasks = is_array($content['tasks'] ?? null) ? $content['tasks'] : [];
            if (empty($tasks)) {
                continue;
            }

            foreach ($tasks as $task) {
                if (! is_array($task)) {
                    continue;
                }

                $responsibleIds = $task['responsible_user_ids'] ?? [];
                if (! is_array($responsibleIds)) {
                    $responsibleIds = [];
                }
                $responsibleIds = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $responsibleIds)));
                if (! in_array((string) $user->id, $responsibleIds, true)) {
                    continue;
                }

                $taskLabel = trim((string) ($task['task'] ?? ''));
                if ($taskLabel === '') {
                    continue;
                }

                $taskKey = trim((string) ($task['task_uid'] ?? ''));
                if ($taskKey === '') {
                    $taskKey = md5($taskLabel.'|'.implode(',', $responsibleIds));
                }

                $taskTimeline[$taskKey]['task'] = $taskLabel;
                $taskTimeline[$taskKey]['history'][] = [
                    'period_label' => 'Week '.$document->period->week_number.' of '.$document->period->year,
                    'period_range' => $document->period->date_range,
                    'progress' => $task['progress'] ?? 'Not Started',
                    'notes_progress' => $task['notes_progress'] ?? '',
                    'due_date' => $task['due_date'] ?? null,
                    'compilation_date' => $task['compilation_date'] ?? null,
                    'is_current_period' => $currentPeriod && $document->period_id === $currentPeriod->id,
                    'document_id' => $document->id,
                ];
            }
        }

        $activeTasks = collect($taskTimeline)
            ->map(function ($taskData) {
                $history = collect($taskData['history'] ?? [])->values();
                $latest = $history->last();
                if (! $latest) {
                    return null;
                }

                return [
                    'task' => $taskData['task'] ?? '',
                    'latest_progress' => $latest['progress'] ?? 'Not Started',
                    'history' => $history->all(),
                ];
            })
            ->filter(fn ($row) => $row && ($row['latest_progress'] ?? 'Not Started') !== 'Done')
            ->sortBy('task')
            ->values()
            ->all();

        return [
            'activeTasks' => $activeTasks,
            'currentDraftId' => $isActionLogManager ? $currentDraftDoc?->id : null,
            'showNotifications' => ! empty($activeTasks),
        ];
    }

    private function isUserAssignedToActionLog(User $user, Document $document): bool
    {
        $content = is_array($document->content) ? $document->content : [];
        $tasks = is_array($content['tasks'] ?? null) ? $content['tasks'] : [];

        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }

            $responsibleIds = $task['responsible_user_ids'] ?? [];
            if (! is_array($responsibleIds)) {
                continue;
            }

            $ids = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $responsibleIds)));
            if (in_array((string) $user->id, $ids, true)) {
                return true;
            }
        }

        return false;
    }
}
