<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\WeeklySubmissionSlot;
use App\Mail\WeeklyGracePeriodMail;
use App\Models\Document;
use App\Models\ReminderDispatchLog;
use App\Models\User;
use App\Models\WorkPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class WeeklyGracePeriodNotificationDispatcher
{
    public function __construct(
        protected ReminderConfigService $configService,
        protected WeeklySubmissionWindowService $submissionWindows,
    ) {}

    /**
     * Notify staff after an admin extends deadlines into an active grace window.
     *
     * @return array{sent: int, email_sent: int, plan_sent: int, report_sent: int}
     */
    public function notifyAfterPeriodUpdate(
        WorkPeriod $period,
        ?string $previousPlanDeadline,
        ?string $previousReportDeadline,
    ): array {
        $config = $this->configService->getWeeklyGracePeriodConfig();

        if (! $config->enabled || ! $period->isOpen()) {
            return ['sent' => 0, 'email_sent' => 0, 'plan_sent' => 0, 'report_sent' => 0];
        }

        $period = $period->fresh();
        $planResult = ['sent' => 0, 'email_sent' => 0];
        $reportResult = ['sent' => 0, 'email_sent' => 0];

        if ($this->shouldNotifyPlanGrace($period, $previousPlanDeadline)) {
            $planResult = $this->dispatchForActivity($period, Document::TYPE_WEEKLY_PLAN, $config);
        }

        if ($this->shouldNotifyReportGrace($period, $previousReportDeadline)) {
            $reportResult = $this->dispatchForActivity($period, Document::TYPE_WEEKLY_REPORT, $config);
        }

        $emailSent = $planResult['email_sent'] + $reportResult['email_sent'];

        return [
            'sent' => $emailSent,
            'email_sent' => $emailSent,
            'plan_sent' => $planResult['email_sent'],
            'report_sent' => $reportResult['email_sent'],
        ];
    }

    /**
     * Manually notify staff for every open work period currently in a grace window.
     *
     * @return array{sent: int, email_sent: int, plan_sent: int, report_sent: int, periods_with_grace: int, skipped_disabled: bool, skipped_already_emailed: int}
     */
    public function dispatchNow(bool $forceResend = false): array
    {
        $config = $this->configService->getWeeklyGracePeriodConfig();

        if (! $config->enabled) {
            return [
                'sent' => 0,
                'email_sent' => 0,
                'plan_sent' => 0,
                'report_sent' => 0,
                'periods_with_grace' => 0,
                'skipped_disabled' => true,
                'skipped_already_emailed' => 0,
            ];
        }

        $planSent = 0;
        $reportSent = 0;
        $periodsWithGrace = 0;
        $skippedAlreadyEmailed = 0;

        $openPeriods = WorkPeriod::query()
            ->where('status', WorkPeriod::STATUS_OPEN)
            ->orderBy('week_start_date')
            ->get();

        foreach ($openPeriods as $period) {
            $hadGrace = false;

            if ($period->isPlanGracePeriodActive()) {
                $hadGrace = true;
                $result = $this->dispatchForActivity($period, Document::TYPE_WEEKLY_PLAN, $config, $forceResend);
                $planSent += $result['email_sent'];
                $skippedAlreadyEmailed += $result['skipped_already_emailed'];
            }

            if ($period->isReportGracePeriodActive()) {
                $hadGrace = true;
                $result = $this->dispatchForActivity($period, Document::TYPE_WEEKLY_REPORT, $config, $forceResend);
                $reportSent += $result['email_sent'];
                $skippedAlreadyEmailed += $result['skipped_already_emailed'];
            }

            if ($hadGrace) {
                $periodsWithGrace++;
            }
        }

        $emailSent = $planSent + $reportSent;

        return [
            'sent' => $emailSent,
            'email_sent' => $emailSent,
            'plan_sent' => $planSent,
            'report_sent' => $reportSent,
            'periods_with_grace' => $periodsWithGrace,
            'skipped_disabled' => false,
            'skipped_already_emailed' => $skippedAlreadyEmailed,
        ];
    }

    /**
     * Send a sample grace-period email (for SMTP configuration testing).
     */
    public function sendTestEmail(string $email): void
    {
        $period = WorkPeriod::query()
            ->where('status', WorkPeriod::STATUS_OPEN)
            ->orderByDesc('week_start_date')
            ->first();

        $cutoff = ($period ?? WorkPeriod::getCurrent())?->getPlanDeadlineCutoff() ?? Carbon::now()->addDays(2);

        Mail::to($email)->send(new WeeklyGracePeriodMail(
            'Test Recipient',
            'Weekly Plan',
            $period
                ? "Week {$period->week_number} of {$period->year}"
                : 'Week (sample)',
            $cutoff,
            route('dashboard'),
        ));
    }

    private function shouldNotifyPlanGrace(WorkPeriod $period, ?string $previousPlanDeadline): bool
    {
        if (! $period->isPlanGracePeriodActive()) {
            return false;
        }

        $newDate = $period->plan_deadline?->format('Y-m-d');
        if ($newDate === null) {
            return false;
        }

        if ($previousPlanDeadline === null) {
            return true;
        }

        return Carbon::parse($newDate)->greaterThan(Carbon::parse($previousPlanDeadline));
    }

    private function shouldNotifyReportGrace(WorkPeriod $period, ?string $previousReportDeadline): bool
    {
        if (! $period->isReportGracePeriodActive()) {
            return false;
        }

        $newDate = $period->report_deadline?->format('Y-m-d');
        if ($newDate === null) {
            return false;
        }

        if ($previousReportDeadline === null) {
            return true;
        }

        return Carbon::parse($newDate)->greaterThan(Carbon::parse($previousReportDeadline));
    }

    /**
     * @return array{sent: int, email_sent: int, skipped_already_emailed: int}
     */
    private function dispatchForActivity(
        WorkPeriod $period,
        string $documentType,
        \App\Models\ReminderConfig $config,
        bool $forceResend = false,
    ): array {
        if (! $config->enabled) {
            return ['sent' => 0, 'email_sent' => 0, 'skipped_already_emailed' => 0];
        }

        $deadlineDate = $documentType === Document::TYPE_WEEKLY_PLAN
            ? $period->plan_deadline?->format('Y-m-d')
            : $period->report_deadline?->format('Y-m-d');

        $activityKey = $documentType === Document::TYPE_WEEKLY_PLAN ? 'plan' : 'report';
        $windowKey = "{$period->id}_{$activityKey}_{$deadlineDate}";

        $activityLabel = $documentType === Document::TYPE_WEEKLY_PLAN
            ? 'Weekly Plan'
            : 'Weekly Report';

        $cutoff = $documentType === Document::TYPE_WEEKLY_PLAN
            ? $period->getPlanDeadlineCutoff()
            : $period->getReportDeadlineCutoff();

        $emailSent = 0;
        $skippedAlreadyEmailed = 0;

        foreach ($this->submissionWindows->staffOwingWeeklyDocument($period, $documentType) as $row) {
            /** @var User $user */
            $user = $row['user'];
            /** @var WeeklySubmissionSlot $slot */
            $slot = $row['slot'];

            if (! $forceResend && $this->alreadyEmailedForWindow($config->id, $user->id, $documentType, $windowKey)) {
                $skippedAlreadyEmailed++;

                continue;
            }

            $delivery = $this->deliverEmail($user, $slot, $documentType, $activityLabel, $period, $cutoff);

            if (! $delivery['email_sent']) {
                continue;
            }

            ReminderDispatchLog::query()->create([
                'reminder_config_id' => $config->id,
                'user_id' => $user->id,
                'activity_type' => $documentType,
                'window_key' => $windowKey,
                'channels' => ['email'],
                'status' => 'sent',
                'meta' => [
                    'period_id' => $period->id,
                    'week_number' => $period->week_number,
                    'year' => $period->year,
                    'grace_cutoff' => $cutoff->toIso8601String(),
                    'delivered_channels' => ['email'],
                    'failed_channels' => $delivery['failed_channels'],
                    'dispatch_type' => 'grace_period',
                ],
            ]);

            $emailSent++;
        }

        return [
            'sent' => $emailSent,
            'email_sent' => $emailSent,
            'skipped_already_emailed' => $skippedAlreadyEmailed,
        ];
    }

    private function alreadyEmailedForWindow(
        string $configId,
        string $userId,
        string $documentType,
        string $windowKey,
    ): bool {
        return ReminderDispatchLog::query()
            ->where('reminder_config_id', $configId)
            ->where('user_id', $userId)
            ->where('activity_type', $documentType)
            ->where('window_key', $windowKey)
            ->get()
            ->contains(function (ReminderDispatchLog $log) {
                $delivered = $log->meta['delivered_channels'] ?? $log->channels ?? [];

                return is_array($delivered) && in_array('email', $delivered, true);
            });
    }

    /**
     * @return array{email_sent: bool, failed_channels: array<string, string>}
     */
    private function deliverEmail(
        User $user,
        WeeklySubmissionSlot $slot,
        string $documentType,
        string $activityLabel,
        WorkPeriod $period,
        Carbon $cutoff,
    ): array {
        $failedChannels = [];

        if (! $user->canReceiveGracePeriodWorkEmail()) {
            return ['email_sent' => false, 'failed_channels' => ['email' => 'No email address on file.']];
        }

        $actionUrl = $slot->actionUrl($documentType, route('dashboard'));
        $periodLabel = "Week {$period->week_number} of {$period->year}";

        try {
            Mail::to($user->email)->send(new WeeklyGracePeriodMail(
                $user->name,
                $activityLabel,
                $periodLabel,
                $cutoff,
                $actionUrl,
            ));

            return ['email_sent' => true, 'failed_channels' => []];
        } catch (Throwable $e) {
            $failedChannels['email'] = $e->getMessage();
            Log::error('Failed sending grace period alert via email.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'document_type' => $documentType,
                'error' => $e->getMessage(),
            ]);

            return ['email_sent' => false, 'failed_channels' => $failedChannels];
        }
    }
}
