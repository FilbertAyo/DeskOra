<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

$appTimezone = config('app.timezone', 'UTC');

// Ensure next work period exists every Thursday at 00:01.
Schedule::command('work-periods:auto-create --ensure-next')
    ->weeklyOn(4, '00:01')
    ->timezone($appTimezone)
    ->description('Ensure immediate next work period exists for next-week planning');

// Close ended work periods early Monday.
Schedule::command('work-periods:auto-create --close-ended')
    ->weeklyOn(1, '00:10')
    ->timezone($appTimezone)
    ->description('Close work periods that ended before today');

// Schedule reminder evaluation frequently; config decides when to send.
Schedule::command('work-periods:send-reminders')
    ->everyMinute()
    ->timezone($appTimezone)
    ->description('Dispatch configured weekly plan/report reminders');

// Morning personal digest (work log, actions, approvals, budgets).
Schedule::command('workhub:send-daily-digests')
    ->dailyAt('07:30')
    ->timezone($appTimezone)
    ->description('Send rule-based daily digests to active users');

// Drain the database queue without a long-running worker (cPanel-friendly).
// Safe with existing schedule:run cron; does not require Supervisor.
Schedule::command('queue:work database --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->description('Process queued mail and jobs until empty');

// Open the monthly staff appreciation nomination window (26th) and close ended windows.
Schedule::command('nominations:manage')
    ->dailyAt('00:05')
    ->timezone($appTimezone)
    ->description('Manage monthly staff appreciation nomination periods');

// Prune feature usage analytics older than retention (default 365 days).
Schedule::command('feature-usage:prune')
    ->dailyAt('01:20')
    ->timezone($appTimezone)
    ->description('Prune old feature usage analytics events');
