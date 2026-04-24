<?php

namespace Tests\Feature;

use App\Mail\WeeklySubmissionReminderMail;
use App\Models\Department;
use App\Models\Document;
use App\Models\ReminderConfig;
use App\Models\ReminderDispatchLog;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Notifications\WeeklySubmissionReminderNotification;
use App\Services\WeeklySubmissionReminderDispatcher;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WeeklyReminderAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_notification_settings_page_updates_weekly_reminder_config(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['status' => 'active']);
        $admin->givePermissionTo('view settings');

        $response = $this->actingAs($admin)->put(route('notification.weekly-reminder.update'), [
            'enabled' => '1',
            'day_of_week' => 5,
            'time' => '15:00',
            'channels' => ['email', 'in_app'],
        ]);

        $response->assertRedirect(route('notification.index'));

        $config = ReminderConfig::query()
            ->where('key', ReminderConfig::KEY_WEEKLY_PLAN_REPORT)
            ->first();

        $this->assertNotNull($config);
        $this->assertTrue($config->enabled);
        $this->assertSame(5, $config->day_of_week);
        $this->assertSame('15:00', $config->time);
        $this->assertSame(['email', 'in_app'], $config->channels);
    }

    public function test_dispatch_sends_separate_reminders_for_missing_plan_and_report(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-24 15:00:00', config('app.timezone')));
        Mail::fake();

        $department = Department::create(['name' => 'Operations', 'status' => 'active']);
        /** @var User $staff */
        $staff = User::factory()->create(['status' => 'active', 'department_id' => $department->id]);
        $staff->givePermissionTo(['create weekly plan', 'create weekly report']);

        $current = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 17,
            'week_start_date' => '2026-04-20',
            'week_end_date' => '2026-04-26',
            'plan_deadline' => '2026-04-24',
            'report_deadline' => '2026-04-25',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);
        WorkPeriod::create([
            'year' => 2026,
            'week_number' => 18,
            'week_start_date' => '2026-04-27',
            'week_end_date' => '2026-05-03',
            'plan_deadline' => '2026-05-01',
            'report_deadline' => '2026-05-02',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        // Staff has submitted the weekly plan only; report is still missing.
        Document::create([
            'user_id' => $staff->id,
            'period_id' => $current->getNextPeriod()?->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'state' => Document::STATE_SUBMITTED,
            'title' => 'Plan submission',
        ]);

        $result = app(WeeklySubmissionReminderDispatcher::class)->dispatchIfDue();

        $this->assertTrue($result['due']);
        $this->assertSame(1, $result['sent']);

        Mail::assertSent(WeeklySubmissionReminderMail::class, 1);
        $this->assertCount(1, $staff->notifications);
        $this->assertSame(Document::TYPE_WEEKLY_REPORT, $staff->notifications()->latest()->first()->data['activity_type']);
    }

    public function test_dispatch_is_idempotent_per_schedule_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-24 15:00:00', config('app.timezone')));
        Mail::fake();

        $department = Department::create(['name' => 'Finance', 'status' => 'active']);
        /** @var User $staff */
        $staff = User::factory()->create(['status' => 'active', 'department_id' => $department->id]);
        $staff->givePermissionTo(['create weekly plan', 'create weekly report']);

        WorkPeriod::create([
            'year' => 2026,
            'week_number' => 17,
            'week_start_date' => '2026-04-20',
            'week_end_date' => '2026-04-26',
            'plan_deadline' => '2026-04-24',
            'report_deadline' => '2026-04-25',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);
        WorkPeriod::create([
            'year' => 2026,
            'week_number' => 18,
            'week_start_date' => '2026-04-27',
            'week_end_date' => '2026-05-03',
            'plan_deadline' => '2026-05-01',
            'report_deadline' => '2026-05-02',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $dispatcher = app(WeeklySubmissionReminderDispatcher::class);
        $first = $dispatcher->dispatchIfDue();
        $second = $dispatcher->dispatchIfDue();

        $this->assertSame(2, $first['sent']);
        $this->assertSame(0, $second['sent']);
        $this->assertCount(2, ReminderDispatchLog::all());
    }

    public function test_user_can_mark_single_notification_as_read(): void
    {
        /** @var User $staff */
        $staff = User::factory()->create(['status' => 'active']);
        $staff->notify(new WeeklySubmissionReminderNotification(
            Document::TYPE_WEEKLY_REPORT,
            'Weekly Report',
            'week 17 (Apr 20 - Apr 26, 2026)'
        ));

        $notificationId = $staff->unreadNotifications()->firstOrFail()->id;

        $response = $this->actingAs($staff)->post(route('notifications.read', $notificationId));
        $response->assertRedirect();

        $this->assertCount(0, $staff->fresh()->unreadNotifications);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        /** @var User $staff */
        $staff = User::factory()->create(['status' => 'active']);
        $staff->notify(new WeeklySubmissionReminderNotification(
            Document::TYPE_WEEKLY_PLAN,
            'Weekly Plan',
            'week 18 (Apr 27 - May 03, 2026)'
        ));
        $staff->notify(new WeeklySubmissionReminderNotification(
            Document::TYPE_WEEKLY_REPORT,
            'Weekly Report',
            'week 17 (Apr 20 - Apr 26, 2026)'
        ));

        $this->assertCount(2, $staff->unreadNotifications);

        $response = $this->actingAs($staff)->post(route('notifications.read-all'));
        $response->assertRedirect();

        $this->assertCount(0, $staff->fresh()->unreadNotifications);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
