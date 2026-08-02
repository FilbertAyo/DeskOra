<?php

namespace Tests\Feature;

use App\Jobs\DispatchWeeklySubmissionRemindersJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class WeeklyReminderJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_reminders_command_queues_job_when_not_sync(): void
    {
        Bus::fake();

        config(['queue.default' => 'database']);

        $this->artisan('work-periods:send-reminders')
            ->assertSuccessful();

        Bus::assertDispatched(DispatchWeeklySubmissionRemindersJob::class);
    }

    public function test_send_reminders_command_runs_inline_with_sync_flag(): void
    {
        Bus::fake();

        $this->artisan('work-periods:send-reminders', ['--sync' => true])
            ->assertSuccessful();

        Bus::assertNotDispatched(DispatchWeeklySubmissionRemindersJob::class);
    }
}
