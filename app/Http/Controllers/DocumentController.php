<?php

namespace App\Http\Controllers;

use App\Exports\WeeklyBudgetReviewExport;
use App\Models\Document;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanBudgetItem;
use App\Models\WeeklyPlanBudgetReview;
use App\Models\WorkPeriod;
use App\Services\ActionLogProgressService;
use App\Services\Documents\DocumentListingService;
use App\Services\Documents\DocumentContentAssembler;
use App\Services\Documents\DocumentDomainSyncService;
use App\Services\Documents\WeeklyBudgetReviewService;
use App\Services\MonthlyReportYearPlanActivities;
use App\Services\WeeklyActivityAssignmentService;
use App\Services\WeeklySubmissionReminderService;
use App\Services\WorkflowEmailNotificationService;
use App\Services\WorkPeriodService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class DocumentController extends Controller
{
    public function __construct(
        protected readonly WorkPeriodService $workPeriodService,
        protected readonly WeeklySubmissionReminderService $weeklySubmissionReminderService,
        protected readonly WeeklyActivityAssignmentService $weeklyActivityAssignmentService,
        protected readonly WorkflowEmailNotificationService $workflowEmailNotificationService,
        protected readonly DocumentListingService $documentListingService,
        protected readonly WeeklyBudgetReviewService $weeklyBudgetReviewService,
        protected readonly DocumentDomainSyncService $documentDomainSyncService,
        protected readonly ActionLogProgressService $actionLogProgressService,
    ) {}

    /**
     * @param  Collection<int, Document>  $weeklyDocs
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildWeeklyRowsForIndex(Collection $weeklyDocs): Collection
    {
        return $this->documentListingService->buildWeeklyRowsForIndex($weeklyDocs);
    }

    /**
     * @param  Collection<int, Document>  $weeklyDocs
     * @return Collection<int, User>
     */
    protected function resolveWeeklyDocSharedUsersForIndex(?Document $doc, Collection $weeklyDocs): Collection
    {
        return $this->documentListingService->resolveWeeklyDocSharedUsersForIndex($doc, $weeklyDocs);
    }

    /**
     * @return Collection<int, User>
     */
    protected function explicitWeeklyDocSharedUsersForIndex(?Document $doc): Collection
    {
        return $this->documentListingService->explicitWeeklyDocSharedUsersForIndex($doc);
    }

    /**
     * @return Collection<int, mixed>
     */
    protected function assignedWeeklyActivitiesForWeeklyPlanDocument(Document $document): Collection
    {
        if ($document->type !== Document::TYPE_WEEKLY_PLAN || ! $document->period_id) {
            return collect();
        }

        $document->loadMissing('sharedUsers:id');
        $planParticipantIds = collect($document->allEditableUserIds())
            ->map(static fn ($id) => (string) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->weeklyActivityAssignmentService->assignmentsForUsers(
            $planParticipantIds,
            (string) $document->period_id
        );
    }

    /**
     * Employee dashboard - Show pending submissions and history
     */
    public function employeeDashboard(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Allow users who can create documents (have initial permissions)
        // Restrict users who only have approval/view permissions
        $canCreateDocs = $user->hasAnyPermission([
            'create weekly plan',
            'create weekly report',
            'create monthly report',
            'create action logs',
        ]);

        if (! $canCreateDocs && ! $user->hasPermissionTo('approve documents')) {
            abort(403, 'Unauthorized access. This dashboard is for document creators only.');
        }

        $availableTypes = Document::getAvailableTypesForUser($user);
        $currentPeriod = $this->workPeriodService->periodForWeeklyReportSubmission();
        $nextPeriod = $this->workPeriodService->periodForWeeklyPlanSubmission();

        $periodFilterId = $request->input('period_id');
        $periodOptions = WorkPeriod::orderBy('year', 'desc')
            ->orderBy('week_number', 'desc')
            ->take(20)
            ->get();

        $deadlineStatuses = $this->workPeriodService->deadlineStatusesForUi($currentPeriod, $nextPeriod);

        // Get pending submissions (draft documents)
        $pendingDocumentsQuery = Document::query()
            ->forOwnerOrSharedUser($user->id)
            ->drafts()
            ->latest();

        $allDocumentsQuery = Document::query()
            ->forOwnerOrSharedUser($user->id)
            ->latest();

        if ($periodFilterId) {
            $pendingDocumentsQuery->where('period_id', $periodFilterId);
            $allDocumentsQuery->where('period_id', $periodFilterId);
        }

        $pendingDocuments = $pendingDocumentsQuery->get();

        // Get all documents (history)
        $allDocuments = $allDocumentsQuery->get();

        // Group pending by type for better organization
        $pendingByType = [
            'weekly_plan' => $pendingDocuments->where('type', Document::TYPE_WEEKLY_PLAN),
            'weekly_report' => $pendingDocuments->where('type', Document::TYPE_WEEKLY_REPORT),
            'monthly_report' => $pendingDocuments->where('type', Document::TYPE_MONTHLY_REPORT),
        ];

        // Statistics
        $stats = [
            'total_pending' => $pendingDocuments->count(),
            'total_submitted' => $allDocuments->where('state', Document::STATE_SUBMITTED)->count(),
            'weekly_plan_pending' => $pendingByType['weekly_plan']->count(),
            'weekly_report_pending' => $pendingByType['weekly_report']->count(),
            'monthly_report_pending' => $pendingByType['monthly_report']->count(),
        ];

        $periodInfo = [
            'current' => $currentPeriod,
            'next' => $nextPeriod,
        ];

        $timelineData = WorkPeriod::orderBy('year', 'desc')
            ->orderBy('week_number', 'desc')
            ->take(6)
            ->get()
            ->map(function ($period) use ($user) {
                $plan = Document::query()
                    ->forOwnerOrSharedUser($user->id)
                    ->where('period_id', $period->id)
                    ->where('type', Document::TYPE_WEEKLY_PLAN)
                    ->orderByRaw('CASE WHEN state = ? THEN 0 ELSE 1 END', [Document::STATE_SUBMITTED])
                    ->latest('id')
                    ->first();

                $report = Document::query()
                    ->forOwnerOrSharedUser($user->id)
                    ->where('period_id', $period->id)
                    ->where('type', Document::TYPE_WEEKLY_REPORT)
                    ->orderByRaw('CASE WHEN state = ? THEN 0 ELSE 1 END', [Document::STATE_SUBMITTED])
                    ->latest('id')
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

        $selectedPeriod = $periodOptions->firstWhere('id', $periodFilterId);

        return view('documents.employee-dashboard', compact(
            'pendingDocuments',
            'allDocuments',
            'pendingByType',
            'stats',
            'periodInfo',
            'deadlineStatuses',
            'availableTypes',
            'periodOptions',
            'selectedPeriod',
            'periodFilterId',
            'timelineData'
        ));
    }

    protected function resolveWeeklyBudgetReviewDefaultPeriod(Request $request): ?WorkPeriod
    {
        return $this->weeklyBudgetReviewService->resolveWeeklyBudgetReviewDefaultPeriod($request);
    }

    protected function buildWeeklyBudgetReviewQuery(Request $request, ?WorkPeriod $defaultPeriod, ?string $statusFilter)
    {
        return $this->weeklyBudgetReviewService->buildWeeklyBudgetReviewQuery($request, $defaultPeriod, $statusFilter);
    }

    protected function weeklyBudgetReviewFilterSummary(Request $request, ?WorkPeriod $defaultPeriod, ?string $statusFilter): array
    {
        return $this->weeklyBudgetReviewService->weeklyBudgetReviewFilterSummary($request, $defaultPeriod, $statusFilter);
    }

    protected function weeklyBudgetReviewExportFilename(string $extension, array $filterSummary): string
    {
        return $this->weeklyBudgetReviewService->weeklyBudgetReviewExportFilename($extension, $filterSummary);
    }

    /**
     * Group budget documents by department -> staff -> budget items for the review UI.
     *
     * @return array<string, array{department_name: string, department_id: string|null, requested_total: float, provided_total: float, staff: array}>
     */
    protected function groupBudgetDocumentsByDepartment(\Illuminate\Support\Collection $documents): array
    {
        return $this->weeklyBudgetReviewService->groupBudgetDocumentsByDepartment($documents);
    }

    /**
     * Compute aggregate stats across multiple budget documents.
     */
    protected function computeBudgetStats(\Illuminate\Support\Collection $documents): array
    {
        return $this->weeklyBudgetReviewService->computeBudgetStats($documents);
    }

    protected function resolveBudgetApprovalActionStage(string $currentStage, User $user): ?string
    {
        return \App\Support\WeeklyBudgetApprovalFlow::resolveNextActionStage($currentStage, $user);
    }

    protected function stageAfterAction(string $actionStage, string $decision): string
    {
        if ($decision === Document::BUDGET_REVIEW_REJECTED) {
            return Document::BUDGET_REVIEW_STAGE_REJECTED;
        }

        return match ($actionStage) {
            'review' => Document::BUDGET_REVIEW_STAGE_REVIEWED,
            'approve' => Document::BUDGET_REVIEW_STAGE_APPROVED,
            'authorize' => Document::BUDGET_REVIEW_STAGE_AUTHORIZED,
            default => Document::BUDGET_REVIEW_STAGE_PENDING,
        };
    }

    protected function determinePlanBudgetReviewStatus(array $summary): string
    {
        if ($summary['all_approved']) {
            return Document::BUDGET_REVIEW_APPROVED;
        }
        if ($summary['all_rejected']) {
            return Document::BUDGET_REVIEW_REJECTED;
        }
        if ($summary['pending'] === 0 && $summary['items_total'] > 0) {
            return Document::BUDGET_REVIEW_APPROVED;
        }

        return Document::BUDGET_REVIEW_PENDING;
    }

    /**
     * Build document_id => latest rejection reason map for visible documents.
     */
    protected function getReturnedDocumentMapForIds(array $documentIds): array
    {
        return $this->documentListingService->getReturnedDocumentMapForIds($documentIds);
    }

    protected function budgetReviewDefaultsForWeeklyPlan(?array $content, ?array $existingReviewItems = null): array
    {
        if (! $this->templateHasBudgetRows($content)) {
            return [
                'budget_review_status' => null,
                'budget_reviewed_by' => null,
                'budget_reviewed_at' => null,
                'budget_provided_amount' => null,
                'budget_review_note' => null,
                'budget_review_items' => null,
            ];
        }

        // Build initial per-item review entries, preserving existing decisions if UIDs match
        $existingByUid = [];
        if (is_array($existingReviewItems)) {
            foreach ($existingReviewItems as $row) {
                if (is_array($row) && ! empty($row['budget_uid'])) {
                    $existingByUid[(string) $row['budget_uid']] = $row;
                }
            }
        }

        $budgetRows = $content['budget'] ?? [];
        $itemReviews = [];
        foreach ($budgetRows as $budgetRow) {
            if (! is_array($budgetRow)) {
                continue;
            }
            $uid = trim((string) ($budgetRow['budget_uid'] ?? ''));
            if ($uid === '') {
                continue;
            }

            if (isset($existingByUid[$uid])) {
                $itemReviews[] = $existingByUid[$uid];
            } else {
                $itemReviews[] = [
                    'budget_uid' => $uid,
                    'decision' => Document::BUDGET_REVIEW_PENDING,
                    'provided_amount' => 0,
                    'note' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ];
            }
        }

        return [
            'budget_review_status' => Document::BUDGET_REVIEW_PENDING,
            'budget_reviewed_by' => null,
            'budget_reviewed_at' => null,
            'budget_provided_amount' => null,
            'budget_review_note' => null,
            'budget_review_items' => $itemReviews,
        ];
    }

    private const MONTHLY_IMPLEMENTATION_STATUSES = [
        'Not started',
        'In progress',
        'Completed',
        'Deferred',
        'Not applicable',
    ];

    protected function withEffectiveWeeklyReportCollaborators($documents, User $viewer)
    {
        return collect($documents)->map(function (Document $document) use ($viewer) {
            $effectiveSharedUsers = $document->sharedUsers;

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

    protected function shareableDepartmentUsersFor(User $owner)
    {
        if (! $owner->department_id) {
            return collect();
        }

        return User::query()
            ->active()
            ->select('id', 'name')
            ->where('department_id', $owner->department_id)
            ->where('id', '!=', $owner->id)
            ->orderBy('name')
            ->get();
    }

    protected function teamUsersForSelection()
    {
        return User::query()
            ->active()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    protected function activeUserIdRule(): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('users', 'id')->where(static fn ($query) => $query->where('status', 'active'));
    }

    /**
     * @return array<int, string>
     */
    protected function weeklyReportSharedUserIdsFromPlanContext(User $actingUser, ?string $periodId): array
    {
        if (! $periodId) {
            return [];
        }

        $plan = Document::findWeeklyPlanForUserAndPeriod($periodId, (string) $actingUser->id);
        if (! $plan) {
            return [];
        }

        return array_values(array_unique(array_map(
            static fn ($id) => (string) $id,
            $plan->allEditableUserIds()
        )));
    }

    /**
     * @return array<int, string>
     */
    protected function extractRequestedSharedUserIds(Request $request): array
    {
        $rawIds = $request->input('shared_user_ids', []);
        if (! is_array($rawIds)) {
            return [];
        }

        $normalized = array_map(static fn ($id) => trim((string) $id), $rawIds);
        $normalized = array_values(array_filter($normalized, static fn ($id) => $id !== ''));

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<int, string>  $sharedUserIds
     * @return array<int, string>
     */
    protected function validateSharedMembersWithinDepartment(User $owner, array $sharedUserIds): array
    {
        if ($sharedUserIds === []) {
            return [];
        }

        $sharedUserIds = array_values(array_filter(
            $sharedUserIds,
            static fn ($id) => $id !== '' && $id !== (string) $owner->id
        ));

        if ($sharedUserIds === []) {
            return [];
        }

        if (! $owner->department_id) {
            throw ValidationException::withMessages([
                'shared_user_ids' => 'You can only share weekly documents after you are assigned to a department.',
            ]);
        }

        $validIds = User::query()
            ->whereIn('id', $sharedUserIds)
            ->where('department_id', $owner->department_id)
            ->pluck('id')
            ->map(static fn ($id) => (string) $id)
            ->all();

        if (count($validIds) !== count($sharedUserIds)) {
            throw ValidationException::withMessages([
                'shared_user_ids' => 'Selected staff must belong to your department.',
            ]);
        }

        return $validIds;
    }

    /**
     * @param  array<int, string>  $memberIds
     */
    protected function firstCollaborationConflictMessage(
        ?string $periodId,
        string $type,
        array $memberIds,
        ?string $excludeDocumentId = null
    ): ?string {
        if (! $periodId || ! Document::supportsSharedEditing($type) || $memberIds === []) {
            return null;
        }

        $query = Document::query()
            ->with(['user:id,name', 'sharedUsers:id,name'])
            ->where('period_id', $periodId)
            ->where('type', $type)
            ->where(function ($nested) use ($memberIds) {
                $nested->whereIn('user_id', $memberIds)
                    ->orWhereHas('sharedUsers', function ($sharedUsers) use ($memberIds) {
                        $sharedUsers->whereIn('users.id', $memberIds);
                    });
            });

        if ($excludeDocumentId) {
            $query->where('id', '!=', $excludeDocumentId);
        }

        /** @var Document|null $conflict */
        $conflict = $query->first();
        if (! $conflict) {
            return null;
        }

        $ownerName = $conflict->user?->name ?? 'another staff member';
        $typeLabel = $type === Document::TYPE_WEEKLY_PLAN ? 'weekly plan' : 'weekly report';

        return "A {$typeLabel} collaboration already exists for this week with {$ownerName}. Open that document instead of creating a new group.";
    }

    /**
     * @param  array<int, string>  $sharedUserIds
     */
    protected function syncDocumentShares(Document $document, array $sharedUserIds, User $actor): void
    {
        $weeklyPlanId = null;
        $weeklyReportId = null;
        if ($document->type === Document::TYPE_WEEKLY_PLAN) {
            $weeklyPlanId = (string) $document->id;
        } elseif ($document->type === Document::TYPE_WEEKLY_REPORT) {
            $weeklyReportId = (string) $document->id;
        }

        $desiredIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => trim((string) $id), $sharedUserIds),
            static fn ($id) => $id !== '' && $id !== (string) $document->user_id
        )));

        $existingIds = $document->sharedUsers()
            ->pluck('users.id')
            ->map(static fn ($id) => (string) $id)
            ->all();

        $document->sharedUsers()->sync($desiredIds);

        $addedIds = array_values(array_diff($desiredIds, $existingIds));
        $removedIds = array_values(array_diff($existingIds, $desiredIds));
        if ($addedIds === [] && $removedIds === []) {
            return;
        }

        $now = now();
        $events = [];
        foreach ($addedIds as $targetUserId) {
            $events[] = [
                'weekly_plan_id' => $weeklyPlanId,
                'weekly_report_id' => $weeklyReportId,
                'actor_user_id' => $actor->id,
                'target_user_id' => $targetUserId,
                'event_type' => Document::SHARE_EVENT_ADDED,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach ($removedIds as $targetUserId) {
            $events[] = [
                'weekly_plan_id' => $weeklyPlanId,
                'weekly_report_id' => $weeklyReportId,
                'actor_user_id' => $actor->id,
                'target_user_id' => $targetUserId,
                'event_type' => Document::SHARE_EVENT_REMOVED,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('document_share_events')->insert($events);
    }

    /**
     * For staff lists: rows where you are a collaborator (not the author) appear first, then by last update.
     * Reviewers keep a simple newest-first ordering by {@see Document::$updated_at}.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Document>  $query
     */
    protected function applyWeeklyListSortForUser($query, User $user): void
    {
        $this->documentListingService->applyWeeklyListSortForUser($query, $user);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{planned: list<array<string, mixed>>, off_plan: list<array<string, mixed>>, month_summary: string}
     */
    protected function normalizeMonthlyReportContent(
        User $owner,
        int $calendarYear,
        int $month,
        array $input,
        string $action
    ): array {
        $activities = MonthlyReportYearPlanActivities::plannedForUserMonth($owner, $calendarYear, $month);
        $rawPlanned = collect($input['planned'] ?? [])->keyBy(fn ($r) => (int) (is_array($r) ? ($r['year_plan_activity_id'] ?? 0) : 0));

        $planned = [];
        foreach ($activities as $act) {
            $row = $rawPlanned->get($act->id);
            if (! is_array($row)) {
                $row = [];
            }
            $status = $row['implementation_status'] ?? 'Not started';
            if (! in_array($status, self::MONTHLY_IMPLEMENTATION_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'content.planned' => 'Invalid implementation status for a year plan activity.',
                ]);
            }
            $planned[] = [
                'year_plan_activity_id' => $act->id,
                'implementation_status' => $status,
                'achievements' => $this->clipString(trim((string) ($row['achievements'] ?? '')), 5000),
                'challenges' => $this->clipString(trim((string) ($row['challenges'] ?? '')), 5000),
                'next_steps' => $this->clipString(trim((string) ($row['next_steps'] ?? '')), 4000),
            ];
        }

        $offPlan = [];
        foreach ($input['off_plan'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            if ($title === '' && $description === '') {
                continue;
            }
            $offPlan[] = [
                'title' => $this->clipString($title, 500),
                'description' => $this->clipString($description, 4000),
                'status' => $this->clipString(trim((string) ($row['status'] ?? '')), 100),
                'notes' => $this->clipString(trim((string) ($row['notes'] ?? '')), 4000),
            ];
        }

        $summary = $this->clipString(trim((string) ($input['month_summary'] ?? '')), 10000);

        if ($action === 'submit') {
            if ($planned !== []) {
                foreach ($planned as $p) {
                    $idle = $p['implementation_status'] === 'Not started'
                        && $p['achievements'] === ''
                        && $p['challenges'] === ''
                        && $p['next_steps'] === '';
                    if ($idle) {
                        throw ValidationException::withMessages([
                            'content.planned' => 'For each year plan activity, select a status or describe achievements, challenges, or next steps.',
                        ]);
                    }
                }
            } elseif ($summary === '' && $offPlan === []) {
                throw ValidationException::withMessages([
                    'content.month_summary' => 'Add a month summary, off-plan work, or assign sub-departments with planned activities for this month in the year plan.',
                ]);
            }
        }

        return [
            'planned' => $planned,
            'off_plan' => $offPlan,
            'month_summary' => $summary,
        ];
    }

    protected function clipString(string $value, int $max): string
    {
        if ($max <= 0) {
            return '';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($value) > $max) {
            return mb_substr($value, 0, $max);
        }

        return strlen($value) > $max ? substr($value, 0, $max) : $value;
    }

    /**
     * Normalize + trim template payload before saving.
     * - removes empty rows so weekly_report budget loads cleanly
     * - trims string values to reduce accidental whitespace-only entries
     */
    protected function normalizeTemplateContent(array $content, string $type): array
    {
        $content = $this->trimDeep($content);

        if ($type === Document::TYPE_WEEKLY_PLAN) {
            $objective = is_array($content['objective'] ?? null) ? $content['objective'] : [];
            $reflectionProgress = $this->clipString(trim((string) ($objective['reflection_progress'] ?? '')), 4000);
            $description = $this->clipString(trim((string) ($objective['description'] ?? '')), 2000);
            $content['objective'] = array_filter([
                'description' => $description,
                'reflection_progress' => $reflectionProgress,
            ], static fn ($value) => is_string($value) ? $value !== '' : $value !== null);

            // Keep only "complete" activity rows (Day + Activity).
            // This prevents downstream sync from failing due to half-filled rows.
            $plannedActivities = is_array($content['planned_activities'] ?? null) ? $content['planned_activities'] : [];
            $content['planned_activities'] = array_values(array_filter($plannedActivities, function ($row) {
                if (! is_array($row)) {
                    return false;
                }

                $day = trim((string) ($row['day'] ?? ''));
                $activity = trim((string) ($row['activity'] ?? ''));

                // Treat blank rows as empty.
                if ($day === '' && $activity === '') {
                    return false;
                }

                // Discard incomplete rows; they should be caught by validation anyway.
                return $day !== '' && $activity !== '';
            }));
            $content['planned_activities'] = $this->dedupeWeeklyPlanPlannedActivities($content['planned_activities']);
            $content = app(DocumentContentAssembler::class)->normalizeWeeklyPlanContent($content);
            $content['planned_activities'] = $this->ensureWeeklyPlanActivityUids($content['planned_activities']);

            $content['key_meetings'] = $this->filterNonEmptyRows(
                $content['key_meetings'] ?? [],
                ['date', 'meeting', 'participant_user_ids', 'participants', 'location', 'reactions_notes']
            );
            $content['key_meetings'] = array_values(array_map(function ($row) {
                if (! is_array($row)) {
                    return [];
                }

                $participantIds = is_array($row['participant_user_ids'] ?? null)
                    ? $row['participant_user_ids']
                    : [];
                $row['participant_user_ids'] = array_values(array_unique(array_filter(array_map(
                    static fn ($id) => trim((string) $id),
                    $participantIds
                ))));
                $uid = trim((string) ($row['meeting_uid'] ?? ''));
                if ($uid === '') {
                    $row['meeting_uid'] = (string) Str::ulid();
                }

                return $row;
            }, $content['key_meetings']));

            $content['budget'] = $this->filterNonEmptyRows(
                $content['budget'] ?? [],
                ['item', 'description_purpose', 'amount']
            );
            $content['budget'] = $this->ensureBudgetRowUids($content['budget']);
        }

        if ($type === Document::TYPE_WEEKLY_REPORT) {
            $content = app(DocumentContentAssembler::class)->normalizeWeeklyReportContent($content);

            $content['key_achievements'] = array_values(array_filter(array_map(function ($item) {
                if (is_string($item)) {
                    $text = trim($item);

                    return $text !== '' ? $text : null;
                }

                if (is_array($item)) {
                    $text = trim((string) ($item['achievement'] ?? ''));

                    return $text !== '' ? $text : null;
                }

                return null;
            }, is_array($content['key_achievements'] ?? null) ? $content['key_achievements'] : [])));

            $content['key_meetings'] = $this->filterNonEmptyRows(
                $content['key_meetings'] ?? [],
                ['date', 'meeting', 'participant_user_ids', 'status', 'location', 'reactions_notes']
            );
            $content['key_meetings'] = array_values(array_map(function ($row) {
                if (! is_array($row)) {
                    return [];
                }

                $participantIds = is_array($row['participant_user_ids'] ?? null)
                    ? $row['participant_user_ids']
                    : [];
                $row['participant_user_ids'] = array_values(array_unique(array_filter(array_map(
                    static fn ($id) => trim((string) $id),
                    $participantIds
                ))));
                $row['status'] = trim((string) ($row['status'] ?? ''));
                $row['date'] = $this->formatMeetingDateField($row['date'] ?? '');

                return $row;
            }, $content['key_meetings']));

            $content['activity_progress'] = $this->filterNonEmptyRows(
                $content['activity_progress'] ?? [],
                ['activity_output', 'off_plan_activity', 'progress', 'remarks_challenges', 'next_step']
            );
            $content['activity_progress'] = array_values(array_map(function ($row) {
                if (! is_array($row)) {
                    return [];
                }

                $activityOutput = trim((string) ($row['activity_output'] ?? ''));
                $offPlanActivity = trim((string) ($row['off_plan_activity'] ?? ''));
                if ($activityOutput !== '__off_plan__') {
                    $offPlanActivity = '';
                }
                $progress = trim((string) ($row['progress'] ?? ''));
                $row['carry_to_next_plan'] = in_array($progress, ['Partially done', 'Done and carry'], true);

                $row['activity_output'] = $activityOutput;
                $row['off_plan_activity'] = $offPlanActivity;

                return $row;
            }, $content['activity_progress']));

            $content['challenges_mitigation'] = $this->filterNonEmptyRows(
                $content['challenges_mitigation'] ?? [],
                ['challenge', 'action_taken_planned']
            );

            unset($content['lessons_recommendations']);

            $content['budget_statuses'] = $this->filterNonEmptyRows(
                $content['budget_statuses'] ?? [],
                ['budget_uid', 'status', 'notes']
            );
        }

        return $content;
    }

    /**
     * Weekly-plan planned activities must be either completely empty or contain both:
     * - day
     * - activity
     *
     * For submit we also require at least one complete activity row.
     *
     * @param  array<string,mixed>  $content
     */
    protected function validateWeeklyPlanPlannedActivities(array $content, string $action): void
    {
        $plannedActivities = is_array($content['planned_activities'] ?? null)
            ? $content['planned_activities']
            : [];

        if (! is_array($plannedActivities)) {
            $plannedActivities = [];
        }

        $messages = [];
        $completeRows = 0;

        foreach ($plannedActivities as $idx => $row) {
            if (! is_array($row)) {
                continue;
            }

            $day = trim((string) ($row['day'] ?? ''));
            $activity = trim((string) ($row['activity'] ?? ''));
            $location = trim((string) ($row['location'] ?? ''));
            $notes = trim((string) ($row['notes_inclusion'] ?? ''));

            $teamIds = is_array($row['team_member_ids'] ?? null) ? $row['team_member_ids'] : [];
            $teamIds = array_values(array_filter(array_map(static fn ($id) => trim((string) $id), $teamIds), static fn ($id) => $id !== ''));

            $hasAny =
                $day !== ''
                || $activity !== ''
                || $location !== ''
                || $notes !== ''
                || $teamIds !== [];

            if (! $hasAny) {
                continue;
            }

            if ($day === '' || $activity === '') {
                // Drafts are allowed to be incomplete; we rely on normalization
                // to discard half-filled rows. Submitting should be strict.
                if ($action === 'submit') {
                    $rowNo = is_numeric($idx) ? ((int) $idx + 1) : $idx;
                    $messages["content.planned_activities.$idx.day"] = "Activity row {$rowNo} requires both Day and Activity.";
                }

                continue;
            }

            $completeRows++;
        }

        if ($action === 'submit' && $completeRows === 0) {
            $messages['content.planned_activities'] = 'Please add at least one activity (Day + Activity text) before submitting.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    /**
     * @param  array<string, mixed>  $existingRow
     * @param  array<string, mixed>  $defaults
     */
    protected function preferWeeklyReportMeetingField(array $existingRow, array $defaults, string $field): string
    {
        $existingValue = trim((string) ($existingRow[$field] ?? ''));
        if ($existingValue !== '') {
            return $field === 'date' ? $this->formatMeetingDateField($existingValue) : $existingValue;
        }

        $defaultValue = trim((string) ($defaults[$field] ?? ''));

        return $field === 'date' ? $this->formatMeetingDateField($defaultValue) : $defaultValue;
    }

    protected function formatMeetingDateField(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        if ($date instanceof \Carbon\CarbonInterface) {
            return $date->toDateString();
        }

        $trimmed = trim((string) $date);
        if ($trimmed === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($trimmed)->toDateString();
        } catch (\Throwable) {
            return $trimmed;
        }
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function preserveWeeklyReportBudgetNotes(array $content, Document $document): array
    {
        $existingStatuses = is_array($document->content) ? ($document->content['budget_statuses'] ?? []) : [];
        $notesByUid = [];
        foreach ($existingStatuses as $row) {
            if (! is_array($row)) {
                continue;
            }
            $uid = trim((string) ($row['budget_uid'] ?? ''));
            $notes = trim((string) ($row['notes'] ?? ''));
            if ($uid !== '' && $notes !== '') {
                $notesByUid[$uid] = $notes;
            }
        }

        $incomingStatuses = is_array($content['budget_statuses'] ?? null) ? $content['budget_statuses'] : [];
        foreach ($incomingStatuses as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $uid = trim((string) ($row['budget_uid'] ?? ''));
            $incomingNotes = trim((string) ($row['notes'] ?? ''));
            if ($incomingNotes === '' && $uid !== '' && isset($notesByUid[$uid])) {
                $incomingStatuses[$index]['notes'] = $notesByUid[$uid];
            }
        }

        $content['budget_statuses'] = $incomingStatuses;

        return $content;
    }

    protected function assertWeeklyReportActionTasksUpdated(User $user): void
    {
        if (! $this->actionLogProgressService->hasPendingUpdates($user)) {
            return;
        }

        throw ValidationException::withMessages([
            'action_tasks' => 'Please update all assigned action tasks before submitting your weekly report.',
        ]);
    }

    protected function normalizeDocumentAction(Request $request): string
    {
        $action = $request->input('action', 'draft');

        return in_array($action, ['submit', 'draft', 'continue'], true) ? $action : 'draft';
    }

    protected function ensureWeeklyReportWizardDocument(Document $document): void
    {
        if ($document->type !== Document::TYPE_WEEKLY_REPORT || ! $document->isDraft()) {
            abort(404);
        }
    }

    protected function redirectToWeeklyReportWizardNextStep(Document $document, User $user, string $successMessage = ''): RedirectResponse
    {
        $route = $this->actionLogProgressService->hasPendingUpdates($user)
            ? 'documents.weekly-report.action-tasks'
            : 'documents.weekly-report.review';

        $redirect = redirect()->route($route, $document);
        if ($successMessage !== '') {
            $redirect->with('success', $successMessage);
        }

        return $redirect;
    }

    protected function redirectAfterWeeklyReportWrite(
        Document $document,
        string $action,
        string $successMessage,
        User $user
    ): RedirectResponse {
        $resolved = Document::fromDomainId((string) $document->id) ?? $document;

        if ($document->type !== Document::TYPE_WEEKLY_REPORT) {
            return $this->redirectToWeeklyDocument($resolved, $successMessage);
        }

        if ($action === 'continue') {
            return $this->redirectToWeeklyReportWizardNextStep($resolved, $user, $successMessage);
        }

        if ($action === 'draft') {
            return redirect()->route('documents.edit', $resolved->id)
                ->with('success', $successMessage);
        }

        return $this->redirectToWeeklyDocument($resolved, $successMessage);
    }

    protected function resolveWeeklyReportTargetStatus(Request $request, ?Document $document = null): ?string
    {
        $status = trim((string) $request->input('target_accomplished_status', ''));
        if ($status === '' && $request->has('target_accomplished')) {
            $raw = $request->input('target_accomplished');
            if ($raw === true || $raw === 1 || $raw === '1') {
                $status = 'completed';
            } elseif ($raw === false || $raw === 0 || $raw === '0') {
                $status = 'not_completed';
            }
        }

        if ($status === '' && $document) {
            $content = is_array($document->content) ? $document->content : [];
            $status = trim((string) ($content['target_accomplished_status'] ?? ''));
            if ($status === '') {
                if ($document->target_accomplished === true || $document->target_accomplished === 1 || $document->target_accomplished === '1') {
                    $status = 'completed';
                } elseif ($document->target_accomplished === false || $document->target_accomplished === 0 || $document->target_accomplished === '0') {
                    $status = 'not_completed';
                }
            }
        }

        return in_array($status, ['completed', 'partially_completed', 'not_completed'], true) ? $status : null;
    }

    protected function targetAccomplishedBooleanFromStatus(?string $status): ?bool
    {
        return match ($status) {
            'completed' => true,
            'partially_completed', 'not_completed' => false,
            default => null,
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildCarriedActivitiesFromWeeklyReport(Document $weeklyReport): array
    {
        if ($weeklyReport->type !== Document::TYPE_WEEKLY_REPORT || ! $weeklyReport->period_id) {
            return [];
        }

        $reportContent = is_array($weeklyReport->content) ? $weeklyReport->content : [];
        $activityProgress = is_array($reportContent['activity_progress'] ?? null) ? $reportContent['activity_progress'] : [];
        if ($activityProgress === []) {
            return [];
        }

        $sourcePlan = Document::findWeeklyPlanForPeriodOwner($weeklyReport->period_id, (string) $weeklyReport->user_id);
        $planActivities = is_array($sourcePlan?->content ?? null) ? ($sourcePlan->content['planned_activities'] ?? []) : [];
        $planActivities = is_array($planActivities) ? $planActivities : [];

        $planByLabel = [];
        foreach ($planActivities as $planRow) {
            if (! is_array($planRow)) {
                continue;
            }
            $activity = trim((string) ($planRow['activity'] ?? ''));
            if ($activity === '') {
                continue;
            }
            $day = trim((string) ($planRow['day'] ?? ''));
            $label = $day !== '' ? ($day.' - '.$activity) : $activity;
            $planByLabel[$label] = $planRow;
        }

        $carriedRows = [];
        $seenKeys = [];
        foreach ($activityProgress as $row) {
            if (! is_array($row)) {
                continue;
            }

            $progress = trim((string) ($row['progress'] ?? ''));
            if (! in_array($progress, ['Partially done', 'Done and carry'], true)) {
                continue;
            }

            $activityOutput = trim((string) ($row['activity_output'] ?? ''));
            $offPlanActivity = trim((string) ($row['off_plan_activity'] ?? ''));
            $day = '';
            $activity = '';
            $location = '';
            $teamMemberIds = [];
            $sourceActivityUid = '';

            if ($activityOutput === '__off_plan__') {
                $activity = $offPlanActivity;
            } elseif ($activityOutput !== '' && isset($planByLabel[$activityOutput]) && is_array($planByLabel[$activityOutput])) {
                $sourceRow = $planByLabel[$activityOutput];
                $day = trim((string) ($sourceRow['day'] ?? ''));
                $activity = trim((string) ($sourceRow['activity'] ?? ''));
                $location = trim((string) ($sourceRow['location'] ?? ''));
                $teamMemberIds = is_array($sourceRow['team_member_ids'] ?? null) ? array_values(array_filter($sourceRow['team_member_ids'])) : [];
                $sourceActivityUid = trim((string) ($sourceRow['activity_uid'] ?? ''));
            } else {
                $activity = $activityOutput;
                if (str_contains($activityOutput, ' - ')) {
                    [$parsedDay, $parsedActivity] = array_map('trim', explode(' - ', $activityOutput, 2));
                    if (in_array($parsedDay, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'], true)) {
                        $day = $parsedDay;
                        $activity = $parsedActivity;
                    }
                }
            }

            $activity = trim($activity);
            if ($activity === '') {
                continue;
            }

            $dedupeKey = mb_strtolower(trim($day).'|'.$activity);
            if (isset($seenKeys[$dedupeKey])) {
                continue;
            }
            $seenKeys[$dedupeKey] = true;

            $carryNote = trim((string) ($row['remarks_challenges'] ?? ''));
            $notes = 'Carried from weekly report: '.$progress;
            if ($carryNote !== '') {
                $notes .= ' - '.$carryNote;
            }

            $carriedRows[] = [
                'activity_uid' => $sourceActivityUid !== '' ? $sourceActivityUid : (string) Str::ulid(),
                'day' => $day,
                'activity' => $activity,
                'location' => $location,
                'team_member_ids' => $teamMemberIds,
                'notes_inclusion' => $this->clipString($notes, 4000),
                'carry_over_source_weekly_report_id' => optional($weeklyReport->weeklyReportDomain)->id,
            ];
        }

        return $carriedRows;
    }

    /**
     * Resolve activities carried from previous week for weekly-plan view/edit fallbacks.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function carriedActivitiesForWeeklyPlanDocument(Document $document): array
    {
        if ($document->type !== Document::TYPE_WEEKLY_PLAN) {
            return [];
        }

        $period = $document->period;
        if (! $period) {
            return [];
        }

        $previousPeriod = $period->getPreviousPeriod();
        if (! $previousPeriod) {
            return [];
        }

        $ownerUserId = (string) $document->user_id;
        $latestWeeklyReport = Document::findWeeklyReportForPeriodOwner($previousPeriod->id, $ownerUserId);
        if (! $latestWeeklyReport) {
            // Fallback for draft plans created/edited outside expected week cadence:
            // use the active weekly-report period as a carry source when available.
            $currentReportPeriod = $this->workPeriodService->periodForWeeklyReportSubmission();
            if ($currentReportPeriod) {
                $latestWeeklyReport = Document::findWeeklyReportForPeriodOwner($currentReportPeriod->id, $ownerUserId);
            }
        }
        if (! $latestWeeklyReport) {
            return [];
        }

        return $this->buildCarriedActivitiesFromWeeklyReport($latestWeeklyReport);
    }

    /**
     * Collect meeting rows from peer weekly plans in same period where this
     * plan owner was selected as a participant.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function incomingMeetingsForWeeklyPlanDocument(Document $document): array
    {
        if ($document->type !== Document::TYPE_WEEKLY_PLAN || ! $document->period_id) {
            return [];
        }

        $ownerUserId = (string) $document->user_id;
        $peerPlans = Document::query()
            ->where('type', Document::TYPE_WEEKLY_PLAN)
            ->where('period_id', $document->period_id)
            ->where('id', '!=', $document->id)
            ->select(['id', 'user_id', 'content'])
            ->get();

        $incomingRows = [];
        foreach ($peerPlans as $peerPlan) {
            $peerContent = is_array($peerPlan->content) ? $peerPlan->content : [];
            $meetingRows = is_array($peerContent['key_meetings'] ?? null) ? $peerContent['key_meetings'] : [];
            foreach ($meetingRows as $meetingRow) {
                if (! is_array($meetingRow)) {
                    continue;
                }

                $participantIds = is_array($meetingRow['participant_user_ids'] ?? null)
                    ? array_values(array_filter(array_map(static fn ($id) => trim((string) $id), $meetingRow['participant_user_ids'])))
                    : [];
                if (! in_array($ownerUserId, $participantIds, true)) {
                    continue;
                }

                $uid = trim((string) ($meetingRow['meeting_uid'] ?? ''));
                if ($uid === '') {
                    $meetingRow['meeting_uid'] = (string) Str::ulid();
                }
                $meetingRow['incoming_source_plan_id'] = (string) $peerPlan->id;
                $meetingRow['incoming_source_user_id'] = (string) $peerPlan->user_id;
                $incomingRows[] = $meetingRow;
            }
        }

        return $incomingRows;
    }

    /**
     * Build weekly report meeting rows for a viewer in a period:
     * - own planned meetings (editable)
     * - assigned meetings from peer plans (view-only)
     * - unmatched draft report rows (editable)
     *
     * @param  array<int, mixed>  $existingReportRows
     * @return array<int, array<string, mixed>>
     */
    protected function weeklyReportMeetingRowsForUserPeriod(string $periodId, string $viewerUserId, array $existingReportRows = []): array
    {
        if (trim($periodId) === '' || trim($viewerUserId) === '') {
            return [];
        }

        $existingByKey = [];
        foreach ($existingReportRows as $existingReportRow) {
            if (! is_array($existingReportRow)) {
                continue;
            }
            $normalizedExisting = $this->normalizeWeeklyReportMeetingRow(
                $existingReportRow,
                'report_manual',
                null,
                $viewerUserId,
                false
            );
            $existingKey = $this->weeklyReportMeetingRowKey($normalizedExisting);
            $existingByKey[$existingKey] = $normalizedExisting;
        }

        $rows = [];
        $seen = [];

        $ownPlan = Document::findWeeklyPlanForUserAndPeriod($periodId, $viewerUserId);
        if ($ownPlan && is_array($ownPlan->content)) {
            $ownPlanMeetings = is_array($ownPlan->content['key_meetings'] ?? null)
                ? $ownPlan->content['key_meetings']
                : [];
            foreach ($ownPlanMeetings as $ownPlanMeetingRow) {
                if (! is_array($ownPlanMeetingRow)) {
                    continue;
                }
                $normalizedOwnRow = $this->normalizeWeeklyReportMeetingRow(
                    $ownPlanMeetingRow,
                    'own_plan',
                    (string) $ownPlan->id,
                    (string) $viewerUserId,
                    false
                );
                $rowKey = $this->weeklyReportMeetingRowKey($normalizedOwnRow);
                if (isset($existingByKey[$rowKey])) {
                    $existingRow = $existingByKey[$rowKey];
                    $normalizedOwnRow['date'] = $this->preferWeeklyReportMeetingField($existingRow, $normalizedOwnRow, 'date');
                    $normalizedOwnRow['meeting'] = $this->preferWeeklyReportMeetingField($existingRow, $normalizedOwnRow, 'meeting');
                    $normalizedOwnRow['status'] = $this->preferWeeklyReportMeetingField($existingRow, $normalizedOwnRow, 'status');
                    $normalizedOwnRow['participant_user_ids'] = is_array($existingRow['participant_user_ids'] ?? null)
                        && $existingRow['participant_user_ids'] !== []
                        ? $existingRow['participant_user_ids']
                        : $normalizedOwnRow['participant_user_ids'];
                    $normalizedOwnRow['location'] = $this->preferWeeklyReportMeetingField($existingRow, $normalizedOwnRow, 'location');
                    $normalizedOwnRow['reactions_notes'] = $this->preferWeeklyReportMeetingField($existingRow, $normalizedOwnRow, 'reactions_notes');
                }
                if (isset($seen[$rowKey])) {
                    continue;
                }
                $seen[$rowKey] = true;
                $rows[] = $normalizedOwnRow;
            }
        }

        $peerPlans = Document::query()
            ->where('type', Document::TYPE_WEEKLY_PLAN)
            ->where('period_id', $periodId)
            ->where('user_id', '!=', $viewerUserId)
            ->select(['id', 'user_id', 'content'])
            ->get();

        foreach ($peerPlans as $peerPlan) {
            $peerContent = is_array($peerPlan->content) ? $peerPlan->content : [];
            $peerMeetings = is_array($peerContent['key_meetings'] ?? null)
                ? $peerContent['key_meetings']
                : [];
            foreach ($peerMeetings as $peerMeetingRow) {
                if (! is_array($peerMeetingRow)) {
                    continue;
                }
                $participantIds = is_array($peerMeetingRow['participant_user_ids'] ?? null)
                    ? array_values(array_filter(array_map(static fn ($id) => trim((string) $id), $peerMeetingRow['participant_user_ids'])))
                    : [];
                if (! in_array($viewerUserId, $participantIds, true)) {
                    continue;
                }
                $normalizedAssignedRow = $this->normalizeWeeklyReportMeetingRow(
                    $peerMeetingRow,
                    'assigned_plan',
                    (string) $peerPlan->id,
                    (string) $peerPlan->user_id,
                    true
                );
                $rowKey = $this->weeklyReportMeetingRowKey($normalizedAssignedRow);
                if (isset($seen[$rowKey])) {
                    continue;
                }
                $seen[$rowKey] = true;
                $rows[] = $normalizedAssignedRow;
            }
        }

        foreach ($existingByKey as $existingKey => $existingRow) {
            if (isset($seen[$existingKey])) {
                continue;
            }
            $sourceType = trim((string) ($existingRow['source_type'] ?? ''));
            $sourceOwnerUserId = trim((string) ($existingRow['source_owner_user_id'] ?? ''));
            if ($sourceType === 'assigned_plan' && $sourceOwnerUserId !== '' && $sourceOwnerUserId !== $viewerUserId) {
                continue;
            }
            $existingRow['is_readonly_for_current_user'] = false;
            if ($sourceType === '') {
                $existingRow['source_type'] = 'report_manual';
            }
            if ($sourceOwnerUserId === '') {
                $existingRow['source_owner_user_id'] = $viewerUserId;
            }
            $rows[] = $existingRow;
        }

        if ($rows === []) {
            $rows[] = $this->normalizeWeeklyReportMeetingRow(
                ['date' => '', 'meeting' => '', 'participant_user_ids' => [], 'location' => '', 'reactions_notes' => ''],
                'report_manual',
                null,
                $viewerUserId,
                false
            );
        }

        return array_values($rows);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normalizeWeeklyReportMeetingRow(
        array $row,
        string $sourceType,
        ?string $sourcePlanId,
        string $sourceOwnerUserId,
        bool $isReadonlyForCurrentUser
    ): array {
        $participantIds = is_array($row['participant_user_ids'] ?? null)
            ? array_values(array_unique(array_filter(array_map(static fn ($id) => trim((string) $id), $row['participant_user_ids']))))
            : [];
        sort($participantIds);

        $meetingUid = trim((string) ($row['meeting_uid'] ?? ''));
        if ($meetingUid === '' && $sourcePlanId !== null && in_array($sourceType, ['own_plan', 'assigned_plan'], true)) {
            $meetingUid = 'derived_'.substr(sha1(implode('|', [
                $sourcePlanId,
                trim((string) ($row['date'] ?? '')),
                trim((string) ($row['meeting'] ?? '')),
                trim((string) ($row['location'] ?? '')),
                implode(',', $participantIds),
            ])), 0, 20);
        }

        return [
            'meeting_uid' => $meetingUid,
            'date' => $this->formatMeetingDateField($row['date'] ?? ''),
            'meeting' => trim((string) ($row['meeting'] ?? '')),
            'participant_user_ids' => $participantIds,
            'status' => trim((string) ($row['status'] ?? '')),
            'location' => trim((string) ($row['location'] ?? '')),
            'reactions_notes' => trim((string) ($row['reactions_notes'] ?? '')),
            'source_type' => trim($sourceType) !== '' ? trim($sourceType) : 'report_manual',
            'source_plan_id' => $sourcePlanId !== null ? trim($sourcePlanId) : '',
            'source_owner_user_id' => trim($sourceOwnerUserId),
            'is_readonly_for_current_user' => $isReadonlyForCurrentUser,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function weeklyReportMeetingRowKey(array $row): string
    {
        $meetingUid = trim((string) ($row['meeting_uid'] ?? ''));
        if ($meetingUid !== '') {
            return 'uid:'.$meetingUid;
        }

        $participantIds = is_array($row['participant_user_ids'] ?? null)
            ? array_values(array_filter(array_map(static fn ($id) => trim((string) $id), $row['participant_user_ids'])))
            : [];
        sort($participantIds);

        return mb_strtolower(implode('|', [
            trim((string) ($row['date'] ?? '')),
            trim((string) ($row['meeting'] ?? '')),
            trim((string) ($row['location'] ?? '')),
            implode(',', $participantIds),
            trim((string) ($row['source_plan_id'] ?? '')),
            trim((string) ($row['source_owner_user_id'] ?? '')),
        ]));
    }

    /**
     * Keep only editable own/manual meeting rows for weekly report save.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function stripReadonlyAssignedMeetingsFromWeeklyReportContent(array $content, User $actingUser, ?string $periodId): array
    {
        if (trim((string) $periodId) === '') {
            return $content;
        }

        $resolvedRows = $this->weeklyReportMeetingRowsForUserPeriod((string) $periodId, (string) $actingUser->id);
        $lockedUidSet = [];
        $lockedKeySet = [];
        foreach ($resolvedRows as $resolvedRow) {
            if (! is_array($resolvedRow) || ! ($resolvedRow['is_readonly_for_current_user'] ?? false)) {
                continue;
            }
            $uid = trim((string) ($resolvedRow['meeting_uid'] ?? ''));
            if ($uid !== '') {
                $lockedUidSet[$uid] = true;
            }
            $lockedKeySet[$this->weeklyReportMeetingRowKey($resolvedRow)] = true;
        }

        $inputRows = is_array($content['key_meetings'] ?? null) ? $content['key_meetings'] : [];
        $filteredRows = [];
        foreach ($inputRows as $inputRow) {
            if (! is_array($inputRow)) {
                continue;
            }

            $submittedSourceType = trim((string) ($inputRow['source_type'] ?? ''));
            $submittedOwnerUserId = trim((string) ($inputRow['source_owner_user_id'] ?? ''));
            if ($submittedSourceType === 'assigned_plan' && $submittedOwnerUserId !== '' && $submittedOwnerUserId !== (string) $actingUser->id) {
                continue;
            }

            $uid = trim((string) ($inputRow['meeting_uid'] ?? ''));
            if ($uid !== '' && isset($lockedUidSet[$uid])) {
                continue;
            }

            $normalizedInputRow = $this->normalizeWeeklyReportMeetingRow(
                $inputRow,
                (string) ($inputRow['source_type'] ?? ''),
                isset($inputRow['source_plan_id']) ? trim((string) $inputRow['source_plan_id']) : null,
                (string) ($inputRow['source_owner_user_id'] ?? ''),
                false
            );
            $inputKey = $this->weeklyReportMeetingRowKey($normalizedInputRow);
            if (isset($lockedKeySet[$inputKey])) {
                continue;
            }

            unset(
                $inputRow['source_type'],
                $inputRow['source_plan_id'],
                $inputRow['source_owner_user_id'],
                $inputRow['is_readonly_for_current_user']
            );
            $filteredRows[] = $inputRow;
        }

        $content['key_meetings'] = $filteredRows;

        return $content;
    }

    protected function syncNextWeeklyPlanCarryOverFromReport(Document $weeklyReport): void
    {
        if ($weeklyReport->type !== Document::TYPE_WEEKLY_REPORT) {
            return;
        }

        $weeklyReport->loadMissing('period');
        $currentPeriod = $weeklyReport->period;
        if (! $currentPeriod) {
            return;
        }

        $nextPeriod = $currentPeriod->getNextPeriod();
        if (! $nextPeriod) {
            return;
        }

        $nextPlan = Document::findWeeklyPlanForPeriodOwner($nextPeriod->id, (string) $weeklyReport->user_id);
        if (! $nextPlan || ! $nextPlan->isDraft()) {
            return;
        }

        $domainWeeklyReportId = optional($weeklyReport->weeklyReportDomain)->id;
        $nextPlanContent = is_array($nextPlan->content) ? $nextPlan->content : [];
        $existingActivities = is_array($nextPlanContent['planned_activities'] ?? null) ? $nextPlanContent['planned_activities'] : [];

        $carriedRows = $this->buildCarriedActivitiesFromWeeklyReport($weeklyReport);
        $carriedByUid = [];
        foreach ($carriedRows as $carriedRow) {
            if (! is_array($carriedRow)) {
                continue;
            }
            $uid = trim((string) ($carriedRow['activity_uid'] ?? ''));
            if ($uid !== '') {
                $carriedByUid[$uid] = $carriedRow;
            }
        }

        $mergedActivities = [];
        $existingKeys = [];
        foreach ($existingActivities as $existingRow) {
            if (! is_array($existingRow)) {
                continue;
            }

            $carrySource = trim((string) ($existingRow['carry_over_source_weekly_report_id'] ?? ''));
            $uid = trim((string) ($existingRow['activity_uid'] ?? ''));
            if ($domainWeeklyReportId !== null && $carrySource === (string) $domainWeeklyReportId) {
                if ($uid !== '' && isset($carriedByUid[$uid])) {
                    unset($carriedByUid[$uid]);
                } else {
                    $existingActivity = mb_strtolower(trim((string) ($existingRow['activity'] ?? '')));
                    foreach ($carriedByUid as $carriedUid => $carriedRow) {
                        $carriedActivity = mb_strtolower(trim((string) ($carriedRow['activity'] ?? '')));
                        if ($carriedActivity === '' || $existingActivity === '') {
                            continue;
                        }
                        if ($existingActivity === $carriedActivity
                            || str_starts_with($existingActivity, $carriedActivity)
                            || str_starts_with($carriedActivity, $existingActivity)) {
                            unset($carriedByUid[$carriedUid]);
                            break;
                        }
                    }
                }

                $mergedActivities[] = $existingRow;
                $existingKeys[mb_strtolower(trim((string) ($existingRow['day'] ?? '')).'|'.trim((string) ($existingRow['activity'] ?? '')))] = true;

                continue;
            }

            $mergedActivities[] = $existingRow;
            $existingKeys[mb_strtolower(trim((string) ($existingRow['day'] ?? '')).'|'.trim((string) ($existingRow['activity'] ?? '')))] = true;
        }

        foreach ($carriedByUid as $carriedRow) {
            $rowKey = mb_strtolower(trim((string) ($carriedRow['day'] ?? '')).'|'.trim((string) ($carriedRow['activity'] ?? '')));
            if (isset($existingKeys[$rowKey])) {
                continue;
            }
            $mergedActivities[] = $carriedRow;
            $existingKeys[$rowKey] = true;
        }

        $nextPlanContent['planned_activities'] = $this->ensureWeeklyPlanActivityUids($mergedActivities);
        $nextPlanContent = $this->normalizeTemplateContent($nextPlanContent, Document::TYPE_WEEKLY_PLAN);
        $nextPlan->update(['content' => $nextPlanContent]);
        $this->documentDomainSyncService->syncFromDocument($nextPlan);
        $this->syncWeeklyPlanAssignments($nextPlan);
    }

    protected function ensureWeeklyPlanActivityUids(array $rows): array
    {
        foreach ($rows as &$row) {
            if (! is_array($row)) {
                continue;
            }

            $uid = trim((string) ($row['activity_uid'] ?? ''));
            if ($uid === '') {
                $row['activity_uid'] = (string) Str::ulid();
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Collapse duplicate carried activities (e.g. original carry row + staff-edited copy).
     *
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function dedupeWeeklyPlanPlannedActivities(array $rows): array
    {
        $deduped = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $uid = trim((string) ($row['activity_uid'] ?? ''));
            $carrySource = trim((string) ($row['carry_over_source_weekly_report_id'] ?? ''));
            $activity = mb_strtolower(trim((string) ($row['activity'] ?? '')));
            $day = mb_strtolower(trim((string) ($row['day'] ?? '')));

            $skip = false;
            foreach ($deduped as $index => $existing) {
                $existingUid = trim((string) ($existing['activity_uid'] ?? ''));
                if ($uid !== '' && $existingUid !== '' && $uid === $existingUid) {
                    $skip = true;
                    break;
                }

                $existingCarry = trim((string) ($existing['carry_over_source_weekly_report_id'] ?? ''));
                $existingActivity = mb_strtolower(trim((string) ($existing['activity'] ?? '')));
                $existingDay = mb_strtolower(trim((string) ($existing['day'] ?? '')));

                if ($carrySource !== '' && $carrySource === $existingCarry) {
                    $sameDay = $day === $existingDay || $day === '' || $existingDay === '';
                    $relatedActivity = $activity === $existingActivity
                        || ($activity !== '' && $existingActivity !== ''
                            && (str_starts_with($activity, $existingActivity) || str_starts_with($existingActivity, $activity)));

                    if ($relatedActivity && $sameDay) {
                        if (strlen($activity) >= strlen($existingActivity)) {
                            $deduped[$index] = $row;
                        }
                        $skip = true;
                        break;
                    }
                }

                if ($day.'|'.$activity !== '|' && $day.'|'.$activity === $existingDay.'|'.$existingActivity) {
                    $skip = true;
                    break;
                }
            }

            if (! $skip) {
                $deduped[] = $row;
            }
        }

        return array_values($deduped);
    }

    protected function syncWeeklyPlanAssignments(Document $document): void
    {
        if ($document->type !== Document::TYPE_WEEKLY_PLAN) {
            return;
        }

        // Avoid `$document->refresh()` which queries the `documents` union view.
        // In SQLite test environments (and during cutover states), the union view
        // may not reflect the just-synced domain row within the same transaction.
        $this->weeklyActivityAssignmentService->syncForWeeklyPlan($document);
    }

    protected function trimDeep(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->trimDeep($v);
            }

            return $value;
        }

        return is_string($value) ? trim($value) : $value;
    }

    protected function filterNonEmptyRows(mixed $rows, array $keys): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, function ($row) use ($keys) {
            if (! is_array($row)) {
                return false;
            }

            foreach ($keys as $key) {
                if (! array_key_exists($key, $row)) {
                    continue;
                }
                $v = $row[$key];
                if (is_array($v)) {
                    if (! empty($v)) {
                        return true;
                    }

                    continue;
                }
                if ($v !== null && $v !== '') {
                    return true;
                }
            }

            return false;
        }));
    }

    protected function templateHasBudgetRows(?array $content): bool
    {
        if (! is_array($content)) {
            return false;
        }

        $budgetRows = $content['budget'] ?? [];
        if (! is_array($budgetRows)) {
            return false;
        }

        foreach ($budgetRows as $budgetRow) {
            if (! is_array($budgetRow)) {
                continue;
            }

            $item = trim((string) ($budgetRow['item'] ?? ''));
            $description = trim((string) ($budgetRow['description_purpose'] ?? ''));
            $amount = trim((string) ($budgetRow['amount'] ?? ''));
            if ($item !== '' || $description !== '' || $amount !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Assign a stable unique identifier to each budget row that doesn't already have one.
     */
    protected function ensureBudgetRowUids(array $budgetRows): array
    {
        foreach ($budgetRows as &$row) {
            if (! is_array($row)) {
                continue;
            }
            $uid = trim((string) ($row['budget_uid'] ?? ''));
            if ($uid === '') {
                $row['budget_uid'] = (string) Str::ulid();
            }
        }
        unset($row);

        return $budgetRows;
    }

    protected function requestedBudgetTotal(Document $document): float
    {
        if (! is_array($document->content)) {
            return 0.0;
        }

        $budgetRows = $document->content['budget'] ?? [];
        if (! is_array($budgetRows)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($budgetRows as $budgetRow) {
            if (! is_array($budgetRow)) {
                continue;
            }

            $rawAmount = trim((string) ($budgetRow['amount'] ?? ''));
            if ($rawAmount === '') {
                continue;
            }

            $normalizedAmount = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $rawAmount));
            if ($normalizedAmount === null || $normalizedAmount === '' || ! is_numeric($normalizedAmount)) {
                continue;
            }

            $total += (float) $normalizedAmount;
        }

        return $total;
    }

    /**
     * @return array{0: ?Document, 1: ?array<string, mixed>}
     */
    protected function resolveWeeklyRelatedDocumentContext(Document $document, bool $includeCreateAction = true): array
    {
        if (! in_array($document->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true)) {
            return [null, null];
        }

        $relatedDocument = $document->relatedWeeklyDocument();

        $relatedAction = null;
        if ($includeCreateAction && $relatedDocument === null && Auth::check()) {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $relatedAction = $document->relatedWeeklyCreateActionFor($user);
        }

        if ($relatedDocument) {
            $relatedDocument->loadMissing('user');
        }

        return [$relatedDocument, $relatedAction];
    }

    protected function shouldShowWeeklyRelatedPanel(
        Document $document,
        ?Document $relatedDocument,
        ?array $relatedAction,
        bool $reviewerPresentation = false
    ): bool {
        if (! in_array($document->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true)) {
            return false;
        }

        if ($relatedDocument || $relatedAction) {
            return true;
        }

        if ($reviewerPresentation) {
            return true;
        }

        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user?->hasPermissionTo('view all documents') ?? false;
    }

    protected function redirectToWeeklyDocument(Document $document, string $successMessage): RedirectResponse
    {
        $resolved = Document::fromDomainId((string) $document->id) ?? $document;

        return redirect()->route('documents.show', ['document' => $resolved->id])
            ->with('success', $successMessage);
    }
}
