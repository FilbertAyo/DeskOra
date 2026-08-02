<?php

namespace App\Console\Commands;

use App\Jobs\DispatchWeeklySubmissionRemindersJob;
use App\Services\WeeklySubmissionReminderDispatcher;
use Illuminate\Console\Command;

class SendWorkPeriodDeadlineReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'work-periods:send-reminders {--sync : Run reminders inline instead of queueing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send configurable weekly plan/report reminders';

    public function __construct(protected WeeklySubmissionReminderDispatcher $dispatcher)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('sync') || config('queue.default') === 'sync') {
            $result = $this->dispatcher->dispatchIfDue();

            if (! ($result['due'] ?? false)) {
                $this->info('No reminder window matched at this tick.');

                return Command::SUCCESS;
            }

            $this->info(sprintf('Weekly submission reminders sent: %d', (int) ($result['sent'] ?? 0)));

            return Command::SUCCESS;
        }

        DispatchWeeklySubmissionRemindersJob::dispatch();
        $this->info('Weekly submission reminder job queued.');

        return Command::SUCCESS;
    }
}
