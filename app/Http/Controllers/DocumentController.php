<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\WorkPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Vinkla\Hashids\Facades\Hashids;

class DocumentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Document::class);

        $user = Auth::user();
        $query = Document::with(['user', 'period']);

        // Reviewers and admins can see all documents, others see only their own
        if (!$user->hasAnyRole(['reviewer', 'admin', 'verifier', 'approver'])) {
            $query->forUser($user->id);
        }

        // Get current period for defaults
        $currentPeriod = WorkPeriod::getCurrent();
        $currentYear = date('Y');

        // Filter by type if provided (default: all types)
        if ($request->has('type') && $request->type) {
            $query->ofType($request->type);
        }

        // Filter by period - default to current period if not specified
        $periodId = $request->input('period_id');
        if (!$periodId && $currentPeriod) {
            $periodId = $currentPeriod->id;
        }
        if ($periodId) {
            $query->forPeriod($periodId);
        }

        // Filter by year - default to current year if not specified
        $year = $request->input('year');
        if (!$year) {
            $year = $currentYear;
        }
        if ($year) {
            $query->whereHas('period', function($q) use ($year) {
                $q->where('year', $year);
            });
        }

        $documents = $query->latest()->paginate(15)->withQueryString();

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
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
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
                return view('documents.create', compact('availableTypes', 'selectedType', 'workPeriods', 'defaultPeriodId', 'currentPeriod', 'nextPeriod', 'relatedPlan'));
            }
        }

        return view('documents.create', compact('availableTypes', 'workPeriods', 'defaultPeriodId', 'currentPeriod', 'nextPeriod', 'relatedPlan'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // Validate file upload for PDF documents
        $validationRules = [
            'type' => ['required', 'string', 'in:weekly_plan,weekly_report,monthly_report,weekly_minutes'],
            'file' => ['required', 'file', 'mimes:pdf', 'max:10240'], // Max 10MB
            'title' => ['nullable', 'string', 'max:255'],
        ];

        // Make period_id required for weekly plans and reports
        if (in_array($request->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT])) {
            $validationRules['period_id'] = ['required', 'integer', 'exists:work_periods,id'];
        } else {
            $validationRules['period_id'] = ['nullable', 'integer', 'exists:work_periods,id'];
        }

        // Add validation for main_target for weekly plans
        if ($request->type === Document::TYPE_WEEKLY_PLAN) {
            $validationRules['main_target'] = ['required', 'string', 'max:2000'];
        }

        // Add validation for target accomplishment for weekly reports
        if ($request->type === Document::TYPE_WEEKLY_REPORT) {
            $validationRules['target_accomplished'] = ['nullable', 'boolean'];
            $validationRules['target_explanation'] = ['nullable', 'string', 'max:2000'];
        }

        $request->validate($validationRules);

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
        // weekly_minutes doesn't require a period (can be null)

        if ($period && !$period->isOpen()) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'This work period is closed. Please contact your administrator.');
        }

        // Check if document already exists for this user, period, and type
        // Only for weekly plans and reports (one per week period)
        $existingDocument = null;
        if (in_array($request->type, [Document::TYPE_WEEKLY_PLAN, Document::TYPE_WEEKLY_REPORT]) && $period) {
            $existingDocument = Document::where('user_id', $user->id)
                ->where('period_id', $period->id)
                ->where('type', $request->type)
                ->first();
        }

        // Determine state based on action
        $action = $request->input('action', 'draft');
        $state = ($action === 'submit') ? Document::STATE_SUBMITTED : Document::STATE_DRAFT;

        // Handle file upload
        $file = $request->file('file');
        $originalFilename = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        
        // Generate unique filename
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $filePath = $file->storeAs('documents/' . $request->type, $filename, 'public');

        // Generate title if not provided
        $title = $request->input('title');
        if (!$title) {
            $typeName = Document::getTypes()[$request->type] ?? ucwords(str_replace('_', ' ', $request->type));
            if ($period) {
                $title = $typeName . ' - Week ' . $period->week_number;
            } else {
                $title = $typeName . ' - ' . now()->format('M d, Y');
            }
        }

        // If document exists, update it instead of creating a new one
        if ($existingDocument) {
            // If document is submitted, we can't update it (policy restriction)
            if ($existingDocument->isSubmitted()) {
                // Delete the uploaded file since we won't use it
                \Storage::disk('public')->delete($filePath);
                
                return redirect()->route('documents.show', Hashids::encode($existingDocument->id))
                    ->with('info', 'A ' . str_replace('_', ' ', $request->type) . ' already exists for this week period. You can view it below.');
            }

            // Check if we can update (authorize update action - only works for drafts)
            try {
                $this->authorize('update', $existingDocument);
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                // Delete the uploaded file since we won't use it
                \Storage::disk('public')->delete($filePath);
                
                return redirect()->route('documents.show', Hashids::encode($existingDocument->id))
                    ->with('error', 'You do not have permission to update this document.');
            }

            // If submitting, authorize submit action
            if ($action === 'submit') {
                try {
                    $this->authorize('submit', $existingDocument);
                } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                    // Delete the uploaded file since we won't use it
                    \Storage::disk('public')->delete($filePath);
                    
                    return redirect()->route('documents.show', Hashids::encode($existingDocument->id))
                        ->with('error', 'You do not have permission to submit this document.');
                }
            }

            // Delete old file if exists
            if ($existingDocument->file_path) {
                \Storage::disk('public')->delete($existingDocument->file_path);
            }

            // Update document
            $updateData = [
                'file_path' => $filePath,
                'title' => $title,
                'file_size' => $fileSize,
                'original_filename' => $originalFilename,
            ];

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

        // Add main_target for weekly plans
        if ($request->type === Document::TYPE_WEEKLY_PLAN && $request->filled('main_target')) {
            $documentData['main_target'] = $request->main_target;
        }

        // Add target accomplishment fields for weekly reports
        if ($request->type === Document::TYPE_WEEKLY_REPORT) {
            $documentData['target_accomplished'] = $request->has('target_accomplished') ? (bool)$request->target_accomplished : null;
            $documentData['target_explanation'] = $request->filled('target_explanation') ? $request->target_explanation : null;
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

        return view('documents.edit', compact('document'));
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
        ];

        // Add validation for main_target for weekly plans
        if ($document->type === Document::TYPE_WEEKLY_PLAN) {
            $validationRules['main_target'] = ['nullable', 'string', 'max:2000'];
        }

        // Add validation for target accomplishment for weekly reports
        if ($document->type === Document::TYPE_WEEKLY_REPORT) {
            $validationRules['target_accomplished'] = ['nullable', 'boolean'];
            $validationRules['target_explanation'] = ['nullable', 'string', 'max:2000'];
        }

        $request->validate($validationRules);

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
                \Storage::disk('public')->delete($document->file_path);
            }

            $updateData['file_path'] = $filePath;
            $updateData['file_size'] = $fileSize;
            $updateData['original_filename'] = $originalFilename;
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
                $updateData['target_accomplished'] = (bool)$request->target_accomplished;
            }
            if ($request->filled('target_explanation')) {
                $updateData['target_explanation'] = $request->target_explanation;
            } elseif ($request->has('target_explanation')) {
                // Allow clearing the field
                $updateData['target_explanation'] = null;
            }
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
     * Reviewer dashboard - Show all submitted documents grouped by type
     */
    public function reviewerDashboard(Request $request)
    {
        $user = Auth::user();

        // Only reviewers and admins can access this
        if (!$user->hasAnyRole(['reviewer', 'admin'])) {
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

        // Group documents by type
        $groupedDocuments = [
            'weekly_plan' => $allDocuments->where('type', Document::TYPE_WEEKLY_PLAN),
            'weekly_report' => $allDocuments->where('type', Document::TYPE_WEEKLY_REPORT),
            'monthly_report' => $allDocuments->where('type', Document::TYPE_MONTHLY_REPORT),
            'weekly_minutes' => $allDocuments->where('type', Document::TYPE_WEEKLY_MINUTES),
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
            'monthly_reports' => (clone $statsQuery)->monthlyReports()->count(),
            'weekly_minutes' => (clone $statsQuery)->weeklyMinutes()->count(),
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
        $user = Auth::user();

        // Allow employees and admins (admins can see their own dashboard too)
        // Restrict reviewers and minutes_preparer (unless they're also admin)
        if (!$user->hasRole('admin') && $user->hasAnyRole(['reviewer', 'minutes_preparer'])) {
            abort(403, 'Unauthorized access. This dashboard is for employees only.');
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

        $user = Auth::user();
        $query = Document::with(['user', 'period'])
            ->ofType(Document::TYPE_WEEKLY_PLAN);

        // Reviewers and admins can see all documents, others see only their own
        if (!$user->hasAnyRole(['reviewer', 'admin', 'verifier', 'approver'])) {
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

        $documents = $query->latest()->paginate(15)->withQueryString();

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
            ->first();

        if ($plan && $plan->main_target) {
            return response()->json([
                'main_target' => $plan->main_target,
                'plan_exists' => true,
            ]);
        }

        return response()->json([
            'main_target' => null,
            'plan_exists' => false,
        ]);
    }

    /**
     * Display weekly reports page
     */
    public function weeklyReports(Request $request)
    {
        $this->authorize('viewAny', Document::class);

        $user = Auth::user();
        $query = Document::with(['user', 'period'])
            ->ofType(Document::TYPE_WEEKLY_REPORT);

        // Reviewers and admins can see all documents, others see only their own
        if (!$user->hasAnyRole(['reviewer', 'admin', 'verifier', 'approver'])) {
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

        $documents = $query->latest()->paginate(15)->withQueryString();

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
        ]);
    }
}
