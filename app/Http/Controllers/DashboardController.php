<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Document;
use App\Models\Petty;
use App\Models\User;
use App\Models\WorkPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class DashboardController extends Controller
{
    public function redirect()
    {

        //This is very important but later should be moved to Console/Command for automatic update
        DB::table('petties')
            ->where('status', 'resubmission')
            ->where('updated_at', '<', now()->subDay())
            ->update(['status' => 'rejected']);

        Log::info('Dashboard-triggered auto-reject of stale petty cash requests.');

        //End of it

        $userNo = User::all()->count();
        $pettyNo = Petty::all()->count();
        $paidAmount = Petty::where('status', 'paid')->sum('amount');
        $myExpense = Petty::where('user_id', Auth::user()->id)->where('status', 'paid')->sum('amount');

        $departmentNo = Department::all()->count();

        // Status breakdown for pie chart
        $statusBreakdown = [
            'pending' => Petty::where('status', 'pending')->count(),
            'approved' => Petty::where('status', 'approved')->count(),
            'paid' => Petty::where('status', 'paid')->count(),
            'rejected' => Petty::where('status', 'rejected')->count(),
            'resubmission' => Petty::where('status', 'resubmission')->count(),
        ];

        // Monthly expense trend (last 6 months)
        $monthlyExpenses = [];
        $monthLabels = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthLabels[] = $date->format('M Y');
            $monthlyExpenses[] = Petty::where('status', 'paid')
                ->whereYear('paid_date', $date->year)
                ->whereMonth('paid_date', $date->month)
                ->sum('amount');
        }

        // Top 5 departments by spending
        $topDepartments = Petty::where('petties.status', 'paid')
            ->join('departments', 'petties.department_id', '=', 'departments.id')
            ->select('departments.name', DB::raw('SUM(petties.amount) as total'))
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // Recent requests (last 5)
        $recentRequests = Petty::with(['user', 'department'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Current month statistics
        $currentMonthTotal = Petty::where('status', 'paid')
            ->whereYear('paid_date', Carbon::now()->year)
            ->whereMonth('paid_date', Carbon::now()->month)
            ->sum('amount');

        $lastMonthTotal = Petty::where('status', 'paid')
            ->whereYear('paid_date', Carbon::now()->subMonth()->year)
            ->whereMonth('paid_date', Carbon::now()->subMonth()->month)
            ->sum('amount');

        $percentageChange = $lastMonthTotal > 0
            ? (($currentMonthTotal - $lastMonthTotal) / $lastMonthTotal) * 100
            : 0;

        // Active users (users who made requests this month)
        $activeUsers = Petty::whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', Carbon::now()->month)
            ->distinct('user_id')
            ->count('user_id');

        // Individual user analytics
        $myTotalRequests = Petty::where('user_id', Auth::user()->id)->count();
        $myApprovedRequests = Petty::where('user_id', Auth::user()->id)->where('status', 'approved')->count();
        $myPendingRequests = Petty::where('user_id', Auth::user()->id)->where('status', 'pending')->count();

        // My status breakdown
        $myStatusBreakdown = [
            'pending' => Petty::where('user_id', Auth::user()->id)->where('status', 'pending')->count(),
            'approved' => Petty::where('user_id', Auth::user()->id)->where('status', 'approved')->count(),
            'paid' => Petty::where('user_id', Auth::user()->id)->where('status', 'paid')->count(),
            'rejected' => Petty::where('user_id', Auth::user()->id)->where('status', 'rejected')->count(),
            'resubmission' => Petty::where('user_id', Auth::user()->id)->where('status', 'resubmission')->count(),
        ];

        // My monthly request trend (last 6 months)
        $myMonthlyRequests = [];
        $myMonthLabels = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $myMonthLabels[] = $date->format('M Y');
            $myMonthlyRequests[] = Petty::where('user_id', Auth::user()->id)
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
        }

        // My recent requests (last 10)
        $myRecentRequests = Petty::where('user_id', Auth::user()->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Document/Work Period related data for dashboard
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $currentPeriod = WorkPeriod::getCurrent();
        $nextPeriod = $currentPeriod ? $currentPeriod->getNextPeriod() : null;

        // Calculate deadline statuses
        $periodDeadlineStatuses = null;
        if ($currentPeriod) {
            $periodDeadlineStatuses = [
                'plan_badge' => $currentPeriod->getPlanDeadlineBadgeClass(),
                'plan_text' => $currentPeriod->getDeadlineStatusText('plan'),
                'plan_due' => $currentPeriod->plan_deadline,
                'report_badge' => $currentPeriod->getReportDeadlineBadgeClass(),
                'report_text' => $currentPeriod->getDeadlineStatusText('report'),
                'report_due' => $currentPeriod->report_deadline,
            ];
        }

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
            'approve documents'
        ]);

        // Prepare weekly dashboard data if applicable
        $weeklyDashboardData = null;
        if ($canSeeWeeklyDashboard) {
            $periodOptions = WorkPeriod::orderBy('year', 'desc')
                ->orderBy('week_number', 'desc')
                ->take(20)
                ->get();

            $allDocuments = Document::with('comments')
                ->forUser($user->id)
                ->latest()
                ->get();

            $timelineData = WorkPeriod::orderBy('year', 'desc')
                ->orderBy('week_number', 'desc')
                ->take(6)
                ->get()
                ->map(function ($period) use ($user) {
                    $plan = Document::where('user_id', $user->id)
                        ->where('period_id', $period->id)
                        ->where('type', Document::TYPE_WEEKLY_PLAN)
                        ->first();

                    $report = Document::where('user_id', $user->id)
                        ->where('period_id', $period->id)
                        ->where('type', Document::TYPE_WEEKLY_REPORT)
                        ->first();

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
        $documents = Document::with('period')
            ->where('type', Document::TYPE_ACTION_LOG)
            ->whereNotNull('period_id')
            ->get();

        if ($documents->isEmpty()) {
            return [
                'activeTasks' => [],
                'currentDraftId' => null,
            ];
        }

        // Keep one document per period: submitted first, otherwise latest draft.
        $selectedByPeriod = $documents
            ->groupBy('period_id')
            ->map(function ($periodDocs) {
                return $periodDocs
                    ->sortByDesc(function ($doc) {
                        $isSubmitted = $doc->state === Document::STATE_SUBMITTED ? 1 : 0;
                        return ($isSubmitted * 1000000000) + (int) $doc->id;
                    })
                    ->first();
            })
            ->filter()
            ->sortBy(fn ($doc) => optional($doc->period)->week_start_date?->timestamp ?? 0)
            ->values();

        $currentPeriod = WorkPeriod::getCurrent();
        $currentDraftDoc = $currentPeriod
            ? $selectedByPeriod->first(fn ($doc) => (int) $doc->period_id === (int) $currentPeriod->id && $doc->isDraft())
            : null;

        $taskTimeline = [];
        foreach ($selectedByPeriod as $document) {
            if (!$document || !$document->period) {
                continue;
            }

            $content = is_array($document->content) ? $document->content : [];
            $tasks = is_array($content['tasks'] ?? null) ? $content['tasks'] : [];
            if (empty($tasks)) {
                continue;
            }

            foreach ($tasks as $task) {
                if (!is_array($task)) {
                    continue;
                }

                $responsibleIds = $task['responsible_user_ids'] ?? [];
                if (!is_array($responsibleIds)) {
                    $responsibleIds = [];
                }
                $responsibleIds = array_map('intval', $responsibleIds);
                if (!in_array((int) $user->id, $responsibleIds, true)) {
                    continue;
                }

                $taskLabel = trim((string) ($task['task'] ?? ''));
                if ($taskLabel === '') {
                    continue;
                }

                $taskKey = trim((string) ($task['task_uid'] ?? ''));
                if ($taskKey === '') {
                    $taskKey = md5($taskLabel . '|' . implode(',', $responsibleIds));
                }

                $taskTimeline[$taskKey]['task'] = $taskLabel;
                $taskTimeline[$taskKey]['history'][] = [
                    'period_label' => 'Week ' . $document->period->week_number . ' of ' . $document->period->year,
                    'period_range' => $document->period->date_range,
                    'progress' => $task['progress'] ?? 'Not Started',
                    'notes_progress' => $task['notes_progress'] ?? '',
                    'due_date' => $task['due_date'] ?? null,
                    'compilation_date' => $task['compilation_date'] ?? null,
                    'is_current_period' => $currentPeriod && (int) $document->period_id === (int) $currentPeriod->id,
                    'document_hashid' => Hashids::encode($document->id),
                ];
            }
        }

        $activeTasks = collect($taskTimeline)
            ->map(function ($taskData) {
                $history = collect($taskData['history'] ?? [])->values();
                $latest = $history->last();
                if (!$latest) {
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
            'currentDraftId' => $currentDraftDoc ? Hashids::encode($currentDraftDoc->id) : null,
        ];
    }


}
