<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\MonthlyReportYearPlanActivities;
use App\Services\WeeklySubmissionReminderService;
use App\Services\WorkPeriodService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    public function __construct(
        private readonly WorkPeriodService $workPeriodService,
        private readonly WeeklySubmissionReminderService $weeklySubmissionReminderService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Document::class);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $query = Document::with(['user', 'period']);

        // Users with 'view all documents' permission can see all documents, others see only their own
        if (! $user->hasPermissionTo('view all documents')) {
            $query->forUser($user->id);
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

        if ($request->input('review_status') === 'returned') {
            $query->whereIn('type', [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT])
                ->where('state', Document::STATE_DRAFT)
                ->whereNotNull('review_rejection_reason')
                ->where('review_rejection_reason', '!=', '');
        }

        $documents = $query->latest()->paginate(15)->withQueryString();
        $returnedDocumentMap = $this->getReturnedDocumentMapForIds(
            $documents->getCollection()->pluck('id')->all()
        );

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
            'documents' => $documents,
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

        if ($selectedType) {
            if ($selectedType === Document::TYPE_WEEKLY_PLAN && $nextPeriod) {
                // For weekly plan, default to next week
                $defaultPeriodId = $nextPeriod->id;
            } elseif ($selectedType === Document::TYPE_WEEKLY_REPORT && $currentPeriod) {
                // For weekly report, default to current week
                $defaultPeriodId = $currentPeriod->id;
                // Get the related plan for this period to show the main target
                $relatedPlan = Document::where('user_id', $user->id)
                    ->where('period_id', $currentPeriod->id)
                    ->where('type', Document::TYPE_WEEKLY_PLAN)
                    ->first();
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

                return view('documents.create', compact(
                    'availableTypes',
                    'selectedType',
                    'workPeriods',
                    'defaultPeriodId',
                    'currentPeriod',
                    'nextPeriod',
                    'relatedPlan',
                    'teamUsers',
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

        return view('documents.create', compact(
            'availableTypes',
            'workPeriods',
            'defaultPeriodId',
            'currentPeriod',
            'nextPeriod',
            'relatedPlan',
            'teamUsers',
            'monthlyYearPlan',
            'monthlyPlannedActivities',
            'monthlyDefaultMonth',
            'monthlyDefaultYear',
            'monthlyYearChoices',
            'monthlyHasSubDepartments'
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
            $validationRules['target_accomplished'] = ['nullable', 'boolean'];
            $validationRules['target_explanation'] = ['nullable', 'string', 'max:2000'];
        }

        $request->validate($validationRules);

        $action = $request->input('action', 'draft');
        $action = in_array($action, ['submit', 'draft'], true) ? $action : 'draft';

        // Normalize and trim template payload to avoid storing empty rows.
        $templateContent = null;
        if (in_array($request->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT]) && $request->has('content') && is_array($request->input('content'))) {
            $templateContent = $this->normalizeTemplateContent($request->input('content'), $request->type);
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

        // Check if document already exists for this user, period/month/year, and type
        $existingDocument = null;
        if (in_array($request->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT]) && $period) {
            // For weekly plans and reports - one per week period
            $existingDocument = Document::where('user_id', $user->id)
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
                    $updateData = array_merge($updateData, $this->budgetReviewDefaultsForWeeklyPlan($templateContent));
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
                $updateData['target_accomplished'] = $request->has('target_accomplished') ? (bool) $request->target_accomplished : null;
                $updateData['target_explanation'] = $request->filled('target_explanation') ? $request->target_explanation : null;
            }

            // Update state if submitting
            if ($action === 'submit') {
                $updateData['state'] = Document::STATE_SUBMITTED;
                $updateData['review_rejection_reason'] = null;
            }

            $existingDocument->update($updateData);

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
            $documentData['target_accomplished'] = $request->has('target_accomplished') ? (bool) $request->target_accomplished : null;
            $documentData['target_explanation'] = $request->filled('target_explanation') ? $request->target_explanation : null;
        }

        if ($request->type === Document::TYPE_MONTHLY_REPORT && $monthlyContent !== null) {
            $documentData['content'] = $monthlyContent;
        } elseif ($templateContent !== null) {
            $documentData['content'] = $templateContent;
            if ($request->type === Document::TYPE_WEEKLY_PLAN) {
                $documentData = array_merge($documentData, $this->budgetReviewDefaultsForWeeklyPlan($templateContent));
            }
        }

        $document = Document::create($documentData);

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
        $document->load(['user', 'period']);

        // Authorize view action
        $this->authorize('view', $document);

        // Get related documents (plan for report, report for plan)
        $relatedDocument = null;
        $relatedAction = null;
        if ($document->period_id) {
            if ($document->type === Document::TYPE_WEEKLY_PLAN) {
                // Get the report for this period (same period)
                $relatedDocument = Document::where('period_id', $document->period_id)
                    ->where('type', Document::TYPE_WEEKLY_REPORT)
                    ->where('user_id', $document->user_id)
                    ->first();
            } elseif ($document->type === Document::TYPE_WEEKLY_REPORT) {
                // Get the plan for this period (same period)
                $relatedDocument = Document::where('period_id', $document->period_id)
                    ->where('type', Document::TYPE_WEEKLY_PLAN)
                    ->where('user_id', $document->user_id)
                    ->first();
            }

            if (! $relatedDocument) {
                /** @var \App\Models\User $user */
                $user = Auth::user();
                if ($document->type === Document::TYPE_WEEKLY_PLAN &&
                    Document::canUserCreateType($user, Document::TYPE_WEEKLY_REPORT, $document->period)) {
                    $relatedAction = [
                        'type' => Document::TYPE_WEEKLY_REPORT,
                        'label' => 'Create Weekly Report for this Week',
                        'route' => route('documents.create', ['type' => Document::TYPE_WEEKLY_REPORT]),
                        'description' => 'Weekly report is due on '.$document->period->report_deadline->format('M d, Y'),
                    ];
                } elseif ($document->type === Document::TYPE_WEEKLY_REPORT &&
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

        return view('documents.show', compact('document', 'relatedDocument', 'relatedAction'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Document $document)
    {

        // Authorize update action
        $this->authorize('update', $document);

        $document->loadMissing('user');
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

        return view('documents.edit', compact('document', 'teamUsers', 'monthlyYearPlan', 'monthlyPlannedActivities', 'monthlyHasSubDepartments'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Document $document)
    {

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
            $validationRules['target_accomplished'] = ['nullable', 'boolean'];
            $validationRules['target_explanation'] = ['nullable', 'string', 'max:2000'];

            $validationRules['content.summary'] = ['nullable', 'string', 'max:5000'];

            $validationRules['content.activity_progress'] = ['nullable', 'array'];
            $validationRules['content.activity_progress.*.activity_output'] = ['nullable', 'string', 'max:2000'];
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

        $request->validate($validationRules);

        $action = $request->input('action', 'draft');
        $action = in_array($action, ['submit', 'draft'], true) ? $action : 'draft';

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
            if ($request->has('target_accomplished')) {
                $updateData['target_accomplished'] = (bool) $request->target_accomplished;
            }
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
                $updateData = array_merge($updateData, $this->budgetReviewDefaultsForWeeklyPlan($templateContent));
            }
        }

        // Update state if submitting
        if ($action === 'submit') {
            $updateData['state'] = Document::STATE_SUBMITTED;
            $updateData['review_rejection_reason'] = null;
        }

        $document->update($updateData);

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

        $document->delete();

        return redirect()->route('documents.index')
            ->with('success', 'Document deleted successfully.');
    }

    /**
     * Submit a document (change state from draft to submitted)
     */
    public function submit(Request $request, Document $document)
    {

        // Authorize submit action
        $this->authorize('submit', $document);

        $document->submit();

        return redirect()->route('documents.show', $document)
            ->with('success', 'Document submitted successfully.');
    }

    /**
     * Return submitted weekly plan/report to draft with required review reason.
     */
    public function reject(Request $request, Document $document)
    {

        $this->authorize('reject', $document);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $reason = trim($validated['reason']);

        $document->revertToDraft($reason);

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

        return view('documents.reviewer-dashboard', compact(
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
            ->forUser($user->id)
            ->drafts()
            ->latest();

        $allDocumentsQuery = Document::query()
            ->forUser($user->id)
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

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $query = Document::with(['user', 'period'])
            ->ofType(Document::TYPE_WEEKLY_PLAN);

        // Users with 'view all documents' permission can see all documents, others see only their own
        if (! $user->hasPermissionTo('view all documents')) {
            $query->forUser($user->id);
        }

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

        $documents = $query->latest()->paginate(15)->withQueryString();
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
                'plan_due' => $nextPeriod->plan_deadline,
            ];
        }

        $availableTypes = Document::getAvailableTypesForUser($user);

        return view('documents.weekly-plans', [
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

        $plan = Document::where('user_id', $user->id)
            ->where('period_id', $periodId)
            ->where('type', Document::TYPE_WEEKLY_PLAN)
            ->orderByRaw('CASE WHEN state = ? THEN 0 ELSE 1 END', [Document::STATE_SUBMITTED])
            ->latest('id')
            ->first();

        if ($plan) {
            $budgetItems = [];
            if (is_array($plan->content) && ! empty($plan->content['budget']) && is_array($plan->content['budget'])) {
                // Normalize into a simple array for the UI.
                $budgetItems = array_values(array_map(function ($row) {
                    if (! is_array($row)) {
                        return null;
                    }

                    return [
                        'item' => $row['item'] ?? null,
                        'description_purpose' => $row['description_purpose'] ?? null,
                        'amount' => $row['amount'] ?? null,
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

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $query = Document::with(['user', 'period'])
            ->ofType(Document::TYPE_WEEKLY_REPORT);

        // Users with 'view all documents' permission can see all documents, others see only their own
        if (! $user->hasPermissionTo('view all documents')) {
            $query->forUser($user->id);
        }

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

        $documents = $query->latest()->paginate(15)->withQueryString();
        $returnedDocumentMap = $this->getReturnedDocumentMapForIds(
            $documents->getCollection()->pluck('id')->all()
        );

        $nextPeriod = $this->workPeriodService->periodForWeeklyPlanSubmission();
        $deadlineStatuses = $this->workPeriodService->deadlineStatusesForUi($currentPeriod, $nextPeriod);

        $availableTypes = Document::getAvailableTypesForUser($user);

        return view('documents.weekly-reports', [
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

        $query = Document::query()
            ->with(['user', 'period', 'budgetReviewedBy'])
            ->weeklyPlans()
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
        }

        if ($request->filled('year')) {
            $year = (int) $request->input('year');
            $query->whereHas('period', function ($periodQuery) use ($year) {
                $periodQuery->where('year', $year);
            });
        }

        $statusFilter = $request->input('status');
        if ($statusFilter === Document::BUDGET_REVIEW_PENDING) {
            $query->where(function ($statusQuery) {
                $statusQuery
                    ->whereNull('budget_review_status')
                    ->orWhere('budget_review_status', Document::BUDGET_REVIEW_PENDING);
            });
        } elseif (in_array($statusFilter, [Document::BUDGET_REVIEW_APPROVED, Document::BUDGET_REVIEW_REJECTED], true)) {
            $query->where('budget_review_status', $statusFilter);
        }

        $documents = $query->latest()->paginate(15)->withQueryString();
        $documents->setCollection(
            $documents->getCollection()->filter(fn (Document $document) => $document->hasBudgetItems())->values()
        );

        $statsDocuments = (clone $query)
            ->get()
            ->filter(fn (Document $document) => $document->hasBudgetItems());

        $stats = [
            'total' => $statsDocuments->count(),
            'pending' => $statsDocuments->filter(fn (Document $document) => in_array($document->budget_review_status, [null, Document::BUDGET_REVIEW_PENDING], true))->count(),
            'approved' => $statsDocuments->where('budget_review_status', Document::BUDGET_REVIEW_APPROVED)->count(),
            'rejected' => $statsDocuments->where('budget_review_status', Document::BUDGET_REVIEW_REJECTED)->count(),
            'requested_total' => $statsDocuments->sum(fn (Document $document) => $this->requestedBudgetTotal($document)),
            'provided_total' => $statsDocuments->sum(fn (Document $document) => (float) ($document->budget_provided_amount ?? 0)),
        ];

        $usersWithBudgets = User::query()
            ->whereHas('documents', function ($documentsQuery) {
                $documentsQuery
                    ->weeklyPlans()
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

        $years = WorkPeriod::select('year')->distinct()->orderBy('year', 'desc')->pluck('year');

        return view('documents.weekly-budget-review', compact(
            'documents',
            'stats',
            'usersWithBudgets',
            'periodOptions',
            'years',
            'statusFilter'
        ));
    }

    /**
     * Approve or reject weekly plan budget.
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
            'decision' => ['required', 'in:approved,rejected'],
            'provided_amount' => ['nullable', 'numeric', 'min:0', 'required_if:decision,approved'],
            'note' => ['nullable', 'string', 'max:2000', 'required_if:decision,rejected'],
        ]);

        $decision = $validated['decision'];
        $providedAmount = $decision === Document::BUDGET_REVIEW_APPROVED
            ? (float) $validated['provided_amount']
            : 0.0;
        $note = isset($validated['note']) && trim($validated['note']) !== '' ? trim($validated['note']) : null;

        $document->update([
            'budget_review_status' => $decision,
            'budget_reviewed_by' => $user->id,
            'budget_reviewed_at' => now(),
            'budget_provided_amount' => $providedAmount,
            'budget_review_note' => $note,
        ]);

        return redirect()
            ->back()
            ->with('success', 'Weekly plan budget has been reviewed successfully.');
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

    private function budgetReviewDefaultsForWeeklyPlan(?array $content): array
    {
        if (! $this->templateHasBudgetRows($content)) {
            return [
                'budget_review_status' => null,
                'budget_reviewed_by' => null,
                'budget_reviewed_at' => null,
                'budget_provided_amount' => null,
                'budget_review_note' => null,
            ];
        }

        return [
            'budget_review_status' => Document::BUDGET_REVIEW_PENDING,
            'budget_reviewed_by' => null,
            'budget_reviewed_at' => null,
            'budget_provided_amount' => null,
            'budget_review_note' => null,
        ];
    }

    private const MONTHLY_IMPLEMENTATION_STATUSES = [
        'Not started',
        'In progress',
        'Completed',
        'Deferred',
        'Not applicable',
    ];

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

            $content['key_meetings'] = $this->filterNonEmptyRows(
                $content['key_meetings'] ?? [],
                ['date', 'meeting', 'participants', 'location', 'reactions_notes']
            );

            $content['budget'] = $this->filterNonEmptyRows(
                $content['budget'] ?? [],
                ['item', 'description_purpose', 'amount']
            );
        }

        if ($type === Document::TYPE_WEEKLY_REPORT) {
            $content['key_achievements'] = array_values(array_filter(
                is_array($content['key_achievements'] ?? null) ? $content['key_achievements'] : [],
                fn ($item) => is_string($item) && $item !== ''
            ));

            $content['activity_progress'] = $this->filterNonEmptyRows(
                $content['activity_progress'] ?? [],
                ['activity_output', 'progress', 'remarks_challenges', 'next_step']
            );

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
