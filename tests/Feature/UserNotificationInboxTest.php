<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WeeklySubmissionReminderNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserNotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_authenticated_user_can_view_notification_inbox(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('staff');

        $user->notify(new WeeklySubmissionReminderNotification(
            'weekly_plan',
            'weekly plan',
            'Week 10 of 2026'
        ));

        $this->actingAs($user)
            ->get(route('notifications.inbox'))
            ->assertOk()
            ->assertSee('Your notifications')
            ->assertSee('Reminder: submit weekly plan');
    }

    public function test_opening_notification_marks_it_read_and_redirects(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('staff');

        $user->notify(new WeeklySubmissionReminderNotification(
            'weekly_plan',
            'weekly plan',
            'Week 10 of 2026'
        ));

        $notification = $user->unreadNotifications()->first();
        $this->assertNotNull($notification);

        $this->actingAs($user)
            ->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('documents.create'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_nav_preview_only_shows_unread_notifications(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('staff');

        $user->notify(new WeeklySubmissionReminderNotification(
            'weekly_plan',
            'weekly plan',
            'Week 10 of 2026'
        ));

        $readNotification = $user->unreadNotifications()->first();
        $readNotification->markAsRead();

        $user->notify(new WeeklySubmissionReminderNotification(
            'weekly_report',
            'weekly report',
            'Week 10 of 2026'
        ));

        $this->actingAs($user)
            ->get(route('meeting-minutes.index'))
            ->assertOk()
            ->assertSee('Reminder: submit weekly report')
            ->assertDontSee('Reminder: submit weekly plan');
    }
}
