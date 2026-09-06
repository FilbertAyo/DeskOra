<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Dashboard and listing endpoints (reviewer + weekly/monthly reports).
 */
trait HandlesDocumentDashboard
{
    /**
     * Reviewer dashboard - Show all submitted documents grouped by type
     */
    public function reviewerDashboard(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Only users with 'view all documents' permission can access this
        if (! $user->hasPermissionTo('view all documents')) {
            abort(403, 'Unauthorized access.');
        }

        $periodOptions = WorkPeriod::query()
            ->orderBy('year', 'desc')
            ->orderBy('week_number', 'desc')
            ->limit(26)
            ->get();

        $periodOptionIds = $periodOptions->pluck('id')->map(fn ($id): string => (string) $id)->all();

        $latestWorkPeriodIds = WorkPeriod::query()
            ->orderBy('year', 'desc')
            ->orderBy('week_number', 'desc')
            ->limit(2)
            ->pluck('id')
            ->all();

        $defaultWorkPeriodFilter = 'last_2';

        $selectedWorkPeriodId = $request->input('work_period_id');
        if ($selectedWorkPeriodId === null || $selectedWorkPeriodId === '') {
            $selectedWorkPeriodId = $defaultWorkPeriodFilter;
        } elseif ($selectedWorkPeriodId !== $defaultWorkPeriodFilter
            && ! in_array((string) $selectedWorkPeriodId, $periodOptionIds, true)) {
            $selectedWorkPeriodId = $defaultWorkPeriodFilter;
        }

        $baseQuery = Document::with(['user', 'period', 'sharedUsers:id,name'])
            ->submitted();

        if ($selectedWorkPeriodId === $defaultWorkPeriodFilter) {
            if (! empty($latestWorkPeriodIds)) {
                $baseQuery->whereIn('period_id', $latestWorkPeriodIds);
            }
        } elseif ($selectedWorkPeriodId) {
            $baseQuery->where('period_id', $selectedWorkPeriodId);
        }

        if ($request->filled('user_id')) {
            $baseQuery->forUser($request->user_id);
        }

        $allDocuments = (clone $baseQuery)->latest()->get();

        $mergedWeeklyRows = $this->buildWeeklyRowsForIndex(
            $allDocuments->whereIn('type', [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT])->values()
        );

        $groupedDocuments = [
            'action_log' => $allDocuments->where('type', Document::TYPE_ACTION_LOG),
        ];

        $statsQuery = (clone $baseQuery);
        $stats = [
            'total_submitted' => $statsQuery->count(),
            'weekly_plans' => (clone $statsQuery)->weeklyPlans()->count(),
            'weekly_reports' => (clone $statsQuery)->weeklyReports()->count(),
            'action_logs' => (clone $statsQuery)->actionLogs()->count(),
        ];

        $usersWithDocuments = \App\Models\User::whereHas('documents', function ($q) use ($selectedWorkPeriodId, $defaultWorkPeriodFilter, $latestWorkPeriodIds) {
            $q->submitted();
            if ($selectedWorkPeriodId === $defaultWorkPeriodFilter) {
                if (! empty($latestWorkPeriodIds)) {
                    $q->whereIn('period_id', $latestWorkPeriodIds);
                }
            } elseif ($selectedWorkPeriodId) {
                $q->where('period_id', $selectedWorkPeriodId);
            }
        })->orderBy('name')->get();

        return view('documents.reviewer.dashboard', compact(
            'mergedWeeklyRows',
            'groupedDocuments',
            'stats',
            'usersWithDocuments',
            'selectedWorkPeriodId',
            'defaultWorkPeriodFilter',
            'periodOptions'
        ));
    }

    /**
     * Display weekly plans page
     */
    public function weeklyPlans(Request $request)
    {
        $this->authorize('viewAny', Document::class);
        if ($request->hasSession()) {
            $request->session()->put('documents.last_listing_url', $request->fullUrl());
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $query = Document::with(['user', 'period', 'sharedUsers:id,name'])
            ->ofType(Document::TYPE_WEEKLY_PLAN);

        // Weekly plans page is individual + collaboration scope:
        // show plans authored by the user and plans shared with the user.
        $query->forOwnerOrSharedUser($user->id);

        // Get current period for defaults
        $currentPeriod = $this->workPeriodService->periodForWeeklyReportSubmission();
        $currentYear = date('Y');

        // Filter by period - only filter if explicitly provided (not empty)
        $periodId = $request->input('period_id');
        if ($periodId && $periodId !== '') {
            $query->forPeriod($periodId);
        }

        // Filter by year - only filter if explicitly provided (not empty)
        $year = $request->input('year');
        if ($year && $year !== '') {
            $query->whereHas('period', function ($q) use ($year) {
                $q->where('year', $year);
            });
        }

        if ($request->input('review_status') === 'returned') {
            $query->where('state', Document::STATE_DRAFT)
                ->whereNotNull('review_rejection_reason')
                ->where('review_rejection_reason', '!=', '');
        }

        $this->applyWeeklyListSortForUser($query, $user);
        $documents = $query->paginate(15)->withQueryString();
        $returnedDocumentMap = $this->getReturnedDocumentMapForIds(
            $documents->getCollection()->pluck('id')->all()
        );

        $nextPeriod = $this->workPeriodService->periodForWeeklyPlanSubmission();

        $availableTypes = Document::getAvailableTypesForUser($user);

        $selectedCreatePeriod = $request->input('period_id')
            ? WorkPeriod::query()->find($request->input('period_id'))
            : null;
        $weeklyPlanSlot = app(\App\Services\WeeklySubmissionWindowService::class)
            ->resolvePlanSlot($user, $selectedCreatePeriod instanceof WorkPeriod ? $selectedCreatePeriod : null);

        $planDeadlinePeriod = $weeklyPlanSlot->period ?? $nextPeriod;
        $deadlineStatuses = null;
        if ($planDeadlinePeriod) {
            $deadlineStatuses = [
                'plan_badge' => $planDeadlinePeriod->getPlanDeadlineBadgeClass(),
                'plan_text' => $planDeadlinePeriod->getDeadlineStatusText('plan'),
                'plan_due' => $planDeadlinePeriod->getPlanDeadlineCutoff(),
            ];
        }

        // Once the user has already submitted their plan for the deadline period,
        // the deadline reminder is no longer relevant and should be hidden.
        $weeklyPlanAlreadySubmitted = false;
        if ($planDeadlinePeriod) {
            $existingPlan = Document::findWeeklyPlanForUserAndPeriod((string) $planDeadlinePeriod->id, (string) $user->id);
            $weeklyPlanAlreadySubmitted = $existingPlan?->isSubmitted() ?? false;
        }

        return view('documents.weekly.plans', [
            'documents' => $documents,
            'periods' => WorkPeriod::orderBy('year', 'desc')->orderBy('week_number', 'desc')->get(),
            'years' => WorkPeriod::select('year')->distinct()->orderBy('year', 'desc')->pluck('year'),
            'currentPeriod' => $currentPeriod,
            'nextPeriod' => $nextPeriod,
            'deadlineStatuses' => $deadlineStatuses,
            'availableTypes' => $availableTypes,
            'returnedDocumentMap' => $returnedDocumentMap,
            'weeklyPlanSlot' => $weeklyPlanSlot,
            'weeklyPlanAlreadySubmitted' => $weeklyPlanAlreadySubmitted,
        ]);
    }

    /**
     * Get plan's main target for a given period (API endpoint)
     */
    public function getPlanTarget(Request $request)
    {
        $user = Auth::user();
        $periodId = $request->query('period_id');

        if (! $periodId) {
            return response()->json(['error' => 'Period ID is required'], 400);
        }

        $plan = Document::findWeeklyPlanForUserAndPeriod($periodId, (string) $user->id);
        $meetingRows = $this->weeklyReportMeetingRowsForUserPeriod((string) $periodId, (string) $user->id);

        if ($plan) {
            $budgetItems = [];
            $reviewsByUid = $plan->budgetReviewItemsByUid();
            $budgetItems = array_values(array_map(function ($row) use ($reviewsByUid) {
                if (! is_array($row)) {
                    return null;
                }
                $uid = trim((string) ($row['budget_uid'] ?? ''));
                $review = $uid !== '' ? ($reviewsByUid[$uid] ?? null) : null;

                return [
                    'item' => $row['item'] ?? null,
                    'description_purpose' => $row['description_purpose'] ?? null,
                    'amount' => $row['amount'] ?? null,
                    'budget_uid' => $uid,
                    'review_decision' => $review['decision'] ?? null,
                    'review_provided_amount' => $review['provided_amount'] ?? null,
                    'review_note' => $review['note'] ?? null,
                ];
            }, $plan->budgetItems()));
            $budgetItems = array_values(array_filter($budgetItems));

            $plannedActivities = [];
            $weeklyPlan = $plan->weeklyPlanDomain()->with('activities')->first();
            if ($weeklyPlan) {
                $plannedActivities = $weeklyPlan->activities
                    ->map(function ($row) {
                        $activity = trim((string) ($row->activity ?? ''));
                        if ($activity === '') {
                            return null;
                        }
                        $day = trim((string) ($row->day ?? ''));

                        return [
                            'day' => $day,
                            'activity' => $activity,
                            'label' => $day !== '' ? ($day.' - '.$activity) : $activity,
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();
            }

            return response()->json([
                'main_target' => $plan->main_target,
                'plan_exists' => true,
                'budget_items' => $budgetItems,
                'planned_activities' => $plannedActivities,
                'meeting_rows' => $meetingRows,
            ]);
        }

        return response()->json([
            'main_target' => null,
            'plan_exists' => false,
            'budget_items' => [],
            'planned_activities' => [],
            'meeting_rows' => $meetingRows,
        ]);
    }

    /**
     * Display weekly reports page
     */
    public function weeklyReports(Request $request)
    {
        $this->authorize('viewAny', Document::class);
        if ($request->hasSession()) {
            $request->session()->put('documents.last_listing_url', $request->fullUrl());
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $query = Document::with(['user', 'period', 'sharedUsers:id,name'])
            ->ofType(Document::TYPE_WEEKLY_REPORT);

        // Weekly reports follow personal/collaboration visibility only.
        $query->forOwnerSharedOrPlanCollaboratorOnWeeklyReport($user->id);

        // Get current period for defaults
        $currentPeriod = $this->workPeriodService->periodForWeeklyReportSubmission();
        $currentYear = date('Y');

        // Filter by period - only filter if explicitly provided (not empty)
        $periodId = $request->input('period_id');
        if ($periodId && $periodId !== '') {
            $query->forPeriod($periodId);
        }

        // Filter by year - only filter if explicitly provided (not empty)
        $year = $request->input('year');
        if ($year && $year !== '') {
            $query->whereHas('period', function ($q) use ($year) {
                $q->where('year', $year);
            });
        }

        if ($request->input('review_status') === 'returned') {
            $query->where('state', Document::STATE_DRAFT)
                ->whereNotNull('review_rejection_reason')
                ->where('review_rejection_reason', '!=', '');
        }

        $this->applyWeeklyListSortForUser($query, $user);
        $documents = $query->paginate(15)->withQueryString();
        $documents->setCollection(
            $this->withEffectiveWeeklyReportCollaborators($documents->getCollection(), $user)
        );
        $returnedDocumentMap = $this->getReturnedDocumentMapForIds(
            $documents->getCollection()->pluck('id')->all()
        );

        $nextPeriod = $this->workPeriodService->periodForWeeklyPlanSubmission();
        $deadlineStatuses = $this->workPeriodService->deadlineStatusesForUi($currentPeriod, $nextPeriod);

        $availableTypes = Document::getAvailableTypesForUser($user);

        $selectedCreatePeriod = $request->input('period_id')
            ? WorkPeriod::query()->find($request->input('period_id'))
            : null;
        $weeklyReportSlot = app(\App\Services\WeeklySubmissionWindowService::class)
            ->resolveReportSlot($user, $selectedCreatePeriod instanceof WorkPeriod ? $selectedCreatePeriod : null);

        // Once the user has already submitted their report for the deadline period,
        // the deadline reminder is no longer relevant and should be hidden.
        $reportDeadlinePeriod = $weeklyReportSlot->period ?? $currentPeriod;
        $weeklyReportAlreadySubmitted = false;
        if ($reportDeadlinePeriod) {
            $existingReport = Document::findWeeklyReportForUserAndPeriod((string) $reportDeadlinePeriod->id, (string) $user->id);
            $weeklyReportAlreadySubmitted = $existingReport?->isSubmitted() ?? false;
        }

        return view('documents.weekly.reports', [
            'documents' => $documents,
            'periods' => WorkPeriod::orderBy('year', 'desc')->orderBy('week_number', 'desc')->get(),
            'years' => WorkPeriod::select('year')->distinct()->orderBy('year', 'desc')->pluck('year'),
            'currentPeriod' => $currentPeriod,
            'nextPeriod' => $nextPeriod,
            'deadlineStatuses' => $deadlineStatuses,
            'availableTypes' => $availableTypes,
            'returnedDocumentMap' => $returnedDocumentMap,
            'weeklyReportSlot' => $weeklyReportSlot,
            'weeklyReportAlreadySubmitted' => $weeklyReportAlreadySubmitted,
        ]);
    }

    /**
     * Display monthly reports page
     */
    public function monthlyReports(Request $request)
    {
        $this->authorize('viewAny', Document::class);
        if ($request->hasSession()) {
            $request->session()->put('documents.last_listing_url', $request->fullUrl());
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $query = Document::with(['user'])
            ->ofType(Document::TYPE_MONTHLY_REPORT)
            ->forUser($user->id);

        $year = $request->input('year');
        if ($year && $year !== '') {
            $query->where('year', (int) $year);
        }

        if ($request->filled('month')) {
            $query->where('month', (int) $request->input('month'));
        }

        if ($request->input('review_status') === 'returned') {
            $query->where('state', Document::STATE_DRAFT)
                ->whereNotNull('review_rejection_reason')
                ->where('review_rejection_reason', '!=', '');
        }

        $documents = $query->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        $returnedDocumentMap = $this->getReturnedDocumentMapForIds(
            $documents->getCollection()->pluck('id')->all()
        );

        $reportYears = Document::query()
            ->where('type', Document::TYPE_MONTHLY_REPORT)
            ->where('user_id', $user->id)
            ->whereNotNull('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        $years = $reportYears
            ->merge(collect(range((int) date('Y'), (int) date('Y') - 5)))
            ->unique()
            ->sortDesc()
            ->values();

        $availableTypes = Document::getAvailableTypesForUser($user);

        $ref = now()->startOfMonth()->subMonth();

        return view('documents.monthly.reports', [
            'documents' => $documents,
            'years' => $years,
            'returnedDocumentMap' => $returnedDocumentMap,
            'availableTypes' => $availableTypes,
            'defaultReportYear' => (int) $ref->year,
            'defaultReportMonth' => (int) $ref->month,
        ]);
    }
}
