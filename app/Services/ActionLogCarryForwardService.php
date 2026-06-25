<?php

namespace App\Services;

use App\Models\Document;
use App\Models\WorkPeriod;
use App\Support\ShortId;

class ActionLogCarryForwardService
{
    private const PROGRESS_NOT_STARTED = 'Not Started';

    private const PROGRESS_IN_PROGRESS = 'In Progress';

    private const PROGRESS_DONE = 'Done';

    private const PROGRESS_CANCELLED = 'Cancelled';

    /**
     * Collect incomplete tasks from all prior work periods (newest occurrence per task_uid).
     *
     * @return list<array<string, mixed>>
     */
    public function getIncompleteTasksFromPriorPeriods(WorkPeriod $targetPeriod): array
    {
        $priorLogs = $this->getPriorActionLogDocuments($targetPeriod);
        if ($priorLogs->isEmpty()) {
            return [];
        }

        $byUid = [];

        foreach ($priorLogs as $document) {
            $tasks = $this->extractTasks($document->content);
            foreach ($tasks as $task) {
                if (! is_array($task)) {
                    continue;
                }

                $taskUid = trim((string) ($task['task_uid'] ?? ''));
                if ($taskUid === '') {
                    continue;
                }

                $progress = trim((string) ($task['progress'] ?? ''));
                if (in_array($progress, [self::PROGRESS_DONE, self::PROGRESS_CANCELLED], true)) {
                    continue;
                }

                if (! isset($byUid[$taskUid])) {
                    $byUid[$taskUid] = $this->prepareTaskForCarryForward($task);
                }
            }
        }

        return $this->renumberTasks(array_values($byUid));
    }

    /**
     * Merge missing incomplete tasks from prior periods into an existing document.
     *
     * @return array{tasks: list<array<string, mixed>>, added: list<string>, changed: bool}
     */
    public function syncMissingCarriedTasks(Document $document, WorkPeriod $period): array
    {
        $existingTasks = $this->extractTasks($document->content);
        $beforeSnapshot = json_encode($existingTasks);

        $existingUids = collect($existingTasks)
            ->map(fn ($task) => is_array($task) ? trim((string) ($task['task_uid'] ?? '')) : '')
            ->filter()
            ->flip()
            ->all();

        $removedUids = array_flip($this->extractRemovedTaskUids($document->content));

        $toCarry = $this->getIncompleteTasksFromPriorPeriods($period);
        $added = [];

        foreach ($toCarry as $carriedTask) {
            $uid = trim((string) ($carriedTask['task_uid'] ?? ''));
            if ($uid === '' || isset($existingUids[$uid]) || isset($removedUids[$uid])) {
                continue;
            }

            $existingTasks[] = $carriedTask;
            $existingUids[$uid] = true;
            $added[] = trim((string) ($carriedTask['task'] ?? '')) ?: $uid;
        }

        $restoredTasks = $this->restoreMissingResponsibleFromPriorPeriods($existingTasks, $period);

        return [
            'tasks' => $this->renumberTasks($restoredTasks),
            'added' => $added,
            'changed' => $beforeSnapshot !== json_encode($restoredTasks),
        ];
    }

    /**
     * Restore responsible_user_ids from the most recent prior week when empty on current rows.
     *
     * @param  list<array<string, mixed>>  $tasks
     * @return list<array<string, mixed>>
     */
    public function restoreMissingResponsibleFromPriorPeriods(array $tasks, WorkPeriod $targetPeriod): array
    {
        $priorResponsible = $this->buildPriorResponsibleMap($targetPeriod);

        foreach ($tasks as &$task) {
            if (! is_array($task)) {
                continue;
            }

            $uids = $this->normalizeResponsibleIds($task['responsible_user_ids'] ?? []);
            if ($uids !== []) {
                $task['responsible_user_ids'] = $uids;

                continue;
            }

            $taskUid = trim((string) ($task['task_uid'] ?? ''));
            if ($taskUid !== '' && isset($priorResponsible[$taskUid])) {
                $task['responsible_user_ids'] = $priorResponsible[$taskUid];
            }
        }
        unset($task);

        return $tasks;
    }

    /**
     * @return array<string, list<string>>
     */
    public function buildPriorResponsibleMap(WorkPeriod $targetPeriod): array
    {
        $map = [];
        $priorLogs = $this->getPriorActionLogDocuments($targetPeriod);

        foreach ($priorLogs as $document) {
            foreach ($this->extractTasks($document->content) as $task) {
                if (! is_array($task)) {
                    continue;
                }

                $taskUid = trim((string) ($task['task_uid'] ?? ''));
                if ($taskUid === '' || isset($map[$taskUid])) {
                    continue;
                }

                $responsibleIds = $this->normalizeResponsibleIds($task['responsible_user_ids'] ?? []);
                if ($responsibleIds !== []) {
                    $map[$taskUid] = $responsibleIds;
                }
            }
        }

        return $map;
    }

    /**
     * Task UIDs a manager explicitly removed from the current week's log — do not carry forward again.
     *
     * @return list<string>
     */
    public function extractRemovedTaskUids(mixed $content): array
    {
        if (! is_array($content)) {
            return [];
        }

        $meta = is_array($content['meta'] ?? null) ? $content['meta'] : [];
        $removed = is_array($meta['removed_task_uids'] ?? null) ? $meta['removed_task_uids'] : [];

        return array_values(array_unique(array_filter(array_map(
            static fn ($uid) => trim((string) $uid),
            $removed
        ))));
    }

    /**
     * @return \Illuminate\Support\Collection<int, Document>
     */
    private function getPriorActionLogDocuments(WorkPeriod $targetPeriod): \Illuminate\Support\Collection
    {
        return Document::query()
            ->with('period')
            ->ofType(Document::TYPE_ACTION_LOG)
            ->whereNotNull('period_id')
            ->where('period_id', '!=', $targetPeriod->id)
            ->whereHas('period', function ($query) use ($targetPeriod): void {
                $query->whereDate('week_start_date', '<', $targetPeriod->week_start_date);
            })
            ->get()
            ->sortByDesc(function (Document $doc) {
                $period = $doc->period;
                $start = $period?->week_start_date?->timestamp ?? 0;
                $isSubmitted = $doc->state === Document::STATE_SUBMITTED ? 1 : 0;

                return [$start, $isSubmitted, $doc->id];
            })
            ->groupBy('period_id')
            ->map(function ($periodDocs) {
                return $periodDocs
                    ->sortByDesc(function (Document $doc) {
                        $isSubmitted = $doc->state === Document::STATE_SUBMITTED ? 1 : 0;
                        $updatedAt = optional($doc->updated_at)->timestamp ?? 0;

                        return [$updatedAt, $isSubmitted, $doc->id];
                    })
                    ->first();
            })
            ->filter()
            ->sortByDesc(fn (Document $doc) => $doc->period?->week_start_date?->timestamp ?? 0)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    private function prepareTaskForCarryForward(array $task): array
    {
        $task['notes_progress'] = '';
        $task['history'] = [];
        $task['status_reason'] = null;
        $task['responsible_user_ids'] = $this->normalizeResponsibleIds($task['responsible_user_ids'] ?? []);

        return $task;
    }

    /**
     * @param  mixed  $content
     * @return list<array<string, mixed>>
     */
    private function extractTasks($content): array
    {
        if (! is_array($content)) {
            return [];
        }

        $tasks = $content['tasks'] ?? [];

        return is_array($tasks) ? $tasks : [];
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

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @return list<array<string, mixed>>
     */
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
}
