<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendWorkPeriodDeadlineReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'work-periods:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send SMS reminders for weekly plan/report deadlines (2 days before and on deadline day)';

    public function __construct(protected NotificationService $notificationService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Sending work period deadline reminders...');

        $period = WorkPeriod::getCurrent();

        if (! $period) {
            $this->warn('No current work period found. Skipping reminders.');
            Log::warning('Work period reminder skipped - no current period');

            return Command::SUCCESS;
        }

        $today = Carbon::today();
        $planDeadline = Carbon::parse($period->plan_deadline);
        $reportDeadline = Carbon::parse($period->report_deadline);

        $planDiff = $today->diffInDays($planDeadline, false);
        $reportDiff = $today->diffInDays($reportDeadline, false);

        $remindPlan = in_array($planDiff, [2, 0], true);
        $remindReport = in_array($reportDiff, [2, 0], true);

        if (! $remindPlan && ! $remindReport) {
            $this->info('No reminders due today.');

            return Command::SUCCESS;
        }

        $recipients = User::role(['staff', 'verifier'])
            ->whereNotNull('phone')
            ->where('status', 'active')
            ->get();

        if ($recipients->isEmpty()) {
            $this->warn('No active staff/verifier users with phone numbers to notify.');

            return Command::SUCCESS;
        }

        $planMessage = null;
        if ($remindPlan) {
            $when = $planDiff === 0 ? 'today' : 'in 2 days';
            $nextPeriod = $period->getNextPeriod();
            if ($nextPeriod) {
                $planMessage = sprintf(
                    'Reminder: Weekly Plan for next week %d (%s) is due %s (deadline: %s).',
                    $nextPeriod->week_number,
                    $nextPeriod->date_range,
                    $when,
                    $planDeadline->format('d M Y')
                );
            } else {
                Log::warning('Plan reminder skipped: next period missing.', [
                    'current_period_id' => $period->id,
                    'week_number' => $period->week_number,
                    'year' => $period->year,
                ]);
            }
        }

        $reportMessage = null;
        if ($remindReport) {
            $when = $reportDiff === 0 ? 'today' : 'in 2 days';
            $reportMessage = sprintf(
                'Reminder: Weekly Report for week %d (%s) is due %s (deadline: %s).',
                $period->week_number,
                $period->date_range,
                $when,
                $reportDeadline->format('d M Y')
            );
        }

        foreach ($recipients as $user) {
            if ($planMessage) {
                $this->sendSms($user, $planMessage, 'plan');
            }
            if ($reportMessage) {
                $this->sendSms($user, $reportMessage, 'report');
            }
        }

        $this->info(sprintf(
            'Reminders sent. Plan: %s, Report: %s, Recipients: %d',
            $remindPlan ? 'YES' : 'NO',
            $remindReport ? 'YES' : 'NO',
            $recipients->count()
        ));

        return Command::SUCCESS;
    }

    private function sendSms(User $user, string $message, string $type): void
    {
        $result = $this->notificationService->sendSms($user->phone, $message);

        if ($result) {
            Log::info('Deadline reminder sent', [
                'user_id' => $user->id,
                'type' => $type,
                'phone' => $user->phone,
            ]);
        } else {
            Log::error('Failed to send deadline reminder', [
                'user_id' => $user->id,
                'type' => $type,
                'phone' => $user->phone,
            ]);
        }
    }
}
