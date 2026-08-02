<?php

namespace App\Http\Controllers\Concerns;

use App\Exports\WeeklyBudgetReviewExport;
use App\Models\Document;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanBudgetItem;
use App\Models\WeeklyPlanBudgetReview;
use App\Models\WorkPeriod;
use App\Services\MonthlyReportYearPlanActivities;
use Barryvdh\DomPDF\Facade\Pdf;
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

/**
 * CRUD actions for weekly plans, weekly reports and monthly reports.
 */
trait HandlesDocumentCrud
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Document::class);

        if ($request->input('type') === Document::TYPE_MONTHLY_REPORT) {
            return redirect()->route('documents.monthly-report', $request->only(['year', 'month', 'review_status', 'page']));
        }

        if ($request->hasSession()) {
            $request->session()->put('documents.last_listing_url', $request->fullUrl());
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $query = Document::with(['user', 'period', 'sharedUsers:id,name']);

        $reviewStatus = (string) $request->input('review_status', '');

        // Submitted documents are listed organization-wide for Monday stand-ups.
        // Draft-focused views stay limited to owner/collaborators unless user can view all.
        $scopedToOwnDocuments = ! $user->hasPermissionTo('view all documents')
            && in_array($reviewStatus, ['returned', 'draft'], true);

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
            $query->whereHas('period', function ($q) use ($year) {
                $q->where('year', $year);
            });
        }

        // Default to the latest 2 work periods unless user explicitly filters by a specific period.
        if (! $periodId) {
            $latestWorkPeriodIds = WorkPeriod::query()
                ->orderBy('year', 'desc')
                ->orderBy('week_number', 'desc')
                ->limit(2)
                ->pluck('id')
                ->all();

            if (! empty($latestWorkPeriodIds)) {
                $query->whereIn('period_id', $latestWorkPeriodIds);
            }
        }

        // This page focuses on weekly plan/report rows.
        $query->whereIn('type', [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT]);

        if ($reviewStatus === 'returned') {
            $query->whereIn('type', [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT])
                ->where('state', Document::STATE_DRAFT)
                ->whereNotNull('review_rejection_reason')
                ->where('review_rejection_reason', '!=', '');
        } elseif ($reviewStatus === 'draft') {
            $query->where('state', Document::STATE_DRAFT);
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
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $selectedType = $request->query('type');
        $requestedPeriodId = $request->query('period_id');
        $requestedPeriod = is_string($requestedPeriodId) && $requestedPeriodId !== ''
            ? WorkPeriod::find($requestedPeriodId)
            : null;

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
        $reportMeetingRows = [];

        if ($selectedType) {
            if ($selectedType === Document::TYPE_WEEKLY_PLAN) {
                // Honor explicit period when open or in plan grace; otherwise next week.
                $targetPlanPeriod = $nextPeriod;
                if ($requestedPeriod && ($requestedPeriod->isOpen() || $requestedPeriod->isPlanGracePeriodActive())) {
                    $targetPlanPeriod = $requestedPeriod;
                }
                $defaultPeriodId = $targetPlanPeriod?->id;
                if ($currentPeriod) {
                    $latestWeeklyReport = Document::findWeeklyReportForPeriodOwner($currentPeriod->id, (string) $user->id);
                    if ($latestWeeklyReport) {
                        $carriedActivities = $this->buildCarriedActivitiesFromWeeklyReport($latestWeeklyReport);
                    }
                }
            } elseif ($selectedType === Document::TYPE_WEEKLY_REPORT) {
                // Honor explicit period when open or in report grace; otherwise current week.
                // Without grace, a closed prior week (e.g. 29) falls through to the current week
                // and incorrectly loads that week's plan for main goal/activities.
                $targetReportPeriod = $currentPeriod;
                if ($requestedPeriod && ($requestedPeriod->isOpen() || $requestedPeriod->isReportGracePeriodActive())) {
                    $targetReportPeriod = $requestedPeriod;
                }
                $defaultPeriodId = $targetReportPeriod?->id;
                // Get the related plan for this period to show the main target
                if ($targetReportPeriod) {
                    $relatedPlan = Document::findWeeklyPlanForUserAndPeriod(
                        $targetReportPeriod->id,
                        (string) $user->id
                    );
                    $reportMeetingRows = $this->weeklyReportMeetingRowsForUserPeriod(
                        (string) $targetReportPeriod->id,
                        (string) $user->id
                    );
                }
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

        if ($redirect = $this->redirectIfWeeklyDocumentAlreadyExists(
            $request,
            $user,
            $selectedType,
            $defaultPeriodId ? (string) $defaultPeriodId : null
        )) {
            return $redirect;
        }

        // If type is provided in query string, pre-select it
        if ($selectedType && isset($availableTypes[$selectedType])) {
            // Validate that user can create this type
            if (Document::canUserCreateType($user, $selectedType)) {
                $teamUsers = $this->teamUsersForSelection();
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
                    'reportMeetingRows',
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

        $teamUsers = $this->teamUsersForSelection();
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
            'reportMeetingRows',
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
            $validationRules['period_id'] = ['required', 'string', 'short_id', 'exists:work_periods,id'];
            if ($request->type === Document::TYPE_WEEKLY_PLAN) {
                $validationRules['shared_user_ids'] = ['nullable', 'array'];
                $validationRules['shared_user_ids.*'] = ['nullable', 'string', 'short_id', 'exists:users,id'];
            }
        } else {
            $validationRules['period_id'] = ['nullable', 'string', 'short_id', 'exists:work_periods,id'];
        }

        // Add validation for monthly reports
        if ($request->type === Document::TYPE_MONTHLY_REPORT) {
            $validationRules['month'] = ['required', 'integer', 'min:1', 'max:12'];
            $validationRules['year'] = ['required', 'integer', 'min:2000', 'max:2100'];
            $validationRules['attachment'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xlsx,xls', 'max:10240'];
            $validationRules['file'] = ['nullable', 'file', 'mimes:pdf', 'max:10240'];
            $validationRules['content'] = ['nullable', 'array'];
        }

        $action = $this->normalizeDocumentAction($request);

        // Weekly reports are submitted only from the review wizard step.
        if ($request->type === Document::TYPE_WEEKLY_REPORT && $action === 'submit' && ! $request->boolean('wizard_review')) {
            throw ValidationException::withMessages([
                'action' => 'Please submit your weekly report from the review step.',
            ]);
        }

        // Main target is required when submitting; drafts may save without it.
        if ($request->type === Document::TYPE_WEEKLY_PLAN) {
            $validationRules['main_target'] = [
                Rule::requiredIf($action === 'submit'),
                'nullable',
                'string',
                'max:2000',
            ];
        }

        // Online template content (weekly_plan/weekly_report)
        $validationRules['content'] = ['nullable', 'array'];

        if ($request->type === Document::TYPE_WEEKLY_PLAN) {
            $validationRules['content.objective.description'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.objective.reflection_progress'] = ['nullable', 'string', 'max:4000'];

            $validationRules['content.planned_activities'] = ['nullable', 'array'];
            $validationRules['content.planned_activities.*.day'] = ['nullable', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday'];
            $validationRules['content.planned_activities.*.activity'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.planned_activities.*.location'] = ['nullable', 'string', 'max:255'];
            $validationRules['content.planned_activities.*.team_member_ids'] = ['nullable', 'array'];
            $validationRules['content.planned_activities.*.team_member_ids.*'] = ['nullable', 'string', 'short_id', $this->activeUserIdRule()];
            $validationRules['content.planned_activities.*.notes_inclusion'] = ['nullable', 'string', 'max:4000'];

            $validationRules['content.key_meetings'] = ['nullable', 'array'];
            $validationRules['content.key_meetings.*.date'] = ['nullable', 'date'];
            $validationRules['content.key_meetings.*.meeting'] = ['nullable', 'string', 'max:255'];
            $validationRules['content.key_meetings.*.participant_user_ids'] = ['nullable', 'array'];
            $validationRules['content.key_meetings.*.participant_user_ids.*'] = ['nullable', 'string', 'short_id', $this->activeUserIdRule()];
            $validationRules['content.key_meetings.*.status'] = ['nullable', 'string', 'in:Done,Not done'];
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
            $validationRules['content.activity_progress.*.progress'] = ['nullable', 'string', 'in:Done,Partially done,Done and carry,Not started,In progress'];
            $validationRules['content.activity_progress.*.remarks_challenges'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.activity_progress.*.next_step'] = ['nullable', 'string', 'max:2000'];

            $validationRules['content.key_meetings'] = ['nullable', 'array'];
            $validationRules['content.key_meetings.*.date'] = ['nullable', 'date'];
            $validationRules['content.key_meetings.*.meeting'] = ['nullable', 'string', 'max:255'];
            $validationRules['content.key_meetings.*.participant_user_ids'] = ['nullable', 'array'];
            $validationRules['content.key_meetings.*.participant_user_ids.*'] = ['nullable', 'string', 'short_id', $this->activeUserIdRule()];
            $validationRules['content.key_meetings.*.status'] = ['nullable', 'string', 'in:Done,Not done'];
            $validationRules['content.key_meetings.*.location'] = ['nullable', 'string', 'max:255'];
            $validationRules['content.key_meetings.*.reactions_notes'] = ['nullable', 'string', 'max:4000'];

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

        $sharedUserIds = [];

        if ($request->type === Document::TYPE_WEEKLY_PLAN) {
            $content = is_array($request->input('content')) ? $request->input('content') : [];
            $this->validateWeeklyPlanPlannedActivities($content, $action);
        }

        if (Document::supportsSharedEditing($request->type) && $request->type !== Document::TYPE_WEEKLY_REPORT) {
            $sharedUserIds = $this->extractRequestedSharedUserIds($request);
            $sharedUserIds = $this->validateSharedMembersWithinDepartment($user, $sharedUserIds);
        }

        // Normalize and trim template payload to avoid storing empty rows.
        $templateContent = null;
        if (in_array($request->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT]) && $request->has('content') && is_array($request->input('content'))) {
            $rawTemplateContent = $request->input('content');
            if ($request->type === Document::TYPE_WEEKLY_REPORT) {
                $rawTemplateContent = $this->stripReadonlyAssignedMeetingsFromWeeklyReportContent(
                    is_array($rawTemplateContent) ? $rawTemplateContent : [],
                    $user,
                    $request->input('period_id')
                );
            }
            $templateContent = $this->normalizeTemplateContent(is_array($rawTemplateContent) ? $rawTemplateContent : [], $request->type);
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
            if ($action === 'submit') {
                $this->assertWeeklyReportActionTasksUpdated($user);
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

            $isExpectedPeriod = $expectedPeriod && $period->id === $expectedPeriod->id;
            $inGraceWindow = $request->type === Document::TYPE_WEEKLY_PLAN
                ? $period->isPlanGracePeriodActive()
                : $period->isReportGracePeriodActive();
            if (! $isExpectedPeriod && ! $period->isOpen() && ! $inGraceWindow) {
                $errorMessage = $request->type === Document::TYPE_WEEKLY_PLAN
                    ? 'The selected weekly plan work period is closed.'
                    : 'The selected weekly report work period is closed.';

                // Drafts are allowed even when work periods are closed.
                if ($action === 'submit') {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', $errorMessage);
                }
            }
        }

        if ($period && ! $period->isOpen()) {
            $inGraceWindow = $request->type === Document::TYPE_WEEKLY_PLAN
                ? $period->isPlanGracePeriodActive()
                : $period->isReportGracePeriodActive();

            // Drafts are allowed even when work periods are closed.
            if ($action === 'submit' && ! $inGraceWindow) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'This work period is closed. Please contact your administrator.');
            }
        }

        if ($request->type === Document::TYPE_WEEKLY_REPORT) {
            $sharedUserIds = $this->weeklyReportSharedUserIdsFromPlanContext($user, $period?->id);
            $sharedUserIds = $this->validateSharedMembersWithinDepartment($user, $sharedUserIds);
        }

        // Check if document already exists for this user, period/month/year, and type
        $existingDocument = null;
        if (in_array($request->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT]) && $period) {
            $existingDocument = $this->findExistingWeeklyDocumentForStore($user, $period->id, $request->type);
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
            if ($action === 'submit'
                && ! $existingDocument
                && $this->weeklySubmissionReminderService->blocksNewWeeklyDocument($request->type, $period)
            ) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'The submission window for this weekly document has closed.');
            }
            if ($existingDocument && $action === 'submit' && ! $this->weeklySubmissionReminderService->allowsSubmitWeeklyDocument($existingDocument)) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'This work period is closed. You can no longer submit this document.');
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

                return redirect()->back()
                    ->withInput()
                    ->withErrors([
                        'period_id' => $this->existingWeeklyDocumentMessage($existingDocument),
                    ])
                    ->with('existing_document_url', route('documents.show', $existingDocument->id));
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

            try {
                DB::transaction(function () use (
                    $existingDocument,
                    $updateData,
                    $sharedUserIds,
                    $user,
                    $action
                ) {
                    $existingDocument->update($updateData);
                    $this->documentDomainSyncService->syncFromDocument($existingDocument);

                    if (Document::supportsSharedEditing($existingDocument->type)) {
                        $this->syncDocumentShares($existingDocument, $sharedUserIds, $user);
                    }

                    // Keep assignment rows in sync with the weekly plan content.
                    if ($existingDocument->type === Document::TYPE_WEEKLY_PLAN) {
                        $this->syncWeeklyPlanAssignments($existingDocument);
                    }

                    if ($existingDocument->type === Document::TYPE_WEEKLY_REPORT) {
                        $this->syncNextWeeklyPlanCarryOverFromReport($existingDocument->fresh());
                    }
                });

                if ($action === 'submit') {
                    $existingDocument->loadMissing('period');
                    $this->workflowEmailNotificationService->notifyWeeklyDocumentSubmitted($existingDocument, $user);
                    $this->workflowEmailNotificationService->notifyWeeklyBudgetSubmitted($existingDocument, $user);
                }
            } catch (ValidationException $e) {
                throw $e;
            } catch (\Throwable $e) {
                return $this->weeklyDocumentSaveErrorResponse(
                    $e,
                    $request,
                    $action,
                    $existingDocument,
                    (string) $user->id
                );
            }

            $message = ($action === 'submit')
                ? 'Document updated and submitted successfully.'
                : 'Document updated successfully.';

            return $this->redirectAfterWeeklyReportWrite($existingDocument, $action, $message, $user);
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

        $document = null;
        try {
            DB::transaction(function () use ($documentData, &$document, $sharedUserIds, $user, $action) {
                $document = Document::create($documentData);

                if (Document::supportsSharedEditing($document->type)) {
                    $this->syncDocumentShares($document, $sharedUserIds, $user);
                }

                $this->syncWeeklyPlanAssignments($document);

                if ($document->type === Document::TYPE_WEEKLY_REPORT) {
                    $this->syncNextWeeklyPlanCarryOverFromReport($document);
                }
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->weeklyDocumentSaveErrorResponse(
                $e,
                $request,
                $action,
                null,
                (string) $user->id,
                $period?->id
            );
        }

        if (! $document instanceof Document) {
            // Should never happen: the transaction catch returns a redirect response.
            throw new \RuntimeException('Weekly document was not created.');
        }

        if ($action === 'submit') {
            $document->loadMissing('period');
            $this->workflowEmailNotificationService->notifyWeeklyDocumentSubmitted($document, $user);
            $this->workflowEmailNotificationService->notifyWeeklyBudgetSubmitted($document, $user);
        }

        $message = ($action === 'submit')
            ? 'Document submitted successfully.'
            : 'Document saved as draft.';

        return $this->redirectAfterWeeklyReportWrite($document, $action, $message, $user);
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
        $carriedActivities = $this->carriedActivitiesForWeeklyPlanDocument($document);
        $incomingMeetings = $this->incomingMeetingsForWeeklyPlanDocument($document);

        // Authorize view action
        $this->authorize('view', $document);

        [$relatedDocument, $relatedAction] = $this->resolveWeeklyRelatedDocumentContext($document);
        $showWeeklyRelatedPanel = $this->shouldShowWeeklyRelatedPanel($document, $relatedDocument, $relatedAction);

        $assignedWeeklyActivities = $this->assignedWeeklyActivitiesForWeeklyPlanDocument($document);

        $teamUsers = $this->teamUsersForSelection();

        $weeklyReportWizardProgress = null;
        if ($document->type === Document::TYPE_WEEKLY_REPORT && $document->isDraft()) {
            /** @var \App\Models\User $viewer */
            $viewer = Auth::user();
            $weeklyReportWizardProgress = $this->actionLogProgressService->buildForUser($viewer);
        }

        return view('documents.show', compact('document', 'relatedDocument', 'relatedAction', 'showWeeklyRelatedPanel', 'assignedWeeklyActivities', 'carriedActivities', 'incomingMeetings', 'teamUsers', 'weeklyReportWizardProgress'));
    }

    /**
     * Download a weekly plan or weekly report as PDF.
     */
    public function downloadPdf(Document $document)
    {
        if (! in_array($document->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true)) {
            abort(404, 'Document not found.');
        }

        $this->authorize('view', $document);

        $document->load(['user', 'period', 'sharedUsers']);

        $weekLabel = $document->period
            ? 'week_'.$document->period->week_number.'_'.$document->period->year
            : 'no_period';

        if ($document->type === Document::TYPE_WEEKLY_PLAN) {
            $carriedActivities = $this->carriedActivitiesForWeeklyPlanDocument($document);
            $incomingMeetings = $this->incomingMeetingsForWeeklyPlanDocument($document);
            $assignedWeeklyActivities = $this->assignedWeeklyActivitiesForWeeklyPlanDocument($document);

            $pdf = Pdf::loadView('documents.pdf.weekly-plan-pdf', compact(
                'document',
                'carriedActivities',
                'incomingMeetings',
                'assignedWeeklyActivities',
            ));

            $filename = 'weekly_plan_'.$weekLabel.'_'.$document->id.'.pdf';
        } else {
            $teamUsers = $this->teamUsersForSelection();

            $pdf = Pdf::loadView('documents.pdf.weekly-report-pdf', compact(
                'document',
                'teamUsers',
            ));

            $filename = 'weekly_report_'.$weekLabel.'_'.$document->id.'.pdf';
        }

        return $pdf->download($filename);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Document $document)
    {
        /** @var \App\Models\User $viewer */
        $viewer = Auth::user();

        if ($document->type === Document::TYPE_ACTION_LOG) {
            $this->authorize('update', $document);

            return redirect()->route('action-logs.edit', $document);
        }

        // Authorize update action
        $this->authorize('update', $document);

        $document->loadMissing(['user', 'sharedUsers', 'period']);
        $carriedActivities = $this->carriedActivitiesForWeeklyPlanDocument($document);
        $incomingMeetings = $this->incomingMeetingsForWeeklyPlanDocument($document);
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

        $teamUsers = $this->teamUsersForSelection();
        $shareableUsers = $this->shareableDepartmentUsersFor($document->user);
        $assignedWeeklyActivities = $this->assignedWeeklyActivitiesForWeeklyPlanDocument($document);
        $reportMeetingRows = [];
        if ($document->type === Document::TYPE_WEEKLY_REPORT && $document->period_id) {
            $existingMeetingRows = is_array($document->content) ? ($document->content['key_meetings'] ?? []) : [];
            $reportMeetingRows = $this->weeklyReportMeetingRowsForUserPeriod(
                (string) $document->period_id,
                (string) $viewer->id,
                is_array($existingMeetingRows) ? $existingMeetingRows : []
            );
        }

        $actionLogProgress = null;
        if ($document->type === Document::TYPE_WEEKLY_REPORT && $document->isDraft()) {
            $actionLogProgress = $this->actionLogProgressService->buildForUser($viewer);
        }

        return view('documents.edit', compact('document', 'teamUsers', 'shareableUsers', 'monthlyYearPlan', 'monthlyPlannedActivities', 'monthlyHasSubDepartments', 'assignedWeeklyActivities', 'carriedActivities', 'incomingMeetings', 'reportMeetingRows', 'actionLogProgress'));
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

        // Resolve the intended action early so validation rules can mirror the
        // create flow: drafts may be saved incomplete, submissions are strict.
        $action = $this->normalizeDocumentAction($request);

        if ($document->type === Document::TYPE_WEEKLY_REPORT && $action === 'submit' && ! $request->boolean('wizard_review')) {
            throw ValidationException::withMessages([
                'action' => 'Please submit your weekly report from the review step.',
            ]);
        }

        // Validate file upload (file is optional for updates, but if provided must be PDF)
        $validationRules = [
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'], // Max 10MB
            'title' => ['nullable', 'string', 'max:255'],
            // Online template content (weekly_plan/weekly_report)
            'content' => ['nullable', 'array'],
        ];

        // Add validation for main_target for weekly plans.
        // Required only when submitting; drafts may be saved without it (parity with create).
        if ($document->type === Document::TYPE_WEEKLY_PLAN) {
            $validationRules['main_target'] = [
                Rule::requiredIf($action === 'submit'),
                'nullable',
                'string',
                'max:2000',
            ];

            $validationRules['content.objective.description'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.objective.reflection_progress'] = ['nullable', 'string', 'max:4000'];

            $validationRules['content.planned_activities'] = ['nullable', 'array'];
            $validationRules['content.planned_activities.*.day'] = ['nullable', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday'];
            $validationRules['content.planned_activities.*.activity'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.planned_activities.*.location'] = ['nullable', 'string', 'max:255'];
            $validationRules['content.planned_activities.*.team_member_ids'] = ['nullable', 'array'];
            $validationRules['content.planned_activities.*.team_member_ids.*'] = ['nullable', 'string', 'short_id', $this->activeUserIdRule()];
            $validationRules['content.planned_activities.*.notes_inclusion'] = ['nullable', 'string', 'max:4000'];

            $validationRules['content.key_meetings'] = ['nullable', 'array'];
            $validationRules['content.key_meetings.*.date'] = ['nullable', 'date'];
            $validationRules['content.key_meetings.*.meeting'] = ['nullable', 'string', 'max:255'];
            $validationRules['content.key_meetings.*.participant_user_ids'] = ['nullable', 'array'];
            $validationRules['content.key_meetings.*.participant_user_ids.*'] = ['nullable', 'string', 'short_id', $this->activeUserIdRule()];
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
            $validationRules['content.activity_progress.*.progress'] = ['nullable', 'string', 'in:Done,Partially done,Done and carry,Not started,In progress'];
            $validationRules['content.activity_progress.*.remarks_challenges'] = ['nullable', 'string', 'max:2000'];
            $validationRules['content.activity_progress.*.next_step'] = ['nullable', 'string', 'max:2000'];

            $validationRules['content.key_meetings'] = ['nullable', 'array'];
            $validationRules['content.key_meetings.*.date'] = ['nullable', 'date'];
            $validationRules['content.key_meetings.*.meeting'] = ['nullable', 'string', 'max:255'];
            $validationRules['content.key_meetings.*.participant_user_ids'] = ['nullable', 'array'];
            $validationRules['content.key_meetings.*.participant_user_ids.*'] = ['nullable', 'string', 'short_id', $this->activeUserIdRule()];
            $validationRules['content.key_meetings.*.status'] = ['nullable', 'string', 'in:Done,Not done'];
            $validationRules['content.key_meetings.*.location'] = ['nullable', 'string', 'max:255'];
            $validationRules['content.key_meetings.*.reactions_notes'] = ['nullable', 'string', 'max:4000'];

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
            $validationRules['shared_user_ids.*'] = ['nullable', 'string', 'short_id', 'exists:users,id'];
        }

        $request->validate($validationRules);

        $sharedUserIds = [];

        if ($document->type === Document::TYPE_WEEKLY_PLAN) {
            $content = is_array($request->input('content')) ? $request->input('content') : [];
            $this->validateWeeklyPlanPlannedActivities($content, $action);
        }

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
                ->with('error', 'This work period is closed. You can no longer submit this document.');
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
            if ($action === 'submit') {
                $this->assertWeeklyReportActionTasksUpdated($actingUser);
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
            $rawTemplateContent = $request->input('content');
            if ($document->type === Document::TYPE_WEEKLY_REPORT) {
                $rawTemplateContent = $this->stripReadonlyAssignedMeetingsFromWeeklyReportContent(
                    is_array($rawTemplateContent) ? $rawTemplateContent : [],
                    $actingUser,
                    $document->period_id
                );
                $rawTemplateContent = $this->preserveWeeklyReportBudgetNotes(
                    is_array($rawTemplateContent) ? $rawTemplateContent : [],
                    $document
                );
            }
            $templateContent = $this->normalizeTemplateContent(is_array($rawTemplateContent) ? $rawTemplateContent : [], $document->type);
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

        try {
            DB::transaction(function () use (
                $document,
                $updateData,
                $sharedUserIds,
                $actingUser,
                $action
            ) {
                $document->update($updateData);
                $this->documentDomainSyncService->syncFromDocument($document);

                if (Document::supportsSharedEditing($document->type)) {
                    $this->syncDocumentShares($document, $sharedUserIds, $actingUser);
                }

                $this->syncWeeklyPlanAssignments($document);

                if ($document->type === Document::TYPE_WEEKLY_REPORT) {
                    $this->syncNextWeeklyPlanCarryOverFromReport($document->fresh());
                }
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->weeklyDocumentSaveErrorResponse(
                $e,
                $request,
                $action,
                $document,
                (string) $actingUser->id
            );
        }

        if ($action === 'submit' && ! $wasSubmitted) {
            $document->loadMissing('period');
            $this->workflowEmailNotificationService->notifyWeeklyDocumentSubmitted($document, $actingUser);
            $this->workflowEmailNotificationService->notifyWeeklyBudgetSubmitted($document, $actingUser);
        }

        $message = ($action === 'submit')
            ? 'Document submitted successfully.'
            : 'Document updated successfully.';

        return $this->redirectAfterWeeklyReportWrite($document, $action, $message, $actingUser);
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

    protected function findExistingWeeklyDocumentForStore(User $user, string $periodId, string $type): ?Document
    {
        if ($type === Document::TYPE_WEEKLY_PLAN) {
            return Document::findWeeklyPlanForPeriodOwner($periodId, (string) $user->id);
        }

        if ($type === Document::TYPE_WEEKLY_REPORT) {
            return Document::findWeeklyReportForPeriodOwner($periodId, (string) $user->id);
        }

        return Document::query()
            ->forOwnerOrSharedUser($user->id)
            ->where('period_id', $periodId)
            ->where('type', $type)
            ->first();
    }

    protected function redirectIfWeeklyDocumentAlreadyExists(
        Request $request,
        User $user,
        ?string $selectedType,
        ?string $periodId
    ): ?\Illuminate\Http\RedirectResponse {
        if (! $selectedType || ! $periodId) {
            return null;
        }

        $existing = $this->findExistingWeeklyDocumentForStore($user, $periodId, $selectedType);
        if (! $existing) {
            return null;
        }

        if ($existing->isSubmitted()) {
            return redirect()->route('documents.show', $existing->id)
                ->with('info', $this->existingWeeklyDocumentMessage($existing));
        }

        return redirect()->route('documents.edit', $existing->id)
            ->with('info', 'You already have a draft '.$existing->type_name.' for this work period. Continue editing it below.');
    }

    protected function existingWeeklyDocumentMessage(Document $document): string
    {
        $typeName = $document->type_name;
        $weekLabel = $document->period
            ? 'Week '.$document->period->week_number.' of '.$document->period->year
            : 'this work period';

        return "A {$typeName} already exists for {$weekLabel}. Open the existing document instead of creating a new one.";
    }

    protected function weeklyDocumentSaveErrorResponse(
        \Throwable $e,
        Request $request,
        string $action,
        ?Document $document,
        string $userId,
        ?string $periodId = null
    ): \Illuminate\Http\RedirectResponse {
        if ($e instanceof ValidationException) {
            throw $e;
        }

        if ($e instanceof \Illuminate\Database\QueryException && $this->isWeeklyPlanPeriodDuplicate($e)) {
            $existing = null;
            if ($periodId && in_array($request->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT], true)) {
                $existing = $this->findExistingWeeklyDocumentForStore(
                    $request->user(),
                    $periodId,
                    (string) $request->type
                );
            }

            return redirect()->back()
                ->withInput()
                ->withErrors([
                    'period_id' => $existing
                        ? $this->existingWeeklyDocumentMessage($existing)
                        : 'A weekly document already exists for this work period.',
                ])
                ->with('existing_document_url', $existing ? route('documents.show', $existing->id) : null);
        }

        logger()->error('WP_SAVE_FAILED', [
            'user_id' => $userId,
            'document_id' => $document ? (string) $document->id : null,
            'type' => (string) ($document?->type ?? $request->type ?? ''),
            'period_id' => (string) ($document?->period_id ?? $periodId ?? ''),
            'action' => $action,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
        ]);

        $fallback = ($document?->type ?? $request->type) === Document::TYPE_WEEKLY_PLAN
            ? ($action === 'draft'
                ? 'Unable to save your weekly plan draft. Please review the highlighted fields and try again.'
                : 'Unable to save your weekly plan. Please review the highlighted fields and try again.')
            : 'Unable to save your document. Please review the highlighted fields and try again.';

        $message = config('app.debug')
            ? $fallback.' ('.$e->getMessage().')'
            : $fallback;

        if ($document) {
            return redirect()->route('documents.show', $document->id)->with('error', $message);
        }

        return redirect()->back()->withInput()->with('error', $message);
    }

    protected function isWeeklyPlanPeriodDuplicate(\Illuminate\Database\QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'weekly_plans_user_period_unique')
            || str_contains($message, 'weekly_reports_user_period_unique')
            || (str_contains($message, 'duplicate entry') && str_contains($message, 'period_id'));
    }
}
