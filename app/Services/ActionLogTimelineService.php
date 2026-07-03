<?php

namespace App\Services;

use App\Models\ActionLogTaskHistory;
use App\Models\Document;
use App\Models\WorkPeriod;
use App\Support\ActionLogTaskSupport;

class ActionLogTimelineService
{
    /**
     * Build display-ready task rows with cross-week timelines for show/PDF views.
     *
     * @return list<array<string, mixed>>
     */
    public function tasksForPeriodDisplay(Document $document): array
    {
        if ($document->type !== Document::TYPE_ACTION_LOG) {
            return [];
        }

        $content = is_array($document->content) ? $document->content : [];
        $tasks = ActionLogTaskSupport::extractTasks($content);
        $periodId = $document->period_id !== null && $document->period_id !== ''
            ? (string) $document->period_id
            : null;

        $currentPeriod = $periodId !== null ? WorkPeriod::query()->find($periodId) : null;
        $priorResponsibleMap = $currentPeriod
            ? app(ActionLogCarryForwardService::class)->buildPriorResponsibleMap($currentPeriod)
            : [];

        $result = [];
        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }

            if ($periodId === null || $currentPeriod === null) {
                $task['display_notes_progress'] = trim((string) ($task['notes_progress'] ?? ''));
                $task['display_progress'] = trim((string) ($task['progress'] ?? 'Not Started')) ?: 'Not Started';
                $task['display_history'] = is_array($task['history'] ?? null)
                    ? array_values(array_filter($task['history'], static fn ($e) => is_array($e)))
                    : [];
                $result[] = $task;

                continue;
            }

            $taskUid = trim((string) ($task['task_uid'] ?? ''));
            $aggregatedByPeriod = $this->aggregateHistoryByPeriod($taskUid, $periodId, $currentPeriod);
            $task['display_history'] = $this->buildDisplayTimeline($aggregatedByPeriod, $currentPeriod);

            $currentEntry = $aggregatedByPeriod[$periodId] ?? null;
            if (is_array($currentEntry)) {
                $task['display_notes_progress'] = trim((string) ($currentEntry['notes_progress'] ?? ''));
                $toProgress = trim((string) ($currentEntry['to_progress'] ?? ''));
                $task['display_progress'] = $toProgress !== '' ? $toProgress : 'Not Started';
            } elseif ($task['display_history'] === []) {
                $task['display_notes_progress'] = trim((string) ($task['notes_progress'] ?? ''));
                $task['display_progress'] = trim((string) ($task['progress'] ?? 'Not Started')) ?: 'Not Started';
            } else {
                $task['display_notes_progress'] = '';
                $task['display_progress'] = null;
            }

            $responsibleIds = ActionLogTaskSupport::normalizeResponsibleIds($task['responsible_user_ids'] ?? []);
            if ($responsibleIds === [] && $taskUid !== '' && isset($priorResponsibleMap[$taskUid])) {
                $task['responsible_user_ids'] = $priorResponsibleMap[$taskUid];
            }

            $result[] = $task;
        }

        return $result;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function aggregateHistoryByPeriod(string $taskUid, string $currentPeriodId, WorkPeriod $currentPeriod): array
    {
        if ($taskUid === '') {
            return [];
        }

        $rows = ActionLogTaskHistory::query()
            ->select([
                'action_log_task_history.*',
                'action_logs.period_id as log_period_id',
            ])
            ->join('action_log_tasks', 'action_log_tasks.id', '=', 'action_log_task_history.action_log_task_id')
            ->join('action_logs', 'action_logs.id', '=', 'action_log_tasks.action_log_id')
            ->join('work_periods', 'work_periods.id', '=', 'action_logs.period_id')
            ->where('action_log_tasks.task_uid', $taskUid)
            ->whereNull('action_logs.deleted_at')
            ->whereNull('action_logs.superseded_by_action_log_id')
            ->whereDate('work_periods.week_start_date', '<=', $currentPeriod->week_start_date)
            ->orderBy('action_log_task_history.changed_at')
            ->get();

        $collected = [];
        foreach ($rows as $row) {
            $entry = [
                'period_id' => (string) ($row->period_id ?: $row->log_period_id),
                'period_label' => (string) ($row->period_label ?? ''),
                'updated_by' => (string) ($row->updated_by ?? ''),
                'from_progress' => (string) ($row->from_progress ?? ''),
                'to_progress' => (string) ($row->to_progress ?? ''),
                'notes_progress' => (string) ($row->notes_progress ?? ''),
                'status_reason' => (string) ($row->status_reason ?? ''),
                'changed_at' => (string) ($row->changed_at ?? ''),
                'source' => (string) ($row->source ?? ''),
                'reassigned_from' => is_array($row->reassigned_from_user_ids) ? $row->reassigned_from_user_ids : [],
                'reassigned_to' => is_array($row->reassigned_to_user_ids)
                    ? (string) ($row->reassigned_to_user_ids[0] ?? '')
                    : '',
            ];

            if (! ActionLogTaskSupport::isRealHistoryEntry($entry)) {
                continue;
            }

            $periodId = trim((string) ($entry['period_id'] ?? ''));
            if ($periodId === '') {
                continue;
            }

            $existing = $collected[$periodId] ?? null;
            if ($existing === null
                || (strtotime((string) ($entry['changed_at'] ?? '')) ?: 0)
                    >= (strtotime((string) ($existing['changed_at'] ?? '')) ?: 0)
            ) {
                $collected[$periodId] = $entry;
            }
        }

        return $collected;
    }

    /**
     * @param  array<string, array<string, mixed>>  $entriesByPeriod
     * @return list<array<string, mixed>>
     */
    private function buildDisplayTimeline(array $entriesByPeriod, WorkPeriod $currentPeriod): array
    {
        if ($entriesByPeriod === []) {
            return [];
        }

        $periodIds = array_keys($entriesByPeriod);
        $firstPeriod = WorkPeriod::query()
            ->whereIn('id', $periodIds)
            ->orderBy('week_start_date')
            ->first();

        if (! $firstPeriod) {
            return [];
        }

        $firstStartTs = strtotime((string) $firstPeriod->week_start_date) ?: 0;
        $currentStartTs = strtotime((string) $currentPeriod->week_start_date) ?: 0;
        if ($currentStartTs === 0 || $firstStartTs > $currentStartTs) {
            $firstPeriod = $currentPeriod;
        }

        $periods = WorkPeriod::query()
            ->whereDate('week_start_date', '>=', $firstPeriod->week_start_date)
            ->whereDate('week_start_date', '<=', $currentPeriod->week_start_date)
            ->orderBy('week_start_date')
            ->get();

        if ($periods->isEmpty()) {
            return [];
        }

        $timeline = [];
        foreach ($periods as $period) {
            $periodId = (string) $period->id;
            $entry = $entriesByPeriod[$periodId] ?? null;
            if (! is_array($entry)) {
                $timeline[] = $this->emptyTimelineEntry($period);

                continue;
            }

            $entry['period_label'] = ! empty($entry['period_label'])
                ? $entry['period_label']
                : 'Week '.$period->week_number.' of '.$period->year;
            $entry['is_empty'] = false;
            $timeline[] = $entry;
        }

        return $timeline;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyTimelineEntry(WorkPeriod $period): array
    {
        return [
            'period_id' => (string) $period->id,
            'period_label' => 'Week '.$period->week_number.' of '.$period->year,
            'updated_by' => null,
            'from_progress' => null,
            'to_progress' => null,
            'notes_progress' => null,
            'status_reason' => null,
            'changed_at' => null,
            'is_empty' => true,
        ];
    }
}
