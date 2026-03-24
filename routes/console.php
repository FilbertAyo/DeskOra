<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Schedule work period automation to run every Thursday at midnight
Schedule::command('work-periods:auto-create')
    ->weeklyOn(4, '00:00') // Every Thursday at 00:00 (midnight)
    ->timezone('Africa/Dar_es_Salaam') // Adjust to your timezone
    ->description('Automatically create next work period and close current week');

// Schedule daily deadline reminders at 8 AM local time
Schedule::command('work-periods:send-reminders')
    ->dailyAt('08:00')
    ->timezone('Africa/Dar_es_Salaam')
    ->description('Send weekly plan/report deadline reminders to staff and verifiers');
