<?php

namespace App\Console\Commands;

use App\Models\ActionLog;
use App\Models\Document;
use App\Models\WorkPeriod;
use App\Services\ActionLogCarryForwardService;
use Illuminate\Console\Command;

class SyncCarriedActionLogTasks extends Command
{
    protected $signature = 'action-logs:sync-carried-tasks
                            {--period= : Target work period ID (defaults to current open period)}
                            {--dry-run : Preview changes without saving}';

    protected $description = 'Sync incomplete action log tasks from prior weeks into the target period and restore responsible people';

    public function __construct(
        private readonly ActionLogCarryForwardService $actionLogCarryForwardService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $period = $this->resolveTargetPeriod();

        if (! $period) {
            $this->error('No target work period found. Pass --period= or ensure a current open period exists.');

            return Command::FAILURE;
        }

        $document = Document::findCanonicalActionLogForPeriod((string) $period->id);

        if (! $document) {
            $this->error('No action log document exists for '.$period->display_name.'.');

            return Command::FAILURE;
        }

        $this->info('Target period: '.$period->display_name.' ('.$period->id.')');
        $this->info('Action log document: '.$document->id);
        if ($dryRun) {
            $this->warn('Dry run — no changes will be saved.');
        }

        $sync = $this->actionLogCarryForwardService->syncMissingCarriedTasks($document, $period);

        if (! $sync['changed']) {
            $this->info('No carried tasks or responsible people needed syncing.');

            return Command::SUCCESS;
        }

        if (! empty($sync['added'])) {
            $this->info('Tasks to add ('.count($sync['added']).'):');
            foreach ($sync['added'] as $label) {
                $this->line('  - '.$label);
            }
        } else {
            $this->info('Responsible people will be restored on existing tasks.');
        }

        if ($dryRun) {
            $this->info('Dry run complete. Re-run without --dry-run to apply.');

            return Command::SUCCESS;
        }

        $existingContent = is_array($document->content) ? $document->content : [];
        $actionLog = ActionLog::query()->find((string) $document->id);
        $meta = is_array($existingContent['meta'] ?? null) ? $existingContent['meta'] : [];
        if ($actionLog && is_array($actionLog->removed_task_uids) && $actionLog->removed_task_uids !== []) {
            $meta['removed_task_uids'] = $actionLog->removed_task_uids;
        }
        if ($actionLog?->submission_email_sent) {
            $meta['submission_email_sent'] = true;
        }

        $document->update([
            'content' => [
                'tasks' => $sync['tasks'],
                'meta' => $meta,
            ],
        ]);

        $this->info('Action log synced successfully.');

        return Command::SUCCESS;
    }

    private function resolveTargetPeriod(): ?WorkPeriod
    {
        $periodId = trim((string) $this->option('period'));
        if ($periodId !== '') {
            return WorkPeriod::query()->find($periodId);
        }

        return WorkPeriod::getCurrent();
    }
}
