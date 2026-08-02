<?php

namespace App\Jobs;

use App\Services\WeeklySubmissionReminderDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchWeeklySubmissionRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(WeeklySubmissionReminderDispatcher $dispatcher): void
    {
        $dispatcher->dispatchIfDue();
    }
}
