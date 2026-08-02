<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use App\Models\WeeklyWorkLog;
use App\Models\WorkPeriod;
use App\Support\ActionLogTaskSupport;
use Illuminate\Support\Str;

class WeeklyWorkLogActionBridgeService
{
    public function createActionFromBlocker(WeeklyWorkLog $log, User $actor): Document
    {
        if (! $log->hasBlocker()) {
            throw new \RuntimeException('Add a blocker before creating an action item.');
        }

        if ($log->hasLinkedAction()) {
            throw new \RuntimeException('An action item is already linked to this work log.');
        }

        $period = $log->period ?? WorkPeriod::query()->find($log->period_id);
        if (! $period) {
            throw new \RuntimeException('Work period not found for this log.');
        }

        $document = Document::findCanonicalActionLogForPeriod((string) $period->id);

        if (! $document) {
            $document = Document::create([
                'user_id' => $actor->id,
                'period_id' => $period->id,
                'type' => Document::TYPE_ACTION_LOG,
                'title' => 'Action Log — Week '.$period->week_number.', '.$period->year,
                'state' => Document::STATE_DRAFT,
                'content' => [
                    'tasks' => [],
                ],
            ]);
        }

        $tasks = ActionLogTaskSupport::extractTasks($document->content);
        $taskUid = 'task_'.Str::lower((string) Str::ulid());

        $tasks[] = [
            'task_uid' => $taskUid,
            'task_no' => count($tasks) + 1,
            'task' => 'Blocker: '.trim((string) $log->blocker),
            'responsible_user_ids' => [(string) $log->user_id],
            'progress' => ActionLogTaskSupport::PROGRESS_NOT_STARTED,
            'due_date' => null,
            'compilation_date' => null,
            'notes_progress' => 'From daily work log on '.$log->log_date?->format('Y-m-d')
                .($log->done ? '. Done that day: '.Str::limit(trim((string) $log->done), 200) : ''),
            'status_reason' => '',
            'history' => [],
            'source_weekly_work_log_id' => (string) $log->id,
        ];

        $content = is_array($document->content) ? $document->content : [];
        $content['tasks'] = array_values($tasks);
        $document->update(['content' => $content]);

        $log->update(['action_task_uid' => $taskUid]);

        return $document->fresh() ?? $document;
    }
}
