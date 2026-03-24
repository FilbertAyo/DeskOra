<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Vinkla\Hashids\Facades\Hashids;

class DocumentController extends Controller
{
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
        if (!$user->hasPermissionTo('view all documents')) {
            $query->forUser($user->id);
        }

        // Get current period for defaults
        $currentPeriod = WorkPeriod::getCurrent();
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
            $query->whereHas('period', function($q) use ($year) {
                $q->where('year', $year);
            });
        }

        if ($request->input('review_status') === 'returned') {
            $query->whereIn('type', [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT])
                ->where('state', Document::STATE_DRAFT)
                ->whereHas('comments', function ($commentQuery) {
                    $commentQuery->where('comment', 'like', Document::REVIEW_REJECTION_PREFIX . '%');
                });
        }

        $documents = $query->latest()->paginate(15)->withQueryString();
        $returnedDocumentMap = $this->getReturnedDocumentMapForIds(
            $documents->getCollection()->pluck('id')->all()
        );

        $nextPeriod = $currentPeriod ? $currentPeriod->getNextPeriod() : null;
        $deadlineStatuses = null;
        if ($currentPeriod) {
            $deadlineStatuses = [
                'plan_badge' => $currentPeriod->getPlanDeadlineBadgeClass(),
                'plan_text' => $currentPeriod->getDeadlineStatusText('plan'),
                'plan_due' => $currentPeriod->plan_deadline,
                'report_badge' => $currentPeriod->getReportDeadlineBadgeClass(),
                'report_text' => $currentPeriod->getDeadlineStatusText('report'),
                'report_due' => $currentPeriod->report_deadline,
            ];
        }

        return view('documents.index', [
            'documents' => $documents,
            'periods' => WorkPeriod::orderBy('year', 'desc')->orderBy('week_number', 'desc')->get(),
            'years' => WorkPeriod::select('year')->distinct()->orderBy('year', 'desc')->pluck('year'),
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
        $currentPeriod = WorkPeriod::getCurrent();
        $nextPeriod = $currentPeriod ? $currentPeriod->getNextPeriod() : null;
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

        // If type is provided in query string, pre-select it
        if ($selectedType && isset($availableTypes[$selectedType])) {
            // Validate that user can create this type
            if (Document::canUserCreateType($user, $selectedType)) {
                $teamUsers = User::select('id', 'name')->orderBy('name')->get();
                return view('documents.create', compact('availableTypes', 'selectedType', 'workPeriods', 'defaultPeriodId', 'currentPeriod', 'nextPeriod', 'relatedPlan', 'teamUsers'));
            }
        }

        $teamUsers = User::select('id', 'name')->orderBy('name')->get();
        return view('documents.create', compact('availableTypes', 'workPeriods', 'defaultPeriodId', 'currentPeriod', 'nextPeriod', 'relatedPlan', 'teamUsers'));
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
            // monthly_report still uses a PDF upload.
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'], // Max 10MB
            'title' => ['nullable', 'string', 'max:255'],
        ];

        // Make period_id required for weekly plans and reports
        if (in_array($request->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT])) {
            $validationRules['period_id'] = ['required', 'integer', 'exists:work_periods,id'];
        } else {
            $validationRules['period_id'] = ['nullable', 'integer', 'exists:work_periods,id'];
        }

        // Add validation for monthly reports
        if ($request->type === Document::TYPE_MONTHLY_REPORT) {
            $validationRules['month'] = ['required', 'integer', 'min:1', 'max:12'];
            $validationRules['year'] = ['required', 'integer', 'min:2000', 'max:2100'];
            $validationRules['attachment'] = ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xlsx,xls', 'max:10240']; // Max 10MB

            // monthly_report requires a PDF.
            $validationRules['file'] = ['required', 'file', 'mimes:pdf', 'max:10240']; // Max 10MB
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
            $validationRules['content.planned_activities.*.team_member_ids.*'] = ['nullable', 'integer', 'exists:users,id'];
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

        // Normalize and trim template payload to avoid storing empty rows.
        $templateContent = null;
        if (in_array($request->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT]) && $request->has('content') && is_array($request->input('content'))) {
            $templateContent = $this->normalizeTemplateContent($request->input('content'), $request->type);
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
            if (!$period) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Selected work period not found.');
            }
        } elseif (in_array($request->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT])) {
            // Fallback to automatic selection if no period_id provided
            if ($request->type === Document::TYPE_WEEKLY_PLAN) {
                // Plan is for NEXT week (submitted during current week)
                $currentPeriod = WorkPeriod::getCurrent();
                if ($currentPeriod) {
                    $period = $currentPeriod->getNextPeriod();
                    if (!$period) {
                        return redirect()->back()
                            ->withInput()
                            ->with('error', 'Next week period not found. Please contact administrator.');
                    }
                } else {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'No current work period found. Please contact administrator.');
                }
            } elseif ($request->type === Document::TYPE_WEEKLY_REPORT) {
                // Report is for CURRENT week
                $period = WorkPeriod::getCurrent();
                if (!$period) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'No current work period found. Please contact administrator.');
                }
            }
        } elseif ($request->type === Document::TYPE_MONTHLY_REPORT) {
            // Monthly report uses current period
            $period = WorkPeriod::getCurrent();
        }
        if ($period && !$period->isOpen()) {
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

        // Determine state based on action
        $action = $request->input('action', 'draft');
        // Ensure action is either 'submit' or 'draft'
        $action = in_array($action, ['submit', 'draft']) ? $action : 'draft';
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
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('documents/' . $request->type, $filename, 'public');
        }

        // Handle attachment upload for monthly reports
        $attachmentPath = null;
        if ($request->type === Document::TYPE_MONTHLY_REPORT && $request->hasFile('attachment')) {
            $attachmentFile = $request->file('attachment');
            $attachmentFilename = time() . '_' . uniqid() . '_attachment.' . $attachmentFile->getClientOriginalExtension();
            $attachmentPath = $attachmentFile->storeAs('documents/monthly_reports/attachments', $attachmentFilename, 'public');
        }

        // Generate title if not provided
        $title = $request->input('title');
        if (!$title) {
            $typeName = Document::getTypes()[$request->type] ?? ucwords(str_replace('_', ' ', $request->type));
            if ($period) {
                $title = $typeName . ' - Week ' . $period->week_number;
            } elseif ($request->type === Document::TYPE_MONTHLY_REPORT) {
                $monthName = \Carbon\Carbon::create($request->year, $request->month, 1)->format('F');
                $title = $typeName . ' - ' . $monthName . ' ' . $request->year;
            } else {
                $title = $typeName . ' - ' . now()->format('M d, Y');
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
                
                return redirect()->route('documents.show', Hashids::encode($existingDocument->id))
                    ->with('info', 'A ' . str_replace('_', ' ', $request->type) . ' already exists for this week period. You can view it below.');
            }

            // Check if we can update (authorize update action - only works for drafts)
            try {
                $this->authorize('update', $existingDocument);
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                // Delete the uploaded file since we won't use it
                if ($filePath) {
                    Storage::disk('public')->delete($filePath);
                }
                
                return redirect()->route('documents.show', Hashids::encode($existingDocument->id))
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
                    
                    return redirect()->route('documents.show', Hashids::encode($existingDocument->id))
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

            // Save template content when provided (weekly_plan/weekly_report)
            if ($templateContent !== null) {
                $updateData['content'] = $templateContent;
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
                $updateData['target_accomplished'] = $request->has('target_accomplished') ? (bool)$request->target_accomplished : null;
                $updateData['target_explanation'] = $request->filled('target_explanation') ? $request->target_explanation : null;
            }

            // Update state if submitting
            if ($action === 'submit') {
                $updateData['state'] = Document::STATE_SUBMITTED;
            }

            $existingDocument->update($updateData);

            $message = ($action === 'submit')
                ? 'Document updated and submitted successfully.'
                : 'Document updated successfully.';

            return redirect()->route('documents.show', Hashids::encode($existingDocument->id))
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
            $documentData['target_accomplished'] = $request->has('target_accomplished') ? (bool)$request->target_accomplished : null;
            $documentData['target_explanation'] = $request->filled('target_explanation') ? $request->target_explanation : null;
        }

        // Save template content when provided (weekly_plan/weekly_report)
        if ($templateContent !== null) {
            $documentData['content'] = $templateContent;
        }

        $document = Document::create($documentData);

        $message = ($action === 'submit')
            ? 'Document submitted successfully.'
            : 'Document saved as draft.';

        return redirect()->route('documents.show', Hashids::encode($document->id))
            ->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $decodedId = Hashids::decode($id);
        $document = Document::with(['user', 'period', 'comments.user'])
            ->findOrFail($decodedId[0] ?? null);

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

            if (!$relatedDocument) {
                /** @var \App\Models\User $user */
                $user = Auth::user();
                if ($document->type === Document::TYPE_WEEKLY_PLAN &&
                    Document::canUserCreateType($user, Document::TYPE_WEEKLY_REPORT, $document->period)) {
                    $relatedAction = [
                        'type' => Document::TYPE_WEEKLY_REPORT,
                        'label' => 'Create Weekly Report for this Week',
                        'route' => route('documents.create', ['type' => Document::TYPE_WEEKLY_REPORT]),
                        'description' => 'Weekly report is due on ' . $document->period->report_deadline->format('M d, Y'),
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
    public function edit(string $id)
    {
        $decodedId = Hashids::decode($id);
        $document = Document::findOrFail($decodedId[0] ?? null);

        // Authorize update action
        $this->authorize('update', $document);

        $teamUsers = User::select('id', 'name')->orderBy('name')->get();
        return view('documents.edit', compact('document', 'teamUsers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $decodedId = Hashids::decode($id);
        $document = Document::findOrFail($decodedId[0] ?? null);

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
            $validationRules['content.planned_activities.*.team_member_ids.*'] = ['nullable', 'integer', 'exists:users,id'];
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

        $templateContent = null;
        if ($request->has('content') && is_array($request->input('content')) && in_array($document->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT])) {
            $templateContent = $this->normalizeTemplateContent($request->input('content'), $document->type);
        }

        // Determine state based on action
        $action = $request->input('action', 'draft');

        // If submitting, authorize submit action
        if ($action === 'submit') {
            $this->authorize('submit', $document);
        }

        $updateData = [];

        // Handle file upload if provided
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $originalFilename = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            
            // Generate unique filename
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('documents/' . $document->type, $filename, 'public');

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
            $attachmentFilename = time() . '_' . uniqid() . '_attachment.' . $attachmentFile->getClientOriginalExtension();
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

        // Update month and year for monthly reports
        if ($document->type === Document::TYPE_MONTHLY_REPORT) {
            if ($request->filled('month')) {
                $updateData['month'] = $request->month;
            }
            if ($request->filled('year')) {
                $updateData['year'] = $request->year;
            }
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
                $updateData['target_accomplished'] = (bool)$request->target_accomplished;
            }
            if ($request->filled('target_explanation')) {
                $updateData['target_explanation'] = $request->target_explanation;
            } elseif ($request->has('target_explanation')) {
                // Allow clearing the field
                $updateData['target_explanation'] = null;
            }
        }

        // Save template content when provided (weekly_plan/weekly_report)
        if ($templateContent !== null) {
            $updateData['content'] = $templateContent;
        }

        // Update state if submitting
        if ($action === 'submit') {
            $updateData['state'] = Document::STATE_SUBMITTED;
        }

        $document->update($updateData);

        $message = ($action === 'submit')
            ? 'Document submitted successfully.'
            : 'Document updated successfully.';

        return redirect()->route('documents.show', $id)
            ->with('success', $message);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $decodedId = Hashids::decode($id);
        $document = Document::findOrFail($decodedId[0] ?? null);

        // Authorize delete action
        $this->authorize('delete', $document);

        $document->delete();

        return redirect()->route('documents.index')
            ->with('success', 'Document deleted successfully.');
    }

    /**
     * Submit a document (change state from draft to submitted)
     */
    public function submit(Request $request, string $id)
    {
        $decodedId = Hashids::decode($id);
        $document = Document::findOrFail($decodedId[0] ?? null);

        // Authorize submit action
        $this->authorize('submit', $document);

        $document->submit();

        return redirect()->route('documents.show', $id)
            ->with('success', 'Document submitted successfully.');
    }

    /**
     * Return submitted weekly plan/report to draft with required review reason.
     */
    public function reject(Request $request, string $id)
    {
        $decodedId = Hashids::decode($id);
        $document = Document::findOrFail($decodedId[0] ?? null);

        $this->authorize('reject', $document);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $reason = trim($validated['reason']);

        $document->revertToDraft();

        Comment::create([
            'document_id' => $document->id,
            'user_id' => Auth::id(),
            'comment' => Document::REVIEW_REJECTION_PREFIX . $reason,
        ]);

        return redirect()->route('documents.show', $id)
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
        if (!$user->hasPermissionTo('view all documents')) {
            abort(403, 'Unauthorized access.');
        }

        $baseQuery = Document::with(['user', 'comments'])
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
                    now()->endOfWeek()
                ]);
            } elseif ($period === 'last_week') {
                $baseQuery->whereBetween('updated_at', [
                    now()->subWeek()->startOfWeek(),
                    now()->subWeek()->endOfWeek()
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
            $baseQuery->whereHas('period', function($q) use ($request) {
                $q->where('year', $request->year);
            });
        }

        $allDocuments = (clone $baseQuery)->latest()->get();

        $mergedWeeklyRows = $allDocuments
            ->whereIn('type', [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT])
            ->groupBy(fn ($doc) => ($doc->period_id ?? 'none') . '_' . ($doc->user_id ?? 'none'))
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

        $usersWithDocuments = \App\Models\User::whereHas('documents', function($q) {
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
            'create action logs'
        ]);
        
        if (!$canCreateDocs && !$user->hasPermissionTo('approve documents')) {
            abort(403, 'Unauthorized access. This dashboard is for document creators only.');
        }

        $availableTypes = Document::getAvailableTypesForUser($user);
        $currentPeriod = WorkPeriod::getCurrent();
        $nextPeriod = $currentPeriod ? $currentPeriod->getNextPeriod() : null;

        $periodFilterId = $request->input('period_id');
        $periodOptions = WorkPeriod::orderBy('year', 'desc')
            ->orderBy('week_number', 'desc')
            ->take(20)
            ->get();

        $deadlineStatuses = null;
        if ($currentPeriod) {
            $deadlineStatuses = [
                'plan_badge' => $currentPeriod->getPlanDeadlineBadgeClass(),
                'plan_text' => $currentPeriod->getDeadlineStatusText('plan'),
                'plan_due' => $currentPeriod->plan_deadline,
                'report_badge' => $currentPeriod->getReportDeadlineBadgeClass(),
                'report_text' => $currentPeriod->getDeadlineStatusText('report'),
                'report_due' => $currentPeriod->report_deadline,
            ];
        }

        // Get pending submissions (draft documents)
        $pendingDocumentsQuery = Document::with('comments')
            ->forUser($user->id)
            ->drafts()
            ->latest();

        $allDocumentsQuery = Document::with('comments')
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
        if (!$user->hasPermissionTo('view all documents')) {
            $query->forUser($user->id);
        }

        // Get current period for defaults
        $currentPeriod = WorkPeriod::getCurrent();
        $currentYear = date('Y');

        // Filter by period - only filter if explicitly provided (not empty)
        $periodId = $request->input('period_id');
        if ($periodId && $periodId !== '') {
            $query->forPeriod($periodId);
        }

        // Filter by year - only filter if explicitly provided (not empty)
        $year = $request->input('year');
        if ($year && $year !== '') {
            $query->whereHas('period', function($q) use ($year) {
                $q->where('year', $year);
            });
        }

        if ($request->input('review_status') === 'returned') {
            $query->where('state', Document::STATE_DRAFT)
                ->whereHas('comments', function ($commentQuery) {
                    $commentQuery->where('comment', 'like', Document::REVIEW_REJECTION_PREFIX . '%');
                });
        }

        $documents = $query->latest()->paginate(15)->withQueryString();
        $returnedDocumentMap = $this->getReturnedDocumentMapForIds(
            $documents->getCollection()->pluck('id')->all()
        );

        $nextPeriod = $currentPeriod ? $currentPeriod->getNextPeriod() : null;
        $deadlineStatuses = null;
        if ($currentPeriod) {
            $deadlineStatuses = [
                'plan_badge' => $currentPeriod->getPlanDeadlineBadgeClass(),
                'plan_text' => $currentPeriod->getDeadlineStatusText('plan'),
                'plan_due' => $currentPeriod->plan_deadline,
                'report_badge' => $currentPeriod->getReportDeadlineBadgeClass(),
                'report_text' => $currentPeriod->getDeadlineStatusText('report'),
                'report_due' => $currentPeriod->report_deadline,
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

        if (!$periodId) {
            return response()->json(['error' => 'Period ID is required'], 400);
        }

        $plan = Document::where('user_id', $user->id)
            ->where('period_id', $periodId)
            ->where('type', Document::TYPE_WEEKLY_PLAN)
            ->orderByRaw("CASE WHEN state = ? THEN 0 ELSE 1 END", [Document::STATE_SUBMITTED])
            ->latest('id')
            ->first();

        if ($plan) {
            $budgetItems = [];
            if (is_array($plan->content) && !empty($plan->content['budget']) && is_array($plan->content['budget'])) {
                // Normalize into a simple array for the UI.
                $budgetItems = array_values(array_map(function ($row) {
                    if (!is_array($row)) return null;
                    return [
                        'item' => $row['item'] ?? null,
                        'description_purpose' => $row['description_purpose'] ?? null,
                        'amount' => $row['amount'] ?? null,
                    ];
                }, $plan->content['budget']));
                // Remove empty rows (e.g., all fields blank)
                $budgetItems = array_values(array_filter($budgetItems, function ($row) {
                    if (!is_array($row)) return false;
                    return !empty($row['item']) || !empty($row['description_purpose']) || !empty($row['amount']);
                }));
            }

            $plannedActivities = [];
            if (is_array($plan->content) && !empty($plan->content['planned_activities']) && is_array($plan->content['planned_activities'])) {
                $plannedActivities = array_values(array_filter(array_map(function ($row) {
                    if (!is_array($row)) {
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
                        'label' => $day !== '' ? ($day . ' - ' . $activity) : $activity,
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
        if (!$user->hasPermissionTo('view all documents')) {
            $query->forUser($user->id);
        }

        // Get current period for defaults
        $currentPeriod = WorkPeriod::getCurrent();
        $currentYear = date('Y');

        // Filter by period - only filter if explicitly provided (not empty)
        $periodId = $request->input('period_id');
        if ($periodId && $periodId !== '') {
            $query->forPeriod($periodId);
        }

        // Filter by year - only filter if explicitly provided (not empty)
        $year = $request->input('year');
        if ($year && $year !== '') {
            $query->whereHas('period', function($q) use ($year) {
                $q->where('year', $year);
            });
        }

        if ($request->input('review_status') === 'returned') {
            $query->where('state', Document::STATE_DRAFT)
                ->whereHas('comments', function ($commentQuery) {
                    $commentQuery->where('comment', 'like', Document::REVIEW_REJECTION_PREFIX . '%');
                });
        }

        $documents = $query->latest()->paginate(15)->withQueryString();
        $returnedDocumentMap = $this->getReturnedDocumentMapForIds(
            $documents->getCollection()->pluck('id')->all()
        );

        $nextPeriod = $currentPeriod ? $currentPeriod->getNextPeriod() : null;
        $deadlineStatuses = null;
        if ($currentPeriod) {
            $deadlineStatuses = [
                'plan_badge' => $currentPeriod->getPlanDeadlineBadgeClass(),
                'plan_text' => $currentPeriod->getDeadlineStatusText('plan'),
                'plan_due' => $currentPeriod->plan_deadline,
                'report_badge' => $currentPeriod->getReportDeadlineBadgeClass(),
                'report_text' => $currentPeriod->getDeadlineStatusText('report'),
                'report_due' => $currentPeriod->report_deadline,
            ];
        }

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
     * Build document_id => latest rejection reason map for visible documents.
     */
    private function getReturnedDocumentMapForIds(array $documentIds): array
    {
        if (empty($documentIds)) {
            return [];
        }

        $latestByDocument = Comment::whereIn('document_id', $documentIds)
            ->where('comment', 'like', Document::REVIEW_REJECTION_PREFIX . '%')
            ->orderByDesc('id')
            ->get()
            ->unique('document_id');

        $map = [];
        foreach ($latestByDocument as $comment) {
            $reason = trim(substr((string) $comment->comment, strlen(Document::REVIEW_REJECTION_PREFIX)));
            $map[(int) $comment->document_id] = $reason;
        }

        return $map;
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
        if (!is_array($rows)) return [];

        return array_values(array_filter($rows, function ($row) use ($keys) {
            if (!is_array($row)) return false;

            foreach ($keys as $key) {
                if (!array_key_exists($key, $row)) continue;
                $v = $row[$key];
                if (is_array($v)) {
                    if (!empty($v)) return true;
                    continue;
                }
                if ($v !== null && $v !== '') return true;
            }

            return false;
        }));
    }
}
