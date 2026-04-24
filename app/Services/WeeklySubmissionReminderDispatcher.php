<?php

namespace App\Services;

use App\Mail\WeeklySubmissionReminderMail;
use App\Models\Document;
use App\Models\ReminderConfig;
use App\Models\ReminderDispatchLog;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Notifications\WeeklySubmissionReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class WeeklySubmissionReminderDispatcher
{
    public function __construct(
        protected ReminderConfigService $configService
    ) {
    }

    public function dispatchIfDue(?Carbon $now = null): array
    {
        $config = $this->configService->getWeeklyPlanReportConfig();
        $now = $now ?? Carbon::now($config->timezone ?: config('app.timezone', 'UTC'));
        $slot = $this->scheduledSlotForNow($config, $now);

        if ($slot === null) {
            return ['due' => false, 'sent' => 0];
        }

        return $this->dispatchNow($config, $now, $slot);
    }

    public function dispatchNow(?ReminderConfig $config = null, ?Carbon $now = null, ?Carbon $scheduledSlot = null): array
    {
        $config = $config ?? $this->configService->getWeeklyPlanReportConfig();
        $timezone = $config->timezone ?: config('app.timezone', 'UTC');
        $now = ($now ?? Carbon::now($timezone))->copy()->setTimezone($timezone);
        $scheduledSlot = $scheduledSlot ? $scheduledSlot->copy()->setTimezone($timezone) : $this->scheduledSlotForNow($config, $now);
        $windowKey = ($scheduledSlot ?? $now)->format('Y-m-d_H:i');
        $channels = collect($config->channels ?: [])->values()->all();
        $targets = $this->resolveTargets();
        $sent = 0;

        foreach ($targets as $target) {
            $alreadySent = ReminderDispatchLog::query()
                ->where('reminder_config_id', $config->id)
                ->where('user_id', $target['user']->id)
                ->where('activity_type', $target['activity_type'])
                ->where('window_key', $windowKey)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $this->deliver($target['user'], $target['activity_type'], $target['activity_label'], $target['period_label'], $channels);

            ReminderDispatchLog::query()->create([
                'reminder_config_id' => $config->id,
                'user_id' => $target['user']->id,
                'activity_type' => $target['activity_type'],
                'window_key' => $windowKey,
                'channels' => $channels,
                'status' => 'sent',
                'meta' => [
                    'timezone' => $timezone,
                    'triggered_at' => $now->toIso8601String(),
                ],
            ]);

            $sent++;
        }

        return ['due' => true, 'sent' => $sent, 'targets' => count($targets)];
    }

    private function scheduledSlotForNow(ReminderConfig $config, Carbon $now): ?Carbon
    {
        if (! $config->enabled) {
            return null;
        }

        [$hour, $minute] = explode(':', $config->time ?: '15:00');
        $scheduledSlot = $now->copy()->setTime((int) $hour, (int) $minute, 0);
        $graceMinutes = (int) (($config->meta['grace_minutes'] ?? 15));
        $graceMinutes = max(0, $graceMinutes);
        $windowEnd = $scheduledSlot->copy()->addMinutes($graceMinutes);

        if ((int) $config->day_of_week !== $now->dayOfWeek) {
            return null;
        }

        if ($now->lessThan($scheduledSlot) || $now->greaterThan($windowEnd)) {
            return null;
        }

        return $scheduledSlot;
    }

    /**
     * @return array<int, array{user: User, activity_type: string, activity_label: string, period_label: string}>
     */
    private function resolveTargets(): array
    {
        $currentPeriod = WorkPeriod::getCurrent();
        $planPeriod = WorkPeriod::getPeriodForPlanSubmission();
        $reportPeriod = WorkPeriod::getPeriodForReportSubmission();

        if (! $currentPeriod || ! $planPeriod || ! $reportPeriod) {
            return [];
        }

        // Pull active users first, then evaluate permissions through hasPermissionTo()
        // so both direct permissions and role-based permissions are supported.
        $users = User::query()
            ->where('status', 'active')
            ->get();

        $targets = [];

        foreach ($users as $user) {
            if ($user->hasPermissionTo('create weekly plan')) {
                $hasPlan = Document::query()
                    ->where('user_id', $user->id)
                    ->where('period_id', $planPeriod->id)
                    ->where('type', Document::TYPE_WEEKLY_PLAN)
                    ->where('state', Document::STATE_SUBMITTED)
                    ->exists();

                if (! $hasPlan) {
                    $targets[] = [
                        'user' => $user,
                        'activity_type' => Document::TYPE_WEEKLY_PLAN,
                        'activity_label' => 'Weekly Plan',
                        'period_label' => "week {$planPeriod->week_number} ({$planPeriod->date_range})",
                    ];
                }
            }

            if ($user->hasPermissionTo('create weekly report')) {
                $hasReport = Document::query()
                    ->where('user_id', $user->id)
                    ->where('period_id', $reportPeriod->id)
                    ->where('type', Document::TYPE_WEEKLY_REPORT)
                    ->where('state', Document::STATE_SUBMITTED)
                    ->exists();

                if (! $hasReport) {
                    $targets[] = [
                        'user' => $user,
                        'activity_type' => Document::TYPE_WEEKLY_REPORT,
                        'activity_label' => 'Weekly Report',
                        'period_label' => "week {$reportPeriod->week_number} ({$reportPeriod->date_range})",
                    ];
                }
            }
        }

        return $targets;
    }

    private function deliver(User $user, string $activityType, string $activityLabel, string $periodLabel, array $channels): void
    {
        if (in_array('in_app', $channels, true)) {
            $user->notify(new WeeklySubmissionReminderNotification($activityType, $activityLabel, $periodLabel));
        }

        if (in_array('email', $channels, true) && ! empty($user->email)) {
            Mail::to($user->email)->send(new WeeklySubmissionReminderMail($user->name, $activityLabel, $periodLabel));
        }
    }
}
