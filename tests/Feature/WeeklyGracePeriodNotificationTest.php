<?php

namespace Tests\Feature;

use App\Mail\WeeklyGracePeriodMail;
use App\Models\Document;
use App\Models\ReminderConfig;
use App\Models\ReminderDispatchLog;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\ReminderConfigService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WeeklyGracePeriodNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_extending_plan_deadline_into_grace_sends_email_to_staff_without_submitted_plan(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 3, 10, 0, 0));
        Mail::fake();

        [$admin, $owingStaff, $submittedStaff, $period] = $this->seedGraceScenario();

        $response = $this->actingAs($admin)->put(route('work-periods.update', $period), [
            'week_start_date' => $period->week_start_date->format('Y-m-d'),
            'plan_deadline' => '2026-06-03',
            'report_deadline' => $period->report_deadline->format('Y-m-d'),
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $response->assertRedirect(route('work-periods.index'));
        $response->assertSessionHas('success');

        Mail::assertSent(WeeklyGracePeriodMail::class, function (WeeklyGracePeriodMail $mail) use ($owingStaff) {
            return $mail->hasTo($owingStaff->email);
        });

        Mail::assertNotSent(WeeklyGracePeriodMail::class, function (WeeklyGracePeriodMail $mail) use ($submittedStaff) {
            return $mail->hasTo($submittedStaff->email);
        });

        $graceConfig = app(ReminderConfigService::class)->getWeeklyGracePeriodConfig();
        $this->assertDatabaseHas('reminder_dispatch_logs', [
            'reminder_config_id' => $graceConfig->id,
            'user_id' => $owingStaff->id,
            'activity_type' => Document::TYPE_WEEKLY_PLAN,
        ]);
    }

    public function test_grace_alerts_not_sent_when_config_disabled(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 3, 10, 0, 0));
        Mail::fake();

        [$admin, $owingStaff, , $period] = $this->seedGraceScenario();

        app(ReminderConfigService::class)->updateWeeklyGracePeriodConfig([
            'enabled' => false,
            'channels' => ['email', 'in_app'],
        ]);

        $this->actingAs($admin)->put(route('work-periods.update', $period), [
            'week_start_date' => $period->week_start_date->format('Y-m-d'),
            'plan_deadline' => '2026-06-03',
            'report_deadline' => $period->report_deadline->format('Y-m-d'),
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        Mail::assertNothingSent();
    }

    public function test_duplicate_save_with_same_deadline_does_not_send_twice(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 3, 10, 0, 0));
        Mail::fake();

        [$admin, $owingStaff, , $period] = $this->seedGraceScenario();

        $payload = [
            'week_start_date' => $period->week_start_date->format('Y-m-d'),
            'plan_deadline' => '2026-06-03',
            'report_deadline' => $period->report_deadline->format('Y-m-d'),
            'status' => WorkPeriod::STATUS_OPEN,
        ];

        $this->actingAs($admin)->put(route('work-periods.update', $period), $payload);
        $this->actingAs($admin)->put(route('work-periods.update', $period->fresh()), $payload);

        Mail::assertSent(WeeklyGracePeriodMail::class, 1);

        $graceConfig = app(ReminderConfigService::class)->getWeeklyGracePeriodConfig();
        $this->assertSame(
            1,
            ReminderDispatchLog::query()
                ->where('reminder_config_id', $graceConfig->id)
                ->where('user_id', $owingStaff->id)
                ->where('activity_type', Document::TYPE_WEEKLY_PLAN)
                ->count()
        );
    }

    public function test_notification_settings_page_updates_grace_period_config(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['status' => 'active']);
        $admin->givePermissionTo(['view notification settings', 'manage notifications']);

        $response = $this->actingAs($admin)->put(route('notification.grace-period.update'), [
            'enabled' => '1',
        ]);

        $response->assertRedirect(route('notification.index'));

        $config = ReminderConfig::query()
            ->where('key', ReminderConfig::KEY_WEEKLY_GRACE_PERIOD)
            ->first();

        $this->assertNotNull($config);
        $this->assertTrue($config->enabled);
        $this->assertSame(['email'], $config->channels);
    }

    public function test_grace_email_sent_to_staff_with_sms_notification_channel(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 3, 10, 0, 0));
        Mail::fake();

        [, $owingStaff, , $period] = $this->seedGraceScenario();
        $owingStaff->update(['notification_channel' => 'sms']);

        $admin = User::factory()->create(['status' => 'active']);
        $admin->givePermissionTo('manage work periods');

        $this->actingAs($admin)->put(route('work-periods.update', $period), [
            'week_start_date' => $period->week_start_date->format('Y-m-d'),
            'plan_deadline' => '2026-06-03',
            'report_deadline' => $period->report_deadline->format('Y-m-d'),
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        Mail::assertSent(WeeklyGracePeriodMail::class, function (WeeklyGracePeriodMail $mail) use ($owingStaff) {
            return $mail->hasTo($owingStaff->email);
        });
    }

    public function test_manage_notifications_user_can_run_grace_alerts_manually(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 3, 10, 0, 0));
        Mail::fake();

        [, $owingStaff, , $period] = $this->seedGraceScenario();

        $period->update(['plan_deadline' => '2026-06-03']);

        /** @var User $manager */
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo(['view notification settings', 'manage notifications']);

        $response = $this->actingAs($manager)->post(route('notification.grace-period.run-now'));

        $response->assertRedirect(route('notification.index'));
        $response->assertSessionHas('success');

        Mail::assertSent(WeeklyGracePeriodMail::class, function (WeeklyGracePeriodMail $mail) use ($owingStaff) {
            return $mail->hasTo($owingStaff->email);
        });
    }

    public function test_user_without_manage_notifications_cannot_update_grace_config(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('view notification settings');

        $response = $this->actingAs($user)->put(route('notification.grace-period.update'), [
            'enabled' => '1',
        ]);

        $response->assertForbidden();
    }

    public function test_user_without_manage_notifications_cannot_run_grace_alerts_manually(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('view notification settings');

        $response = $this->actingAs($user)->post(route('notification.grace-period.run-now'));

        $response->assertForbidden();
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: WorkPeriod}
     */
    private function seedGraceScenario(): array
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->givePermissionTo('manage work periods');

        $owingStaff = User::factory()->create([
            'status' => 'active',
            'notification_channel' => 'email',
        ]);
        $owingStaff->givePermissionTo('create weekly plan');

        $submittedStaff = User::factory()->create([
            'status' => 'active',
            'notification_channel' => 'email',
        ]);
        $submittedStaff->givePermissionTo('create weekly plan');

        WorkPeriod::create([
            'year' => 2026,
            'week_number' => 22,
            'week_start_date' => '2026-05-25',
            'week_end_date' => '2026-05-31',
            'plan_deadline' => '2026-05-23',
            'report_deadline' => '2026-05-30',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $period = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 23,
            'week_start_date' => '2026-06-01',
            'week_end_date' => '2026-06-07',
            'plan_deadline' => '2026-05-30',
            'report_deadline' => '2026-06-06',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        WorkPeriod::create([
            'year' => 2026,
            'week_number' => 24,
            'week_start_date' => '2026-06-08',
            'week_end_date' => '2026-06-14',
            'plan_deadline' => '2026-06-06',
            'report_deadline' => '2026-06-13',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        Document::create([
            'user_id' => $submittedStaff->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Submitted plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Done',
        ]);

        app(ReminderConfigService::class)->getWeeklyGracePeriodConfig();

        return [$admin, $owingStaff, $submittedStaff, $period];
    }
}
