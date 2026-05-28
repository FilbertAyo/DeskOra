<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\ActionLogTask;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\Documents\DocumentDomainSyncService;
use App\Services\WorkflowEmailNotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ActionLogController extends Controller
{
    public function __construct(
        private readonly WorkflowEmailNotificationService $workflowEmailNotificationService,
        private readonly DocumentDomainSyncService $documentDomainSyncService,
    ) {}

    private const PROGRESS_NOT_STARTED = 'Not Started';

    private const PROGRESS_IN_PROGRESS = 'In Progress';

    private const PROGRESS_DONE = 'Done';

    private const PROGRESS_CANCELLED = 'Cancelled';

    private const PROGRESS_OPTIONS = [
        self::PROGRESS_NOT_STARTED,
        self::PROGRESS_IN_PROGRESS,
        self::PROGRESS_DONE,
        self::PROGRESS_CANCELLED,
    ];

    private const STAFF_PROGRESS_OPTIONS = [
        self::PROGRESS_NOT_STARTED,
        self::PROGRESS_IN_PROGRESS,
        self::PROGRESS_DONE,
    ];

    /**
     * Display a listing of action log documents.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $canViewAllActionLogs = $user->hasAnyPermission(['create action logs', 'manage action logs', 'view action logs', 'view all documents']);
        $query = Document::with(['user', 'period'])->actionLogs();

        // Filter by state if provided
        if ($request->has('state') && $request->state) {
            $query->ofState($request->state);
        }

        if ($canViewAllActionLogs) {
            $documents = $query->latest()->paginate(15)->withQueryString();
        } else {
            // Assigned staff can access only action logs where they are responsible for at least one task.
            $assignedDocs = $query->latest()->get()->filter(function (Document $document) use ($user) {
                return $this->isUserAssignedToActionLog($user, $document);
            })->values();

            $perPage = 15;
            $currentPage = (int) $request->input('page', 1);
            $offset = max(0, ($currentPage - 1) * $perPage);
            $pageItems = $assignedDocs->slice($offset, $perPage)->values();

            $documents = new \Illuminate\Pagination\LengthAwarePaginator(
                $pageItems,
                $assignedDocs->count(),
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            if ($documents->total() === 0) {
                abort(403, 'Unauthorized access. You are not assigned to any action log tasks.');
            }
        }

        return view('action-logs.index', compact('documents'));
    }

    /**
     * Show the form for creating a new action log document.
     */
    public function create()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $isManager = $this->isActionLogManager($user);

        if (! $isManager) {
            abort(403, 'Unauthorized access. Only users with action log permissions can create action logs.');
        }

        // Authorize create action for action_log type
        $this->authorize('createType', [Document::class, Document::TYPE_ACTION_LOG]);

        // Get all work periods for dropdown
        $workPeriods = WorkPeriod::orderBy('year', 'desc')
            ->orderBy('week_number', 'desc')
            ->get();

        // Action logs are tracked for current week.
        $currentPeriod = WorkPeriod::getCurrent();
        $defaultPeriodId = $currentPeriod ? $currentPeriod->id : null;

        // If shared action log already exists for current period, redirect to edit.
        if ($defaultPeriodId) {
            $existingCurrent = Document::where('period_id', $defaultPeriodId)
                ->where('type', Document::TYPE_ACTION_LOG)
                ->latest('id')
                ->first();

            if ($existingCurrent) {
                return redirect()->route('action-logs.edit', $existingCurrent)
                    ->with('info', 'Action log for this week already exists. You can continue updating it.');
            }
        }

        $carryForwardTasks = $currentPeriod ? $this->getCarryForwardTasks($currentPeriod) : [];
        $teamUsers = User::select('id', 'name')->orderBy('name')->get();

        return view('action-logs.create', compact('workPeriods', 'defaultPeriodId', 'currentPeriod', 'carryForwardTasks', 'teamUsers', 'isManager'));
    }

    /**
     * Store a newly created action log document.
     */
    public function store(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $isManager = $this->isActionLogManager($user);

        if (! $isManager) {
            abort(403, 'Unauthorized access. Only users with action log permissions can create action logs.');
        }

        // Authorize create action for action_log type
        $this->authorize('createType', [Document::class, Document::TYPE_ACTION_LOG]);

        $request->validate([
            'period_id' => ['required', 'string', 'ulid', 'exists:work_periods,id'],
            'content' => ['nullable', 'array'],
            'content.tasks' => ['nullable', 'array'],
            'content.tasks.*.task_no' => ['nullable', 'integer', 'min:1'],
            'content.tasks.*.task' => ['nullable', 'string', 'max:4000'],
            'content.tasks.*.responsible_user_ids' => ['nullable', 'array'],
            'content.tasks.*.responsible_user_ids.*' => ['nullable', 'string', 'ulid', 'exists:users,id'],
            'content.tasks.*.progress' => ['nullable', 'string', 'in:Not Started,In Progress,Done,Cancelled'],
            'content.tasks.*.due_date' => ['nullable', 'date'],
            'content.tasks.*.compilation_date' => ['nullable', 'date'],
            'content.tasks.*.notes_progress' => ['nullable', 'string', 'max:4000'],
            'content.tasks.*.status_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        // Get period
        $period = WorkPeriod::find($request->period_id);
        if (! $period) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Selected work period not found.');
        }

        // Check if period is open
        if (! $period->isOpen()) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'This work period is closed. Please contact your administrator.');
        }

        // Shared action log per week period.
        $existingDocument = Document::where('type', Document::TYPE_ACTION_LOG)
            ->where('period_id', $period->id)
            ->latest('id')
            ->first();
        $wasSubmitted = $existingDocument?->isSubmitted() ?? false;

        $title = $this->buildActionLogTitle($period);

        // Determine state based on action
        $action = $request->input('action', 'draft');
        $state = ($action === 'submit') ? Document::STATE_SUBMITTED : Document::STATE_DRAFT;
        $normalizedContent = $this->normalizeActionLogContent($request->input('content', []));

        if (empty($normalizedContent['tasks']) && ! $existingDocument) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Please add at least one action log task.');
        }

        // If document exists, merge new rows into the same weekly log.
        if ($existingDocument) {
            // If submitting, authorize submit action
            if ($action === 'submit' && ! $existingDocument->isSubmitted()) {
                $this->authorize('submit', $existingDocument);
            }

            $oldTasks = $this->extractTasks($existingDocument->content);
            $finalTasks = $this->mergeManagerStoreTasks(
                $oldTasks,
                $normalizedContent['tasks'] ?? [],
                $period,
                (string) $user->id
            );

            if (empty($finalTasks)) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Action log must contain at least one task.');
            }
            $cancelledReasonError = $this->validateCancelledTasks($finalTasks, true);
            if ($cancelledReasonError) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', $cancelledReasonError);
            }

            $nextState = $existingDocument->isSubmitted()
                ? Document::STATE_SUBMITTED
                : $state;

            $existingDocument->update([
                'title' => $title,
                'content' => ['tasks' => $this->renumberTasks($finalTasks)],
                'state' => $nextState,
            ]);
            $this->documentDomainSyncService->syncFromDocument($existingDocument);

            $document = $existingDocument;
        } else {
            $initialTasks = $this->initializeTaskHistoryEntries(
                $normalizedContent['tasks'] ?? [],
                $period,
                (string) $user->id
            );
            $cancelledReasonError = $this->validateCancelledTasks($initialTasks, true);
            if ($cancelledReasonError) {
                return redirect()->back()
                    ->withInput()
                    ->with('error', $cancelledReasonError);
            }
            // Create new document
            $document = Document::create([
                'user_id' => $user->id,
                'period_id' => $period->id,
                'type' => Document::TYPE_ACTION_LOG,
                'title' => $title,
                'content' => ['tasks' => $this->renumberTasks($initialTasks)],
                'state' => $state,
            ]);
            $this->documentDomainSyncService->syncFromDocument($document);
        }

        $message = ($action === 'submit')
            ? 'Action Log submitted successfully.'
            : 'Action Log saved as draft.';
        if ($action === 'submit' && ! $wasSubmitted) {
            $document->loadMissing('period');
            $this->workflowEmailNotificationService->notifyWeeklyActionLogSubmitted($document, $user);
        }

        return redirect()->route('action-logs.show', $document)
            ->with('success', $message);
    }

    /**
     * Display the specified action log document.
     */
    public function show(Document $document)
    {
        $document->load(['user', 'period']);

        // Ensure it's an action log document
        if ($document->type !== Document::TYPE_ACTION_LOG) {
            abort(404, 'Document not found.');
        }

        // Authorize view action
        $this->authorize('view', $document);

        $tasks = $document->getActionLogTasksForPeriodDisplay();

        return view('action-logs.show', compact('document', 'tasks'));
    }

    /**
     * Download the specified action log as PDF.
     */
    public function downloadPdf(Document $document)
    {
        $document->load(['user', 'period']);

        if ($document->type !== Document::TYPE_ACTION_LOG) {
            abort(404, 'Document not found.');
        }

        $this->authorize('view', $document);

        $tasks = $document->getActionLogTasksForPeriodDisplay();

        $responsibleIds = collect($tasks)
            ->filter(fn ($row) => is_array($row) && is_array($row['responsible_user_ids'] ?? null))
            ->flatMap(fn ($row) => $row['responsible_user_ids'])
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $responsibleNameMap = User::query()
            ->whereIn('id', $responsibleIds)
            ->pluck('name', 'id')
            ->toArray();

        $pdf = Pdf::loadView('action-logs.pdf', [
            'document' => $document,
            'tasks' => $tasks,
            'responsibleNameMap' => $responsibleNameMap,
        ]);

        $weekLabel = $document->period
            ? 'week_'.$document->period->week_number.'_'.$document->period->year
            : 'no_period';
        $filename = 'action_log_'.$weekLabel.'_'.$document->id.'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Show the form for editing the specified action log document.
     */
    public function edit(Document $document)
    {
        $document->load(['period']);

        // Ensure it's an action log document
        if ($document->type !== Document::TYPE_ACTION_LOG) {
            abort(404, 'Document not found.');
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $isManager = $this->isActionLogManager($user);
        $isAssigned = $this->isUserAssignedToActionLog($user, $document);

        if ($document->period && ! $document->period->isOpen()) {
            return redirect()->route('action-logs.show', $document)
                ->with('error', 'This action log belongs to a closed work period and cannot be edited.');
        }

        if (! $isManager && ! $isAssigned) {
            return redirect()->route('action-logs.show', $document)
                ->with('error', 'You can only edit tasks where you are assigned as responsible person.');
        }

        // Authorize update action
        $this->authorize('update', $document);

        // Get all work periods for dropdown
        $workPeriods = WorkPeriod::orderBy('year', 'desc')
            ->orderBy('week_number', 'desc')
            ->get();

        $teamUsers = User::select('id', 'name')->orderBy('name')->get();

        return view('action-logs.edit', compact('document', 'workPeriods', 'teamUsers', 'isManager'));
    }

    /**
     * Update the specified action log document.
     */
    public function update(Request $request, Document $document)
    {
        /** @var \App\Models\User $actingUser */
        $actingUser = Auth::user();

        // Ensure it's an action log document
        if ($document->type !== Document::TYPE_ACTION_LOG) {
            abort(404, 'Document not found.');
        }

        // Authorize update action
        $this->authorize('update', $document);

        // Keep period stable when the UI shows a disabled work-period selector.
        if (! $request->filled('period_id') && ! empty($document->period_id)) {
            $request->merge(['period_id' => (string) $document->period_id]);
        }

        $request->validate([
            'period_id' => ['required', 'string', 'ulid', 'exists:work_periods,id'],
            'content' => ['nullable', 'array'],
            'content.tasks' => ['nullable', 'array'],
            'content.tasks.*.task_uid' => ['nullable', 'string', 'max:80'],
            'content.tasks.*.task_no' => ['nullable', 'integer', 'min:1'],
            'content.tasks.*.task' => ['nullable', 'string', 'max:4000'],
            'content.tasks.*.responsible_user_ids' => ['nullable', 'array'],
            'content.tasks.*.responsible_user_ids.*' => ['nullable', 'string', 'ulid', 'exists:users,id'],
            'content.tasks.*.progress' => ['nullable', 'string', 'in:Not Started,In Progress,Done,Cancelled'],
            'content.tasks.*.due_date' => ['nullable', 'date'],
            'content.tasks.*.compilation_date' => ['nullable', 'date'],
            'content.tasks.*.notes_progress' => ['nullable', 'string', 'max:4000'],
            'content.tasks.*.status_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        // Get period
        $period = WorkPeriod::find($request->period_id);
        if (! $period) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Selected work period not found.');
        }

        // Check if period is open
        if (! $period->isOpen()) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'This work period is closed. Please contact your administrator.');
        }

        // Determine state based on action
        $action = $request->input('action', 'draft');
        $wasSubmitted = $document->isSubmitted();

        // If submitting, authorize submit action
        if ($action === 'submit' && ! $document->isSubmitted()) {
            $this->authorize('submit', $document);
        }

        $isManager = $this->isActionLogManager(Auth::user());
        $normalizedContent = $this->normalizeActionLogContent($request->input('content', []));
        $existingTasks = $this->extractTasks($document->content);
        $incomingTasks = $normalizedContent['tasks'] ?? [];

        // Non-managers can only update their assigned rows and limited columns.
        $finalTasks = $isManager
            ? $this->mergeManagerTaskUpdates($existingTasks, $incomingTasks, $period, (string) Auth::id())
            : $this->mergeResponsibleTaskUpdates($existingTasks, $incomingTasks, $period, (string) Auth::id());

        if (empty($finalTasks)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Action log must contain at least one task.');
        }
        $cancelledReasonError = $this->validateCancelledTasks($finalTasks, $isManager);
        if ($cancelledReasonError) {
            return redirect()->back()
                ->withInput()
                ->with('error', $cancelledReasonError);
        }

        $updateData = [
            'content' => ['tasks' => $this->renumberTasks($finalTasks)],
            'title' => $this->buildActionLogTitle($period),
        ];

        if ($isManager) {
            $updateData['period_id'] = $period->id;
        }

        // Update state if submitting
        if ($action === 'submit') {
            $updateData['state'] = Document::STATE_SUBMITTED;
        }

        $document->update($updateData);
        $this->documentDomainSyncService->syncFromDocument($document);

        $message = ($action === 'submit')
            ? 'Action Log submitted successfully.'
            : 'Action Log updated successfully.';
        if ($action === 'submit' && ! $wasSubmitted) {
            $document->loadMissing('period');
            $this->workflowEmailNotificationService->notifyWeeklyActionLogSubmitted($document, $actingUser);
        }

        return redirect()->route('action-logs.show', $document)
            ->with('success', $message);
    }

    /**
     * Remove the specified action log document.
     */
    public function destroy(Document $document)
    {

        // Ensure it's an action log document
        if ($document->type !== Document::TYPE_ACTION_LOG) {
            abort(404, 'Document not found.');
        }

        // Authorize delete action
        $this->authorize('delete', $document);

        $document->delete();

        return redirect()->route('action-logs.index')
            ->with('success', 'Action Log deleted successfully.');
    }

    private function isActionLogManager(User $user): bool
    {
        return $user->hasAnyPermission(['create action logs', 'manage action logs']);
    }

    private function getCarryForwardTasks(WorkPeriod $period): array
    {
        $previousPeriod = $period->getPreviousPeriod();
        if (! $previousPeriod) {
            return [];
        }

        $previousLog = Document::where('type', Document::TYPE_ACTION_LOG)
            ->where('period_id', $previousPeriod->id)
            ->orderByRaw('CASE WHEN state = ? THEN 0 ELSE 1 END', [Document::STATE_SUBMITTED])
            ->latest('id')
            ->first();

        if (! $previousLog) {
            return [];
        }

        $tasks = $this->extractTasks($previousLog->content);
        $tasks = array_values(array_filter($tasks, function (array $task) {
            $progress = trim((string) ($task['progress'] ?? ''));

            return ! in_array($progress, [self::PROGRESS_DONE, self::PROGRESS_CANCELLED], true);
        }));

        $tasks = array_map(function (array $task): array {
            $task['notes_progress'] = '';
            $task['history'] = [];

            return $task;
        }, $tasks);

        return $this->renumberTasks($tasks);
    }

    private function normalizeActionLogContent(array $content): array
    {
        $tasks = $this->extractTasks($content);
        $normalized = [];

        foreach ($tasks as $task) {
            $taskNo = (int) ($task['task_no'] ?? 0);
            $taskText = trim((string) ($task['task'] ?? ''));
            $progress = trim((string) ($task['progress'] ?? ''));
            $dueDate = trim((string) ($task['due_date'] ?? ''));
            $compilationDate = trim((string) ($task['compilation_date'] ?? ''));
            $notesProgress = trim((string) ($task['notes_progress'] ?? ''));
            $taskUid = trim((string) ($task['task_uid'] ?? ''));
            $statusReason = trim((string) ($task['status_reason'] ?? ''));

            $responsibleIds = $task['responsible_user_ids'] ?? [];
            if (! is_array($responsibleIds)) {
                $responsibleIds = [];
            }
            $filtered = [];
            foreach ($responsibleIds as $rid) {
                $sid = trim((string) $rid);
                if ($sid !== '' && Str::isUlid($sid)) {
                    $filtered[] = $sid;
                }
            }
            $responsibleIds = array_values(array_unique($filtered));

            if ($taskText === '' && empty($responsibleIds) && $progress === '' && $dueDate === '' && $compilationDate === '' && $notesProgress === '') {
                continue;
            }

            if (! in_array($progress, self::PROGRESS_OPTIONS, true)) {
                $progress = self::PROGRESS_NOT_STARTED;
            }

            $history = $task['history'] ?? [];
            if (! is_array($history)) {
                $history = [];
            }
            $history = array_values(array_filter($history, static fn ($entry) => is_array($entry)));

            if ($progress !== self::PROGRESS_CANCELLED) {
                $statusReason = '';
            }

            $normalized[] = [
                'task_uid' => $taskUid !== '' ? $taskUid : ('task_'.uniqid()),
                'task_no' => $taskNo > 0 ? $taskNo : (count($normalized) + 1),
                'task' => $taskText,
                'responsible_user_ids' => $responsibleIds,
                'progress' => $progress,
                'due_date' => $dueDate !== '' ? $dueDate : null,
                'compilation_date' => $compilationDate !== '' ? $compilationDate : null,
                'notes_progress' => $notesProgress,
                'status_reason' => $statusReason !== '' ? $statusReason : null,
                'history' => $history,
            ];
        }

        return ['tasks' => $this->renumberTasks($normalized)];
    }

    private function extractTasks($content): array
    {
        if (! is_array($content)) {
            return [];
        }
        $tasks = $content['tasks'] ?? [];

        return is_array($tasks) ? $tasks : [];
    }

    private function mergeResponsibleTaskUpdates(array $existingTasks, array $incomingTasks, WorkPeriod $period, string $userId): array
    {
        $incomingByUid = [];
        foreach ($incomingTasks as $task) {
            if (! is_array($task)) {
                continue;
            }
            $uid = trim((string) ($task['task_uid'] ?? ''));
            if ($uid !== '') {
                $incomingByUid[$uid] = $task;

                continue;
            }
            $taskNo = (int) ($task['task_no'] ?? 0);
            if ($taskNo > 0) {
                $incomingByUid['no:'.$taskNo] = $task;
            }
        }

        $merged = [];
        foreach ($existingTasks as $existing) {
            if (! is_array($existing)) {
                continue;
            }
            $responsibleIds = $existing['responsible_user_ids'] ?? [];
            if (! is_array($responsibleIds)) {
                $responsibleIds = [];
            }
            $responsibleIds = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $responsibleIds)));
            $isAssigned = in_array($userId, $responsibleIds, true);

            $keyUid = trim((string) ($existing['task_uid'] ?? ''));
            $keyNo = (int) ($existing['task_no'] ?? 0);
            $incoming = $keyUid !== '' ? ($incomingByUid[$keyUid] ?? null) : null;
            if (! $incoming && $keyNo > 0) {
                $incoming = $incomingByUid['no:'.$keyNo] ?? null;
            }

            if ($isAssigned && is_array($incoming)) {
                $before = $existing;
                $progress = trim((string) ($incoming['progress'] ?? ($existing['progress'] ?? self::PROGRESS_NOT_STARTED)));
                if (! in_array($progress, self::STAFF_PROGRESS_OPTIONS, true)) {
                    $progress = (string) ($existing['progress'] ?? self::PROGRESS_NOT_STARTED);
                }

                $existing['progress'] = $progress;
                $existing['due_date'] = ! empty($incoming['due_date']) ? $incoming['due_date'] : null;
                $existing['compilation_date'] = ! empty($incoming['compilation_date']) ? $incoming['compilation_date'] : null;
                $existing['notes_progress'] = trim((string) ($incoming['notes_progress'] ?? ($existing['notes_progress'] ?? '')));
                $existing['status_reason'] = $existing['status_reason'] ?? null;
                $existing = $this->appendTaskHistoryIfChanged($before, $existing, $period, $userId);
            }

            $merged[] = $existing;
        }

        return $merged;
    }

    private function mergeManagerStoreTasks(array $existingTasks, array $incomingTasks, WorkPeriod $period, string $actorUserId): array
    {
        $merged = [];
        foreach ($existingTasks as $existingTask) {
            if (! is_array($existingTask)) {
                continue;
            }
            $task = $this->normalizeActionLogContent(['tasks' => [$existingTask]])['tasks'][0] ?? null;
            if (! is_array($task)) {
                continue;
            }
            $merged[$task['task_uid']] = $task;
        }

        foreach ($incomingTasks as $incomingTask) {
            if (! is_array($incomingTask)) {
                continue;
            }
            $task = $this->normalizeActionLogContent(['tasks' => [$incomingTask]])['tasks'][0] ?? null;
            if (! is_array($task)) {
                continue;
            }
            $task = $this->applyCancelledTaskRules($task, true);
            $existing = $merged[$task['task_uid']] ?? null;
            $task = $this->appendTaskHistoryIfChanged($existing, $task, $period, $actorUserId);
            $merged[$task['task_uid']] = $task;
        }

        return array_values($merged);
    }

    private function mergeManagerTaskUpdates(array $existingTasks, array $incomingTasks, WorkPeriod $period, string $actorUserId): array
    {
        $existingByUid = [];
        foreach ($existingTasks as $existingTask) {
            if (! is_array($existingTask)) {
                continue;
            }
            $uid = trim((string) ($existingTask['task_uid'] ?? ''));
            if ($uid !== '') {
                $existingByUid[$uid] = $existingTask;
            }
        }

        $merged = [];
        foreach ($incomingTasks as $incomingTask) {
            if (! is_array($incomingTask)) {
                continue;
            }
            $task = $this->applyCancelledTaskRules($incomingTask, true);
            $uid = trim((string) ($task['task_uid'] ?? ''));
            $existing = $uid !== '' ? ($existingByUid[$uid] ?? null) : null;
            $task = $this->appendTaskHistoryIfChanged($existing, $task, $period, $actorUserId);
            $merged[] = $task;
        }

        return $merged;
    }

    private function initializeTaskHistoryEntries(array $tasks, WorkPeriod $period, string $actorUserId): array
    {
        $initialized = [];
        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }
            $normalizedTask = $this->applyCancelledTaskRules($task, true);
            $initialized[] = $this->appendTaskHistoryIfChanged(null, $normalizedTask, $period, $actorUserId);
        }

        return $initialized;
    }

    private function appendTaskHistoryIfChanged(?array $existingTask, array $newTask, WorkPeriod $period, string $actorUserId): array
    {
        $history = [];
        if (is_array($existingTask['history'] ?? null)) {
            $history = array_values(array_filter($existingTask['history'], static fn ($entry) => is_array($entry)));
        } elseif (is_array($newTask['history'] ?? null)) {
            $history = array_values(array_filter($newTask['history'], static fn ($entry) => is_array($entry)));
        }

        $oldProgress = trim((string) ($existingTask['progress'] ?? ''));
        $newProgress = trim((string) ($newTask['progress'] ?? self::PROGRESS_NOT_STARTED));
        $oldNotes = trim((string) ($existingTask['notes_progress'] ?? ''));
        $newNotes = trim((string) ($newTask['notes_progress'] ?? ''));
        $oldReason = trim((string) ($existingTask['status_reason'] ?? ''));
        $newReason = trim((string) ($newTask['status_reason'] ?? ''));

        $isNewTask = ! $existingTask;
        $changed = $isNewTask
            || $oldProgress !== $newProgress
            || $oldNotes !== $newNotes
            || $oldReason !== $newReason;

        if ($changed) {
            $history[] = [
                'period_id' => (string) $period->id,
                'period_label' => 'Week '.$period->week_number.' of '.$period->year,
                'updated_by' => $actorUserId,
                'from_progress' => $oldProgress !== '' ? $oldProgress : null,
                'to_progress' => $newProgress,
                'notes_progress' => $newNotes !== '' ? $newNotes : null,
                'status_reason' => $newReason !== '' ? $newReason : null,
                'changed_at' => now()->toDateTimeString(),
                'source' => $isNewTask ? 'auto_init' : 'update',
            ];
        }

        $newTask['history'] = $history;

        return $newTask;
    }

    private function applyCancelledTaskRules(array $task, bool $isManager): array
    {
        $progress = trim((string) ($task['progress'] ?? self::PROGRESS_NOT_STARTED));
        if (! in_array($progress, self::PROGRESS_OPTIONS, true)) {
            $progress = self::PROGRESS_NOT_STARTED;
        }

        if (! $isManager && $progress === self::PROGRESS_CANCELLED) {
            $progress = trim((string) ($task['existing_progress'] ?? self::PROGRESS_NOT_STARTED));
            if (! in_array($progress, self::STAFF_PROGRESS_OPTIONS, true)) {
                $progress = self::PROGRESS_NOT_STARTED;
            }
        }

        $task['progress'] = $progress;
        if ($progress !== self::PROGRESS_CANCELLED) {
            $task['status_reason'] = null;
        }

        return $task;
    }

    private function validateCancelledTasks(array $tasks, bool $isManager): ?string
    {
        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }
            $progress = trim((string) ($task['progress'] ?? ''));
            if ($progress !== self::PROGRESS_CANCELLED) {
                continue;
            }
            if (! $isManager) {
                return 'Only authorized personnel can cancel an action log task.';
            }

            $reason = trim((string) ($task['status_reason'] ?? ''));
            if ($reason === '') {
                return 'Please provide a cancellation reason for every cancelled action log task.';
            }
        }

        return null;
    }

    private function buildActionLogTitle(WorkPeriod $period): string
    {
        return 'Action Log - Week '.$period->week_number.' of '.$period->year;
    }

    private function renumberTasks(array $tasks): array
    {
        $counter = 1;
        foreach ($tasks as &$task) {
            if (! is_array($task)) {
                continue;
            }
            $task['task_no'] = $counter++;
        }
        unset($task);

        return array_values($tasks);
    }

    private function isUserAssignedToActionLog(User $user, Document $document): bool
    {
        $actionLog = $document->actionLogDomain()
            ->with('tasks.assignees:id')
            ->first();

        if ($actionLog) {
            return $actionLog->tasks->contains(function (ActionLogTask $task) use ($user) {
                return $task->assignees->contains(fn ($assignee) => (string) $assignee->id === (string) $user->id);
            });
        }

        $content = is_array($document->content) ? $document->content : [];
        $tasks = is_array($content['tasks'] ?? null) ? $content['tasks'] : [];
        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }
            $responsibleIds = $task['responsible_user_ids'] ?? [];
            if (! is_array($responsibleIds)) {
                continue;
            }
            $ids = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $responsibleIds)));
            if (in_array((string) $user->id, $ids, true)) {
                return true;
            }
        }

        return false;
    }
}
