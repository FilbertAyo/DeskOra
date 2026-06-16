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
     * @return array{sent: int, emails_sent: int, plan_sent: int, report_sent: int}
     */
    public function notifyAfterPeriodUpdate(
        WorkPeriod $period,
        ?string $previousPlanDeadline,
        ?string $previousReportDeadline,
    ): array {
        $config = $this->configService->getWeeklyGracePeriodConfig();

        if (! $config->enabled || ! $period->isOpen()) {
            return ['sent' => 0, 'emails_sent' => 0, 'plan_sent' => 0, 'report_sent' => 0];
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
            'emails_sent' => $planSent + $reportSent,
            'plan_sent' => $planSent,
            'report_sent' => $reportSent,
        ];
    }

    /**
     * Manually notify staff for every open work period currently in a grace window.
     *
     * @return array{sent: int, emails_sent: int, plan_sent: int, report_sent: int, periods_with_grace: int, skipped_disabled: bool, skipped_no_email: int}
     */
    public function dispatchNow(): array
    {
        $config = $this->configService->getWeeklyGracePeriodConfig();

        if (! $config->enabled) {
            return [
                'sent' => 0,
                'emails_sent' => 0,
                'plan_sent' => 0,
                'report_sent' => 0,
                'periods_with_grace' => 0,
                'skipped_disabled' => true,
                'skipped_no_email' => 0,
            ];
        }

        $planSent = 0;
        $reportSent = 0;
        $periodsWithGrace = 0;
        $skippedNoEmail = 0;

        $openPeriods = WorkPeriod::query()
            ->where('week_start_date', '>=', now()->subWeeks(16)->startOfWeek()->format('Y-m-d'))
            ->orderBy('week_start_date')
            ->get();

        foreach ($openPeriods as $period) {
            $hadGrace = false;

            if ($period->isPlanGracePeriodActive()) {
                $hadGrace = true;
                [$sent, $skipped] = $this->dispatchForActivityWithStats($period, Document::TYPE_WEEKLY_PLAN, $config);
                $planSent += $sent;
                $skippedNoEmail += $skipped;
            }

            if ($period->isReportGracePeriodActive()) {
                $hadGrace = true;
                [$sent, $skipped] = $this->dispatchForActivityWithStats($period, Document::TYPE_WEEKLY_REPORT, $config);
                $reportSent += $sent;
                $skippedNoEmail += $skipped;
            }

            if ($hadGrace) {
                $periodsWithGrace++;
            }
        }

        $totalSent = $planSent + $reportSent;

        return [
            'sent' => $totalSent,
            'emails_sent' => $totalSent,
            'plan_sent' => $planSent,
            'report_sent' => $reportSent,
            'periods_with_grace' => $periodsWithGrace,
            'skipped_disabled' => false,
            'skipped_no_email' => $skippedNoEmail,
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
        return $this->dispatchForActivityWithStats($period, $documentType, $config)[0];
    }

    /**
     * @return array{0: int, 1: int} [emails_sent, skipped_no_email_address]
     */
    private function dispatchForActivityWithStats(WorkPeriod $period, string $documentType, \App\Models\ReminderConfig $config): array
    {
        $deadlineDate = $documentType === Document::TYPE_WEEKLY_PLAN
            ? $period->plan_deadline?->format('Y-m-d')
            : $period->report_deadline?->format('Y-m-d');

        if ($deadlineDate === null) {
            return [0, 0];
        }

        $activityKey = $documentType === Document::TYPE_WEEKLY_PLAN ? 'plan' : 'report';
        $windowKey = "{$period->id}_{$activityKey}_{$deadlineDate}";

        $activityLabel = $documentType === Document::TYPE_WEEKLY_PLAN
            ? 'Weekly Plan'
            : 'Weekly Report';

        $cutoff = $documentType === Document::TYPE_WEEKLY_PLAN
            ? $period->getPlanDeadlineCutoff()
            : $period->getReportDeadlineCutoff();

        $emailsSent = 0;
        $skippedNoEmail = 0;

        foreach ($this->submissionWindows->staffOwingWeeklyDocument($period, $documentType) as $row) {
            /** @var User $user */
            $user = $row['user'];
            /** @var WeeklySubmissionSlot $slot */
            $slot = $row['slot'];

            if ($this->wasGraceEmailAlreadySent((string) $config->id, (string) $user->id, $documentType, $windowKey)) {
                continue;
            }

            if (! $user->canReceiveGracePeriodWorkEmail()) {
                $skippedNoEmail++;

                continue;
            }

            $delivery = $this->deliverEmail($user, $slot, $documentType, $activityLabel, $period, $cutoff);

            if (! in_array('email', $delivery['delivered_channels'], true)) {
                continue;
            }

            $this->recordGraceEmailDispatch(
                $config,
                $user,
                $documentType,
                $windowKey,
                $period,
                $cutoff,
                $delivery,
            );

            $emailsSent++;
        }

        return [$emailsSent, $skippedNoEmail];
    }

    private function findGraceDispatchLog(
        \App\Models\ReminderConfig $config,
        User $user,
        string $documentType,
        string $windowKey,
    ): ?ReminderDispatchLog {
        return ReminderDispatchLog::query()
            ->where('reminder_config_id', $config->id)
            ->where('user_id', $user->id)
            ->where('activity_type', $documentType)
            ->where('window_key', $windowKey)
            ->first();
    }

    private function wasGraceEmailAlreadySent(
        string $configId,
        string $userId,
        string $activityType,
        string $windowKey,
    ): bool {
        $log = ReminderDispatchLog::query()
            ->where('reminder_config_id', $configId)
            ->where('user_id', $userId)
            ->where('activity_type', $activityType)
            ->where('window_key', $windowKey)
            ->first();

        if (! $log) {
            return false;
        }

        return in_array('email', (array) ($log->meta['delivered_channels'] ?? []), true);
    }

    /**
     * @param  array{delivered_channels: array<int, string>, failed_channels: array<string, string>}  $delivery
     */
    private function recordGraceEmailDispatch(
        \App\Models\ReminderConfig $config,
        User $user,
        string $documentType,
        string $windowKey,
        WorkPeriod $period,
        Carbon $cutoff,
        array $delivery,
    ): void {
        $existing = $this->findGraceDispatchLog($config, $user, $documentType, $windowKey);
        $priorDelivered = $existing
            ? (array) ($existing->meta['delivered_channels'] ?? [])
            : [];

        $deliveredChannels = array_values(array_unique([...$priorDelivered, 'email']));

        ReminderDispatchLog::query()->updateOrCreate(
            [
                'reminder_config_id' => $config->id,
                'user_id' => $user->id,
                'activity_type' => $documentType,
                'window_key' => $windowKey,
            ],
            [
                'channels' => ['email'],
                'status' => empty($delivery['failed_channels']) ? 'sent' : 'partially_sent',
                'meta' => [
                    'period_id' => $period->id,
                    'week_number' => $period->week_number,
                    'year' => $period->year,
                    'grace_cutoff' => $cutoff->toIso8601String(),
                    'delivered_channels' => $deliveredChannels,
                    'failed_channels' => $delivery['failed_channels'],
                    'dispatch_type' => 'grace_period',
                ],
            ],
        );
    }

    /**
     * @return array{delivered_channels: array<int, string>, failed_channels: array<string, string>}
     */
    private function deliverEmail(
        User $user,
        WeeklySubmissionSlot $slot,
        string $documentType,
        string $activityLabel,
        WorkPeriod $period,
        Carbon $cutoff,
    ): array {
        $actionUrl = $slot->actionUrl($documentType);
        $periodLabel = "Week {$period->week_number} of {$period->year}";

        try {
            Mail::to($user->email)->send(new WeeklyGracePeriodMail(
                $user->name,
                $activityLabel,
                $periodLabel,
                $cutoff,
                $actionUrl,
            ));

            return [
                'delivered_channels' => ['email'],
                'failed_channels' => [],
            ];
        } catch (Throwable $e) {
            Log::error('Failed sending grace period alert via email.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'document_type' => $documentType,
                'error' => $e->getMessage(),
            ]);

            return [
                'delivered_channels' => [],
                'failed_channels' => ['email' => $e->getMessage()],
            ];
        }
    }
}
