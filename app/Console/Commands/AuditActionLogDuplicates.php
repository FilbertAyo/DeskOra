<?php

namespace App\Console\Commands;

use App\Models\ActionLog;
use App\Models\Document;
use App\Services\Documents\DocumentContentAssembler;
use App\Support\ActionLogTaskSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class AuditActionLogDuplicates extends Command
{
    protected $signature = 'action-logs:audit-duplicates
                            {--apply : Merge duplicates into a canonical log per period}';

    protected $description = 'List or merge duplicate action logs sharing the same work period';

    public function __construct(
        private readonly DocumentContentAssembler $assembler,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $groups = ActionLog::query()
            ->whereNull('deleted_at')
            ->whereNull('superseded_by_action_log_id')
            ->whereNotNull('period_id')
            ->get()
            ->groupBy('period_id')
            ->filter(fn (Collection $logs) => $logs->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('No duplicate action logs found.');

            return Command::SUCCESS;
        }

        $this->warn('Found '.$groups->count().' period(s) with duplicate action logs.');

        foreach ($groups as $periodId => $logs) {
            $canonical = $this->pickCanonicalLog($logs);
            $this->line('');
            $this->info('Period '.$periodId.' — keeping '.$canonical->id.' ('.$canonical->state.')');

            foreach ($logs as $log) {
                $taskCount = $log->tasks()->count();
                $marker = $log->id === $canonical->id ? '[KEEP]' : '[MERGE]';
                $this->line(sprintf(
                    '  %s %s state=%s tasks=%d updated=%s',
                    $marker,
                    $log->id,
                    $log->state,
                    $taskCount,
                    optional($log->updated_at)?->toDateTimeString() ?? 'n/a'
                ));
            }

            if (! $this->option('apply')) {
                continue;
            }

            $mergedTasks = $this->mergeTasksFromLogs($logs, $canonical);
            $document = Document::query()->find((string) $canonical->id);
            if (! $document) {
                $this->error('Canonical document '.$canonical->id.' not found in documents view.');

                continue;
            }

            $content = is_array($document->content) ? $document->content : [];
            $content['tasks'] = ActionLogTaskSupport::renumberTasks($mergedTasks);
            $document->content = $content;
            $document->save();

            foreach ($logs as $log) {
                if ((string) $log->id === (string) $canonical->id) {
                    continue;
                }
                $log->update(['superseded_by_action_log_id' => (string) $canonical->id]);
            }

            $this->info('Merged into '.$canonical->id);
        }

        if (! $this->option('apply')) {
            $this->line('');
            $this->comment('Dry run only. Re-run with --apply to merge.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param  Collection<int, ActionLog>  $logs
     */
    private function pickCanonicalLog(Collection $logs): ActionLog
    {
        return $logs
            ->sortByDesc(function (ActionLog $log) {
                $isSubmitted = $log->state === Document::STATE_SUBMITTED ? 1 : 0;
                $updatedAt = optional($log->updated_at)->timestamp ?? 0;

                return [$isSubmitted, $updatedAt, (int) $log->id];
            })
            ->first();
    }

    /**
     * @param  Collection<int, ActionLog>  $logs
     * @return list<array<string, mixed>>
     */
    private function mergeTasksFromLogs(Collection $logs, ActionLog $canonical): array
    {
        $byUid = [];
        $canonicalContent = $this->assembler->assembleActionLogContent($canonical);
        foreach (ActionLogTaskSupport::extractTasks($canonicalContent) as $task) {
            if (! is_array($task)) {
                continue;
            }
            $uid = trim((string) ($task['task_uid'] ?? ''));
            if ($uid !== '') {
                $byUid[$uid] = $task;
            }
        }

        foreach ($logs as $log) {
            if ((string) $log->id === (string) $canonical->id) {
                continue;
            }

            $content = $this->assembler->assembleActionLogContent($log);
            foreach (ActionLogTaskSupport::extractTasks($content) as $task) {
                if (! is_array($task)) {
                    continue;
                }
                $uid = trim((string) ($task['task_uid'] ?? ''));
                if ($uid === '' || isset($byUid[$uid])) {
                    continue;
                }
                $byUid[$uid] = $task;
            }
        }

        return array_values($byUid);
    }
}
