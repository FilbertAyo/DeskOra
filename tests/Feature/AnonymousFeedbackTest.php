<?php

namespace Tests\Feature;

use App\Models\AnonymousFeedback;
use App\Models\User;
use App\Notifications\AnonymousFeedbackSubmittedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AnonymousFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-feedback-token-32chars12';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['anonymous_feedback.public_token' => $this->token]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_wrong_token_returns_404(): void
    {
        $response = $this->get('/feedback/wrong-token');
        $response->assertNotFound();
    }

    public function test_empty_config_token_returns_404(): void
    {
        config(['anonymous_feedback.public_token' => '']);

        $response = $this->get('/feedback/'.$this->token);
        $response->assertNotFound();

        config(['anonymous_feedback.public_token' => $this->token]);
    }

    public function test_form_renders_for_valid_token(): void
    {
        $response = $this->get('/feedback/'.$this->token);
        $response->assertOk();
        $response->assertSee('Anonymous feedback', false);
    }

    public function test_submission_creates_row_without_auth(): void
    {
        $response = $this->post('/feedback/'.$this->token, [
            'category' => AnonymousFeedback::CATEGORY_SUGGESTION,
            'body' => 'More tea in the kitchen please.',
        ]);

        $response->assertRedirect(route('anonymous-feedback.form', ['token' => $this->token]));
        $this->assertDatabaseHas('anonymous_feedback', [
            'category' => AnonymousFeedback::CATEGORY_SUGGESTION,
            'body' => 'More tea in the kitchen please.',
            'status' => AnonymousFeedback::STATUS_NEW,
        ]);
    }

    public function test_submission_notifies_managers_regardless_of_channel_preference(): void
    {
        Notification::fake();

        Permission::findOrCreate('manage anonymous feedback');

        $managerEmail = User::factory()->create([
            'status' => 'active',
            'notification_channel' => 'email',
        ]);
        $managerEmail->givePermissionTo('manage anonymous feedback');

        $managerBoth = User::factory()->create([
            'status' => 'active',
            'notification_channel' => 'both',
        ]);
        $managerBoth->givePermissionTo('manage anonymous feedback');

        $managerSmsOnly = User::factory()->create([
            'status' => 'active',
            'notification_channel' => 'sms',
        ]);
        $managerSmsOnly->givePermissionTo('manage anonymous feedback');

        $response = $this->post('/feedback/'.$this->token, [
            'category' => AnonymousFeedback::CATEGORY_COMPLAINT,
            'body' => 'The office heater is not working.',
        ]);

        $response->assertRedirect(route('anonymous-feedback.form', ['token' => $this->token]));

        Notification::assertSentTo($managerEmail, AnonymousFeedbackSubmittedNotification::class);
        Notification::assertSentTo($managerBoth, AnonymousFeedbackSubmittedNotification::class);
        Notification::assertSentTo($managerSmsOnly, AnonymousFeedbackSubmittedNotification::class);
    }

    public function test_submission_notifies_configured_extra_emails(): void
    {
        Notification::fake();

        config(['anonymous_feedback.notify_emails' => ['backup-manager@example.com']]);

        $response = $this->post('/feedback/'.$this->token, [
            'category' => AnonymousFeedback::CATEGORY_SUGGESTION,
            'body' => 'Please add more meeting rooms.',
        ]);

        $response->assertRedirect(route('anonymous-feedback.form', ['token' => $this->token]));

        Notification::assertSentOnDemand(
            AnonymousFeedbackSubmittedNotification::class,
            function ($notification, $channels, $notifiable) {
                return in_array('mail', $channels, true)
                    && ($notifiable->routes['mail'] ?? null) === 'backup-manager@example.com';
            }
        );
    }

    public function test_submission_does_not_notify_users_without_permission(): void
    {
        Notification::fake();

        Permission::findOrCreate('manage anonymous feedback');

        $otherUser = User::factory()->create([
            'status' => 'active',
            'notification_channel' => 'email',
        ]);

        $response = $this->post('/feedback/'.$this->token, [
            'category' => AnonymousFeedback::CATEGORY_OTHER,
            'body' => 'General feedback.',
        ]);

        $response->assertRedirect(route('anonymous-feedback.form', ['token' => $this->token]));

        Notification::assertNothingSent();
    }

    public function test_honeypot_rejects_submission(): void
    {
        $response = $this->post('/feedback/'.$this->token, [
            'category' => AnonymousFeedback::CATEGORY_OTHER,
            'body' => 'Spam',
            'website' => 'http://evil.test',
        ]);

        $response->assertSessionHasErrors('website');
        $this->assertDatabaseCount('anonymous_feedback', 0);
    }

    public function test_staff_can_update_status(): void
    {
        Permission::findOrCreate('manage anonymous feedback');
        $user = User::factory()->create();
        $user->givePermissionTo('manage anonymous feedback');

        $row = AnonymousFeedback::query()->create([
            'category' => AnonymousFeedback::CATEGORY_COMPLAINT,
            'body' => 'Test',
            'status' => AnonymousFeedback::STATUS_NEW,
        ]);

        $response = $this->actingAs($user)->patch(
            route('anonymous-feedback.update', $row),
            ['status' => AnonymousFeedback::STATUS_RESOLVED]
        );

        $response->assertRedirect();
        $this->assertSame(AnonymousFeedback::STATUS_RESOLVED, $row->fresh()->status);
    }

    public function test_user_without_permission_cannot_update_status(): void
    {
        Permission::findOrCreate('manage anonymous feedback');
        $user = User::factory()->create();

        $row = AnonymousFeedback::query()->create([
            'category' => AnonymousFeedback::CATEGORY_COMPLAINT,
            'body' => 'Test',
            'status' => AnonymousFeedback::STATUS_NEW,
        ]);

        $response = $this->actingAs($user)->patch(
            route('anonymous-feedback.update', $row),
            ['status' => AnonymousFeedback::STATUS_RESOLVED]
        );

        $response->assertForbidden();
    }

    public function test_manager_sees_fab_when_new_feedback_exists(): void
    {
        Permission::findOrCreate('manage anonymous feedback');
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage anonymous feedback');

        AnonymousFeedback::query()->create([
            'category' => AnonymousFeedback::CATEGORY_COMPLAINT,
            'body' => 'Needs attention',
            'status' => AnonymousFeedback::STATUS_NEW,
        ]);

        $response = $this->actingAs($manager)->get(route('profile.edit'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->where('overlays.anonymousFeedback.count', 1)
                ->where('overlays.anonymousFeedback.hideOnCurrentPage', false));
    }

    public function test_user_without_permission_does_not_see_fab(): void
    {
        Permission::findOrCreate('manage anonymous feedback');
        $user = User::factory()->create(['status' => 'active']);

        AnonymousFeedback::query()->create([
            'category' => AnonymousFeedback::CATEGORY_COMPLAINT,
            'body' => 'Needs attention',
            'status' => AnonymousFeedback::STATUS_NEW,
        ]);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('overlays.anonymousFeedback', null));
    }

    public function test_fab_hidden_when_no_new_feedback(): void
    {
        Permission::findOrCreate('manage anonymous feedback');
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage anonymous feedback');

        AnonymousFeedback::query()->create([
            'category' => AnonymousFeedback::CATEGORY_COMPLAINT,
            'body' => 'Already handled',
            'status' => AnonymousFeedback::STATUS_RESOLVED,
        ]);

        $response = $this->actingAs($manager)->get(route('profile.edit'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('overlays.anonymousFeedback', null));
    }

    public function test_fab_hidden_on_anonymous_feedback_inbox(): void
    {
        Permission::findOrCreate('manage anonymous feedback');
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage anonymous feedback');

        AnonymousFeedback::query()->create([
            'category' => AnonymousFeedback::CATEGORY_COMPLAINT,
            'body' => 'Needs attention',
            'status' => AnonymousFeedback::STATUS_NEW,
        ]);

        $response = $this->actingAs($manager)->get(route('anonymous-feedback.index'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('overlays.anonymousFeedback.count', 1)
                ->where('overlays.anonymousFeedback.hideOnCurrentPage', true));
    }

    public function test_inbox_shows_status_chips_and_quick_actions(): void
    {
        Permission::findOrCreate('manage anonymous feedback');
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage anonymous feedback');

        AnonymousFeedback::query()->create([
            'category' => AnonymousFeedback::CATEGORY_COMPLAINT,
            'body' => 'Needs attention',
            'status' => AnonymousFeedback::STATUS_NEW,
        ]);
        AnonymousFeedback::query()->create([
            'category' => AnonymousFeedback::CATEGORY_SUGGESTION,
            'body' => 'Already moving',
            'status' => AnonymousFeedback::STATUS_IN_PROGRESS,
        ]);

        $response = $this->actingAs($manager)->get(route('anonymous-feedback.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('AnonymousFeedback/Index')
            ->where('statusCounts.new', 1)
            ->where('statusCounts.in_progress', 1)
            ->has('items.data', 2)
            ->has('qrSvg')
            ->has('feedbackUrl'));
    }

    public function test_marking_feedback_resolved_clears_fab_new_count(): void
    {
        Permission::findOrCreate('manage anonymous feedback');
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage anonymous feedback');

        $row = AnonymousFeedback::query()->create([
            'category' => AnonymousFeedback::CATEGORY_COMPLAINT,
            'body' => 'Needs attention',
            'status' => AnonymousFeedback::STATUS_NEW,
        ]);

        $this->actingAs($manager)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('overlays.anonymousFeedback.count', 1));

        $this->actingAs($manager)->patch(
            route('anonymous-feedback.update', $row),
            ['status' => AnonymousFeedback::STATUS_RESOLVED]
        )->assertRedirect();

        $this->assertSame(AnonymousFeedback::STATUS_RESOLVED, $row->fresh()->status);

        $this->actingAs($manager)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('overlays.anonymousFeedback', null));
    }

    public function test_marking_feedback_in_progress_clears_fab_new_count(): void
    {
        Permission::findOrCreate('manage anonymous feedback');
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('manage anonymous feedback');

        $row = AnonymousFeedback::query()->create([
            'category' => AnonymousFeedback::CATEGORY_ASSISTANCE,
            'body' => 'Help needed',
            'status' => AnonymousFeedback::STATUS_NEW,
        ]);

        $this->actingAs($manager)->patch(
            route('anonymous-feedback.update', $row),
            ['status' => AnonymousFeedback::STATUS_IN_PROGRESS]
        )->assertRedirect();

        $this->assertSame(AnonymousFeedback::STATUS_IN_PROGRESS, $row->fresh()->status);

        $this->actingAs($manager)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('overlays.anonymousFeedback', null));
    }
}
