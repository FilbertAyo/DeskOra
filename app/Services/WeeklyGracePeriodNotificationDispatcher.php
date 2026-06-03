<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\WeeklySubmissionSlot;
use App\Mail\WeeklyGracePeriodMail;
use App\Models\Document;
use App\Models\ReminderDispatchLog;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Notifications\WeeklyGracePeriodNotification;
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
     * @return array{sent: int, plan_sent: int, report_sent: int}
     */
    public function notifyAfterPeriodUpdate(
        WorkPeriod $period,
        ?string $previousPlanDeadline,
        ?string $previousReportDeadline,
    ): array {
        $config = $this->configService->getWeeklyGracePeriodConfig();

        if (! $config->enabled || ! $period->isOpen()) {
            return ['sent' => 0, 'plan_sent' => 0, 'report_sent' => 0];
        }

        $period = $period->fresh();
        $planSent = 0;
        $reportSent = 0;

        if ($this->shouldNotifyPlanGrace($period, $previousPlanDeadline)) {
            $planSent = $this->dispatchForActivity($period, Document::TYPE_WEEKLY_PLAN, $config);
        }

        if ($this->shouldNotifyReportGrace($period, $previousReportDeadline)) {
            $reportSent = $this->dispatchForActivity($period, Document::TYPE_WEEKLY_REPORT, $config);
        }

        return [
            'sent' => $planSent + $reportSent,
            'plan_sent' => $planSent,
            'report_sent' => $reportSent,
        ];
    }

    /**
     * Manually notify staff for every open work period currently in a grace window.
     *
     * @return array{sent: int, plan_sent: int, report_sent: int, periods_with_grace: int, skipped_disabled: bool}
     */
    public function dispatchNow(): array
    {
        $config = $this->configService->getWeeklyGracePeriodConfig();

        if (! $config->enabled) {
            return [
                'sent' => 0,
                'plan_sent' => 0,
                'report_sent' => 0,
                'periods_with_grace' => 0,
                'skipped_disabled' => true,
            ];
        }

        $planSent = 0;
        $reportSent = 0;
        $periodsWithGrace = 0;

        $openPeriods = WorkPeriod::query()
            ->where('status', WorkPeriod::STATUS_OPEN)
            ->orderBy('week_start_date')
            ->get();

        foreach ($openPeriods as $period) {
            $hadGrace = false;

            if ($period->isPlanGracePeriodActive()) {
                $hadGrace = true;
                $planSent += $this->dispatchForActivity($period, Document::TYPE_WEEKLY_PLAN, $config);
            }

            if ($period->isReportGracePeriodActive()) {
                $hadGrace = true;
                $reportSent += $this->dispatchForActivity($period, Document::TYPE_WEEKLY_REPORT, $config);
            }

            if ($hadGrace) {
                $periodsWithGrace++;
            }
        }

        return [
            'sent' => $planSent + $reportSent,
            'plan_sent' => $planSent,
            'report_sent' => $reportSent,
            'periods_with_grace' => $periodsWithGrace,
            'skipped_disabled' => false,
        ];
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

    private function dispatchForActivity(WorkPeriod $period, string $documentType, \App\Models\ReminderConfig $config): int
    {
        $channels = collect($config->channels ?: [])->values()->all();
        if ($channels === []) {
            return 0;
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

        $sent = 0;

        foreach ($this->submissionWindows->staffOwingWeeklyDocument($period, $documentType) as $row) {
            /** @var User $user */
            $user = $row['user'];
            /** @var WeeklySubmissionSlot $slot */
            $slot = $row['slot'];

            $alreadySent = ReminderDispatchLog::query()
                ->where('reminder_config_id', $config->id)
                ->where('user_id', $user->id)
                ->where('activity_type', $documentType)
                ->where('window_key', $windowKey)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $delivery = $this->deliver($user, $slot, $documentType, $activityLabel, $period, $cutoff, $channels);

            if ($delivery['delivered_channels'] === []) {
                continue;
            }

            ReminderDispatchLog::query()->create([
                'reminder_config_id' => $config->id,
                'user_id' => $user->id,
                'activity_type' => $documentType,
                'window_key' => $windowKey,
                'channels' => $channels,
                'status' => empty($delivery['failed_channels']) ? 'sent' : 'partially_sent',
                'meta' => [
                    'period_id' => $period->id,
                    'week_number' => $period->week_number,
                    'year' => $period->year,
                    'grace_cutoff' => $cutoff->toIso8601String(),
                    'delivered_channels' => $delivery['delivered_channels'],
                    'failed_channels' => $delivery['failed_channels'],
                    'dispatch_type' => 'grace_period',
                ],
            ]);

            $sent++;
        }

        return $sent;
    }

    /**
     * @return array{delivered_channels: array<int, string>, failed_channels: array<string, string>}
     */
    private function deliver(
        User $user,
        WeeklySubmissionSlot $slot,
        string $documentType,
        string $activityLabel,
        WorkPeriod $period,
        Carbon $cutoff,
        array $channels,
    ): array {
        $deliveredChannels = [];
        $failedChannels = [];
        $actionUrl = $slot->actionUrl($documentType, route('dashboard'));
        $periodLabel = "Week {$period->week_number} of {$period->year}";

        if (in_array('in_app', $channels, true)) {
            try {
                $user->notify(new WeeklyGracePeriodNotification(
                    $documentType,
                    $activityLabel,
                    $periodLabel,
                    $cutoff,
                    $actionUrl,
                ));
                $deliveredChannels[] = 'in_app';
            } catch (Throwable $e) {
                $failedChannels['in_app'] = $e->getMessage();
                Log::error('Failed sending grace period alert via in_app channel.', [
                    'user_id' => $user->id,
                    'document_type' => $documentType,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (in_array('email', $channels, true) && $user->receivesEmailNotifications()) {
            try {
                Mail::to($user->email)->send(new WeeklyGracePeriodMail(
                    $user->name,
                    $activityLabel,
                    $periodLabel,
                    $cutoff,
                    $actionUrl,
                ));
                $deliveredChannels[] = 'email';
            } catch (Throwable $e) {
                $failedChannels['email'] = $e->getMessage();
                Log::error('Failed sending grace period alert via email channel.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'document_type' => $documentType,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'delivered_channels' => $deliveredChannels,
            'failed_channels' => $failedChannels,
        ];
    }
}
