<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Ensure next work period exists every Thursday at midnight.
Schedule::command('work-periods:auto-create --ensure-next')
    ->weeklyOn(4, '00:00')
    ->timezone('Africa/Dar_es_Salaam') // Adjust to your timezone
    ->description('Ensure immediate next work period exists for next-week planning');

// Close ended work periods early Monday.
Schedule::command('work-periods:auto-create --close-ended')
    ->weeklyOn(1, '00:10')
    ->timezone('Africa/Dar_es_Salaam') // Adjust to your timezone
    ->description('Close work periods that ended before today');

// Schedule reminder evaluation frequently; config decides when to send.
Schedule::command('work-periods:send-reminders')
    ->everyMinute()
    ->timezone(config('app.timezone', 'UTC'))
    ->description('Dispatch configured weekly plan/report reminders');
