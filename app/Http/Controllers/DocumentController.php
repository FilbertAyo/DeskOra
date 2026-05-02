<?php

namespace App\Http\Controllers;

use App\Exports\WeeklyBudgetReviewExport;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\MonthlyReportYearPlanActivities;
use App\Services\WeeklyActivityAssignmentService;
use App\Services\WeeklySubmissionReminderService;
use App\Services\WorkflowEmailNotificationService;
use App\Services\WorkPeriodService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class DocumentController extends Controller
{
    public function __construct(
        private readonly WorkPeriodService $workPeriodService,
        private readonly WeeklySubmissionReminderService $weeklySubmissionReminderService,
        private readonly WeeklyActivityAssignmentService $weeklyActivityAssignmentService,
        private readonly WorkflowEmailNotificationService $workflowEmailNotificationService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Document::class);
        if ($request->hasSession()) {
            $request->session()->put('documents.last_listing_url', $request->fullUrl());
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $query = Document::with(['user', 'period', 'sharedUsers:id,name']);

        // Submitted documents are listed organization-wide for Monday stand-ups.
        // Returned-for-revision drafts stay limited to the author/collaborators (reviewers see all).
        $scopedToOwnDocuments = ! $user->hasPermissionTo('view all documents')
            && $request->input('review_status') === 'returned';

        if ($scopedToOwnDocuments) {
            $query->forOwnerOrSharedUser($user->id);
        }

        // Get current period for defaults
        $currentPeriod = $this->workPeriodService->periodForWeeklyReportSubmission();
        $currentYear = date('Y');

        // Filter by type if provided (default: all types)
        if ($request->has('type') && $request->type) {
            $query->ofType($request->type);
        }

        // Filter by period only when explicitly selected.
        $periodId = $request->input('period_id');
        if ($periodId) {
            $query->forPeriod($periodId);
        }

        // Filter by year only when explicitly selected.
        $year = $request->input('year');
        if ($year) {
            if ($request->input('type') === Document::TYPE_MONTHLY_REPORT) {
                $query->where('year', $year);
            } else {
                $query->whereHas('period', function ($q) use ($year) {
                    $q->where('year', $year);
                });
            }
        }

        if ($request->filled('month') && $request->input('type') === Document::TYPE_MONTHLY_REPORT) {
            $query->where('month', (int) $request->input('month'));
        }

        // This page focuses on weekly plan/report rows.
        $query->whereIn('type', [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT]);

        if ($request->input('review_status') === 'returned') {
            $query->whereIn('type', [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT])
                ->where('state', Document::STATE_DRAFT)
                ->whereNotNull('review_rejection_reason')
                ->where('review_rejection_reason', '!=', '');
        } else {
            // Default documents list to submitted items only.
            $query->submitted();
        }

        if ($scopedToOwnDocuments
            && in_array($request->input('type'), [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true)) {
            $this->applyWeeklyListSortForUser($query, $user);
        } else {
            $query->latest();
        }

        $weeklyDocuments = $query->get();
        $weeklyRowsCollection = $this->buildWeeklyRowsForIndex($weeklyDocuments);
        $page = max((int) $request->input('page', 1), 1);
        $perPage = 15;
        $weeklyRows = new LengthAwarePaginator(
            $weeklyRowsCollection->forPage($page, $perPage)->values(),
            $weeklyRowsCollection->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
        $returnedIds = $weeklyRows->getCollection()
            ->flatMap(function ($row) {
                return [
                    data_get($row, 'plan.id'),
                    data_get($row, 'report.id'),
                ];
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
        $returnedDocumentMap = $this->getReturnedDocumentMapForIds($returnedIds);

        $years = WorkPeriod::select('year')->distinct()->orderBy('year', 'desc')->pluck('year');
        if ($request->input('type') === Document::TYPE_MONTHLY_REPORT) {
            $reportYears = Document::query()
                ->where('type', Document::TYPE_MONTHLY_REPORT)
                ->whereNotNull('year')
                ->distinct()
                ->orderByDesc('year')
                ->pluck('year');
            $years = $reportYears->merge($years)->unique()->sortDesc()->values();
        }

        $nextPeriod = $this->workPeriodService->periodForWeeklyPlanSubmission();
        $deadlineStatuses = $this->workPeriodService->deadlineStatusesForUi($currentPeriod, $nextPeriod);

        return view('documents.index', [
            'documents' => $weeklyRows,
            'weeklyRows' => $weeklyRows,
            'periods' => WorkPeriod::orderBy('year', 'desc')->orderBy('week_number', 'desc')->get(),
            'years' => $years,
            'currentPeriod' => $currentPeriod,
            'nextPeriod' => $nextPeriod,
            'deadlineStatuses' => $deadlineStatuses,
            'returnedDocumentMap' => $returnedDocumentMap,
        ]);
    }

    /**
     * @param  Collection<int, Document>  $weeklyDocs
     * @return Collection<int, array<string, mixed>>
     */
    private function buildWeeklyRowsForIndex(Collection $weeklyDocs): Collection
    {
        $pickPreferred = function (Collection $rows, string $type): ?Document {
            $typedRows = $rows
                ->where('type', $type)
                ->sortByDesc(fn (Document $row) => $row->updated_at?->timestamp ?? 0)
                ->values();

            /** @var Document|null $submitted */
            $submitted = $typedRows->firstWhere('state', Document::STATE_SUBMITTED);

            return $submitted ?? $typedRows->first();
        };

        return $weeklyDocs
            ->groupBy(function (Document $doc) use ($weeklyDocs) {
                $effectiveSharedUsers = $this->resolveWeeklyDocSharedUsersForIndex($doc, $weeklyDocs);
                $participantIds = collect([$doc->user_id])
                    ->merge($effectiveSharedUsers->pluck('id'))
                    ->filter()
                    ->map(fn ($id) => (string) $id)
                    ->unique()
                    ->sort()
                    ->values();

                return ($doc->period_id ?? 'none').'_'.$participantIds->implode('_');
            })
            ->map(function (Collection $items) use ($weeklyDocs, $pickPreferred) {
                $plan = $pickPreferred($items, Document::TYPE_WEEKLY_PLAN);
                $report = $pickPreferred($items, Document::TYPE_WEEKLY_REPORT);
                $participants = $items
                    ->flatMap(fn (Document $row) => collect([$row->user])->merge($this->resolveWeeklyDocSharedUsersForIndex($row, $weeklyDocs)))
                    ->filter()
                    ->unique('id')
                    ->values();

                return [
                    'plan' => $plan,
                    'report' => $report,
                    'period' => $plan?->period ?? $report?->period,
                    'participants' => $participants,
                    'plan_shared_users' => $this->explicitWeeklyDocSharedUsersForIndex($plan),
                    'report_shared_users' => $this->explicitWeeklyDocSharedUsersForIndex($report),
                    'updated_at' => collect([$plan?->updated_at, $report?->updated_at])->filter()->max(),
                ];
            })
            ->sortByDesc(function (array $row) {
                $period = $row['period'] ?? null;
                $year = $period ? (int) $period->year : 0;
                $week = $period ? (int) $period->week_number : 0;
                $updatedAtTimestamp = data_get($row, 'updated_at.timestamp', 0);

                return sprintf('%04d%02d%010d', $year, $week, (int) $updatedAtTimestamp);
            })
            ->values();
    }

    /**
     * @param  Collection<int, Document>  $weeklyDocs
     * @return Collection<int, User>
     */
    private function resolveWeeklyDocSharedUsersForIndex(?Document $doc, Collection $weeklyDocs): Collection
    {
        if (! $doc) {
            return collect();
        }

        $directSharedUsers = collect($doc->sharedUsers)
            ->filter(fn ($member) => $member && (string) $member->id !== (string) $doc->user_id)
            ->unique('id')
            ->values();

        if ($doc->type !== Document::TYPE_WEEKLY_REPORT
            || $directSharedUsers->isNotEmpty()
            || ! $doc->period_id) {
            return $directSharedUsers;
        }

        /** @var Document|null $fallbackPlan */
        $fallbackPlan = $weeklyDocs
            ->where('type', Document::TYPE_WEEKLY_PLAN)
            ->first(function (Document $plan) use ($doc) {
                if ((string) $plan->period_id !== (string) $doc->period_id) {
                    return false;
                }

                if ((string) $plan->user_id === (string) $doc->user_id) {
                    return true;
                }

                return $plan->sharedUsers->contains(function ($sharedUser) use ($doc) {
                    return (string) $sharedUser->id === (string) $doc->user_id;
                });
            });

        if (! $fallbackPlan) {
            return $directSharedUsers;
        }

        return $fallbackPlan->sharedUsers
            ->push($fallbackPlan->user)
            ->filter(fn ($member) => $member && (string) $member->id !== (string) $doc->user_id)
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function explicitWeeklyDocSharedUsersForIndex(?Document $doc): Collection
    {
        if (! $doc) {
            return collect();
        }

        return collect($doc->sharedUsers)
            ->filter(fn ($member) => $member && (string) $member->id !== (string) $doc->user_id)
            ->unique('id')
            ->values();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $selectedType = $request->query('type');

        // Authorize create action
        $this->authorize('create', Document::class);

        // If type is specified, also check if user can create that specific type
        if ($selectedType) {
            $this->authorize('createType', [Document::class, $selectedType]);
        }

        $availableTypes = Document::getAvailableTypesForUser($user);

        if (empty($availableTypes)) {
            return redirect()->route('documents.index')
                ->with('error', 'You do not have permission to create documents.');
        }

        // Get all work periods for dropdown
        $workPeriods = WorkPeriod::orderBy('year', 'desc')
            ->orderBy('week_number', 'desc')
            ->get();

        // Determine default period based on document type
        $defaultPeriodId = null;
        $currentPeriod = $this->workPeriodService->periodForWeeklyReportSubmission();
        $nextPeriod = $this->workPeriodService->periodForWeeklyPlanSubmission();
        $relatedPlan = null;
        $carriedActivities = [];

        if ($selectedType) {
            if ($selectedType === Document::TYPE_WEEKLY_PLAN && $nextPeriod) {
                // For weekly plan, default to next week
                $defaultPeriodId = $nextPeriod->id;
                if ($currentPeriod) {
                    $latestWeeklyReport = Document::findWeeklyReportForPeriodOwner($currentPeriod->id, (string) $user->id);
                    if ($latestWeeklyReport) {
                        $carriedActivities = $this->buildCarriedActivitiesFromWeeklyReport($latestWeeklyReport);
                    }
                }
            } elseif ($selectedType === Document::TYPE_WEEKLY_REPORT && $currentPeriod) {
                // For weekly report, default to current week
                $defaultPeriodId = $currentPeriod->id;
                // Get the related plan for this period to show the main target
                $relatedPlan = Document::findWeeklyPlanForUserAndPeriod(
                    $currentPeriod->id,
                    (string) $user->id
                );
            }
        }

        $monthlyYearPlan = null;
        $monthlyPlannedActivities = collect();
        $monthlyHasSubDepartments = $user->subDepartments()->exists();
        $ref = now()->startOfMonth()->subMonth();
        $monthlyDefaultMonth = (int) $ref->month;
        $monthlyDefaultYear = (int) $ref->year;
        $monthlyYearChoices = collect(range((int) date('Y') + 1, (int) date('Y') - 5));

        if ($selectedType === Document::TYPE_MONTHLY_REPORT && isset($availableTypes[$selectedType])) {
            $prefillMonth = $request->query('month');
            $prefillYear = $request->query('year');
            $monthlyDefaultMonth = (int) old('month', ($prefillMonth !== null && $prefillMonth !== '') ? (int) $prefillMonth : $ref->month);
            $monthlyDefaultYear = (int) old('year', ($prefillYear !== null && $prefillYear !== '') ? (int) $prefillYear : $ref->year);
            $monthlyYearPlan = MonthlyReportYearPlanActivities::yearPlanForCalendarYear($monthlyDefaultYear);
            $monthlyPlannedActivities = MonthlyReportYearPlanActivities::plannedForUserMonth($user, $monthlyDefaultYear, $monthlyDefaultMonth);
            $planYears = \App\Models\YearPlan::query()->orderByDesc('year')->pluck('year');
            $monthlyYearChoices = $planYears->merge($monthlyYearChoices)->unique()->sortDesc()->values();
        }

        // If type is provided in query string, pre-select it
        if ($selectedType && isset($availableTypes[$selectedType])) {
            // Validate that user can create this type
            if (Document::canUserCreateType($user, $selectedType)) {
                $teamUsers = User::select('id', 'name')->orderBy('name')->get();
                $shareableUsers = $this->shareableDepartmentUsersFor($user);

                return view('documents.create', compact(
                    'availableTypes',
                    'selectedType',
                    'workPeriods',
                    'defaultPeriodId',
                    'currentPeriod',
                    'nextPeriod',
                    'relatedPlan',
                    'carriedActivities',
                    'teamUsers',
                    'shareableUsers',
                    'monthlyYearPlan',
                    'monthlyPlannedActivities',
                    'monthlyDefaultMonth',
                    'monthlyDefaultYear',
                    'monthlyYearChoices',
                    'monthlyHasSubDepartments'
                ));
            }
        }

        $teamUsers = User::select('id', 'name')->orderBy('name')->get();
        $shareableUsers = $this->shareableDepartmentUsersFor($user);
        $assignedWeeklyActivities = collect();
        if ($selectedType === Document::TYPE_WEEKLY_PLAN && $defaultPeriodId) {
            $assignedWeeklyActivities = $this->weeklyActivityAssignmentService->assignmentsForUser((string) $user->id, (string) $defaultPeriodId);
        }

        return view('documents.create', compact(
            'availableTypes',
            'workPeriods',
            'defaultPeriodId',
            'currentPeriod',
            'nextPeriod',
            'relatedPlan',
            'carriedActivities',
            'teamUsers',
            'shareableUsers',
            'monthlyYearPlan',
            'monthlyPlannedActivities',
            'monthlyDefaultMonth',
            'monthlyDefaultYear',
            'monthlyYearChoices',
            'monthlyHasSubDepartments',
            'assignedWeeklyActivities'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Validate file upload for PDF documents
        $validationRules = [
            'type' => ['required', 'string', 'in:weekly_plan,weekly_report,monthly_report,action_log'],
            // weekly_plan/weekly_report are filled online (no PDF upload).
            // monthly_report: structured form; PDF and attachment are optional.
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'], // Max 10MB
            'title' => ['nullable', 'string', 'max:255'],
        ];

        // Make period_id required for weekly plans and reports
        if (in_array($request->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT])) {
            $validationRules['period_id'] = ['required', 'string', 'ulid', 'exists:work_periods,id'];
            if ($request->type === Document::TYPE_WEEKLY_PLAN) {
                $validationRules['shared_user_ids'] = ['nullable', 'array'];
                $validationRules['shared_user_ids.*'] = ['nullable', 'string', 'ulid', 'exists:users,id'];
            }
        } else {
            $validationRules['period_id'] = ['nullable', 'string', 'ulid', 'exists:work_periods,id'];
        }

        // Add validation for monthly reports
        if ($request->type === Document::TYPE_MONTHLY_REPORT) {
            $validationRules['month'] = ['required', 'integer', 'min:1', 'max:12'];
            $validationRules['year'] = ['required', 'integer', 'min:2000', 'max:2100'];
            $validationRules['attachment'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xlsx,xls', 'max:10240'];
            $validationRules['file'] = ['nullable', 'file', 'mimes:pdf', 'max:10240'];
            $validationRules['content'] = ['nullable', 'array'];
        }

        // Add validation for main_target for weekly plans
        if ($request->type === Document::TYPE_WEEKLY_PLAN) {
            $validationRules['main_target'] = ['required', 'string', 'max:2000'];
        }

        // Online template content (weekly_plan/weekly_report)
        $validationRules['content'] = ['nullable', 'array'];

        if ($request->type === Document::TYPE_WEEKLY_PLAN) {
            $validationRules['content.objective.description'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.objective.status'] = ['nullable', 'string', 'in:Not started,Completed,Carried,In Progress'];
            $validationRules['content.objective.reflection_progress'] = ['nullable', 'string', 'max:4000'];

            $validationRules['content.planned_activities'] = ['nullable', 'array'];
            $validationRules['content.planned_activities.*.day'] = ['nullable', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday'];
            $validationRules['content.planned_activities.*.activity'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.planned_activities.*.location'] = ['nullable', 'string', 'max:255'];
            $validationRules['content.planned_activities.*.team_member_ids'] = ['nullable', 'array'];
            $validationRules['content.planned_activities.*.team_member_ids.*'] = ['nullable', 'string', 'ulid', 'exists:users,id'];
            $validationRules['content.planned_activities.*.notes_inclusion'] = ['nullable', 'string', 'max:4000'];

            $validationRules['content.key_meetings'] = ['nullable', 'array'];
            $validationRules['content.key_meetings.*.date'] = ['nullable', 'date'];
            $validationRules['content.key_meetings.*.meeting'] = ['nullable', 'string', 'max:255'];
            $validationRules['content.key_meetings.*.participants'] = ['nullable', 'string', 'max:255'];
            $validationRules['content.key_meetings.*.location'] = ['nullable', 'string', 'max:255'];
            $validationRules['content.key_meetings.*.reactions_notes'] = ['nullable', 'string', 'max:4000'];

            $validationRules['content.budget'] = ['nullable', 'array'];
            $validationRules['content.budget.*.item'] = ['nullable', 'string', 'max:255'];
            $validationRules['content.budget.*.description_purpose'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.budget.*.amount'] = ['nullable', 'string', 'max:60'];
        }

        if ($request->type === Document::TYPE_WEEKLY_REPORT) {
            $validationRules['content.summary'] = ['nullable', 'string', 'max:5000'];

            $validationRules['content.activity_progress'] = ['nullable', 'array'];
            $validationRules['content.activity_progress.*.activity_output'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.activity_progress.*.off_plan_activity'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.activity_progress.*.progress'] = ['nullable', 'string', 'in:Not started,In progress,Done,Partially done'];
            $validationRules['content.activity_progress.*.remarks_challenges'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.activity_progress.*.next_step'] = ['nullable', 'string', 'max:2000'];

            $validationRules['content.key_achievements'] = ['nullable', 'array'];
            $validationRules['content.key_achievements.*'] = ['nullable', 'string', 'max:2000'];

            $validationRules['content.challenges_mitigation'] = ['nullable', 'array'];
            $validationRules['content.challenges_mitigation.*.challenge'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.challenges_mitigation.*.action_taken_planned'] = ['nullable', 'string', 'max:2000'];

            $validationRules['content.budget_statuses'] = ['nullable', 'array'];
            $validationRules['content.budget_statuses.*.status'] = ['nullable', 'string', 'in:Not started,Pending,Received,Paid'];
            $validationRules['content.budget_statuses.*.notes'] = ['nullable', 'string', 'max:2000'];
        }

        // Add validation for target accomplishment for weekly reports
        if ($request->type === Document::TYPE_WEEKLY_REPORT) {
            $validationRules['target_accomplished_status'] = ['nullable', 'string', 'in:completed,partially_completed,not_completed'];
            $validationRules['target_explanation'] = ['nullable', 'string', 'max:2000'];
        }

        $request->validate($validationRules);

        $action = $request->input('action', 'draft');
        $action = in_array($action, ['submit', 'draft'], true) ? $action : 'draft';
        $sharedUserIds = [];

        if (Document::supportsSharedEditing($request->type) && $request->type !== Document::TYPE_WEEKLY_REPORT) {
            $sharedUserIds = $this->extractRequestedSharedUserIds($request);
            $sharedUserIds = $this->validateSharedMembersWithinDepartment($user, $sharedUserIds);
        }

        // Normalize and trim template payload to avoid storing empty rows.
        $templateContent = null;
        if (in_array($request->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT]) && $request->has('content') && is_array($request->input('content'))) {
            $templateContent = $this->normalizeTemplateContent($request->input('content'), $request->type);
        }
        $weeklyReportTargetStatus = null;
        if ($request->type === Document::TYPE_WEEKLY_REPORT) {
            $weeklyReportTargetStatus = $this->resolveWeeklyReportTargetStatus($request);
            if ($weeklyReportTargetStatus === null && $action === 'submit') {
                throw ValidationException::withMessages([
                    'target_accomplished_status' => 'Please select target accomplishment status.',
                ]);
            }
            if (in_array($weeklyReportTargetStatus, ['partially_completed', 'not_completed'], true)
                && ! $request->filled('target_explanation')
                && $action === 'submit') {
                throw ValidationException::withMessages([
                    'target_explanation' => 'Please explain why the target was not fully accomplished.',
                ]);
            }
            if ($templateContent === null) {
                $templateContent = [];
            }
            if ($weeklyReportTargetStatus !== null) {
                $templateContent['target_accomplished_status'] = $weeklyReportTargetStatus;
            } else {
                unset($templateContent['target_accomplished_status']);
            }
        }

        $monthlyContent = null;
        if ($request->type === Document::TYPE_MONTHLY_REPORT) {
            $monthlyContent = $this->normalizeMonthlyReportContent(
                $user,
                (int) $request->year,
                (int) $request->month,
                is_array($request->input('content')) ? $request->input('content') : [],
                $action
            );
        }

        // Authorize create action
        $this->authorize('create', Document::class);

        // Authorize create action with the specific type
        $this->authorize('createType', [Document::class, $request->type]);

        // Get period from request or determine based on document type
        $period = null;
        if ($request->filled('period_id')) {
            // Use the period selected by user
            $period = WorkPeriod::find($request->period_id);
            if (! $period) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Selected work period not found.');
            }
        } elseif (in_array($request->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT])) {
            // Fallback to automatic selection if no period_id provided
            if ($request->type === Document::TYPE_WEEKLY_PLAN) {
                // Plan is for NEXT week (submitted during current week)
                $period = $this->workPeriodService->periodForWeeklyPlanSubmission();
                if (! $period) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Next week period not found. Please contact administrator.');
                }
            } elseif ($request->type === Document::TYPE_WEEKLY_REPORT) {
                // Report is for CURRENT week
                $period = $this->workPeriodService->periodForWeeklyReportSubmission();
                if (! $period) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'No current work period found. Please contact administrator.');
                }
            }
        } elseif ($request->type === Document::TYPE_MONTHLY_REPORT) {
            $period = null;
        }

        if ($period && in_array($request->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true)) {
            $expectedPeriod = $request->type === Document::TYPE_WEEKLY_PLAN
                ? $this->workPeriodService->periodForWeeklyPlanSubmission()
                : $this->workPeriodService->periodForWeeklyReportSubmission();

            if (! $expectedPeriod || $period->id !== $expectedPeriod->id) {
                $errorMessage = $request->type === Document::TYPE_WEEKLY_PLAN
                    ? 'Weekly plan must be submitted for next week.'
                    : 'Weekly report must be submitted for the current week.';

                return redirect()->back()
                    ->withInput()
                    ->with('error', $errorMessage);
            }
        }

        if ($period && ! $period->isOpen()) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'This work period is closed. Please contact your administrator.');
        }

        if ($request->type === Document::TYPE_WEEKLY_REPORT) {
            $sharedUserIds = $this->weeklyReportSharedUserIdsFromPlanContext($user, $period?->id);
            $sharedUserIds = $this->validateSharedMembersWithinDepartment($user, $sharedUserIds);
        }

        // Check if document already exists for this user, period/month/year, and type
        $existingDocument = null;
        if (in_array($request->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT]) && $period) {
            // For weekly plans and reports, a user can own one or collaborate on one per period.
            $existingDocument = Document::query()
                ->forOwnerOrSharedUser($user->id)
                ->where('period_id', $period->id)
                ->where('type', $request->type)
                ->first();
        } elseif ($request->type === Document::TYPE_MONTHLY_REPORT) {
            // For monthly reports - one per month/year combination
            $existingDocument = Document::where('user_id', $user->id)
                ->where('type', Document::TYPE_MONTHLY_REPORT)
                ->where('month', $request->month)
                ->where('year', $request->year)
                ->first();
        }

        if (Document::supportsSharedEditing($request->type) && $period) {
            $conflictMessage = $this->firstCollaborationConflictMessage(
                $period->id,
                $request->type,
                array_merge([$user->id], $sharedUserIds),
                $existingDocument?->id
            );

            if ($conflictMessage !== null) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['shared_user_ids' => $conflictMessage]);
            }
        }

        if ($period && in_array($request->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true)) {
            if (! $existingDocument && $this->weeklySubmissionReminderService->blocksNewWeeklyDocument($request->type, $period)) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'The submission window for this weekly document has closed.');
            }
            if ($existingDocument && $action === 'submit' && ! $this->weeklySubmissionReminderService->allowsSubmitWeeklyDocument($existingDocument)) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'The submission deadline has passed. You can no longer submit this document.');
            }
        }

        $state = ($action === 'submit') ? Document::STATE_SUBMITTED : Document::STATE_DRAFT;

        // Handle file upload (monthly_report only; weekly_plan/weekly_report are online forms).
        $filePath = null;
        $originalFilename = null;
        $fileSize = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $originalFilename = $file->getClientOriginalName();
            $fileSize = $file->getSize();

            // Generate unique filename
            $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $filePath = $file->storeAs('documents/'.$request->type, $filename, 'public');
        }

        // Handle attachment upload for monthly reports
        $attachmentPath = null;
        if ($request->type === Document::TYPE_MONTHLY_REPORT && $request->hasFile('attachment')) {
            $attachmentFile = $request->file('attachment');
            $attachmentFilename = time().'_'.uniqid().'_attachment.'.$attachmentFile->getClientOriginalExtension();
            $attachmentPath = $attachmentFile->storeAs('documents/monthly_reports/attachments', $attachmentFilename, 'public');
        }

        // Generate title if not provided
        $title = $request->input('title');
        if (! $title) {
            $typeName = Document::getTypes()[$request->type] ?? ucwords(str_replace('_', ' ', $request->type));
            if ($period) {
                $title = $typeName.' - Week '.$period->week_number;
            } elseif ($request->type === Document::TYPE_MONTHLY_REPORT) {
                $monthName = \Carbon\Carbon::create((int) $request->year, (int) $request->month, 1)->format('F');
                $title = $typeName.' - '.$monthName.' '.$request->year;
            } else {
                $title = $typeName.' - '.now()->format('M d, Y');
            }
        }

        // If document exists, update it instead of creating a new one
        if ($existingDocument) {
            // If document is submitted, we can't update it (policy restriction)
            if ($existingDocument->isSubmitted()) {
                // Delete the uploaded file since we won't use it
                if ($filePath) {
                    Storage::disk('public')->delete($filePath);
                }

                return redirect()->route('documents.show', $existingDocument)
                    ->with('info', 'A '.str_replace('_', ' ', $request->type).' already exists for this week period. You can view it below.');
            }

            // Check if we can update (authorize update action - only works for drafts)
            try {
                $this->authorize('update', $existingDocument);
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                // Delete the uploaded file since we won't use it
                if ($filePath) {
                    Storage::disk('public')->delete($filePath);
                }

                return redirect()->route('documents.show', $existingDocument)
                    ->with('error', 'You do not have permission to update this document.');
            }

            // If submitting, authorize submit action
            if ($action === 'submit') {
                try {
                    $this->authorize('submit', $existingDocument);
                } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                    // Delete the uploaded file since we won't use it
                    if ($filePath) {
                        Storage::disk('public')->delete($filePath);
                    }

                    return redirect()->route('documents.show', $existingDocument)
                        ->with('error', 'You do not have permission to submit this document.');
                }
            }

            // Delete old file if exists
            if ($existingDocument->file_path && $filePath) {
                Storage::disk('public')->delete($existingDocument->file_path);
            }

            // Delete old attachment if exists and new one is uploaded
            if ($existingDocument->attachment && $attachmentPath) {
                Storage::disk('public')->delete($existingDocument->attachment);
            }

            // Update document
            $updateData = [
                'title' => $title,
            ];

            // Only replace the PDF if a new one was uploaded.
            if ($filePath) {
                $updateData['file_path'] = $filePath;
                $updateData['file_size'] = $fileSize;
                $updateData['original_filename'] = $originalFilename;
            }

            if ($request->type === Document::TYPE_MONTHLY_REPORT && $monthlyContent !== null) {
                $updateData['content'] = $monthlyContent;
            } elseif ($templateContent !== null) {
                $updateData['content'] = $templateContent;
                if ($request->type === Document::TYPE_WEEKLY_PLAN) {
                    $updateData = array_merge(
                        $updateData,
                        $this->budgetReviewDefaultsForWeeklyPlan($templateContent, $existingDocument->budget_review_items)
                    );
                }
            }

            // Add attachment for monthly reports
            if ($request->type === Document::TYPE_MONTHLY_REPORT && $attachmentPath) {
                $updateData['attachment'] = $attachmentPath;
            }

            // Add month and year for monthly reports
            if ($request->type === Document::TYPE_MONTHLY_REPORT) {
                $updateData['month'] = $request->month;
                $updateData['year'] = $request->year;
            }

            // Add main_target for weekly plans
            if ($request->type === Document::TYPE_WEEKLY_PLAN && $request->filled('main_target')) {
                $updateData['main_target'] = $request->main_target;
            }

            // Add target accomplishment fields for weekly reports
            if ($request->type === Document::TYPE_WEEKLY_REPORT) {
                $updateData['target_accomplished'] = $this->targetAccomplishedBooleanFromStatus($weeklyReportTargetStatus);
                $updateData['target_explanation'] = $request->filled('target_explanation') ? $request->target_explanation : null;
            }

            // Update state if submitting
            if ($action === 'submit') {
                $updateData['state'] = Document::STATE_SUBMITTED;
                $updateData['review_rejection_reason'] = null;
            }

            $existingDocument->update($updateData);
            if (Document::supportsSharedEditing($existingDocument->type)) {
                $this->syncDocumentShares($existingDocument, $sharedUserIds, $user);
            }
            if ($existingDocument->type === Document::TYPE_WEEKLY_REPORT) {
                $this->syncNextWeeklyPlanCarryOverFromReport($existingDocument->fresh());
            }
            if ($action === 'submit') {
                $existingDocument->loadMissing('period');
                $this->workflowEmailNotificationService->notifyWeeklyDocumentSubmitted($existingDocument, $user);
                $this->workflowEmailNotificationService->notifyWeeklyBudgetSubmitted($existingDocument, $user);
            }

            $message = ($action === 'submit')
                ? 'Document updated and submitted successfully.'
                : 'Document updated successfully.';

            return redirect()->route('documents.show', $existingDocument)
                ->with('success', $message);
        }

        // Create new document
        $documentData = [
            'user_id' => $user->id,
            'period_id' => $period?->id,
            'type' => $request->type,
            'file_path' => $filePath,
            'title' => $title,
            'file_size' => $fileSize,
            'original_filename' => $originalFilename,
            'state' => $state,
        ];

        // Add attachment for monthly reports
        if ($request->type === Document::TYPE_MONTHLY_REPORT && $attachmentPath) {
            $documentData['attachment'] = $attachmentPath;
        }

        // Add month and year for monthly reports
        if ($request->type === Document::TYPE_MONTHLY_REPORT) {
            $documentData['month'] = $request->month;
            $documentData['year'] = $request->year;
        }

        // Add main_target for weekly plans
        if ($request->type === Document::TYPE_WEEKLY_PLAN && $request->filled('main_target')) {
            $documentData['main_target'] = $request->main_target;
        }

        // Add target accomplishment fields for weekly reports
        if ($request->type === Document::TYPE_WEEKLY_REPORT) {
            $documentData['target_accomplished'] = $this->targetAccomplishedBooleanFromStatus($weeklyReportTargetStatus);
            $documentData['target_explanation'] = $request->filled('target_explanation') ? $request->target_explanation : null;
        }

        if ($request->type === Document::TYPE_MONTHLY_REPORT && $monthlyContent !== null) {
            $documentData['content'] = $monthlyContent;
        } elseif ($templateContent !== null) {
            $documentData['content'] = $templateContent;
            if ($request->type === Document::TYPE_WEEKLY_PLAN) {
                $documentData = array_merge(
                    $documentData,
                    $this->budgetReviewDefaultsForWeeklyPlan($templateContent)
                );
            }
        }

        $document = Document::create($documentData);
        if (Document::supportsSharedEditing($document->type)) {
            $this->syncDocumentShares($document, $sharedUserIds, $user);
        }
        $this->syncWeeklyPlanAssignments($document);
        if ($document->type === Document::TYPE_WEEKLY_REPORT) {
            $this->syncNextWeeklyPlanCarryOverFromReport($document->fresh());
        }
        if ($action === 'submit') {
            $document->loadMissing('period');
            $this->workflowEmailNotificationService->notifyWeeklyDocumentSubmitted($document, $user);
            $this->workflowEmailNotificationService->notifyWeeklyBudgetSubmitted($document, $user);
        }

        $message = ($action === 'submit')
            ? 'Document submitted successfully.'
            : 'Document saved as draft.';

        return redirect()->route('documents.show', $document)
            ->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(Document $document)
    {
        if ($document->type === Document::TYPE_ACTION_LOG) {
            $this->authorize('view', $document);

            return redirect()->route('action-logs.show', $document);
        }

        $document->load(['user', 'period', 'sharedUsers']);

        // Authorize view action
        $this->authorize('view', $document);

        // Get related documents (plan for report, report for plan)
        $relatedDocument = null;
        $relatedAction = null;
        if ($document->period_id) {
            if ($document->type === Document::TYPE_WEEKLY_PLAN) {
                $relatedDocument = Document::findWeeklyReportForPeriodOwner(
                    $document->period_id,
                    (string) $document->user_id
                );
            } elseif ($document->type === Document::TYPE_WEEKLY_REPORT) {
                $relatedDocument = Document::findWeeklyPlanForUserAndPeriod(
                    $document->period_id,
                    (string) Auth::id()
                );
            }

            if (! $relatedDocument) {
                /** @var \App\Models\User $user */
                $user = Auth::user();
                $isAuthor = (string) $document->user_id === (string) $user->id;
                if ($isAuthor &&
                    $document->type === Document::TYPE_WEEKLY_PLAN &&
                    Document::canUserCreateType($user, Document::TYPE_WEEKLY_REPORT, $document->period)) {
                    $relatedAction = [
                        'type' => Document::TYPE_WEEKLY_REPORT,
                        'label' => 'Create Weekly Report for this Week',
                        'route' => route('documents.create', ['type' => Document::TYPE_WEEKLY_REPORT]),
                        'description' => 'Weekly report is due on '.$document->period->getReportDeadlineCutoff()->format('M d, Y H:i'),
                    ];
                } elseif ($isAuthor &&
                    $document->type === Document::TYPE_WEEKLY_REPORT &&
                    Document::canUserCreateType($user, Document::TYPE_WEEKLY_PLAN, $document->period)) {
                    $relatedAction = [
                        'type' => Document::TYPE_WEEKLY_PLAN,
                        'label' => 'Create Weekly Plan for this Week',
                        'route' => route('documents.create', ['type' => Document::TYPE_WEEKLY_PLAN]),
                        'description' => 'Weekly plan should outline goals for this week.',
                    ];
                }
            }
        }

        $assignedWeeklyActivities = collect();
        if ($document->type === Document::TYPE_WEEKLY_PLAN && $document->period_id) {
            $document->loadMissing('sharedUsers:id');
            $planParticipantIds = collect($document->allEditableUserIds())
                ->map(static fn ($id) => (string) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
            $assignedWeeklyActivities = $this->weeklyActivityAssignmentService->assignmentsForUsers($planParticipantIds, (string) $document->period_id);
        }

        return view('documents.show', compact('document', 'relatedDocument', 'relatedAction', 'assignedWeeklyActivities'));
    }

    /**
     * Reviewer-facing document view: same UI as {@see self::show} without related plan/report chrome.
     */
    public function reviewerShow(Document $document)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (! $user->hasPermissionTo('view all documents')) {
            abort(403, 'Unauthorized access.');
        }

        if (! in_array($document->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true)) {
            abort(403, 'This view is only available for weekly plans and weekly reports.');
        }

        $this->authorize('view', $document);

        $document->load(['user', 'period']);

        $relatedDocument = null;
        $relatedAction = null;
        $reviewerPresentation = true;

        return view('documents.show', compact('document', 'relatedDocument', 'relatedAction', 'reviewerPresentation'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Document $document)
    {
        if ($document->type === Document::TYPE_ACTION_LOG) {
            $this->authorize('update', $document);

            return redirect()->route('action-logs.edit', $document);
        }

        // Authorize update action
        $this->authorize('update', $document);

        $document->loadMissing(['user', 'sharedUsers']);
        $monthlyYearPlan = null;
        $monthlyPlannedActivities = collect();
        $monthlyHasSubDepartments = false;
        if ($document->type === Document::TYPE_MONTHLY_REPORT) {
            $owner = $document->user;
            $monthlyHasSubDepartments = $owner->subDepartments()->exists();
            $monthlyYearPlan = MonthlyReportYearPlanActivities::yearPlanForCalendarYear((int) $document->year);
            $monthlyPlannedActivities = MonthlyReportYearPlanActivities::plannedForUserMonth(
                $owner,
                (int) $document->year,
                (int) $document->month
            );
        }

        $teamUsers = User::select('id', 'name')->orderBy('name')->get();
        $shareableUsers = $this->shareableDepartmentUsersFor($document->user);
        $assignedWeeklyActivities = collect();
        if ($document->type === Document::TYPE_WEEKLY_PLAN && $document->period_id) {
            $document->loadMissing('sharedUsers:id');
            $planParticipantIds = collect($document->allEditableUserIds())
                ->map(static fn ($id) => (string) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
            $assignedWeeklyActivities = $this->weeklyActivityAssignmentService->assignmentsForUsers($planParticipantIds, (string) $document->period_id);
        }

        return view('documents.edit', compact('document', 'teamUsers', 'shareableUsers', 'monthlyYearPlan', 'monthlyPlannedActivities', 'monthlyHasSubDepartments', 'assignedWeeklyActivities'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Document $document)
    {
        /** @var \App\Models\User $actingUser */
        $actingUser = Auth::user();
        $wasSubmitted = $document->isSubmitted();

        // Authorize update action
        $this->authorize('update', $document);

        // Validate file upload (file is optional for updates, but if provided must be PDF)
        $validationRules = [
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'], // Max 10MB
            'title' => ['nullable', 'string', 'max:255'],
            // Online template content (weekly_plan/weekly_report)
            'content' => ['nullable', 'array'],
        ];

        // Add validation for main_target for weekly plans
        if ($document->type === Document::TYPE_WEEKLY_PLAN) {
            $validationRules['main_target'] = ['nullable', 'string', 'max:2000'];

            $validationRules['content.objective.description'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.objective.status'] = ['nullable', 'string', 'in:Not started,Completed,Carried,In Progress'];
            $validationRules['content.objective.reflection_progress'] = ['nullable', 'string', 'max:4000'];

            $validationRules['content.planned_activities'] = ['nullable', 'array'];
            $validationRules['content.planned_activities.*.day'] = ['nullable', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday'];
            $validationRules['content.planned_activities.*.activity'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.planned_activities.*.location'] = ['nullable', 'string', 'max:255'];
            $validationRules['content.planned_activities.*.team_member_ids'] = ['nullable', 'array'];
            $validationRules['content.planned_activities.*.team_member_ids.*'] = ['nullable', 'string', 'ulid', 'exists:users,id'];
            $validationRules['content.planned_activities.*.notes_inclusion'] = ['nullable', 'string', 'max:4000'];

            $validationRules['content.key_meetings'] = ['nullable', 'array'];
            $validationRules['content.key_meetings.*.date'] = ['nullable', 'date'];
            $validationRules['content.key_meetings.*.meeting'] = ['nullable', 'string', 'max:255'];
            $validationRules['content.key_meetings.*.participants'] = ['nullable', 'string', 'max:255'];
            $validationRules['content.key_meetings.*.location'] = ['nullable', 'string', 'max:255'];
            $validationRules['content.key_meetings.*.reactions_notes'] = ['nullable', 'string', 'max:4000'];

            $validationRules['content.budget'] = ['nullable', 'array'];
            $validationRules['content.budget.*.item'] = ['nullable', 'string', 'max:255'];
            $validationRules['content.budget.*.description_purpose'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.budget.*.amount'] = ['nullable', 'string', 'max:60'];
        }

        // Add validation for target accomplishment for weekly reports
        if ($document->type === Document::TYPE_WEEKLY_REPORT) {
            $validationRules['target_accomplished_status'] = ['nullable', 'string', 'in:completed,partially_completed,not_completed'];
            $validationRules['target_explanation'] = ['nullable', 'string', 'max:2000'];

            $validationRules['content.summary'] = ['nullable', 'string', 'max:5000'];

            $validationRules['content.activity_progress'] = ['nullable', 'array'];
            $validationRules['content.activity_progress.*.activity_output'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.activity_progress.*.off_plan_activity'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.activity_progress.*.progress'] = ['nullable', 'string', 'in:Not started,In progress,Done,Partially done'];
            $validationRules['content.activity_progress.*.remarks_challenges'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.activity_progress.*.next_step'] = ['nullable', 'string', 'max:2000'];

            $validationRules['content.key_achievements'] = ['nullable', 'array'];
            $validationRules['content.key_achievements.*'] = ['nullable', 'string', 'max:2000'];

            $validationRules['content.challenges_mitigation'] = ['nullable', 'array'];
            $validationRules['content.challenges_mitigation.*.challenge'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.challenges_mitigation.*.action_taken_planned'] = ['nullable', 'string', 'max:2000'];

            $validationRules['content.budget_statuses'] = ['nullable', 'array'];
            $validationRules['content.budget_statuses.*.status'] = ['nullable', 'string', 'in:Not started,Pending,Received,Paid'];
            $validationRules['content.budget_statuses.*.notes'] = ['nullable', 'string', 'max:2000'];
        }

        // Add validation for monthly reports
        if ($document->type === Document::TYPE_MONTHLY_REPORT) {
            $validationRules['month'] = ['nullable', 'integer', 'min:1', 'max:12'];
            $validationRules['year'] = ['nullable', 'integer', 'min:2000', 'max:2100'];
            $validationRules['attachment'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xlsx,xls', 'max:10240'];
        }

        if ($document->type === Document::TYPE_WEEKLY_PLAN) {
            $validationRules['shared_user_ids'] = ['nullable', 'array'];
            $validationRules['shared_user_ids.*'] = ['nullable', 'string', 'ulid', 'exists:users,id'];
        }

        $request->validate($validationRules);

        $action = $request->input('action', 'draft');
        $action = in_array($action, ['submit', 'draft'], true) ? $action : 'draft';
        $sharedUserIds = [];

        if (Document::supportsSharedEditing($document->type)) {
            if ($document->type === Document::TYPE_WEEKLY_REPORT) {
                $sharedUserIds = $this->weeklyReportSharedUserIdsFromPlanContext($actingUser, $document->period_id);
                $sharedUserIds = $this->validateSharedMembersWithinDepartment($document->user, $sharedUserIds);
            } else {
                $sharedUserIds = $this->extractRequestedSharedUserIds($request);
                $sharedUserIds = $this->validateSharedMembersWithinDepartment($document->user, $sharedUserIds);
            }
            $conflictMessage = $this->firstCollaborationConflictMessage(
                $document->period_id,
                $document->type,
                array_merge([$document->user_id], $sharedUserIds),
                $document->id
            );
            if ($conflictMessage !== null) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['shared_user_ids' => $conflictMessage]);
            }
        } else {
            $sharedUserIds = [];
        }

        if ($action === 'submit') {
            $this->authorize('submit', $document);
        }

        if ($action === 'submit'
            && in_array($document->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true)
            && ! $this->weeklySubmissionReminderService->allowsSubmitWeeklyDocument($document)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'The submission deadline has passed. You can no longer submit this document.');
        }

        $document->loadMissing('user');
        $weeklyReportTargetStatus = null;
        if ($document->type === Document::TYPE_WEEKLY_REPORT) {
            $weeklyReportTargetStatus = $this->resolveWeeklyReportTargetStatus($request, $document);
            if ($weeklyReportTargetStatus === null && $action === 'submit') {
                throw ValidationException::withMessages([
                    'target_accomplished_status' => 'Please select target accomplishment status.',
                ]);
            }
            if (in_array($weeklyReportTargetStatus, ['partially_completed', 'not_completed'], true)
                && ! $request->filled('target_explanation')
                && $action === 'submit') {
                throw ValidationException::withMessages([
                    'target_explanation' => 'Please explain why the target was not fully accomplished.',
                ]);
            }
        }

        $templateContent = null;
        $monthlyContent = null;
        if ($document->type === Document::TYPE_MONTHLY_REPORT) {
            $monthlyContent = $this->normalizeMonthlyReportContent(
                $document->user,
                (int) $document->year,
                (int) $document->month,
                is_array($request->input('content')) ? $request->input('content') : [],
                $action
            );
        } elseif ($request->has('content') && is_array($request->input('content')) && in_array($document->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT])) {
            $templateContent = $this->normalizeTemplateContent($request->input('content'), $document->type);
        }
        if ($document->type === Document::TYPE_WEEKLY_REPORT) {
            if ($templateContent === null) {
                $templateContent = is_array($document->content) ? $document->content : [];
            }
            if ($weeklyReportTargetStatus !== null) {
                $templateContent['target_accomplished_status'] = $weeklyReportTargetStatus;
            } else {
                unset($templateContent['target_accomplished_status']);
            }
        }

        $updateData = [];

        // Handle file upload if provided
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $originalFilename = $file->getClientOriginalName();
            $fileSize = $file->getSize();

            // Generate unique filename
            $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $filePath = $file->storeAs('documents/'.$document->type, $filename, 'public');

            // Delete old file if exists
            if ($document->file_path) {
                Storage::disk('public')->delete($document->file_path);
            }

            $updateData['file_path'] = $filePath;
            $updateData['file_size'] = $fileSize;
            $updateData['original_filename'] = $originalFilename;
        }

        // Handle attachment upload for monthly reports
        if ($document->type === Document::TYPE_MONTHLY_REPORT && $request->hasFile('attachment')) {
            $attachmentFile = $request->file('attachment');
            $attachmentFilename = time().'_'.uniqid().'_attachment.'.$attachmentFile->getClientOriginalExtension();
            $attachmentPath = $attachmentFile->storeAs('documents/monthly_reports/attachments', $attachmentFilename, 'public');

            // Delete old attachment if exists
            if ($document->attachment) {
                Storage::disk('public')->delete($document->attachment);
            }

            $updateData['attachment'] = $attachmentPath;
        }

        // Update title if provided
        if ($request->filled('title')) {
            $updateData['title'] = $request->input('title');
        }

        // Update main_target for weekly plans
        if ($document->type === Document::TYPE_WEEKLY_PLAN) {
            if ($request->filled('main_target')) {
                $updateData['main_target'] = $request->main_target;
            } elseif ($request->has('main_target')) {
                // Allow clearing the field
                $updateData['main_target'] = null;
            }
        }

        // Update target accomplishment fields for weekly reports
        if ($document->type === Document::TYPE_WEEKLY_REPORT) {
            $updateData['target_accomplished'] = $this->targetAccomplishedBooleanFromStatus($weeklyReportTargetStatus);
            if ($request->filled('target_explanation')) {
                $updateData['target_explanation'] = $request->target_explanation;
            } elseif ($request->has('target_explanation')) {
                // Allow clearing the field
                $updateData['target_explanation'] = null;
            }
        }

        if ($monthlyContent !== null) {
            $updateData['content'] = $monthlyContent;
        } elseif ($templateContent !== null) {
            $updateData['content'] = $templateContent;
            if ($document->type === Document::TYPE_WEEKLY_PLAN) {
                $updateData = array_merge(
                    $updateData,
                    $this->budgetReviewDefaultsForWeeklyPlan($templateContent, $document->budget_review_items)
                );
            }
        }

        // Update state if submitting
        if ($action === 'submit') {
            $updateData['state'] = Document::STATE_SUBMITTED;
            $updateData['review_rejection_reason'] = null;
        }

        $document->update($updateData);
        if (Document::supportsSharedEditing($document->type)) {
            $this->syncDocumentShares($document, $sharedUserIds, $actingUser);
        }
        $this->syncWeeklyPlanAssignments($document);
        if ($document->type === Document::TYPE_WEEKLY_REPORT) {
            $this->syncNextWeeklyPlanCarryOverFromReport($document->fresh());
        }
        if ($action === 'submit' && ! $wasSubmitted) {
            $document->loadMissing('period');
            $this->workflowEmailNotificationService->notifyWeeklyDocumentSubmitted($document, $actingUser);
            $this->workflowEmailNotificationService->notifyWeeklyBudgetSubmitted($document, $actingUser);
        }

        $message = ($action === 'submit')
            ? 'Document submitted successfully.'
            : 'Document updated successfully.';

        return redirect()->route('documents.show', $document)
            ->with('success', $message);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Document $document)
    {

        // Authorize delete action
        $this->authorize('delete', $document);

        $this->weeklyActivityAssignmentService->clearForSourceDocument($document);
        $document->delete();

        return redirect()->route('documents.index')
            ->with('success', 'Document deleted successfully.');
    }

    /**
     * Submit a document (change state from draft to submitted)
     */
    public function submit(Request $request, Document $document)
    {
        /** @var \App\Models\User $actingUser */
        $actingUser = Auth::user();
        $wasSubmitted = $document->isSubmitted();

        // Authorize submit action
        $this->authorize('submit', $document);

        $document->submit();
        $this->syncWeeklyPlanAssignments($document);
        if (! $wasSubmitted) {
            $document->loadMissing('period');
            $this->workflowEmailNotificationService->notifyWeeklyDocumentSubmitted($document, $actingUser);
            $this->workflowEmailNotificationService->notifyWeeklyBudgetSubmitted($document, $actingUser);
        }

        return redirect()->route('documents.show', $document)
            ->with('success', 'Document submitted successfully.');
    }

    /**
     * Return submitted weekly plan/report to draft with required review reason.
     */
    public function reject(Request $request, Document $document)
    {
        /** @var \App\Models\User $actingUser */
        $actingUser = Auth::user();

        $this->authorize('reject', $document);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $reason = trim($validated['reason']);

        $document->revertToDraft($reason);
        $this->syncWeeklyPlanAssignments($document);
        $document->loadMissing('user', 'period');
        $this->workflowEmailNotificationService->notifyWeeklyDocumentReturnedToDraft($document, $actingUser, $reason);

        return redirect()->route('documents.show', $document->id)
            ->with('warning', 'Document returned to draft. Staff can now update and re-submit after addressing the review reason.');
    }

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

        $baseQuery = Document::with(['user'])
            ->submitted();

        // Filter by user if provided
        if ($request->has('user_id') && $request->user_id) {
            $baseQuery->forUser($request->user_id);
        }

        // Filter by week/month if provided
        if ($request->has('period') && $request->period) {
            $period = $request->period;
            if ($period === 'this_week') {
                $baseQuery->whereBetween('updated_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek(),
                ]);
            } elseif ($period === 'last_week') {
                $baseQuery->whereBetween('updated_at', [
                    now()->subWeek()->startOfWeek(),
                    now()->subWeek()->endOfWeek(),
                ]);
            } elseif ($period === 'this_month') {
                $baseQuery->whereMonth('updated_at', now()->month)
                    ->whereYear('updated_at', now()->year);
            } elseif ($period === 'last_month') {
                $baseQuery->whereMonth('updated_at', now()->subMonth()->month)
                    ->whereYear('updated_at', now()->subMonth()->year);
            }
        }

        if ($request->has('work_period_id') && $request->work_period_id) {
            $baseQuery->where('period_id', $request->work_period_id);
        }

        if ($request->has('year') && $request->year) {
            $baseQuery->whereHas('period', function ($q) use ($request) {
                $q->where('year', $request->year);
            });
        }

        $allDocuments = (clone $baseQuery)->latest()->get();

        $mergedWeeklyRows = $allDocuments
            ->whereIn('type', [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT])
            ->groupBy(fn ($doc) => ($doc->period_id ?? 'none').'_'.($doc->user_id ?? 'none'))
            ->map(function ($items) {
                $plan = $items->firstWhere('type', Document::TYPE_WEEKLY_PLAN);
                $report = $items->firstWhere('type', Document::TYPE_WEEKLY_REPORT);

                return [
                    'plan' => $plan,
                    'report' => $report,
                    'period' => $plan?->period ?? $report?->period,
                    'user' => $plan?->user ?? $report?->user,
                    'updated_at' => collect([$plan?->updated_at, $report?->updated_at])->filter()->max(),
                ];
            })
            ->values();

        // Group documents by type
        $groupedDocuments = [
            'action_log' => $allDocuments->where('type', Document::TYPE_ACTION_LOG),
        ];

        // Filter by type if provided (for single type view)
        $selectedType = $request->has('type') && $request->type ? $request->type : null;
        if ($selectedType && isset($groupedDocuments[$selectedType])) {
            $groupedDocuments = [$selectedType => $groupedDocuments[$selectedType]];
        }

        // Get statistics (apply same filters)
        $statsQuery = (clone $baseQuery);
        $stats = [
            'total_submitted' => $statsQuery->count(),
            'weekly_plans' => (clone $statsQuery)->weeklyPlans()->count(),
            'weekly_reports' => (clone $statsQuery)->weeklyReports()->count(),
            'action_logs' => (clone $statsQuery)->actionLogs()->count(),
        ];

        $usersWithDocuments = \App\Models\User::whereHas('documents', function ($q) {
            $q->submitted();
        })->orderBy('name')->get();

        $periodOptions = WorkPeriod::orderBy('year', 'desc')
            ->orderBy('week_number', 'desc')
            ->take(20)
            ->get();

        $years = WorkPeriod::select('year')->distinct()->orderBy('year', 'desc')->pluck('year');

        return view('documents.reviewer.dashboard', compact(
            'mergedWeeklyRows',
            'groupedDocuments',
            'stats',
            'usersWithDocuments',
            'selectedType',
            'periodOptions',
            'years'
        ));
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
        $deadlineStatuses = null;
        // Plan submissions target the next work period; use that period's plan_deadline for the banner.
        if ($nextPeriod) {
            $deadlineStatuses = [
                'plan_badge' => $nextPeriod->getPlanDeadlineBadgeClass(),
                'plan_text' => $nextPeriod->getDeadlineStatusText('plan'),
                'plan_due' => $nextPeriod->getPlanDeadlineCutoff(),
            ];
        }

        $availableTypes = Document::getAvailableTypesForUser($user);

        return view('documents.weekly.plans', [
            'documents' => $documents,
            'periods' => WorkPeriod::orderBy('year', 'desc')->orderBy('week_number', 'desc')->get(),
            'years' => WorkPeriod::select('year')->distinct()->orderBy('year', 'desc')->pluck('year'),
            'currentPeriod' => $currentPeriod,
            'nextPeriod' => $nextPeriod,
            'deadlineStatuses' => $deadlineStatuses,
            'availableTypes' => $availableTypes,
            'returnedDocumentMap' => $returnedDocumentMap,
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

        if ($plan) {
            $budgetItems = [];
            $reviewsByUid = $plan->budgetReviewItemsByUid();
            if (is_array($plan->content) && ! empty($plan->content['budget']) && is_array($plan->content['budget'])) {
                // Normalize into a simple array for the UI, including review metadata.
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
                }, $plan->content['budget']));
                // Remove empty rows (e.g., all fields blank)
                $budgetItems = array_values(array_filter($budgetItems, function ($row) {
                    if (! is_array($row)) {
                        return false;
                    }

                    return ! empty($row['item']) || ! empty($row['description_purpose']) || ! empty($row['amount']);
                }));
            }

            $plannedActivities = [];
            if (is_array($plan->content) && ! empty($plan->content['planned_activities']) && is_array($plan->content['planned_activities'])) {
                $plannedActivities = array_values(array_filter(array_map(function ($row) {
                    if (! is_array($row)) {
                        return null;
                    }
                    $activity = trim((string) ($row['activity'] ?? ''));
                    if ($activity === '') {
                        return null;
                    }
                    $day = trim((string) ($row['day'] ?? ''));

                    return [
                        'day' => $day,
                        'activity' => $activity,
                        'label' => $day !== '' ? ($day.' - '.$activity) : $activity,
                    ];
                }, $plan->content['planned_activities'])));
            }

            return response()->json([
                'main_target' => $plan->main_target,
                'plan_exists' => true,
                'budget_items' => $budgetItems,
                'planned_activities' => $plannedActivities,
            ]);
        }

        return response()->json([
            'main_target' => null,
            'plan_exists' => false,
            'budget_items' => [],
            'planned_activities' => [],
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

        return view('documents.weekly.reports', [
            'documents' => $documents,
            'periods' => WorkPeriod::orderBy('year', 'desc')->orderBy('week_number', 'desc')->get(),
            'years' => WorkPeriod::select('year')->distinct()->orderBy('year', 'desc')->pluck('year'),
            'currentPeriod' => $currentPeriod,
            'nextPeriod' => $nextPeriod,
            'deadlineStatuses' => $deadlineStatuses,
            'availableTypes' => $availableTypes,
            'returnedDocumentMap' => $returnedDocumentMap,
        ]);
    }

    /**
     * Weekly budget review panel.
     */
    public function weeklyBudgetReviewPanel(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (! $user->hasPermissionTo('view weekly budgets')) {
            abort(403, 'Unauthorized access.');
        }

        $defaultPeriod = $this->resolveWeeklyBudgetReviewDefaultPeriod($request);
        $statusFilter = $request->input('status');
        $query = $this->buildWeeklyBudgetReviewQuery($request, $defaultPeriod, $statusFilter);

        $documents = $query->latest()->paginate(100)->withQueryString();
        $documents->setCollection(
            $documents->getCollection()->filter(fn (Document $document) => $document->hasBudgetItems())->values()
        );

        // Build grouped data: department -> staff -> budget items
        $grouped = $this->groupBudgetDocumentsByDepartment($documents->getCollection());

        // Stats from current result set
        $statsDocuments = $documents->getCollection();
        $aggregateStats = $this->computeBudgetStats($statsDocuments);

        $usersWithBudgets = User::query()
            ->whereHas('documents', function ($documentsQuery) {
                $documentsQuery
                    ->weeklyPlans()
                    ->submitted()
                    ->where(function ($budgetQuery) {
                        $budgetQuery
                            ->whereNotNull('budget_review_status')
                            ->orWhere('content', 'like', '%"budget"%');
                    });
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $periodOptions = WorkPeriod::orderBy('year', 'desc')
            ->orderBy('week_number', 'desc')
            ->take(52)
            ->get();

        $financeTargetUsers = User::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $currentBudgetEntryPeriod = WorkPeriod::getCurrent();
        $years = WorkPeriod::select('year')->distinct()->orderBy('year', 'desc')->pluck('year');

        return view('documents.reviewer.budget-weekly', [
            'documents' => $documents,
            'grouped' => $grouped,
            'stats' => $aggregateStats,
            'usersWithBudgets' => $usersWithBudgets,
            'periodOptions' => $periodOptions,
            'financeTargetUsers' => $financeTargetUsers,
            'currentBudgetEntryPeriod' => $currentBudgetEntryPeriod,
            'years' => $years,
            'statusFilter' => $statusFilter,
            'defaultPeriod' => $defaultPeriod,
        ]);
    }

    public function exportWeeklyBudgetReviewPdf(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (! $user->hasPermissionTo('view weekly budgets')) {
            abort(403, 'Unauthorized access.');
        }

        $defaultPeriod = $this->resolveWeeklyBudgetReviewDefaultPeriod($request);
        $statusFilter = $request->input('status');
        $documents = $this->buildWeeklyBudgetReviewQuery($request, $defaultPeriod, $statusFilter)
            ->latest()
            ->get()
            ->filter(fn (Document $document) => $document->hasBudgetItems())
            ->values();

        $grouped = $this->groupBudgetDocumentsByDepartment($documents);
        $filterSummary = $this->weeklyBudgetReviewFilterSummary($request, $defaultPeriod, $statusFilter);
        $filename = $this->weeklyBudgetReviewExportFilename('pdf', $filterSummary);

        $pdf = Pdf::loadView('documents.reviewer.budget-weekly-pdf', [
            'grouped' => $grouped,
            'workPeriodRange' => $filterSummary['work_period_range'],
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    public function exportWeeklyBudgetReviewExcel(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (! $user->hasPermissionTo('view weekly budgets')) {
            abort(403, 'Unauthorized access.');
        }

        $defaultPeriod = $this->resolveWeeklyBudgetReviewDefaultPeriod($request);
        $statusFilter = $request->input('status');
        $documents = $this->buildWeeklyBudgetReviewQuery($request, $defaultPeriod, $statusFilter)
            ->latest()
            ->get()
            ->filter(fn (Document $document) => $document->hasBudgetItems())
            ->values();

        $filterSummary = $this->weeklyBudgetReviewFilterSummary($request, $defaultPeriod, $statusFilter);
        $filename = $this->weeklyBudgetReviewExportFilename('xlsx', $filterSummary);

        return Excel::download(
            new WeeklyBudgetReviewExport($documents),
            $filename
        );
    }

    private function resolveWeeklyBudgetReviewDefaultPeriod(Request $request): ?WorkPeriod
    {
        $hasExplicitFilter = $request->filled('period_id') || $request->filled('year') || $request->filled('user_id') || $request->filled('status');

        if ($hasExplicitFilter) {
            return null;
        }

        return $this->workPeriodService->periodForWeeklyPlanSubmission();
    }

    private function buildWeeklyBudgetReviewQuery(Request $request, ?WorkPeriod $defaultPeriod, ?string $statusFilter)
    {
        $query = Document::query()
            ->with(['user.department', 'period', 'budgetReviewedBy'])
            ->weeklyPlans()
            ->submitted()
            ->where(function ($budgetQuery) {
                $budgetQuery
                    ->whereNotNull('budget_review_status')
                    ->orWhere('content', 'like', '%"budget"%');
            });

        if ($request->filled('user_id')) {
            $query->forUser($request->string('user_id')->toString());
        }

        if ($request->filled('period_id')) {
            $query->forPeriod($request->string('period_id')->toString());
        } elseif ($defaultPeriod) {
            $query->forPeriod($defaultPeriod->id);
        }

        if ($request->filled('year')) {
            $year = (int) $request->input('year');
            $query->whereHas('period', function ($periodQuery) use ($year) {
                $periodQuery->where('year', $year);
            });
        }

        if ($statusFilter === Document::BUDGET_REVIEW_PENDING) {
            $query->where(function ($statusQuery) {
                $statusQuery
                    ->whereNull('budget_review_status')
                    ->orWhere('budget_review_status', Document::BUDGET_REVIEW_PENDING);
            });
        } elseif (in_array($statusFilter, [Document::BUDGET_REVIEW_APPROVED, Document::BUDGET_REVIEW_REJECTED], true)) {
            $query->where('budget_review_status', $statusFilter);
        }

        return $query;
    }

    private function weeklyBudgetReviewFilterSummary(Request $request, ?WorkPeriod $defaultPeriod, ?string $statusFilter): array
    {
        $resolvedPeriodId = $request->filled('period_id')
            ? (string) $request->string('period_id')
            : ($defaultPeriod?->id ? (string) $defaultPeriod->id : null);

        $periodLabel = 'All Weeks';
        $periodDateRange = 'All Work Periods';
        if ($resolvedPeriodId) {
            $period = WorkPeriod::query()->find($resolvedPeriodId);
            if ($period) {
                $periodLabel = sprintf('Week %s (%s)', (string) $period->week_number, (string) $period->year);
                $start = $period->week_start_date ? $period->week_start_date->format('Y-m-d') : null;
                $end = $period->week_end_date ? $period->week_end_date->format('Y-m-d') : null;
                $periodDateRange = ($start && $end) ? "{$start} to {$end}" : ($period->date_range ?? 'Selected Work Period');
            }
        }

        $userLabel = 'All Staff';
        if ($request->filled('user_id')) {
            $selectedUser = User::query()->find((string) $request->string('user_id'));
            if ($selectedUser) {
                $userLabel = $selectedUser->name;
            }
        }

        return [
            'period' => $periodLabel,
            'work_period_range' => $periodDateRange,
            'year' => $request->filled('year') ? (string) $request->input('year') : 'All Years',
            'status' => $statusFilter ? ucfirst($statusFilter) : 'All Statuses',
            'staff' => $userLabel,
        ];
    }

    private function weeklyBudgetReviewExportFilename(string $extension, array $filterSummary): string
    {
        $periodSlug = Str::slug((string) ($filterSummary['period'] ?? 'all-weeks'));
        $statusSlug = Str::slug((string) ($filterSummary['status'] ?? 'all-statuses'));
        $dateStamp = now()->format('Ymd_His');

        return "weekly_budget_review_{$periodSlug}_{$statusSlug}_{$dateStamp}.{$extension}";
    }

    /**
     * Group budget documents by department -> staff -> budget items for the review UI.
     *
     * @return array<string, array{department_name: string, department_id: string|null, requested_total: float, provided_total: float, staff: array}>
     */
    private function groupBudgetDocumentsByDepartment(\Illuminate\Support\Collection $documents): array
    {
        $grouped = [];

        foreach ($documents as $document) {
            $departmentName = $document->user?->department?->name ?? 'No Department';
            $departmentId = (string) ($document->user?->department_id ?? 'none');

            if (! isset($grouped[$departmentId])) {
                $grouped[$departmentId] = [
                    'department_name' => $departmentName,
                    'department_id' => $departmentId,
                    'requested_total' => 0.0,
                    'provided_total' => 0.0,
                    'staff' => [],
                ];
            }

            $userId = (string) $document->user_id;
            if (! isset($grouped[$departmentId]['staff'][$userId])) {
                $grouped[$departmentId]['staff'][$userId] = [
                    'user' => $document->user,
                    'document' => $document,
                    'items' => [],
                    'requested_total' => 0.0,
                    'provided_total' => 0.0,
                    'summary' => $document->budgetReviewSummary(),
                ];
            }

            $budgetItems = $document->budgetItems();
            $reviewsByUid = $document->budgetReviewItemsByUid();

            foreach ($budgetItems as $item) {
                $uid = $item['budget_uid'];
                $review = $reviewsByUid[$uid] ?? null;
                $amountNumeric = (float) ($item['amount_numeric'] ?? 0);
                $providedAmount = (float) ($review['provided_amount'] ?? 0);

                $grouped[$departmentId]['staff'][$userId]['items'][] = [
                    'budget_uid' => $uid,
                    'item' => $item['item'],
                    'description_purpose' => $item['description_purpose'],
                    'amount' => $item['amount'],
                    'amount_numeric' => $amountNumeric,
                    'decision' => $review['decision'] ?? Document::BUDGET_REVIEW_PENDING,
                    'provided_amount' => $providedAmount,
                    'note' => $review['note'] ?? null,
                    'reviewed_by' => $review['reviewed_by'] ?? null,
                    'reviewed_at' => $review['reviewed_at'] ?? null,
                    'document_id' => $document->id,
                ];

                $grouped[$departmentId]['staff'][$userId]['requested_total'] += $amountNumeric;
                $grouped[$departmentId]['staff'][$userId]['provided_total'] += $providedAmount;
                $grouped[$departmentId]['requested_total'] += $amountNumeric;
                $grouped[$departmentId]['provided_total'] += $providedAmount;
            }
        }

        // Sort departments alphabetically
        uasort($grouped, fn ($a, $b) => strcasecmp($a['department_name'], $b['department_name']));

        return $grouped;
    }

    /**
     * Compute aggregate stats across multiple budget documents.
     */
    private function computeBudgetStats(\Illuminate\Support\Collection $documents): array
    {
        $totalItems = 0;
        $pendingItems = 0;
        $approvedItems = 0;
        $rejectedItems = 0;
        $requestedTotal = 0.0;
        $providedTotal = 0.0;

        foreach ($documents as $document) {
            $summary = $document->budgetReviewSummary();
            $totalItems += $summary['items_total'];
            $pendingItems += $summary['pending'];
            $approvedItems += $summary['approved'];
            $rejectedItems += $summary['rejected'];
            $requestedTotal += $summary['requested_total'];
            $providedTotal += $summary['provided_total'];
        }

        return [
            'total' => $documents->count(),
            'total_items' => $totalItems,
            'pending' => $pendingItems,
            'approved' => $approvedItems,
            'rejected' => $rejectedItems,
            'requested_total' => $requestedTotal,
            'provided_total' => $providedTotal,
        ];
    }

    /**
     * Approve or reject a single weekly plan budget item.
     */
    public function processWeeklyBudgetReview(Request $request, Document $document)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (! $user->hasPermissionTo('process weekly budgets')) {
            abort(403, 'Unauthorized action.');
        }

        if ($document->type !== Document::TYPE_WEEKLY_PLAN || ! $document->hasBudgetItems()) {
            return redirect()
                ->back()
                ->with('error', 'Only weekly plans with budget rows can be processed.');
        }

        $validated = $request->validate([
            'budget_uid' => ['required', 'string', 'max:255'],
            'decision' => ['required', 'in:approved,rejected'],
            'provided_amount' => ['nullable', 'numeric', 'min:0', 'required_if:decision,approved'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $budgetUid = trim($validated['budget_uid']);
        $decision = $validated['decision'];
        $providedAmount = $decision === Document::BUDGET_REVIEW_APPROVED
            ? (float) $validated['provided_amount']
            : 0.0;
        $note = isset($validated['note']) && trim($validated['note']) !== '' ? trim($validated['note']) : null;

        // Verify the UID exists in the plan's budget items
        $budgetItems = $document->budgetItems();
        $uidExists = collect($budgetItems)->contains(fn ($item) => $item['budget_uid'] === $budgetUid);
        if (! $uidExists) {
            return redirect()
                ->back()
                ->with('error', 'Budget item not found in this plan.');
        }

        // Update the specific item in budget_review_items
        $existingReviews = is_array($document->budget_review_items) ? $document->budget_review_items : [];
        $found = false;
        foreach ($existingReviews as &$review) {
            if (is_array($review) && ($review['budget_uid'] ?? '') === $budgetUid) {
                $review['decision'] = $decision;
                $review['provided_amount'] = $providedAmount;
                $review['note'] = $note;
                $review['reviewed_by'] = $user->id;
                $review['reviewed_at'] = now()->toIso8601String();
                $found = true;
                break;
            }
        }
        unset($review);

        if (! $found) {
            $existingReviews[] = [
                'budget_uid' => $budgetUid,
                'decision' => $decision,
                'provided_amount' => $providedAmount,
                'note' => $note,
                'reviewed_by' => $user->id,
                'reviewed_at' => now()->toIso8601String(),
            ];
        }

        // Recompute plan-level summary from all item decisions
        $document->budget_review_items = $existingReviews;
        $summary = $document->budgetReviewSummary();

        $planStatus = Document::BUDGET_REVIEW_PENDING;
        if ($summary['all_approved']) {
            $planStatus = Document::BUDGET_REVIEW_APPROVED;
        } elseif ($summary['all_rejected']) {
            $planStatus = Document::BUDGET_REVIEW_REJECTED;
        } elseif ($summary['pending'] === 0 && $summary['items_total'] > 0) {
            // All items reviewed but mixed decisions
            $planStatus = Document::BUDGET_REVIEW_APPROVED;
        }

        $document->update([
            'budget_review_items' => $existingReviews,
            'budget_review_status' => $planStatus,
            'budget_reviewed_by' => $user->id,
            'budget_reviewed_at' => now(),
            'budget_provided_amount' => $summary['provided_total'],
            'budget_review_note' => null,
        ]);

        return redirect()
            ->back()
            ->with('success', 'Budget item "'.$budgetUid.'" has been reviewed.');
    }

    public function addMidWeekBudgetEntry(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (! $user->hasPermissionTo('process weekly budgets')) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'user_id' => ['required', 'string', 'exists:users,id'],
            'period_id' => ['required', 'string', 'exists:work_periods,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $targetDocument = Document::query()
            ->weeklyPlans()
            ->submitted()
            ->where('user_id', (string) $validated['user_id'])
            ->where('period_id', (string) $validated['period_id'])
            ->latest()
            ->first();

        if (! $targetDocument) {
            return redirect()
                ->back()
                ->with('error', 'No submitted weekly plan found for the selected staff and work period.');
        }

        $content = is_array($targetDocument->content) ? $targetDocument->content : [];
        $budgetRows = is_array($content['budget'] ?? null) ? $content['budget'] : [];
        $budgetUid = (string) Str::ulid();
        $amountNumeric = (float) $validated['amount'];

        $budgetRows[] = [
            'budget_uid' => $budgetUid,
            'item' => 'Mid-week Budget',
            'description_purpose' => trim((string) $validated['reason']),
            'amount' => number_format($amountNumeric, 2, '.', ''),
        ];
        $content['budget'] = $budgetRows;

        $existingReviews = is_array($targetDocument->budget_review_items) ? $targetDocument->budget_review_items : [];
        $existingReviews[] = [
            'budget_uid' => $budgetUid,
            'decision' => Document::BUDGET_REVIEW_APPROVED,
            'provided_amount' => $amountNumeric,
            'note' => null,
            'reviewed_by' => $user->id,
            'reviewed_at' => now()->toIso8601String(),
        ];

        $targetDocument->content = $content;
        $targetDocument->budget_review_items = $existingReviews;
        $summary = $targetDocument->budgetReviewSummary();

        $planStatus = Document::BUDGET_REVIEW_PENDING;
        if ($summary['all_approved']) {
            $planStatus = Document::BUDGET_REVIEW_APPROVED;
        } elseif ($summary['all_rejected']) {
            $planStatus = Document::BUDGET_REVIEW_REJECTED;
        } elseif ($summary['pending'] === 0 && $summary['items_total'] > 0) {
            $planStatus = Document::BUDGET_REVIEW_APPROVED;
        }

        $targetDocument->update([
            'content' => $content,
            'budget_review_items' => $existingReviews,
            'budget_review_status' => $planStatus,
            'budget_reviewed_by' => $user->id,
            'budget_reviewed_at' => now(),
            'budget_provided_amount' => $summary['provided_total'],
            'budget_review_note' => null,
        ]);

        return redirect()
            ->back()
            ->with('success', 'Mid-week budget entry added successfully.');
    }

    /**
     * Build document_id => latest rejection reason map for visible documents.
     */
    private function getReturnedDocumentMapForIds(array $documentIds): array
    {
        if (empty($documentIds)) {
            return [];
        }

        return Document::query()
            ->whereIn('id', $documentIds)
            ->whereNotNull('review_rejection_reason')
            ->where('review_rejection_reason', '!=', '')
            ->get(['id', 'review_rejection_reason'])
            ->mapWithKeys(function (Document $doc) {
                return [(string) $doc->id => trim((string) $doc->review_rejection_reason)];
            })
            ->all();
    }

    private function budgetReviewDefaultsForWeeklyPlan(?array $content, ?array $existingReviewItems = null): array
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

    private function withEffectiveWeeklyReportCollaborators($documents, User $viewer)
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

    private function shareableDepartmentUsersFor(User $owner)
    {
        if (! $owner->department_id) {
            return collect();
        }

        return User::query()
            ->select('id', 'name')
            ->where('department_id', $owner->department_id)
            ->where('id', '!=', $owner->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    private function weeklyReportSharedUserIdsFromPlanContext(User $actingUser, ?string $periodId): array
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
    private function extractRequestedSharedUserIds(Request $request): array
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
    private function validateSharedMembersWithinDepartment(User $owner, array $sharedUserIds): array
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
    private function firstCollaborationConflictMessage(
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
    private function syncDocumentShares(Document $document, array $sharedUserIds, User $actor): void
    {
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
                'document_id' => $document->id,
                'actor_user_id' => $actor->id,
                'target_user_id' => $targetUserId,
                'event_type' => Document::SHARE_EVENT_ADDED,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach ($removedIds as $targetUserId) {
            $events[] = [
                'document_id' => $document->id,
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
    private function applyWeeklyListSortForUser($query, User $user): void
    {
        if ($user->hasPermissionTo('view all documents')) {
            $query->orderByDesc('documents.updated_at');

            return;
        }

        $query->orderByRaw('CASE WHEN documents.user_id = ? THEN 1 ELSE 0 END', [$user->id])
            ->orderByDesc('documents.updated_at');
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{planned: list<array<string, mixed>>, off_plan: list<array<string, mixed>>, month_summary: string}
     */
    private function normalizeMonthlyReportContent(
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

    private function clipString(string $value, int $max): string
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
    private function normalizeTemplateContent(array $content, string $type): array
    {
        $content = $this->trimDeep($content);

        if ($type === Document::TYPE_WEEKLY_PLAN) {
            $content['planned_activities'] = $this->filterNonEmptyRows(
                $content['planned_activities'] ?? [],
                ['day', 'activity', 'location', 'team_member_ids', 'notes_inclusion']
            );
            $content['planned_activities'] = $this->ensureWeeklyPlanActivityUids($content['planned_activities']);

            $content['key_meetings'] = $this->filterNonEmptyRows(
                $content['key_meetings'] ?? [],
                ['date', 'meeting', 'participants', 'location', 'reactions_notes']
            );

            $content['budget'] = $this->filterNonEmptyRows(
                $content['budget'] ?? [],
                ['item', 'description_purpose', 'amount']
            );
            $content['budget'] = $this->ensureBudgetRowUids($content['budget']);
        }

        if ($type === Document::TYPE_WEEKLY_REPORT) {
            $content['key_achievements'] = array_values(array_filter(
                is_array($content['key_achievements'] ?? null) ? $content['key_achievements'] : [],
                fn ($item) => is_string($item) && $item !== ''
            ));

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
                $row['carry_to_next_plan'] = in_array($progress, ['Not started', 'Partially done'], true);

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
                ['status', 'notes']
            );
        }

        return $content;
    }

    private function resolveWeeklyReportTargetStatus(Request $request, ?Document $document = null): ?string
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

    private function targetAccomplishedBooleanFromStatus(?string $status): ?bool
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
    private function buildCarriedActivitiesFromWeeklyReport(Document $weeklyReport): array
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
            if (! in_array($progress, ['Not started', 'Partially done'], true)) {
                continue;
            }

            $activityOutput = trim((string) ($row['activity_output'] ?? ''));
            $offPlanActivity = trim((string) ($row['off_plan_activity'] ?? ''));
            $day = '';
            $activity = '';
            $location = '';
            $teamMemberIds = [];

            if ($activityOutput === '__off_plan__') {
                $activity = $offPlanActivity;
            } elseif ($activityOutput !== '' && isset($planByLabel[$activityOutput]) && is_array($planByLabel[$activityOutput])) {
                $sourceRow = $planByLabel[$activityOutput];
                $day = trim((string) ($sourceRow['day'] ?? ''));
                $activity = trim((string) ($sourceRow['activity'] ?? ''));
                $location = trim((string) ($sourceRow['location'] ?? ''));
                $teamMemberIds = is_array($sourceRow['team_member_ids'] ?? null) ? array_values(array_filter($sourceRow['team_member_ids'])) : [];
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
                'activity_uid' => (string) Str::ulid(),
                'day' => $day,
                'activity' => $activity,
                'location' => $location,
                'team_member_ids' => $teamMemberIds,
                'notes_inclusion' => $this->clipString($notes, 4000),
                'carry_over_source_report_id' => (string) $weeklyReport->id,
            ];
        }

        return $carriedRows;
    }

    private function syncNextWeeklyPlanCarryOverFromReport(Document $weeklyReport): void
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

        $nextPlanContent = is_array($nextPlan->content) ? $nextPlan->content : [];
        $existingActivities = is_array($nextPlanContent['planned_activities'] ?? null) ? $nextPlanContent['planned_activities'] : [];
        $existingActivities = array_values(array_filter($existingActivities, function ($row) use ($weeklyReport) {
            if (! is_array($row)) {
                return false;
            }

            return trim((string) ($row['carry_over_source_report_id'] ?? '')) !== (string) $weeklyReport->id;
        }));

        $carriedRows = $this->buildCarriedActivitiesFromWeeklyReport($weeklyReport);
        $existingKeys = [];
        foreach ($existingActivities as $existingRow) {
            if (! is_array($existingRow)) {
                continue;
            }
            $existingKeys[mb_strtolower(trim((string) ($existingRow['day'] ?? '')).'|'.trim((string) ($existingRow['activity'] ?? '')))] = true;
        }
        foreach ($carriedRows as $carriedRow) {
            $rowKey = mb_strtolower(trim((string) ($carriedRow['day'] ?? '')).'|'.trim((string) ($carriedRow['activity'] ?? '')));
            if (isset($existingKeys[$rowKey])) {
                continue;
            }
            $existingActivities[] = $carriedRow;
            $existingKeys[$rowKey] = true;
        }

        $nextPlanContent['planned_activities'] = $this->ensureWeeklyPlanActivityUids($existingActivities);
        $nextPlanContent = $this->normalizeTemplateContent($nextPlanContent, Document::TYPE_WEEKLY_PLAN);
        $nextPlan->update(['content' => $nextPlanContent]);
        $this->syncWeeklyPlanAssignments($nextPlan);
    }

    private function ensureWeeklyPlanActivityUids(array $rows): array
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

    private function syncWeeklyPlanAssignments(Document $document): void
    {
        if ($document->type !== Document::TYPE_WEEKLY_PLAN) {
            return;
        }

        $document->refresh();
        $this->weeklyActivityAssignmentService->syncForWeeklyPlan($document);
    }

    private function trimDeep(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->trimDeep($v);
            }

            return $value;
        }

        return is_string($value) ? trim($value) : $value;
    }

    private function filterNonEmptyRows(mixed $rows, array $keys): array
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

    private function templateHasBudgetRows(?array $content): bool
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
    private function ensureBudgetRowUids(array $budgetRows): array
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

    private function requestedBudgetTotal(Document $document): float
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
}
