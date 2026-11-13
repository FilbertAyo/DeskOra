<?php

namespace App\Http\Controllers;

use App\Data\WeeklyReminderSegment;
use App\Data\WeeklySubmissionSlot;
use App\Models\Department;
use App\Models\Document;
use App\Models\InventoryBorrowRequest;
use App\Models\InventoryDisposalRequest;
use App\Models\OfficialLetter;
use App\Models\Request as FinanceRequest;
use App\Models\User;
use App\Models\WeeklyWorkLog;
use App\Models\WorkPeriod;
use App\Models\YearPlan;
use App\Models\YearPlanActivity;
use App\Services\ActionLogProgressService;
use App\Services\Documents\WeeklyBudgetReviewService;
use App\Services\GreetingService;
use App\Services\MonthlyPerformanceService;
use App\Services\RequestApprovalNavService;
use App\Services\WeeklyActivityAssignmentService;
use App\Services\WeeklyScorecardService;
use App\Services\WeeklySubmissionReminderService;
use App\Services\WeeklySubmissionWindowService;
use App\Services\WorkPeriodService;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __construct(
        private readonly WorkPeriodService $workPeriodService,
        private readonly WeeklyActivityAssignmentService $weeklyActivityAssignmentService,
        private readonly ActionLogProgressService $actionLogProgressService,
        private readonly RequestApprovalNavService $requestApprovalNavService,
        private readonly WeeklyBudgetReviewService $weeklyBudgetReviewService,
        private readonly WeeklyScorecardService $weeklyScorecardService,
        private readonly MonthlyPerformanceService $monthlyPerformanceService,
    ) {}

    public function redirect(): Response
    {

        if (Schema::hasTable('requests') && ! Auth::user()?->isDemoViewer()) {
            DB::table('requests')
                ->where('status', 'resubmission')
                ->where('updated_at', '<', now()->subDay())
                ->update(['status' => 'rejected']);

            Log::info('Dashboard-triggered auto-reject of stale petty cash requests.');
        }

        // End of it
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();
        $departmentScopeIds = $authUser->departmentScopeIds();

        $userNo = User::count();
        $pettyNo = FinanceRequest::whereIn('department_id', $departmentScopeIds)->count();
        $paidAmount = FinanceRequest::whereIn('department_id', $departmentScopeIds)->where('status', 'paid')->with('pettyDetails')->get()->sum('amount');
        $myExpense = FinanceRequest::where('user_id', Auth::user()->id)->where('status', 'paid')->with('pettyDetails')->get()->sum('amount');

        $departmentNo = Department::query()
            ->where('status', 'active')
            ->whereNull('parent_id')
            ->count();

        // Status breakdown for pie chart
        $statusTotals = FinanceRequest::query()
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
        $monthlyExpenseByKey = FinanceRequest::query()
            ->where('status', 'paid')
            ->whereIn('department_id', $departmentScopeIds)
            ->whereDate('paid_at', '>=', Carbon::now()->subMonths(5)->startOfMonth())
            ->with('pettyDetails')
            ->get()
            ->groupBy(fn ($row) => optional($row->paid_at)->format('Y-n'))
            ->map(fn ($group) => (object) [
                'y' => optional($group->first()->paid_at)->year,
                'm' => optional($group->first()->paid_at)->month,
                'total' => $group->sum('amount'),
            ])
            ->keyBy(fn ($row) => $row->y.'-'.$row->m);
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthLabels[] = $date->format('M Y');
            $key = $date->year.'-'.$date->month;
            $monthlyExpenses[] = (float) ($monthlyExpenseByKey->get($key)->total ?? 0);
        }

        // Top 5 departments by spending
        $topDepartments = FinanceRequest::with(['department', 'pettyDetails', 'requisitionDetails'])
            ->where('status', 'paid')
            ->whereIn('department_id', $departmentScopeIds)
            ->get()
            ->groupBy('department_id')
            ->map(fn ($rows) => (object) [
                'name' => optional($rows->first()->department)->name,
                'total' => $rows->sum('amount'),
            ])
            ->sortByDesc('total')
            ->take(5)
            ->values();

        // Recent requests (last 5)
        $recentRequests = FinanceRequest::with(['user', 'department', 'pettyDetails', 'requisitionDetails'])
            ->whereIn('department_id', $departmentScopeIds)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Current month statistics
        $currentMonthTotal = FinanceRequest::where('status', 'paid')
            ->whereIn('department_id', $departmentScopeIds)
            ->whereYear('paid_at', Carbon::now()->year)
            ->whereMonth('paid_at', Carbon::now()->month)
            ->with('pettyDetails')
            ->get()
            ->sum('amount');

        $lastMonthTotal = FinanceRequest::where('status', 'paid')
            ->whereIn('department_id', $departmentScopeIds)
            ->whereYear('paid_at', Carbon::now()->subMonth()->year)
            ->whereMonth('paid_at', Carbon::now()->subMonth()->month)
            ->with('pettyDetails')
            ->get()
            ->sum('amount');

        $percentageChange = $lastMonthTotal > 0
            ? (($currentMonthTotal - $lastMonthTotal) / $lastMonthTotal) * 100
            : 0;

        // Active users (users who made requests this month)
        $activeUsers = FinanceRequest::whereYear('created_at', Carbon::now()->year)
            ->whereIn('department_id', $departmentScopeIds)
            ->whereMonth('created_at', Carbon::now()->month)
            ->distinct('user_id')
            ->count('user_id');

        // Individual user analytics
        $myTotalRequests = FinanceRequest::where('user_id', Auth::user()->id)->count();
        $myStatusTotals = FinanceRequest::query()
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
        $myMonthlyRequestsByKey = FinanceRequest::query()
            ->where('user_id', Auth::user()->id)
            ->whereDate('created_at', '>=', Carbon::now()->subMonths(5)->startOfMonth())
            ->get(['created_at'])
            ->countBy(fn ($row) => $row->created_at->year.'-'.$row->created_at->month);
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $myMonthLabels[] = $date->format('M Y');
            $key = $date->year.'-'.$date->month;
            $myMonthlyRequests[] = (int) ($myMonthlyRequestsByKey->get($key) ?? 0);
        }

        // My recent requests (last 10)
        $myRecentRequests = FinanceRequest::where('user_id', Auth::user()->id)
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
        $pendingDocuments = Document::query()
            ->with(['sharedUsers:id,name'])
            ->forOwnerSharedOrPlanCollaboratorOnWeeklyReport($user->id)
            ->drafts()
            ->get();
        $pendingDocuments = $this->withEffectiveWeeklyReportCollaborators($pendingDocuments, $user);

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
                ->with(['sharedUsers:id,name'])
                ->forOwnerSharedOrPlanCollaboratorOnWeeklyReport($user->id)
                ->latest()
                ->get();
            $allDocuments = $this->withEffectiveWeeklyReportCollaborators($allDocuments, $user);

            $timelinePeriods = WorkPeriod::orderBy('year', 'desc')
                ->orderBy('week_number', 'desc')
                ->take(6)
                ->get();
            $timelinePeriodIds = $timelinePeriods->pluck('id')->map(static fn ($id) => (string) $id)->all();
            $timelineDocumentTypes = [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT];
            $timelineDocs = Document::query()
                ->with(['sharedUsers:id,name'])
                ->forOwnerSharedOrPlanCollaboratorOnWeeklyReport($user->id)
                ->when(
                    $timelinePeriodIds === [],
                    fn ($query) => $query->whereRaw('1 = 0'),
                    fn ($query) => $query->whereIn('period_id', $timelinePeriodIds)
                )
                ->whereIn('type', $timelineDocumentTypes)
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

        $recentPeriodIds = $this->workPeriodService->latestReminderPeriodIds(2);

        $sharedWithMeCount = Document::query()
            ->whereIn('type', [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT])
            ->forOwnerSharedOrPlanCollaboratorOnWeeklyReport($user->id)
            ->where('user_id', '<>', (string) $user->id)
            ->when(
                $recentPeriodIds === [],
                fn ($query) => $query->whereRaw('1 = 0'),
                fn ($query) => $query->whereIn('period_id', $recentPeriodIds)
            )
            ->count();

        $collaborationEvents = $this->recentCollaborationEventsFor($user, $recentPeriodIds);

        $actionLogProgress = $this->actionLogProgressService->buildForUser($user);
        $teamUsers = User::query()
            ->active()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        $assignedWeeklyActivities = $currentPeriod
            ? $this->weeklyActivityAssignmentService->assignmentsForUser(
                (string) $user->id,
                (string) $currentPeriod->id,
            )
            : collect();
        $todaysDayName = now()->format('l');
        $todaysWeeklyTasks = $currentPeriod
            ? $this->weeklyActivityAssignmentService->todaysTasksForUser(
                (string) $user->id,
                (string) $currentPeriod->id,
                $todaysDayName,
            )
            : collect();

        $currentPlanYear = (int) now()->year;
        $currentYearPlan = YearPlan::query()
            ->where('year', $currentPlanYear)
            ->first();
        $yearPlanMonthCoverageAlert = [
            'show' => false,
            'year' => $currentPlanYear,
            'totalActivities' => 0,
            'missingMonthCount' => 0,
            'actionUrl' => null,
        ];

        if ($currentYearPlan) {
            $userSubDepartmentIds = $user->subDepartments()
                ->pluck('departments.id')
                ->filter()
                ->values()
                ->all();

            if (empty($userSubDepartmentIds) && $user->department && $user->department->parent_id !== null) {
                $userSubDepartmentIds = [(string) $user->department_id];
            }

            $scopeDepartmentIds = ! empty($userSubDepartmentIds)
                ? $userSubDepartmentIds
                : $user->departmentScopeIds();
            $scopedYearPlanActivities = YearPlanActivity::query()
                ->where('year_plan_id', $currentYearPlan->id)
                ->whereIn('department_id', $scopeDepartmentIds);

            $totalScopedActivities = (clone $scopedYearPlanActivities)->count();
            $missingPlannedMonthCount = (clone $scopedYearPlanActivities)
                ->where(function ($query) {
                    $query
                        ->whereNull('planned_months')
                        ->orWhereJsonLength('planned_months', '=', 0);
                })
                ->count();

            $yearPlanMonthCoverageAlert = [
                'show' => $totalScopedActivities > 0 && $missingPlannedMonthCount > 0,
                'year' => $currentYearPlan->year,
                'totalActivities' => $totalScopedActivities,
                'missingMonthCount' => $missingPlannedMonthCount,
                'actionUrl' => route('year-plan.show', $currentYearPlan->year),
            ];
        }

        $approvalActions = $this->approvalActionsFor($user);

        $todayWorkLog = $currentPeriod
            ? WeeklyWorkLog::query()
                ->where('user_id', $user->id)
                ->where('period_id', $currentPeriod->id)
                ->whereDate('log_date', now()->toDateString())
                ->first()
            : null;

        $weeklyScorecard = $this->weeklyScorecardService->forUser($user, $currentPeriod);
        $reportReadyHint = $currentPeriod
            && $weeklyScorecard['work_log_days'] > 0
            && ! $weeklyScorecard['report_submitted']
            && Document::canUserCreateType($user, Document::TYPE_WEEKLY_REPORT);
        $monthlyPerformance = $this->monthlyPerformanceService->forUser($user, 6);

        return Inertia::render('Dashboard/Index', $this->dashboardProps(
            $user,
            $currentPeriod,
            $periodDeadlineStatuses,
            $dashboardAvailableTypes,
            $sharedWithMeCount,
            $collaborationEvents,
            $actionLogProgress,
            $teamUsers,
            $assignedWeeklyActivities,
            $todaysDayName,
            $todaysWeeklyTasks,
            $yearPlanMonthCoverageAlert,
            $approvalActions,
            $todayWorkLog,
            $weeklyScorecard,
            $reportReadyHint,
            $monthlyPerformance,
        ));
    }

    /**
     * @return list<array{label: string, count: int, url: string}>
     */
    private function approvalActionsFor(User $user): array
    {
        $actions = [];

        if ($user->can(OfficialLetter::PERMISSION_AUTHORIZE_DOWNLOADS)) {
            $letterCount = OfficialLetter::pendingDownloadAuthorizationCount();
            if ($letterCount > 0) {
                $actions[] = [
                    'label' => 'Authorize letter downloads',
                    'count' => $letterCount,
                    'url' => route('official-letters.index', ['tab' => 'authorizations']),
                ];
            }
        }

        foreach ([
            ['review', 'petty', 'Review petty cash', ['Petty Cash', 'Reimbursement']],
            ['review', 'requisition', 'Review requisitions', ['Staff Requisition']],
            ['approve', 'petty', 'Approve petty cash', ['Petty Cash', 'Reimbursement']],
            ['approve', 'requisition', 'Approve requisitions', ['Staff Requisition']],
            ['authorize', 'petty', 'Authorize petty cash', ['Petty Cash', 'Reimbursement']],
            ['authorize', 'requisition', 'Authorize requisitions', ['Staff Requisition']],
        ] as [$stage, $type, $label, $types]) {
            $count = $this->requestApprovalNavService->countForStageAndTypes($user, $stage, $types);
            if ($count < 1) {
                continue;
            }

            $actions[] = [
                'label' => $label,
                'count' => $count,
                'url' => route('petty.list', ['stage' => $stage, 'type' => $type]),
            ];
        }

        $budgetCounts = $this->weeklyBudgetReviewService->actionableStageCountsFor($user);
        foreach ([
            ['review', 'Review weekly budgets'],
            ['approve', 'Approve weekly budgets'],
            ['authorize', 'Authorize weekly budgets'],
        ] as [$stage, $label]) {
            $count = (int) ($budgetCounts[$stage] ?? 0);
            if ($count < 1) {
                continue;
            }

            $actions[] = [
                'label' => $label,
                'count' => $count,
                'url' => route('documents.weekly-budget-review', ['status' => 'pending']),
            ];
        }

        if ($user->can('approve inventory requests')) {
            $borrowCount = InventoryBorrowRequest::pendingReviewCount();
            if ($borrowCount > 0) {
                $actions[] = [
                    'label' => 'Review inventory borrows',
                    'count' => $borrowCount,
                    'url' => route('inventory.borrowing.index', ['tab' => 'pending']),
                ];
            }

            $disposalCount = InventoryDisposalRequest::pendingReviewCount();
            if ($disposalCount > 0) {
                $actions[] = [
                    'label' => 'Review inventory disposals',
                    'count' => $disposalCount,
                    'url' => route('inventory.disposal.index', ['tab' => 'pending']),
                ];
            }
        }

        return $actions;
    }

    /**
     * @param  list<string>  $periodIds
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function recentCollaborationEventsFor(User $user, array $periodIds)
    {
        return DB::table('document_share_events as dse')
            ->leftJoin('weekly_plans as wp', 'wp.id', '=', 'dse.weekly_plan_id')
            ->leftJoin('weekly_reports as wr', 'wr.id', '=', 'dse.weekly_report_id')
            ->leftJoin('users as actor', 'actor.id', '=', 'dse.actor_user_id')
            ->where('dse.target_user_id', $user->id)
            ->where(function ($query) {
                $query->whereNotNull('dse.weekly_plan_id')
                    ->orWhereNotNull('dse.weekly_report_id');
            })
            ->when(
                $periodIds === [],
                fn ($query) => $query->whereRaw('1 = 0'),
                function ($query) use ($periodIds) {
                    $query->where(function ($periodQuery) use ($periodIds) {
                        $periodQuery
                            ->whereIn('wp.period_id', $periodIds)
                            ->orWhereIn('wr.period_id', $periodIds);
                    });
                }
            )
            ->orderByDesc('dse.created_at')
            ->limit(5)
            ->get([
                'dse.event_type',
                'dse.created_at',
                DB::raw('COALESCE(wp.id, wr.id) as document_id'),
                DB::raw("CASE WHEN dse.weekly_plan_id IS NOT NULL THEN 'weekly_plan' WHEN dse.weekly_report_id IS NOT NULL THEN 'weekly_report' END as document_type"),
                DB::raw('COALESCE(wp.title, wr.title) as document_title'),
                'actor.name as actor_name',
            ])
            ->map(function ($event) {
                $typeLabel = $event->document_type === Document::TYPE_WEEKLY_PLAN ? 'Weekly Plan' : 'Weekly Report';
                $verb = $event->event_type === Document::SHARE_EVENT_REMOVED ? 'removed you from' : 'shared';
                $actor = $event->actor_name ?: 'A teammate';
                $title = $event->document_title ?: $typeLabel;

                $isRemoved = $event->event_type === Document::SHARE_EVENT_REMOVED;

                return [
                    'document_id' => $event->document_id,
                    'message' => "{$actor} {$verb} {$typeLabel}: {$title}",
                    'event_type' => $event->event_type,
                    'badge_label' => $isRemoved ? 'Removed' : 'Shared',
                    'badge_class' => $isRemoved ? 'bg-light-secondary border border-secondary' : 'bg-light-success border border-success',
                    'created_at' => Carbon::parse($event->created_at),
                ];
            })
            ->values();
    }

    private function withEffectiveWeeklyReportCollaborators($documents, User $viewer)
    {
        return collect($documents)->map(function (Document $document) use ($viewer) {
            $effectiveSharedUsers = $document->sharedUsers ?? collect();

            if ($document->type === Document::TYPE_WEEKLY_REPORT && $effectiveSharedUsers->isEmpty() && $document->period_id) {
                $relatedPlan = Document::findWeeklyPlanForUserAndPeriod($document->period_id, (string) $viewer->id);
                if ($relatedPlan) {
                    $relatedPlan->loadMissing(['user:id,name', 'sharedUsers:id,name']);
                    $effectiveSharedUsers = $relatedPlan->sharedUsers
                        ->push($relatedPlan->user)
                        ->filter(fn ($member) => $member && (string) $member->id !== (string) $document->user_id)
                        ->unique('id')
                        ->values();
                }
            }

            $document->setRelation('effectiveSharedUsers', $effectiveSharedUsers);

            return $document;
        });
    }

    /**
     * @param  array<string, mixed>|null  $deadlineStatuses
     * @param  array<string, mixed>  $availableTypes
     * @param  mixed  $collaborationEvents
     * @param  array<string, mixed>  $actionLogProgress
     * @param  mixed  $teamUsers
     * @param  mixed  $assignedWeeklyActivities
     * @param  mixed  $todaysWeeklyTasks
     * @param  array<string, mixed>  $coverage
     * @param  list<array{label: string, count: int, url: string}>  $approvalActions
     * @param  array<string, mixed>  $scorecard
     * @param  array<string, mixed>  $performance
     * @return array<string, mixed>
     */
    private function dashboardProps(
        User $user,
        ?WorkPeriod $currentPeriod,
        ?array $deadlineStatuses,
        array $availableTypes,
        int $sharedWithMeCount,
        mixed $collaborationEvents,
        array $actionLogProgress,
        mixed $teamUsers,
        mixed $assignedWeeklyActivities,
        string $todaysDayName,
        mixed $todaysWeeklyTasks,
        array $coverage,
        array $approvalActions,
        ?WeeklyWorkLog $todayWorkLog,
        array $scorecard,
        bool $reportReadyHint,
        array $performance,
    ): array {
        $weekday = in_array($todaysDayName, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'], true);
        $tasks = collect($actionLogProgress['activeTasks'] ?? [])
            ->filter(function ($task) {
                if (! is_array($task)) {
                    return false;
                }

                $progress = strtolower(trim((string) ($task['latest_progress'] ?? 'Not Started')));

                return ! in_array($progress, ['done', 'cancelled'], true);
            })
            ->map(function (array $task) {
                $documentId = $task['document_id'] ?? null;

                return [
                    'task' => (string) ($task['task'] ?? 'Task'),
                    'latestProgress' => (string) ($task['latest_progress'] ?? 'Not Started'),
                    'periodLabel' => (string) ($task['period_label'] ?? ''),
                    'needsUpdate' => (bool) ($task['needs_update'] ?? false),
                    'carriedFrom' => (string) ($task['carried_from_period_label'] ?? ''),
                    'editable' => (bool) ($task['editable'] ?? false),
                    'documentId' => $documentId,
                    'taskUid' => (string) ($task['task_uid'] ?? ''),
                    'notes' => (string) ($task['latest_notes'] ?? ''),
                    'showUrl' => $documentId ? route('action-logs.show', $documentId) : null,
                ];
            })
            ->values()
            ->all();

        $reminder = [
            'showToast' => false,
            'missedMessages' => [],
            'plan' => null,
            'report' => null,
        ];
        $planGrace = [];
        $reportGrace = [];

        if (($user->status ?? '') === 'active') {
            $weeklyReminder = app(WeeklySubmissionReminderService::class)->forUser($user);
            $windows = app(WeeklySubmissionWindowService::class);
            $reminder = [
                'showToast' => $weeklyReminder->showToast,
                'missedMessages' => array_values($weeklyReminder->missedMessages),
                'plan' => $this->segmentPayload($weeklyReminder->plan, Document::TYPE_WEEKLY_PLAN),
                'report' => $this->segmentPayload($weeklyReminder->report, Document::TYPE_WEEKLY_REPORT),
            ];
            $planGrace = $this->gracePayload($windows->gracePlanEntries($user), Document::TYPE_WEEKLY_PLAN);
            $reportGrace = $this->gracePayload($windows->graceReportEntries($user), Document::TYPE_WEEKLY_REPORT);
        }

        $scorePeriod = $scorecard['period'] ?? null;

        return [
            'greeting' => app(GreetingService::class)->forUser($user),
            'matrix' => $currentPeriod ? [
                'week' => $currentPeriod->week_number,
                'year' => $currentPeriod->year,
                'tableUrl' => route('weekly-matrix.period.table', $currentPeriod),
                'openUrl' => route('weekly-matrix.period', $currentPeriod),
                'downloadUrl' => route('weekly-matrix.download', $currentPeriod),
            ] : null,
            'coverage' => [
                'show' => (bool) ($coverage['show'] ?? false),
                'year' => (int) ($coverage['year'] ?? now()->year),
                'totalActivities' => (int) ($coverage['totalActivities'] ?? 0),
                'missingMonthCount' => (int) ($coverage['missingMonthCount'] ?? 0),
                'actionUrl' => $coverage['actionUrl'] ?? null,
            ],
            'stats' => [
                'openTasks' => count($tasks),
                'shared' => $sharedWithMeCount,
                'assigned' => collect($assignedWeeklyActivities)->count(),
                'weeklyPlansUrl' => isset($availableTypes[Document::TYPE_WEEKLY_PLAN]) ? route('documents.weekly-plans') : null,
                'weeklyReportsUrl' => isset($availableTypes[Document::TYPE_WEEKLY_REPORT]) ? route('documents.weekly-reports') : null,
            ],
            'workLog' => [
                'canLog' => $currentPeriod !== null,
                'todayLabel' => now()->format('D, M j'),
                'todayKey' => now()->toDateString(),
                'periodId' => $currentPeriod?->id,
                'storeUrl' => route('weekly-work-logs.store'),
                'updateUrl' => $todayWorkLog ? route('weekly-work-logs.update', $todayWorkLog) : null,
                'entry' => $todayWorkLog ? [
                    'done' => (string) $todayWorkLog->done,
                    'blocker' => (string) ($todayWorkLog->blocker ?? ''),
                    'next' => (string) ($todayWorkLog->next ?? ''),
                ] : null,
                'reportReady' => $reportReadyHint,
                'reportUrl' => route('documents.create', ['type' => Document::TYPE_WEEKLY_REPORT]),
            ],
            'performance' => [
                'categories' => array_values($performance['categories'] ?? []),
                'series' => array_values($performance['series'] ?? []),
                'tips' => array_values($performance['tips'] ?? []),
                'overallScore' => (int) ($performance['overall_score'] ?? 0),
                'pendingReviews' => (int) ($performance['pending_reviews'] ?? 0),
            ],
            'deadlines' => [
                'planDue' => isset($deadlineStatuses['plan_due']) ? Carbon::parse($deadlineStatuses['plan_due'])->format('M d, Y H:i') : null,
                'reportDue' => isset($deadlineStatuses['report_due']) ? Carbon::parse($deadlineStatuses['report_due'])->format('M d, Y H:i') : null,
            ],
            'reminder' => $reminder,
            'planGrace' => $planGrace,
            'reportGrace' => $reportGrace,
            'today' => [
                'day' => $todaysDayName,
                'showTasks' => $weekday,
                'tasks' => collect($todaysWeeklyTasks)->map(fn ($task) => [
                    'activity' => (string) ($task['activity'] ?? ''),
                    'source' => (string) ($task['source_label'] ?? ''),
                ])->values()->all(),
            ],
            'collaboration' => collect($collaborationEvents)->map(function (array $event) {
                $createdAt = $event['created_at'] ?? null;

                return [
                    'message' => (string) ($event['message'] ?? ''),
                    'badge' => (string) ($event['badge_label'] ?? ''),
                    'removed' => ($event['event_type'] ?? null) === Document::SHARE_EVENT_REMOVED,
                    'href' => ! empty($event['document_id']) ? route('documents.show', $event['document_id']) : null,
                    'when' => $createdAt instanceof Carbon
                        ? $createdAt->diffForHumans()
                        : ($createdAt ? Carbon::parse($createdAt)->diffForHumans() : ''),
                ];
            })->values()->all(),
            'actions' => $approvalActions,
            'scorecard' => $scorePeriod ? [
                'week' => $scorePeriod->week_number,
                'planSubmitted' => (bool) ($scorecard['plan_submitted'] ?? false),
                'reportSubmitted' => (bool) ($scorecard['report_submitted'] ?? false),
                'workLogDays' => (int) ($scorecard['work_log_days'] ?? 0),
                'workLogExpectedDays' => (int) ($scorecard['work_log_expected_days'] ?? 0),
                'openActions' => (int) ($scorecard['open_actions'] ?? 0),
                'carryOvers' => (int) ($scorecard['carry_overs'] ?? 0),
            ] : null,
            'actionTasks' => $tasks,
            'actionLogsUrl' => route('action-logs.index'),
            'teamUsers' => collect($teamUsers)->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function segmentPayload(?WeeklyReminderSegment $segment, string $type): ?array
    {
        if ($segment === null) {
            return null;
        }

        $document = $segment->document;
        $url = null;

        if ($segment->showCta) {
            $url = $document
                ? route('documents.edit', $document->id)
                : route('documents.create', [
                    'type' => $type,
                    'period_id' => $segment->period->id,
                ]);
        }

        return [
            'showCta' => $segment->showCta,
            'week' => $segment->period->week_number,
            'year' => $segment->period->year,
            'url' => $url,
            'needsRevision' => $document !== null && $document->isReturnedForRevision(),
            'hasDocument' => $document !== null,
        ];
    }

    /**
     * @param  iterable<int, WeeklySubmissionSlot>  $entries
     * @return list<array<string, mixed>>
     */
    private function gracePayload(iterable $entries, string $type): array
    {
        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = [
                'week' => $entry->period?->week_number,
                'year' => $entry->period?->year,
                'until' => $entry->graceEndsAt?->format('M d, Y H:i'),
                'actionUrl' => $entry->actionUrl($type),
                'actionLabel' => $entry->showAction() ? $entry->actionLabel($type) : null,
            ];
        }

        return $rows;
    }
}
