<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\Documents\DocumentDomainSyncService;

class ActionLogProgressService
{
    public function __construct(
        private readonly ActionLogCarryForwardService $actionLogCarryForwardService,
        private readonly DocumentDomainSyncService $documentDomainSyncService,
    ) {}

    /**
     * @return array{activeTasks: array<int, array<string, mixed>>, currentDraftId: int|null, showNotifications: bool}
     */
    public function buildForUser(User $user): array
    {
        $this->syncCurrentPeriodActionLogIfNeeded();

        $canManageActionLogs = $user->hasPermissionTo('manage action logs');
        $documentsQuery = Document::with('period')
            ->actionLogs()
            ->whereNotNull('period_id');

        $documents = $canManageActionLogs
            ? $documentsQuery->get()
            : $documentsQuery->where('state', Document::STATE_SUBMITTED)->get()
                ->filter(fn (Document $document) => $this->isUserAssignedToActionLog($user, $document))->values();

        if ($documents->isEmpty()) {
            return [
                'activeTasks' => [],
                'currentDraftId' => null,
                'showNotifications' => false,
            ];
        }

        // Keep one document per period: prefer the latest updated record,
        // and break ties toward drafts so in-progress staff updates are reflected.
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

        $currentPeriod = WorkPeriod::getCurrent();
        $priorResponsibleMap = $currentPeriod
            ? $this->actionLogCarryForwardService->buildPriorResponsibleMap($currentPeriod)
            : [];
        $currentDraftDoc = $currentPeriod
            ? $selectedByPeriod->first(fn ($doc) => $doc->period_id === $currentPeriod->id && $doc->isDraft())
            : null;

        // Focus tasks should reflect what is still pending now.
        // Prefer current-week action log rows; fall back to historical timeline only if current week has none.
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

            $content = is_array($document->content) ? $document->content : [];
            $tasks = is_array($content['tasks'] ?? null) ? $content['tasks'] : [];
            if (empty($tasks)) {
                continue;
            }

            foreach ($tasks as $task) {
                if (! is_array($task)) {
                    continue;
                }

                $responsibleIds = $task['responsible_user_ids'] ?? [];
                if (! is_array($responsibleIds)) {
                    $responsibleIds = [];
                }
                $responsibleIds = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $responsibleIds)));
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
                    && $this->taskHasRealUpdateInPeriod($task, (string) $currentPeriod->id);
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

        return [
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

    private function syncCurrentPeriodActionLogIfNeeded(): void
    {
        $currentPeriod = WorkPeriod::getCurrent();
        if (! $currentPeriod || ! $currentPeriod->isOpen()) {
            return;
        }

        $currentDoc = Document::query()
            ->actionLogs()
            ->where('period_id', $currentPeriod->id)
            ->latest('id')
            ->first();

        if (! $currentDoc) {
            return;
        }

        $sync = $this->actionLogCarryForwardService->syncMissingCarriedTasks($currentDoc, $currentPeriod);
        if (! $sync['changed']) {
            return;
        }

        $content = is_array($currentDoc->content) ? $currentDoc->content : [];
        $content['tasks'] = $sync['tasks'];

        $currentDoc->update([
            'content' => $content,
        ]);
        $this->documentDomainSyncService->syncFromDocument($currentDoc->fresh());
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

            $content = is_array($document->content) ? $document->content : [];
            $tasks = is_array($content['tasks'] ?? null) ? $content['tasks'] : [];
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

    /**
     * @param  array<string, mixed>  $task
     */
    private function taskHasRealUpdateInPeriod(array $task, string $periodId): bool
    {
        $history = is_array($task['history'] ?? null) ? $task['history'] : [];
        foreach ($history as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            if (trim((string) ($entry['period_id'] ?? '')) !== $periodId) {
                continue;
            }

            $source = trim((string) ($entry['source'] ?? ''));
            if ($source === 'auto_init') {
                continue;
            }
            if ($source === 'update') {
                return true;
            }

            $notes = trim((string) ($entry['notes_progress'] ?? ''));
            $reason = trim((string) ($entry['status_reason'] ?? ''));
            if ($notes !== '' || $reason !== '') {
                return true;
            }

            $fromProgress = trim((string) ($entry['from_progress'] ?? ''));
            $toProgress = trim((string) ($entry['to_progress'] ?? ''));

            if ($fromProgress !== '' && $toProgress !== '' && $fromProgress !== $toProgress) {
                return true;
            }
        }

        return false;
    }

    private function isUserAssignedToActionLog(User $user, Document $document): bool
    {
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
