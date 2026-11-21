<?php

namespace App\Services;

use App\Models\ActionLog;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\Documents\DocumentContentAssembler;
use App\Support\ActionLogTaskSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ActionLogProgressService
{
    public const UPDATE_SNOOZE_SESSION_KEY = 'action_log_update_snooze';

    /** @var array<string, array<string, mixed>> */
    private array $buildForUserCache = [];

    public function __construct(
        private readonly ActionLogCarryForwardService $actionLogCarryForwardService,
    ) {}

    /**
     * @return array{activeTasks: array<int, array<string, mixed>>, currentDraftId: int|null, showNotifications: bool}
     */
    public function buildForUser(User $user): array
    {
        $cacheKey = (string) $user->id;
        if (isset($this->buildForUserCache[$cacheKey])) {
            return $this->buildForUserCache[$cacheKey];
        }

        $canManageActionLogs = $user->hasPermissionTo('manage action logs');
        $currentPeriod = WorkPeriod::getCurrent();

        $actionLogQuery = ActionLog::query()
            ->with(['period', 'tasks.assignees', 'tasks.history'])
            ->whereNull('superseded_by_action_log_id')
            ->whereNotNull('period_id');

        if ($canManageActionLogs) {
            $actionLogQuery->where(function (Builder $query) use ($currentPeriod): void {
                if ($currentPeriod) {
                    $query->where('period_id', $currentPeriod->id);
                }
                $query->orWhere(function (Builder $draftQuery): void {
                    $draftQuery->where('state', Document::STATE_DRAFT)
                        ->whereHas('period', fn (Builder $periodQuery) => $periodQuery->where('status', WorkPeriod::STATUS_OPEN));
                });
            });
        } else {
            $actionLogQuery
                ->where('state', Document::STATE_SUBMITTED)
                ->whereHas('tasks.assignees', fn (Builder $query) => $query->where('users.id', $user->id));
        }

        $assembler = app(DocumentContentAssembler::class);
        $documents = $actionLogQuery->get()
            ->map(function (ActionLog $log) use ($assembler) {
                $document = Document::fromActionLogModel($log);
                $document->setRelation('period', $log->period);
                $document->setAttribute('content', $assembler->assembleActionLogContent($log));

                return $document;
            })
            ->values();

        if ($documents->isEmpty()) {
            return $this->buildForUserCache[$cacheKey] = [
                'activeTasks' => [],
                'currentDraftId' => null,
                'showNotifications' => false,
            ];
        }

        $selectedByPeriod = $documents
            ->groupBy('period_id')
            ->map(function ($periodDocs) {
                return $periodDocs
                    ->sortByDesc(function ($doc) {
                        $isDraft = $doc->state === Document::STATE_DRAFT ? 1 : 0;
                        $updatedAt = optional($doc->updated_at)->timestamp ?? 0;

                        return [$updatedAt, $isDraft, $doc->id];
                    })
                    ->first();
            })
            ->filter()
            ->sortBy(fn ($doc) => optional($doc->period)->week_start_date?->timestamp ?? 0)
            ->values();

        $priorResponsibleMap = $currentPeriod
            ? $this->actionLogCarryForwardService->buildPriorResponsibleMap($currentPeriod)
            : [];
        $currentDraftDoc = $currentPeriod
            ? $selectedByPeriod->first(fn ($doc) => $doc->period_id === $currentPeriod->id && $doc->isDraft())
            : null;

        $focusPeriodDocuments = $selectedByPeriod;
        if ($currentPeriod) {
            $currentPeriodDocs = $selectedByPeriod
                ->filter(fn ($doc) => $doc->period_id === $currentPeriod->id)
                ->values();

            if ($currentPeriodDocs->isNotEmpty()) {
                $focusPeriodDocuments = $currentPeriodDocs;
            }
        }

        $taskTimeline = [];
        foreach ($focusPeriodDocuments as $document) {
            if (! $document || ! $document->period) {
                continue;
            }

            $tasks = ActionLogTaskSupport::extractTasks($document->content);
            if ($tasks === []) {
                continue;
            }

            foreach ($tasks as $task) {
                if (! is_array($task)) {
                    continue;
                }

                $responsibleIds = ActionLogTaskSupport::normalizeResponsibleIds($task['responsible_user_ids'] ?? []);
                $taskUid = trim((string) ($task['task_uid'] ?? ''));
                if ($responsibleIds === [] && $taskUid !== '' && isset($priorResponsibleMap[$taskUid])) {
                    $responsibleIds = $priorResponsibleMap[$taskUid];
                }
                if (! in_array((string) $user->id, $responsibleIds, true)) {
                    continue;
                }

                $taskLabel = trim((string) ($task['task'] ?? ''));
                if ($taskLabel === '') {
                    continue;
                }

                $hasRealUpdateThisPeriod = $currentPeriod
                    && (string) $document->period_id === (string) $currentPeriod->id
                    && ActionLogTaskSupport::hasRealUpdateInPeriod($task, (string) $currentPeriod->id);
                $carriedFromLabel = null;
                if ($taskUid !== '' && $currentPeriod && (string) $document->period_id === (string) $currentPeriod->id) {
                    $carriedFromLabel = $this->findLatestPriorPeriodLabelForTask($taskUid, $selectedByPeriod, $currentPeriod);
                }

                $taskKey = $taskUid !== '' ? $taskUid : md5($taskLabel.'|'.implode(',', $responsibleIds));

                $taskTimeline[$taskKey]['task'] = $taskLabel;
                $taskTimeline[$taskKey]['task_uid'] = $taskUid;
                $taskTimeline[$taskKey]['history'][] = [
                    'period_label' => 'Week '.$document->period->week_number.' of '.$document->period->year,
                    'period_range' => $document->period->date_range,
                    'progress' => $task['progress'] ?? 'Not Started',
                    'notes_progress' => $task['notes_progress'] ?? '',
                    'due_date' => $task['due_date'] ?? null,
                    'compilation_date' => $task['compilation_date'] ?? null,
                    'is_current_period' => $currentPeriod && $document->period_id === $currentPeriod->id,
                    'period_open' => (bool) $document->period->isOpen(),
                    'document_id' => $document->id,
                    'has_real_update' => $hasRealUpdateThisPeriod,
                    'carried_from_period_label' => $carriedFromLabel,
                ];
            }
        }

        $activeTasks = collect($taskTimeline)
            ->map(function ($taskData) {
                $history = collect($taskData['history'] ?? [])->values();
                $latest = $history->last();
                if (! $latest) {
                    return null;
                }

                $taskUid = trim((string) ($taskData['task_uid'] ?? ''));
                $documentId = $latest['document_id'] ?? null;
                $periodOpen = (bool) ($latest['period_open'] ?? false);
                $isCurrentPeriod = (bool) ($latest['is_current_period'] ?? false);
                $hasRealUpdate = (bool) ($latest['has_real_update'] ?? false);
                $needsUpdate = $isCurrentPeriod && $periodOpen && ! $hasRealUpdate;
                $editable = $taskUid !== ''
                    && ! empty($documentId)
                    && $periodOpen;

                $latestProgress = $latest['progress'] ?? 'Not Started';
                if ($needsUpdate && $history->count() > 1) {
                    $prior = $history->slice(0, -1)->last();
                    $priorProgress = trim((string) ($prior['progress'] ?? ''));
                    if ($priorProgress !== '' && ! in_array($priorProgress, ['Done', 'Cancelled'], true)) {
                        $latestProgress = $priorProgress;
                    }
                }

                return [
                    'task' => $taskData['task'] ?? '',
                    'task_uid' => $taskUid,
                    'document_id' => $documentId,
                    'period_label' => $latest['period_label'] ?? '',
                    'latest_progress' => $latestProgress,
                    'latest_notes' => (string) ($latest['notes_progress'] ?? ''),
                    'editable' => $editable,
                    'needs_update' => $needsUpdate,
                    'carried_from_period_label' => $latest['carried_from_period_label'] ?? null,
                    'history' => $history->all(),
                ];
            })
            ->filter(function ($row) {
                if (! $row) {
                    return false;
                }

                $latestProgress = trim((string) ($row['latest_progress'] ?? 'Not Started'));

                return ! in_array($latestProgress, ['Done', 'Cancelled'], true);
            })
            ->sortBy('task')
            ->values()
            ->all();

        return $this->buildForUserCache[$cacheKey] = [
            'activeTasks' => $activeTasks,
            'currentDraftId' => $canManageActionLogs ? $currentDraftDoc?->id : null,
            'showNotifications' => ! empty($activeTasks),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pendingUpdatesForUser(User $user): array
    {
        $progress = $this->buildForUser($user);

        return array_values(array_filter(
            $progress['activeTasks'] ?? [],
            fn (array $task) => ! empty($task['needs_update'])
        ));
    }

    public function hasPendingUpdates(User $user): bool
    {
        return $this->pendingUpdatesForUser($user) !== [];
    }

    /**
     * Current-week assignment snapshot for one action log.
     * Used by the scorecard and the update reminder so those screens do not
     * rebuild the full task timeline.
     *
     * @return array{
     *     period_id: ?string,
     *     week: int|string|null,
     *     year: int|string|null,
     *     date_range: ?string,
     *     open: bool,
     *     document_id: ?string,
     *     open_count: int,
     *     needs_update: int,
     *     updated_by_you: int,
     *     assigned: int,
     *     pending_tasks: list<array{task: string, progress: string}>
     * }
     */
    public function weekStatusFor(User $user, ?WorkPeriod $period = null): array
    {
        $period = $period ?? WorkPeriod::getCurrent();
        if (! $period) {
            return $this->emptyWeekStatus(null);
        }

        $stamp = ActionLog::query()
            ->where('period_id', $period->id)
            ->whereNull('superseded_by_action_log_id')
            ->orderByDesc('id')
            ->first(['id', 'updated_at']);

        $key = 'action-log-week-status:'.$user->id.':'.$period->id.':'
            .($stamp->id ?? '0').':'
            .($stamp?->updated_at?->getTimestamp() ?? 0);

        return Cache::remember($key, now()->addMinutes(5), fn () => $this->buildWeekStatus($user, $period));
    }

    /**
     * @return array{
     *     week: int|string|null,
     *     year: int|string|null,
     *     dateRange: ?string,
     *     pending: int,
     *     updated: int,
     *     tasks: list<array{task: string, progress: string}>,
     *     boardUrl: string
     * }|null
     */
    public function reminderPayload(User $user): ?array
    {
        if (($user->status ?? '') !== 'active' || $user->isDemoViewer()) {
            return null;
        }

        $status = $this->weekStatusFor($user);
        if (! $status['open'] || $status['assigned'] < 1 || $status['needs_update'] < 1 || $status['document_id'] === null) {
            return null;
        }

        return [
            'week' => $status['week'],
            'year' => $status['year'],
            'dateRange' => $status['date_range'],
            'pending' => $status['needs_update'],
            'updated' => $status['updated_by_you'],
            'tasks' => $status['pending_tasks'],
            'boardUrl' => route('action-logs.board', ['focus' => ActionLogTaskSupport::STATE_NEEDS_UPDATE]),
        ];
    }

    /**
     * @return array{
     *     period_id: ?string,
     *     week: int|string|null,
     *     year: int|string|null,
     *     date_range: ?string,
     *     open: bool,
     *     document_id: ?string,
     *     open_count: int,
     *     needs_update: int,
     *     updated_by_you: int,
     *     assigned: int,
     *     pending_tasks: list<array{task: string, progress: string}>
     * }
     */
    private function emptyWeekStatus(?WorkPeriod $period): array
    {
        return [
            'period_id' => $period ? (string) $period->id : null,
            'week' => $period?->week_number,
            'year' => $period?->year,
            'date_range' => $period?->date_range,
            'open' => $period ? $period->isOpen() : false,
            'document_id' => null,
            'open_count' => 0,
            'needs_update' => 0,
            'updated_by_you' => 0,
            'assigned' => 0,
            'pending_tasks' => [],
        ];
    }

    /**
     * @return array{
     *     period_id: ?string,
     *     week: int|string|null,
     *     year: int|string|null,
     *     date_range: ?string,
     *     open: bool,
     *     document_id: ?string,
     *     open_count: int,
     *     needs_update: int,
     *     updated_by_you: int,
     *     assigned: int,
     *     pending_tasks: list<array{task: string, progress: string}>
     * }
     */
    private function buildWeekStatus(User $user, WorkPeriod $period): array
    {
        $status = $this->emptyWeekStatus($period);
        $document = Document::findCanonicalActionLogForPeriod((string) $period->id);
        if (! $document) {
            return $status;
        }

        $viewerId = (string) $user->id;
        $periodId = (string) $period->id;
        $periodOpen = $period->isOpen();
        $pending = [];

        foreach (ActionLogTaskSupport::extractTasks($document->content) as $task) {
            if (! is_array($task)) {
                continue;
            }

            $label = trim((string) ($task['task'] ?? ''));
            if ($label === '') {
                continue;
            }

            $responsible = ActionLogTaskSupport::normalizeResponsibleIds($task['responsible_user_ids'] ?? []);
            if (! in_array($viewerId, $responsible, true)) {
                continue;
            }

            $status['assigned']++;
            $progress = trim((string) ($task['progress'] ?? ActionLogTaskSupport::PROGRESS_NOT_STARTED));
            if ($progress === '') {
                $progress = ActionLogTaskSupport::PROGRESS_NOT_STARTED;
            }
            if (! in_array($progress, [ActionLogTaskSupport::PROGRESS_DONE, ActionLogTaskSupport::PROGRESS_CANCELLED], true)) {
                $status['open_count']++;
            }

            $state = ActionLogTaskSupport::updateState($task, $periodId, $viewerId, $periodOpen);
            if ($state === ActionLogTaskSupport::STATE_NEEDS_UPDATE) {
                $status['needs_update']++;
                if (count($pending) < 6) {
                    $pending[] = [
                        'task' => $label,
                        'progress' => $progress,
                    ];
                }
            }
            if ($state === ActionLogTaskSupport::STATE_UPDATED_BY_YOU) {
                $status['updated_by_you']++;
            }
        }

        $status['document_id'] = (string) $document->id;
        $status['pending_tasks'] = $pending;

        return $status;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Document>  $documentsByPeriod
     */
    private function findLatestPriorPeriodLabelForTask(
        string $taskUid,
        $documentsByPeriod,
        WorkPeriod $currentPeriod
    ): ?string {
        $label = null;
        foreach ($documentsByPeriod as $document) {
            if (! $document->period || $document->period_id === $currentPeriod->id) {
                continue;
            }
            if ($document->period->week_start_date >= $currentPeriod->week_start_date) {
                continue;
            }

            $tasks = ActionLogTaskSupport::extractTasks($document->content);
            foreach ($tasks as $task) {
                if (! is_array($task)) {
                    continue;
                }
                if (trim((string) ($task['task_uid'] ?? '')) !== $taskUid) {
                    continue;
                }

                $label = 'Week '.$document->period->week_number.' of '.$document->period->year;
            }
        }

        return $label;
    }
}
