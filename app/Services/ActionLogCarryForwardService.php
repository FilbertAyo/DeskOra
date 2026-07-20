<?php

namespace App\Services;

use App\Models\ActionLog;
use App\Models\Document;
use App\Models\WorkPeriod;
use App\Support\ShortId;

class ActionLogCarryForwardService
{
    private const PROGRESS_DONE = 'Done';

    private const PROGRESS_CANCELLED = 'Cancelled';

    /**
     * Collect incomplete tasks from the latest prior action log (immediate previous week when present,
     * otherwise the most recent earlier week that has an action log).
     * Deduplicates by normalized task text and merges responsible_user_ids.
     *
     * @return list<array<string, mixed>>
     */
    public function getIncompleteTasksFromPriorPeriods(WorkPeriod $targetPeriod): array
    {
        $previousLog = $this->getLatestPriorActionLogDocument($targetPeriod);
        if (! $previousLog) {
            return [];
        }

        $byTextKey = [];

        foreach ($this->extractTasks($previousLog->content) as $task) {
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

            $textKey = $this->normalizeTaskTextKey($task['task'] ?? '');
            if ($textKey === '') {
                continue;
            }

            $prepared = $this->prepareTaskForCarryForward($task);

            if (! isset($byTextKey[$textKey])) {
                $byTextKey[$textKey] = $prepared;

                continue;
            }

            $byTextKey[$textKey]['responsible_user_ids'] = $this->mergeResponsibleIds(
                $byTextKey[$textKey]['responsible_user_ids'] ?? [],
                $prepared['responsible_user_ids'] ?? []
            );
        }

        return $this->renumberTasks(array_values($byTextKey));
    }

    /**
     * Merge missing incomplete tasks from the previous period into an existing document.
     *
     * @return array{tasks: list<array<string, mixed>>, added: list<string>, removed: list<string>, changed: bool}
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

        $existingTextKeys = collect($existingTasks)
            ->map(fn ($task) => is_array($task) ? $this->normalizeTaskTextKey($task['task'] ?? '') : '')
            ->filter()
            ->flip()
            ->all();

        $removedUids = array_flip($this->extractRemovedTaskUids($document->content));

        $toCarry = $this->getIncompleteTasksFromPriorPeriods($period);
        $added = [];

        foreach ($toCarry as $carriedTask) {
            $uid = trim((string) ($carriedTask['task_uid'] ?? ''));
            $textKey = $this->normalizeTaskTextKey($carriedTask['task'] ?? '');

            if ($uid === '' || isset($existingUids[$uid]) || isset($removedUids[$uid])) {
                continue;
            }

            if ($textKey !== '' && isset($existingTextKeys[$textKey])) {
                $existingTasks = $this->mergeAssigneesIntoMatchingTextTask($existingTasks, $textKey, $carriedTask);
                $existingUids[$uid] = true;

                continue;
            }

            $existingTasks[] = $carriedTask;
            $existingUids[$uid] = true;
            if ($textKey !== '') {
                $existingTextKeys[$textKey] = true;
            }
            $added[] = trim((string) ($carriedTask['task'] ?? '')) ?: $uid;
        }

        $restoredTasks = $this->restoreMissingResponsibleFromPriorPeriods($existingTasks, $period);
        $finalTasks = $this->renumberTasks($this->dedupeTasksByText($restoredTasks));

        return [
            'tasks' => $finalTasks,
            'added' => $added,
            'removed' => [],
            'changed' => $beforeSnapshot !== json_encode($finalTasks),
        ];
    }

    /**
     * Rebuild current-week tasks: keep true local additions + correct previous-week carry set.
     * Drops wrongly carried rows from older weeks and text duplicates.
     *
     * @return array{tasks: list<array<string, mixed>>, added: list<string>, removed: list<string>, changed: bool}
     */
    public function rebuildCarriedTasks(Document $document, WorkPeriod $period): array
    {
        $existingTasks = $this->extractTasks($document->content);
        $beforeSnapshot = json_encode($existingTasks);

        $removedUids = array_flip($this->extractRemovedTaskUids($document->content));
        $correctCarry = $this->getIncompleteTasksFromPriorPeriods($period);
        $correctCarryByUid = [];
        $correctCarryByText = [];

        foreach ($correctCarry as $task) {
            $uid = trim((string) ($task['task_uid'] ?? ''));
            $textKey = $this->normalizeTaskTextKey($task['task'] ?? '');
            if ($uid !== '') {
                $correctCarryByUid[$uid] = $task;
            }
            if ($textKey !== '') {
                $correctCarryByText[$textKey] = $task;
            }
        }

        $allPriorUids = $this->collectAllPriorPeriodTaskUids($period);
        $kept = [];
        $keptUids = [];
        $keptTextKeys = [];
        $removed = [];

        foreach ($existingTasks as $task) {
            if (! is_array($task)) {
                continue;
            }

            $uid = trim((string) ($task['task_uid'] ?? ''));
            $textKey = $this->normalizeTaskTextKey($task['task'] ?? '');
            $label = trim((string) ($task['task'] ?? '')) ?: $uid;

            if ($uid !== '' && isset($removedUids[$uid])) {
                $removed[] = $label;

                continue;
            }

            $isCorrectCarry = ($uid !== '' && isset($correctCarryByUid[$uid]))
                || ($textKey !== '' && isset($correctCarryByText[$textKey]));
            $isLocalAddition = $uid === '' || ! isset($allPriorUids[$uid]);

            if (! $isCorrectCarry && ! $isLocalAddition) {
                $removed[] = $label;

                continue;
            }

            if ($textKey !== '' && isset($keptTextKeys[$textKey])) {
                $kept = $this->mergeAssigneesIntoMatchingTextTask($kept, $textKey, $task);
                if ($uid !== '') {
                    $keptUids[$uid] = true;
                }

                continue;
            }

            if ($isCorrectCarry && $textKey !== '' && isset($correctCarryByText[$textKey])) {
                $carryTask = $correctCarryByText[$textKey];
                $carryTask['responsible_user_ids'] = $this->mergeResponsibleIds(
                    $carryTask['responsible_user_ids'] ?? [],
                    $task['responsible_user_ids'] ?? []
                );
                $task = $carryTask;
            }

            $kept[] = $task;
            if ($uid !== '') {
                $keptUids[$uid] = true;
            }
            if ($textKey !== '') {
                $keptTextKeys[$textKey] = true;
            }
        }

        $added = [];
        foreach ($correctCarry as $carriedTask) {
            $uid = trim((string) ($carriedTask['task_uid'] ?? ''));
            $textKey = $this->normalizeTaskTextKey($carriedTask['task'] ?? '');

            if ($uid !== '' && isset($removedUids[$uid])) {
                continue;
            }

            if (($uid !== '' && isset($keptUids[$uid])) || ($textKey !== '' && isset($keptTextKeys[$textKey]))) {
                if ($textKey !== '' && isset($keptTextKeys[$textKey])) {
                    $kept = $this->mergeAssigneesIntoMatchingTextTask($kept, $textKey, $carriedTask);
                }

                continue;
            }

            $kept[] = $carriedTask;
            if ($uid !== '') {
                $keptUids[$uid] = true;
            }
            if ($textKey !== '') {
                $keptTextKeys[$textKey] = true;
            }
            $added[] = trim((string) ($carriedTask['task'] ?? '')) ?: $uid;
        }

        $restoredTasks = $this->restoreMissingResponsibleFromPriorPeriods($kept, $period);
        $finalTasks = $this->renumberTasks($this->dedupeTasksByText($restoredTasks));

        return [
            'tasks' => $finalTasks,
            'added' => $added,
            'removed' => array_values(array_unique($removed)),
            'changed' => $beforeSnapshot !== json_encode($finalTasks),
        ];
    }

    /**
     * Restore responsible_user_ids from the latest prior action log when empty on current rows.
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
        $previousLog = $this->getLatestPriorActionLogDocument($targetPeriod);
        if (! $previousLog) {
            return $map;
        }

        foreach ($this->extractTasks($previousLog->content) as $task) {
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
     * Prefer the immediate previous week's action log; if that week was skipped,
     * fall back to the most recent earlier week that has one.
     */
    private function getLatestPriorActionLogDocument(WorkPeriod $targetPeriod): ?Document
    {
        $previousPeriod = $targetPeriod->getPreviousPeriod();
        if ($previousPeriod) {
            $immediate = Document::findCanonicalActionLogForPeriod((string) $previousPeriod->id);
            if ($immediate) {
                return $immediate;
            }
        }

        $priorLog = ActionLog::query()
            ->whereNull('action_logs.superseded_by_action_log_id')
            ->whereNotNull('action_logs.period_id')
            ->where('action_logs.period_id', '!=', $targetPeriod->id)
            ->join('work_periods', 'work_periods.id', '=', 'action_logs.period_id')
            ->whereDate('work_periods.week_start_date', '<', $targetPeriod->week_start_date)
            ->orderByDesc('work_periods.week_start_date')
            ->orderByDesc('action_logs.id')
            ->select('action_logs.*')
            ->first();

        return $priorLog ? Document::fromDomainId((string) $priorLog->id) : null;
    }

    /**
     * All task_uids that appear in any action log before the target period.
     *
     * @return array<string, true>
     */
    private function collectAllPriorPeriodTaskUids(WorkPeriod $targetPeriod): array
    {
        $uids = [];

        $logs = ActionLog::query()
            ->whereNull('superseded_by_action_log_id')
            ->whereNotNull('period_id')
            ->where('period_id', '!=', $targetPeriod->id)
            ->whereHas('period', function ($query) use ($targetPeriod): void {
                $query->whereDate('week_start_date', '<', $targetPeriod->week_start_date);
            })
            ->get();

        foreach ($logs as $log) {
            $document = Document::fromDomainId((string) $log->id);
            if (! $document) {
                continue;
            }

            foreach ($this->extractTasks($document->content) as $task) {
                if (! is_array($task)) {
                    continue;
                }
                $uid = trim((string) ($task['task_uid'] ?? ''));
                if ($uid !== '') {
                    $uids[$uid] = true;
                }
            }
        }

        return $uids;
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @param  array<string, mixed>  $incoming
     * @return list<array<string, mixed>>
     */
    private function mergeAssigneesIntoMatchingTextTask(array $tasks, string $textKey, array $incoming): array
    {
        foreach ($tasks as &$task) {
            if (! is_array($task)) {
                continue;
            }

            if ($this->normalizeTaskTextKey($task['task'] ?? '') !== $textKey) {
                continue;
            }

            $task['responsible_user_ids'] = $this->mergeResponsibleIds(
                $task['responsible_user_ids'] ?? [],
                $incoming['responsible_user_ids'] ?? []
            );

            break;
        }
        unset($task);

        return $tasks;
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @return list<array<string, mixed>>
     */
    private function dedupeTasksByText(array $tasks): array
    {
        $byTextKey = [];

        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }

            $textKey = $this->normalizeTaskTextKey($task['task'] ?? '');
            if ($textKey === '') {
                $byTextKey['__empty_'.count($byTextKey)] = $task;

                continue;
            }

            if (! isset($byTextKey[$textKey])) {
                $task['responsible_user_ids'] = $this->normalizeResponsibleIds($task['responsible_user_ids'] ?? []);
                $byTextKey[$textKey] = $task;

                continue;
            }

            $byTextKey[$textKey]['responsible_user_ids'] = $this->mergeResponsibleIds(
                $byTextKey[$textKey]['responsible_user_ids'] ?? [],
                $task['responsible_user_ids'] ?? []
            );
        }

        return array_values($byTextKey);
    }

    private function normalizeTaskTextKey(mixed $taskText): string
    {
        $text = trim((string) $taskText);
        if ($text === '') {
            return '';
        }

        return mb_strtolower($text);
    }

    /**
     * @param  mixed  $left
     * @param  mixed  $right
     * @return list<string>
     */
    private function mergeResponsibleIds($left, $right): array
    {
        return $this->normalizeResponsibleIds(array_merge(
            is_array($left) ? $left : [],
            is_array($right) ? $right : []
        ));
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
