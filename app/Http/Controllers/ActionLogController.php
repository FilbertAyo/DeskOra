<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\ActionLogNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class ActionLogController extends Controller
{
    public function __construct(private ActionLogNotificationService $actionLogNotificationService)
    {
    }

    /**
     * Display a listing of action log documents.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $canViewAllActionLogs = $user->hasAnyPermission(['create action logs', 'approve documents', 'view action logs', 'view all documents']);
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

        if (!$isManager) {
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

        // If shared action log already exists for current period, redirect to edit/show.
        if ($defaultPeriodId) {
            $existingCurrent = Document::where('period_id', $defaultPeriodId)
                ->where('type', Document::TYPE_ACTION_LOG)
                ->latest('id')
                ->first();

            if ($existingCurrent) {
                $encoded = Hashids::encode($existingCurrent->id);
                if ($existingCurrent->isDraft()) {
                    return redirect()->route('action-logs.edit', $encoded)
                        ->with('info', 'Action log for this week already exists. You can continue updating it.');
                }
                return redirect()->route('action-logs.show', $encoded)
                    ->with('info', 'Action log for this week is already submitted.');
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

        if (!$isManager) {
            abort(403, 'Unauthorized access. Only users with action log permissions can create action logs.');
        }

        // Authorize create action for action_log type
        $this->authorize('createType', [Document::class, Document::TYPE_ACTION_LOG]);

        $request->validate([
            'period_id' => ['required', 'integer', 'exists:work_periods,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'array'],
            'content.tasks' => ['nullable', 'array'],
            'content.tasks.*.task_no' => ['nullable', 'integer', 'min:1'],
            'content.tasks.*.task' => ['nullable', 'string', 'max:4000'],
            'content.tasks.*.responsible_user_ids' => ['nullable', 'array'],
            'content.tasks.*.responsible_user_ids.*' => ['nullable', 'integer', 'exists:users,id'],
            'content.tasks.*.progress' => ['nullable', 'string', 'in:Not Started,In Progress,Done'],
            'content.tasks.*.due_date' => ['nullable', 'date'],
            'content.tasks.*.compilation_date' => ['nullable', 'date'],
            'content.tasks.*.notes_progress' => ['nullable', 'string', 'max:4000'],
        ]);

        // Get period
        $period = WorkPeriod::find($request->period_id);
        if (!$period) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Selected work period not found.');
        }

        // Check if period is open
        if (!$period->isOpen()) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'This work period is closed. Please contact your administrator.');
        }

        // Shared action log per week period.
        $existingDocument = Document::where('type', Document::TYPE_ACTION_LOG)
            ->where('period_id', $period->id)
            ->latest('id')
            ->first();

        if ($existingDocument && $existingDocument->isSubmitted()) {
            return redirect()->route('action-logs.show', Hashids::encode($existingDocument->id))
                ->with('info', 'Action log already exists for this week period. You can view it below.');
        }

        // Generate title if not provided
        $title = trim((string) $request->input('title', ''));
        if (!$title) {
            $title = 'Action Log - Week ' . $period->week_number . ' of ' . $period->year;
        }

        // Determine state based on action
        $action = $request->input('action', 'draft');
        $state = ($action === 'submit') ? Document::STATE_SUBMITTED : Document::STATE_DRAFT;
        $normalizedContent = $this->normalizeActionLogContent($request->input('content', []));

        if (empty($normalizedContent['tasks'])) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Please add at least one action log task.');
        }

        // If document exists (draft), update it
        if ($existingDocument) {
            // If submitting, authorize submit action
            if ($action === 'submit') {
                $this->authorize('submit', $existingDocument);
            }

            $oldTasks = $this->extractTasks($existingDocument->content);
            $oldState = (string) $existingDocument->state;
            $existingDocument->update([
                'title' => $title,
                'content' => $normalizedContent,
                'state' => $state,
            ]);

            $document = $existingDocument;

            try {
                $notificationSummary = $this->actionLogNotificationService->notifyOnUpdate(
                    $document,
                    $oldTasks,
                    $normalizedContent['tasks'] ?? [],
                    $oldState,
                    $state,
                    (int) $user->id
                );
            } catch (\Throwable $e) {
                Log::warning('Action log notifications failed on store(update-existing)', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
                $notificationSummary = ['assignment_notifications' => 0, 'submission_notifications' => 0];
            }
        } else {
            // Create new document
            $document = Document::create([
                'user_id' => $user->id,
                'period_id' => $period->id,
                'type' => Document::TYPE_ACTION_LOG,
                'title' => $title,
                'content' => $normalizedContent,
                'state' => $state,
            ]);

            try {
                $notificationSummary = $this->actionLogNotificationService->notifyOnStore(
                    $document,
                    $normalizedContent['tasks'] ?? [],
                    $state === Document::STATE_SUBMITTED,
                    (int) $user->id
                );
            } catch (\Throwable $e) {
                Log::warning('Action log notifications failed on store(create-new)', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
                $notificationSummary = ['assignment_notifications' => 0, 'submission_notifications' => 0];
            }
        }

        $message = ($action === 'submit') 
            ? 'Action Log submitted successfully.' 
            : 'Action Log saved as draft.';

        $redirect = redirect()->route('action-logs.show', Hashids::encode($document->id))
            ->with('success', $message);

        $assignmentCount = (int) ($notificationSummary['assignment_notifications'] ?? 0);
        $submissionCount = (int) ($notificationSummary['submission_notifications'] ?? 0);
        if (($assignmentCount + $submissionCount) > 0) {
            $redirect->with('info', "Notifications sent: {$assignmentCount} assignment, {$submissionCount} submission.");
        }

        return $redirect;
    }

    /**
     * Display the specified action log document.
     */
    public function show(string $id)
    {
        $decodedId = Hashids::decode($id);
        $document = Document::with(['user', 'period', 'comments.user'])
            ->findOrFail($decodedId[0] ?? null);

        // Ensure it's an action log document
        if ($document->type !== Document::TYPE_ACTION_LOG) {
            abort(404, 'Document not found.');
        }

        // Authorize view action
        $this->authorize('view', $document);

        return view('action-logs.show', compact('document'));
    }

    /**
     * Show the form for editing the specified action log document.
     */
    public function edit(string $id)
    {
        $decodedId = Hashids::decode($id);
        $document = Document::with(['period'])->findOrFail($decodedId[0] ?? null);

        // Ensure it's an action log document
        if ($document->type !== Document::TYPE_ACTION_LOG) {
            abort(404, 'Document not found.');
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $isManager = $this->isActionLogManager($user);
        $isAssigned = $this->isUserAssignedToActionLog($user, $document);

        if ($document->period && !$document->period->isOpen()) {
            return redirect()->route('action-logs.show', $id)
                ->with('error', 'This action log belongs to a closed work period and cannot be edited.');
        }

        if (!$isManager && !$isAssigned) {
            return redirect()->route('action-logs.show', $id)
                ->with('error', 'You can only edit tasks where you are assigned as responsible person.');
        }

        if ($isManager && !$document->isDraft()) {
            return redirect()->route('action-logs.show', $id)
                ->with('error', 'This action log is submitted. Managers can only edit draft action logs.');
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
    public function update(Request $request, string $id)
    {
        $decodedId = Hashids::decode($id);
        $document = Document::findOrFail($decodedId[0] ?? null);

        // Ensure it's an action log document
        if ($document->type !== Document::TYPE_ACTION_LOG) {
            abort(404, 'Document not found.');
        }

        // Authorize update action
        $this->authorize('update', $document);

        $request->validate([
            'period_id' => ['required', 'integer', 'exists:work_periods,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'array'],
            'content.tasks' => ['nullable', 'array'],
            'content.tasks.*.task_uid' => ['nullable', 'string', 'max:80'],
            'content.tasks.*.task_no' => ['nullable', 'integer', 'min:1'],
            'content.tasks.*.task' => ['nullable', 'string', 'max:4000'],
            'content.tasks.*.responsible_user_ids' => ['nullable', 'array'],
            'content.tasks.*.responsible_user_ids.*' => ['nullable', 'integer', 'exists:users,id'],
            'content.tasks.*.progress' => ['nullable', 'string', 'in:Not Started,In Progress,Done'],
            'content.tasks.*.due_date' => ['nullable', 'date'],
            'content.tasks.*.compilation_date' => ['nullable', 'date'],
            'content.tasks.*.notes_progress' => ['nullable', 'string', 'max:4000'],
        ]);

        // Get period
        $period = WorkPeriod::find($request->period_id);
        if (!$period) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Selected work period not found.');
        }

        // Check if period is open
        if (!$period->isOpen()) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'This work period is closed. Please contact your administrator.');
        }

        // Determine state based on action
        $action = $request->input('action', 'draft');
        
        // If submitting, authorize submit action
        if ($action === 'submit') {
            $this->authorize('submit', $document);
        }

        $isManager = $this->isActionLogManager(Auth::user());
        $normalizedContent = $this->normalizeActionLogContent($request->input('content', []));
        $existingTasks = $this->extractTasks($document->content);
        $incomingTasks = $normalizedContent['tasks'] ?? [];

        // Non-managers can only update their assigned rows and limited columns.
        $finalTasks = $isManager
            ? $incomingTasks
            : $this->mergeResponsibleTaskUpdates($existingTasks, $incomingTasks, (int) Auth::id());

        if (empty($finalTasks)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Action log must contain at least one task.');
        }

        $updateData = [
            'content' => ['tasks' => $this->renumberTasks($finalTasks)],
        ];

        if ($isManager) {
            $updateData['period_id'] = $period->id;

            if ($request->has('title')) {
                $updateData['title'] = trim((string) $request->title);
            } elseif (!$document->title) {
                $updateData['title'] = 'Action Log - Week ' . $period->week_number . ' of ' . $period->year;
            }
        }

        // Update state if submitting
        if ($action === 'submit') {
            $updateData['state'] = Document::STATE_SUBMITTED;
        }

        $oldTasks = $existingTasks;
        $oldState = (string) $document->state;
        $document->update($updateData);

        try {
            $notificationSummary = $this->actionLogNotificationService->notifyOnUpdate(
                $document,
                $oldTasks,
                $updateData['content']['tasks'] ?? [],
                $oldState,
                (string) ($updateData['state'] ?? $document->state),
                (int) Auth::id()
            );
        } catch (\Throwable $e) {
            Log::warning('Action log notifications failed on update', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
            $notificationSummary = ['assignment_notifications' => 0, 'submission_notifications' => 0];
        }

        $message = ($action === 'submit') 
            ? 'Action Log submitted successfully.' 
            : 'Action Log updated successfully.';

        $redirect = redirect()->route('action-logs.show', $id)
            ->with('success', $message);

        $assignmentCount = (int) ($notificationSummary['assignment_notifications'] ?? 0);
        $submissionCount = (int) ($notificationSummary['submission_notifications'] ?? 0);
        if (($assignmentCount + $submissionCount) > 0) {
            $redirect->with('info', "Notifications sent: {$assignmentCount} assignment, {$submissionCount} submission.");
        }

        return $redirect;
    }

    /**
     * Remove the specified action log document.
     */
    public function destroy(string $id)
    {
        $decodedId = Hashids::decode($id);
        $document = Document::findOrFail($decodedId[0] ?? null);

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
        return $user->hasAnyPermission(['create action logs', 'approve documents']);
    }

    private function getCarryForwardTasks(WorkPeriod $period): array
    {
        $previousPeriod = $period->getPreviousPeriod();
        if (!$previousPeriod) {
            return [];
        }

        $previousLog = Document::where('type', Document::TYPE_ACTION_LOG)
            ->where('period_id', $previousPeriod->id)
            ->orderByRaw("CASE WHEN state = ? THEN 0 ELSE 1 END", [Document::STATE_SUBMITTED])
            ->latest('id')
            ->first();

        if (!$previousLog) {
            return [];
        }

        $tasks = $this->extractTasks($previousLog->content);
        $tasks = array_values(array_filter($tasks, function (array $task) {
            return strtolower((string) ($task['progress'] ?? '')) !== 'done';
        }));

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

            $responsibleIds = $task['responsible_user_ids'] ?? [];
            if (!is_array($responsibleIds)) {
                $responsibleIds = [];
            }
            $responsibleIds = array_values(array_unique(array_map('intval', array_filter($responsibleIds, fn ($v) => !empty($v)))));

            if ($taskText === '' && empty($responsibleIds) && $progress === '' && $dueDate === '' && $compilationDate === '' && $notesProgress === '') {
                continue;
            }

            if (!in_array($progress, ['Not Started', 'In Progress', 'Done'], true)) {
                $progress = 'Not Started';
            }

            $normalized[] = [
                'task_uid' => $taskUid !== '' ? $taskUid : ('task_' . uniqid()),
                'task_no' => $taskNo > 0 ? $taskNo : (count($normalized) + 1),
                'task' => $taskText,
                'responsible_user_ids' => $responsibleIds,
                'progress' => $progress,
                'due_date' => $dueDate !== '' ? $dueDate : null,
                'compilation_date' => $compilationDate !== '' ? $compilationDate : null,
                'notes_progress' => $notesProgress,
            ];
        }

        return ['tasks' => $this->renumberTasks($normalized)];
    }

    private function extractTasks($content): array
    {
        if (!is_array($content)) {
            return [];
        }
        $tasks = $content['tasks'] ?? [];
        return is_array($tasks) ? $tasks : [];
    }

    private function mergeResponsibleTaskUpdates(array $existingTasks, array $incomingTasks, int $userId): array
    {
        $incomingByUid = [];
        foreach ($incomingTasks as $task) {
            if (!is_array($task)) {
                continue;
            }
            $uid = trim((string) ($task['task_uid'] ?? ''));
            if ($uid !== '') {
                $incomingByUid[$uid] = $task;
                continue;
            }
            $taskNo = (int) ($task['task_no'] ?? 0);
            if ($taskNo > 0) {
                $incomingByUid['no:' . $taskNo] = $task;
            }
        }

        $merged = [];
        foreach ($existingTasks as $existing) {
            if (!is_array($existing)) {
                continue;
            }
            $responsibleIds = $existing['responsible_user_ids'] ?? [];
            if (!is_array($responsibleIds)) {
                $responsibleIds = [];
            }
            $responsibleIds = array_map('intval', $responsibleIds);
            $isAssigned = in_array($userId, $responsibleIds, true);

            $keyUid = trim((string) ($existing['task_uid'] ?? ''));
            $keyNo = (int) ($existing['task_no'] ?? 0);
            $incoming = $keyUid !== '' ? ($incomingByUid[$keyUid] ?? null) : null;
            if (!$incoming && $keyNo > 0) {
                $incoming = $incomingByUid['no:' . $keyNo] ?? null;
            }

            if ($isAssigned && is_array($incoming)) {
                $progress = trim((string) ($incoming['progress'] ?? ($existing['progress'] ?? 'Not Started')));
                if (!in_array($progress, ['Not Started', 'In Progress', 'Done'], true)) {
                    $progress = (string) ($existing['progress'] ?? 'Not Started');
                }

                $existing['progress'] = $progress;
                $existing['due_date'] = !empty($incoming['due_date']) ? $incoming['due_date'] : null;
                $existing['compilation_date'] = !empty($incoming['compilation_date']) ? $incoming['compilation_date'] : null;
                $existing['notes_progress'] = trim((string) ($incoming['notes_progress'] ?? ($existing['notes_progress'] ?? '')));
            }

            $merged[] = $existing;
        }

        return $merged;
    }

    private function renumberTasks(array $tasks): array
    {
        $counter = 1;
        foreach ($tasks as &$task) {
            if (!is_array($task)) {
                continue;
            }
            $task['task_no'] = $counter++;
        }
        unset($task);

        return array_values($tasks);
    }

    private function isUserAssignedToActionLog(User $user, Document $document): bool
    {
        $content = is_array($document->content) ? $document->content : [];
        $tasks = is_array($content['tasks'] ?? null) ? $content['tasks'] : [];

        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }

            $responsibleIds = $task['responsible_user_ids'] ?? [];
            if (!is_array($responsibleIds)) {
                continue;
            }

            $ids = array_map('intval', $responsibleIds);
            if (in_array((int) $user->id, $ids, true)) {
                return true;
            }
        }

        return false;
    }
}
