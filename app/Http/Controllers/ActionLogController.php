<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\ActionLogTask;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\ActionLogCarryForwardService;
use App\Services\Documents\DocumentDomainSyncService;
use App\Support\ShortId;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActionLogController extends Controller
{
    public function __construct(
        private readonly DocumentDomainSyncService $documentDomainSyncService,
        private readonly ActionLogCarryForwardService $actionLogCarryForwardService,
    ) {}

    private const PROGRESS_NOT_STARTED = 'Not Started';

    private const PROGRESS_IN_PROGRESS = 'In Progress';

    private const PROGRESS_DONE = 'Done';

    private const PROGRESS_CANCELLED = 'Cancelled';

    private const QUICK_UPDATE_REASSIGN = 'Reassign';

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
        self::PROGRESS_CANCELLED,
    ];

    /**
     * Display a listing of action log documents.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $canManageActionLogs = $user->hasPermissionTo('manage action logs');
        $query = Document::with(['user', 'period'])->actionLogs();

        if ($canManageActionLogs) {
            // Managers see every action log (draft + submitted) and may filter by state.
            if ($request->filled('state')) {
                $query->ofState($request->state);
            }

            $documents = $this->orderActionLogsByWorkPeriodDesc($query)
                ->paginate(15)
                ->withQueryString();
        } else {
            // Everyone else only sees submitted logs. Drafts stay hidden until submitted.
            if ($request->filled('state') && $request->state !== Document::STATE_SUBMITTED) {
                abort(403, 'You do not have permission to view draft action logs.');
            }

            $documents = $this->orderActionLogsByWorkPeriodDesc(
                $query->ofState(Document::STATE_SUBMITTED)
            )
                ->paginate(15)
                ->withQueryString();
        }

        return view('action-logs.index', compact('documents', 'canManageActionLogs'));
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

        $carryForwardTasks = $currentPeriod
            ? $this->actionLogCarryForwardService->getIncompleteTasksFromPriorPeriods($currentPeriod)
            : [];
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
            'period_id' => ['required', 'string', 'short_id', 'exists:work_periods,id'],
            'content' => ['nullable', 'array'],
            'content.tasks' => ['nullable', 'array'],
            'content.tasks.*.task_uid' => ['nullable', 'string', 'max:80'],
            'content.tasks.*.task_no' => ['nullable', 'integer', 'min:1'],
            'content.tasks.*.task' => ['nullable', 'string', 'max:4000'],
            'content.tasks.*.responsible_user_ids' => ['nullable', 'array'],
            'content.tasks.*.responsible_user_ids.*' => ['nullable', 'string', 'short_id', 'exists:users,id'],
            'content.tasks.*.progress' => ['nullable', 'string', 'in:'.implode(',', array_merge(self::PROGRESS_OPTIONS, [self::QUICK_UPDATE_REASSIGN]))],
            'content.tasks.*.due_date' => ['nullable', 'date'],
            'content.tasks.*.compilation_date' => ['nullable', 'date'],
            'content.tasks.*.notes_progress' => ['nullable', 'string', 'max:4000'],
            'content.tasks.*.status_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        // Get period
        $period = WorkPeriod::find($request->period_id);
        if (! $period) {
            return $this->actionLogErrorResponse($request, 'Selected work period not found.');
        }

        if (! $period->isOpen()) {
            return $this->actionLogErrorResponse($request, 'This work period is closed. Please contact your administrator.');
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
            return $this->actionLogErrorResponse($request, 'Please add at least one action log task.');
        }

        // If document exists, merge new rows into the same weekly log.
        if ($existingDocument) {
            $this->persistCarriedTaskSyncIfNeeded($existingDocument, $period);
            $existingDocument->refresh();

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
                return $this->actionLogErrorResponse($request, 'Action log must contain at least one task.');
            }
            $nextState = $this->resolveActionLogStateAfterSave($action, $wasSubmitted);

            $content = $this->mergeActionLogContentWithTasks($existingDocument->content, $finalTasks);

            $existingDocument->update([
                'title' => $title,
                'content' => $content,
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
            // Create new document
            $document = Document::create([
                'user_id' => $user->id,
                'period_id' => $period->id,
                'type' => Document::TYPE_ACTION_LOG,
                'title' => $title,
                'content' => $this->mergeActionLogContentWithTasks(null, $initialTasks),
                'state' => $state,
            ]);
            $this->documentDomainSyncService->syncFromDocument($document);
        }

        $message = ($action === 'submit')
            ? 'Action Log submitted successfully.'
            : 'Action Log saved as draft.';

        if ($request->wantsJson()) {
            return $this->actionLogAutosaveJsonResponse($document, $message);
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

        if ($document->period && ! $document->period->isOpen()) {
            return redirect()->route('action-logs.show', $document)
                ->with('error', 'This action log belongs to a closed work period and cannot be edited.');
        }

        if (! $isManager) {
            return redirect()->route('action-logs.show', $document)
                ->with('error', 'Only action log managers can edit tasks here. Use Dashboard → My Action Tasks to update your assigned tasks.');
        }

        $this->authorize('manageActionLogs', $document);

        if ($document->period && $document->period->isOpen()) {
            $this->persistCarriedTaskSyncIfNeeded($document, $document->period);
            $document->refresh();
        }

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

        $isManager = $this->isActionLogManager($actingUser);
        if (! $isManager) {
            return redirect()->route('action-logs.show', $document)
                ->with('error', 'Only action log managers can edit tasks here. Use Dashboard → My Action Tasks to update your assigned tasks.');
        }

        $this->authorize('manageActionLogs', $document);

        // Keep period stable when the UI shows a disabled work-period selector.
        if (! $request->filled('period_id') && ! empty($document->period_id)) {
            $request->merge(['period_id' => (string) $document->period_id]);
        }

        $request->validate([
            'period_id' => ['required', 'string', 'short_id', 'exists:work_periods,id'],
            'content' => ['nullable', 'array'],
            'content.tasks' => ['nullable', 'array'],
            'content.tasks.*.task_uid' => ['nullable', 'string', 'max:80'],
            'content.tasks.*.task_no' => ['nullable', 'integer', 'min:1'],
            'content.tasks.*.task' => ['nullable', 'string', 'max:4000'],
            'content.tasks.*.responsible_user_ids' => ['nullable', 'array'],
            'content.tasks.*.responsible_user_ids.*' => ['nullable', 'string', 'short_id', 'exists:users,id'],
            'content.tasks.*.progress' => ['nullable', 'string', 'in:'.implode(',', array_merge(self::PROGRESS_OPTIONS, [self::QUICK_UPDATE_REASSIGN]))],
            'content.tasks.*.due_date' => ['nullable', 'date'],
            'content.tasks.*.compilation_date' => ['nullable', 'date'],
            'content.tasks.*.notes_progress' => ['nullable', 'string', 'max:4000'],
            'content.tasks.*.status_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        // Get period
        $period = WorkPeriod::find($request->period_id);
        if (! $period) {
            return $this->actionLogErrorResponse($request, 'Selected work period not found.');
        }

        if (! $period->isOpen()) {
            return $this->actionLogErrorResponse($request, 'This work period is closed. Please contact your administrator.');
        }

        $action = $request->input('action', 'draft');
        $wasSubmitted = $document->isSubmitted();

        if ($action === 'submit' && ! $document->isSubmitted()) {
            $this->authorize('submit', $document);
        }

        $previousTasks = $this->extractTasks($document->content);

        $normalizedContent = $this->normalizeActionLogContent($request->input('content', []));
        $incomingTasks = $normalizedContent['tasks'] ?? [];

        $finalTasks = $this->mergeManagerTaskUpdates($previousTasks, $incomingTasks, $period, (string) Auth::id());

        if (empty($finalTasks)) {
            return $this->actionLogErrorResponse($request, 'Action log must contain at least one task.');
        }

        $content = $this->mergeActionLogContentWithTasks($document->content, $finalTasks);
        $content = $this->recordRemovedTaskUidsAfterManagerSave($content, $previousTasks, $finalTasks);
        $updateData = [
            'content' => $content,
            'title' => $this->buildActionLogTitle($period),
            'period_id' => $period->id,
        ];

        if ($action === 'submit') {
            $updateData['state'] = Document::STATE_SUBMITTED;
        } elseif ($wasSubmitted) {
            $updateData['state'] = Document::STATE_DRAFT;
        }

        $document->update($updateData);
        $this->documentDomainSyncService->syncFromDocument($document);

        $message = ($action === 'submit')
            ? 'Action Log submitted successfully.'
            : 'Action Log updated successfully.';

        if ($request->wantsJson()) {
            return $this->actionLogAutosaveJsonResponse($document, $message);
        }

        return redirect()->route('action-logs.show', $document)
            ->with('success', $message);
    }

    /**
     * Apply a lightweight progress + notes update to a single assigned task.
     * Used by the dashboard "My Action Tasks" quick-update widget so assignees
     * can report progress without opening the full editor.
     */
    public function quickUpdate(Request $request, Document $document)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($document->type !== Document::TYPE_ACTION_LOG) {
            abort(404, 'Document not found.');
        }

        $this->authorize('update', $document);

        $request->merge([
            'notes_progress' => trim((string) $request->input('notes_progress', '')),
        ]);

        $validated = $request->validate([
            'task_uid' => ['required', 'string', 'max:80'],
            'progress' => ['required', 'string', 'in:'.implode(',', array_merge(self::STAFF_PROGRESS_OPTIONS, [self::QUICK_UPDATE_REASSIGN]))],
            'notes_progress' => ['required', 'string', 'max:4000'],
            'reassign_user_id' => ['nullable', 'string', 'short_id', 'exists:users,id'],
        ]);

        $isReassign = $validated['progress'] === self::QUICK_UPDATE_REASSIGN;
        if ($isReassign) {
            $request->validate([
                'reassign_user_id' => ['required', 'string', 'short_id', 'exists:users,id'],
            ]);
            $validated['reassign_user_id'] = trim((string) $request->input('reassign_user_id'));
        }

        $document->loadMissing('period');
        $period = $document->period;
        if (! $period || ! $period->isOpen()) {
            return redirect()->back()
                ->with('error', 'This action log belongs to a closed work period and cannot be updated.');
        }

        $isManager = $this->isActionLogManager($user);
        $tasks = $this->extractTasks($document->content);
        $found = false;

        foreach ($tasks as &$task) {
            if (! is_array($task)) {
                continue;
            }
            if (trim((string) ($task['task_uid'] ?? '')) !== $validated['task_uid']) {
                continue;
            }

            $responsibleIds = is_array($task['responsible_user_ids'] ?? null)
                ? array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $task['responsible_user_ids'])))
                : [];

            if (! $isManager && ! in_array((string) $user->id, $responsibleIds, true)) {
                return redirect()->back()
                    ->with('error', 'You can only update tasks where you are assigned as the responsible person.');
            }

            if ($isReassign) {
                $newAssigneeId = (string) $validated['reassign_user_id'];
                if (in_array($newAssigneeId, $responsibleIds, true)) {
                    return redirect()->back()
                        ->with('error', 'This task is already assigned to the selected person.');
                }

                $task = $this->appendTaskReassignHistory(
                    $task,
                    $period,
                    (string) $user->id,
                    $responsibleIds,
                    $newAssigneeId,
                    $validated['notes_progress']
                );
                $task['responsible_user_ids'] = [$newAssigneeId];
            } else {
                $before = $task;
                $task['progress'] = $validated['progress'];
                $task['notes_progress'] = $validated['notes_progress'];
                $task = $this->appendTaskHistoryIfChanged($before, $task, $period, (string) $user->id);
            }

            $found = true;
            break;
        }
        unset($task);

        if (! $found) {
            return redirect()->back()
                ->with('error', 'The selected task could not be found on this action log.');
        }

        $document->update([
            'content' => $this->mergeActionLogContentWithTasks($document->content, $tasks),
        ]);
        $this->documentDomainSyncService->syncFromDocument($document);

        $message = $isReassign
            ? 'Task reassigned successfully.'
            : 'Task progress updated successfully.';

        return redirect()->back()
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

    /**
     * Newest work periods first (e.g. Week 23, 22, 21 …).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function persistCarriedTaskSyncIfNeeded(Document $document, WorkPeriod $period): void
    {
        $currentPeriod = WorkPeriod::getCurrent();
        if (! $currentPeriod || (string) $document->period_id !== (string) $currentPeriod->id || ! $period->isOpen()) {
            return;
        }

        $sync = $this->actionLogCarryForwardService->syncMissingCarriedTasks($document, $period);
        if (! $sync['changed']) {
            return;
        }

        $document->update([
            'content' => $this->mergeActionLogContentWithTasks($document->content, $sync['tasks']),
        ]);
        $this->documentDomainSyncService->syncFromDocument($document->fresh());
    }

    private function orderActionLogsByWorkPeriodDesc($query)
    {
        return $query
            ->leftJoin('work_periods', 'documents.period_id', '=', 'work_periods.id')
            ->orderByDesc('work_periods.year')
            ->orderByDesc('work_periods.week_number')
            ->orderByDesc('documents.updated_at')
            ->select('documents.*');
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
            $isReassign = $progress === self::QUICK_UPDATE_REASSIGN;

            $responsibleIds = $this->normalizeResponsibleIds($task['responsible_user_ids'] ?? []);

            if ($taskText === '' && empty($responsibleIds) && $progress === '' && $dueDate === '' && $compilationDate === '' && $notesProgress === '') {
                continue;
            }

            if ($isReassign) {
                $progress = '';
            } elseif (! in_array($progress, self::PROGRESS_OPTIONS, true)) {
                $progress = self::PROGRESS_NOT_STARTED;
            }

            $history = $task['history'] ?? [];
            if (! is_array($history)) {
                $history = [];
            }
            $history = array_values(array_filter($history, static fn ($entry) => is_array($entry)));

            $normalized[] = [
                'task_uid' => $taskUid !== '' ? $taskUid : ('task_'.uniqid()),
                'task_no' => $taskNo > 0 ? $taskNo : (count($normalized) + 1),
                'task' => $taskText,
                'responsible_user_ids' => $responsibleIds,
                'progress' => $progress,
                'due_date' => $dueDate !== '' ? $this->formatActionLogDateForStorage($dueDate) : null,
                'compilation_date' => $compilationDate !== '' ? $this->formatActionLogDateForStorage($compilationDate) : null,
                'notes_progress' => $notesProgress,
                'status_reason' => null,
                'reassign' => $isReassign,
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
            $task = $this->applyManagerTaskChange($existing, $task, $period, $actorUserId);
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

            if ($existing) {
                $incomingResponsible = is_array($task['responsible_user_ids'] ?? null)
                    ? array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $task['responsible_user_ids'])))
                    : [];
                $existingResponsible = is_array($existing['responsible_user_ids'] ?? null)
                    ? array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $existing['responsible_user_ids'])))
                    : [];
                if ($incomingResponsible === [] && $existingResponsible !== []) {
                    $task['responsible_user_ids'] = $existingResponsible;
                }
            }

            $task = $this->applyManagerTaskChange($existing, $task, $period, $actorUserId);
            $merged[] = $task;
        }

        return $merged;
    }

    /**
     * @param  mixed  $responsibleIds
     * @return list<string>
     */
    private function normalizeResponsibleIds($responsibleIds): array
    {
        if (! is_array($responsibleIds)) {
            return [];
        }

        $filtered = [];
        foreach ($responsibleIds as $rid) {
            $sid = trim((string) $rid);
            if ($sid !== '' && (ShortId::is($sid) || ShortId::isLegacyUlid($sid))) {
                $filtered[] = $sid;
            }
        }

        return array_values(array_unique($filtered));
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

    private function applyManagerTaskChange(?array $existingTask, array $task, WorkPeriod $period, string $actorUserId): array
    {
        $isReassign = (bool) ($task['reassign'] ?? false);
        unset($task['reassign']);

        if (! $isReassign || ! $existingTask) {
            return $this->appendTaskHistoryIfChanged($existingTask, $task, $period, $actorUserId);
        }

        $existingResponsible = is_array($existingTask['responsible_user_ids'] ?? null)
            ? array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $existingTask['responsible_user_ids'])))
            : [];
        $newResponsible = is_array($task['responsible_user_ids'] ?? null)
            ? array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $task['responsible_user_ids'])))
            : [];

        if ($newResponsible === []) {
            $task['responsible_user_ids'] = $existingResponsible;

            return $this->appendTaskHistoryIfChanged($existingTask, $task, $period, $actorUserId);
        }

        $newAssigneeId = $newResponsible[0];
        if (count($newResponsible) === 1 && in_array($newAssigneeId, $existingResponsible, true)) {
            $task['progress'] = trim((string) ($existingTask['progress'] ?? self::PROGRESS_NOT_STARTED));

            return $this->appendTaskHistoryIfChanged($existingTask, $task, $period, $actorUserId);
        }

        $task['progress'] = trim((string) ($existingTask['progress'] ?? self::PROGRESS_NOT_STARTED));
        $task['responsible_user_ids'] = count($newResponsible) === 1
            ? [$newAssigneeId]
            : $newResponsible;
        $notes = trim((string) ($task['notes_progress'] ?? ''));

        $reassigned = $this->appendTaskReassignHistory(
            array_merge($existingTask, ['progress' => $task['progress']]),
            $period,
            $actorUserId,
            $existingResponsible,
            $newAssigneeId,
            $notes
        );

        return array_merge($task, [
            'history' => $reassigned['history'],
            'notes_progress' => $reassigned['notes_progress'],
            'progress' => $reassigned['progress'],
        ]);
    }

    /**
     * @param  array<int, string>  $previousResponsibleIds
     */
    private function appendTaskReassignHistory(
        array $task,
        WorkPeriod $period,
        string $actorUserId,
        array $previousResponsibleIds,
        string $newAssigneeId,
        string $notes
    ): array {
        $history = is_array($task['history'] ?? null)
            ? array_values(array_filter($task['history'], static fn ($entry) => is_array($entry)))
            : [];

        $currentProgress = trim((string) ($task['progress'] ?? self::PROGRESS_NOT_STARTED));

        $history[] = [
            'period_id' => (string) $period->id,
            'period_label' => 'Week '.$period->week_number.' of '.$period->year,
            'updated_by' => $actorUserId,
            'from_progress' => $currentProgress !== '' ? $currentProgress : null,
            'to_progress' => $currentProgress !== '' ? $currentProgress : self::PROGRESS_NOT_STARTED,
            'notes_progress' => $notes !== '' ? $notes : null,
            'status_reason' => null,
            'reassigned_from' => array_values($previousResponsibleIds),
            'reassigned_to' => $newAssigneeId,
            'changed_at' => now()->toDateTimeString(),
            'source' => 'reassign',
        ];

        $task['history'] = $history;
        $task['notes_progress'] = $notes;

        return $task;
    }

    private function applyCancelledTaskRules(array $task, bool $isManager): array
    {
        $progress = trim((string) ($task['progress'] ?? self::PROGRESS_NOT_STARTED));
        if (! in_array($progress, self::PROGRESS_OPTIONS, true)) {
            $progress = self::PROGRESS_NOT_STARTED;
        }

        $task['progress'] = $progress;
        $task['status_reason'] = null;

        return $task;
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

    private function resolveActionLogStateAfterSave(string $action, bool $wasSubmitted): string
    {
        if ($action === 'submit') {
            return Document::STATE_SUBMITTED;
        }

        if ($wasSubmitted) {
            return Document::STATE_DRAFT;
        }

        return Document::STATE_DRAFT;
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|JsonResponse
     */
    private function actionLogErrorResponse(Request $request, string $message, int $status = 422)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $status);
        }

        return redirect()->back()
            ->withInput()
            ->with('error', $message);
    }

    private function actionLogAutosaveJsonResponse(Document $document, string $message): JsonResponse
    {
        $document->refresh();
        $tasks = $this->extractTasks($document->content);

        return response()->json([
            'success' => true,
            'message' => $message,
            'state' => (string) $document->state,
            'document_id' => (string) $document->id,
            'edit_url' => route('action-logs.update', $document),
            'tasks' => $this->tasksForClientResponse($tasks),
        ]);
    }

    /**
     * @param  array<int, mixed>  $tasks
     * @return list<array<string, mixed>>
     */
    private function tasksForClientResponse(array $tasks): array
    {
        $normalized = [];

        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }

            $responsibleIds = is_array($task['responsible_user_ids'] ?? null)
                ? array_values(array_map('strval', $task['responsible_user_ids']))
                : [];

            $normalized[] = [
                'task_uid' => (string) ($task['task_uid'] ?? ''),
                'task_no' => (int) ($task['task_no'] ?? 0),
                'task' => (string) ($task['task'] ?? ''),
                'responsible_user_ids' => $responsibleIds,
                'progress' => (string) ($task['progress'] ?? self::PROGRESS_NOT_STARTED),
                'due_date' => $this->formatActionLogDateForClient($task['due_date'] ?? null),
                'compilation_date' => $this->formatActionLogDateForClient($task['compilation_date'] ?? null),
                'notes_progress' => (string) ($task['notes_progress'] ?? ''),
            ];
        }

        return $normalized;
    }

    private function formatActionLogDateForStorage(string $value): string
    {
        return Carbon::parse($value)->format('Y-m-d');
    }

    private function formatActionLogDateForClient(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * @param  array<int, mixed>  $tasks
     * @return array<string, mixed>
     */
    private function mergeActionLogContentWithTasks(mixed $existingContent, array $tasks): array
    {
        $content = is_array($existingContent) ? $existingContent : [];
        $content['tasks'] = $this->renumberTasks($tasks);

        return $content;
    }

    /**
     * Remember task UIDs the manager removed so carry-forward sync does not restore them.
     *
     * @param  array<string, mixed>  $content
     * @param  array<int, mixed>  $previousTasks
     * @param  array<int, mixed>  $finalTasks
     * @return array<string, mixed>
     */
    private function recordRemovedTaskUidsAfterManagerSave(array $content, array $previousTasks, array $finalTasks): array
    {
        $meta = is_array($content['meta'] ?? null) ? $content['meta'] : [];
        $removed = is_array($meta['removed_task_uids'] ?? null)
            ? array_values(array_map('strval', $meta['removed_task_uids']))
            : [];

        $finalUids = [];
        foreach ($finalTasks as $task) {
            if (! is_array($task)) {
                continue;
            }
            $uid = trim((string) ($task['task_uid'] ?? ''));
            if ($uid !== '') {
                $finalUids[$uid] = true;
            }
        }

        foreach ($previousTasks as $task) {
            if (! is_array($task)) {
                continue;
            }
            $uid = trim((string) ($task['task_uid'] ?? ''));
            if ($uid !== '' && ! isset($finalUids[$uid])) {
                $removed[] = $uid;
            }
        }

        $meta['removed_task_uids'] = array_values(array_unique(array_filter($removed)));
        $content['meta'] = $meta;

        return $content;
    }
}
