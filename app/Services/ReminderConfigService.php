<?php

namespace App\Services;

use App\Models\ReminderConfig;
use Carbon\Carbon;

class ReminderConfigService
{
    public function getWeeklyPlanReportConfig(): ReminderConfig
    {
        return ReminderConfig::query()->firstOrCreate(
            ['key' => ReminderConfig::KEY_WEEKLY_PLAN_REPORT],
            [
                'name' => 'Weekly Plan/Report Follow-up',
                'enabled' => true,
                'schedule_type' => 'weekly',
                'day_of_week' => Carbon::FRIDAY,
                'time' => '15:00',
                'timezone' => config('app.timezone', 'UTC'),
                'channels' => ['email', 'in_app'],
                'audience_rules' => ['permissions' => ['create weekly plan', 'create weekly report'], 'status' => 'active'],
                'criteria_rules' => ['mode' => 'separate_per_activity'],
                'template_key' => 'weekly_plan_report',
                'meta' => [
                    'description' => 'Reminder for staff missing weekly plan/report submissions.',
                    'grace_minutes' => 15,
                ],
            ]
        );
    }

    public function updateWeeklyPlanReportConfig(array $payload): ReminderConfig
    {
        $config = $this->getWeeklyPlanReportConfig();
        $config->fill($payload);
        $config->save();

        return $config->refresh();
    }

    public function getWeeklyGracePeriodConfig(): ReminderConfig
    {
        return ReminderConfig::query()->firstOrCreate(
            ['key' => ReminderConfig::KEY_WEEKLY_GRACE_PERIOD],
            [
                'name' => 'Weekly Grace Period Alerts',
                'enabled' => true,
                'schedule_type' => 'event',
                'day_of_week' => 0,
                'time' => '00:00',
                'timezone' => config('app.timezone', 'UTC'),
                'channels' => ['email'],
                'audience_rules' => [
                    'permissions' => ['create weekly plan', 'create weekly report'],
                    'status' => 'active',
                ],
                'criteria_rules' => ['mode' => 'on_deadline_extension'],
                'template_key' => 'weekly_grace_period',
                'meta' => [
                    'description' => 'Email staff when a deadline is extended and they still owe that weekly plan or report.',
                ],
            ]
        );

        if ($config->channels !== ['email']) {
            $config->forceFill(['channels' => ['email']])->save();
        }

        return $config;
    }

    public function updateWeeklyGracePeriodConfig(array $payload): ReminderConfig
    {
        $config = $this->getWeeklyGracePeriodConfig();
        $config->fill([
            'enabled' => (bool) ($payload['enabled'] ?? false),
            'channels' => ['email'],
        ]);
        $config->save();

        return $config->refresh();
    }
}
