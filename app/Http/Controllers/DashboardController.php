<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\Request as FinanceRequest;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Models\YearPlan;
use App\Models\YearPlanActivity;
use App\Services\ActionLogProgressService;
use App\Services\WeeklyActivityAssignmentService;
use App\Services\WorkPeriodService;
use Carbon\Carbon;
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
    ) {}

    public function redirect()
    {

        if (Schema::hasTable('requests')) {
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
                    fn ($query) => $query->whereRaw(
                        'BINARY period_id IN ('.implode(', ', array_fill(0, count($timelinePeriodIds), 'BINARY ?')).')',
                        $timelinePeriodIds
                    )
                )
                ->whereRaw(
                    'BINARY type IN ('.implode(', ', array_fill(0, count($timelineDocumentTypes), 'BINARY ?')).')',
                    $timelineDocumentTypes
                )
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

        $sharedWithMeCount = Document::query()
            ->whereRaw('BINARY type IN (BINARY ?, BINARY ?)', [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT])
            ->forOwnerSharedOrPlanCollaboratorOnWeeklyReport($user->id)
            ->whereRaw('BINARY user_id <> BINARY ?', [(string) $user->id])
            ->count();

        $collaborationEvents = DB::table('document_share_events as dse')
            ->leftJoin('weekly_plans as wp', 'wp.id', '=', 'dse.weekly_plan_id')
            ->leftJoin('weekly_reports as wr', 'wr.id', '=', 'dse.weekly_report_id')
            ->leftJoin('users as actor', 'actor.id', '=', 'dse.actor_user_id')
            ->where('dse.target_user_id', $user->id)
            ->where(function ($query) {
                $query->whereNotNull('dse.weekly_plan_id')
                    ->orWhereNotNull('dse.weekly_report_id');
            })
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
            'sharedWithMeCount',
            'collaborationEvents',
            'canSeeWeeklyDashboard',
            'weeklyDashboardData',
            'actionLogProgress',
            'teamUsers',
            'assignedWeeklyActivities',
            'todaysDayName',
            'todaysWeeklyTasks',
            'yearPlanMonthCoverageAlert'
        ));
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

}
