<?php

namespace Tests\Feature;

use App\Models\InHouseMeeting;
use App\Models\InHouseMeetingParticipant;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Notifications\InHouseMeetingCancelledNotification;
use App\Notifications\InHouseMeetingInvitationNotification;
use App\Notifications\InHouseMeetingRsvpNotification;
use App\Notifications\InHouseMeetingUpdatedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InHouseMeetingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_staff_can_open_meetings_index(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('staff');

        $this->actingAs($user)
            ->get(route('meetings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Meetings/Index')
                ->where('tab', 'upcoming')
                ->has('meetings.data'));
    }

    public function test_staff_can_schedule_meeting_and_invitees_are_notified(): void
    {
        Notification::fake();

        /** @var User $organizer */
        $organizer = User::factory()->create(['status' => 'active']);
        $organizer->assignRole('staff');

        /** @var User $invitee */
        $invitee = User::factory()->create(['status' => 'active']);
        $invitee->assignRole('staff');

        $starts = now()->addDay()->setTime(10, 0);
        $ends = (clone $starts)->setTime(11, 0);

        $response = $this->actingAs($organizer)->post(route('meetings.store'), [
            'title' => 'Planning sync',
            'agenda' => 'Discuss next week priorities',
            'timing' => 'scheduled',
            'starts_at' => $starts->format('Y-m-d H:i:s'),
            'ends_at' => $ends->format('Y-m-d H:i:s'),
            'location' => 'Board room',
            'join_url' => 'https://meet.google.com/abc-defg-hij',
            'meeting_type' => InHouseMeeting::TYPE_ADHOC,
            'participant_user_ids' => [$invitee->id],
        ]);

        $meeting = InHouseMeeting::query()->first();
        $this->assertNotNull($meeting);
        $response->assertRedirect(route('meetings.show', $meeting));

        $this->assertSame('Planning sync', $meeting->title);
        $this->assertSame(1, $meeting->participants()->count());
        $this->assertDatabaseHas('in_house_meeting_participants', [
            'meeting_id' => $meeting->id,
            'user_id' => $invitee->id,
            'response' => InHouseMeetingParticipant::RESPONSE_PENDING,
        ]);

        Notification::assertSentTo($invitee, InHouseMeetingInvitationNotification::class);
        Notification::assertNotSentTo($organizer, InHouseMeetingInvitationNotification::class);
    }

    public function test_weekly_meeting_invites_all_active_staff(): void
    {
        Notification::fake();

        /** @var User $organizer */
        $organizer = User::factory()->create(['status' => 'active']);
        $organizer->assignRole('staff');

        /** @var User $staffA */
        $staffA = User::factory()->create(['status' => 'active']);
        /** @var User $staffB */
        $staffB = User::factory()->create(['status' => 'active']);
        User::factory()->create(['status' => 'inactive']);

        $period = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 12,
            'week_start_date' => '2026-03-16',
            'week_end_date' => '2026-03-22',
            'plan_deadline' => '2026-03-22',
            'report_deadline' => '2026-03-22',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $starts = now()->next('Monday')->setTime(9, 0);
        $ends = (clone $starts)->setTime(10, 0);

        $this->actingAs($organizer)->post(route('meetings.store'), [
            'title' => 'Monday meeting',
            'timing' => 'scheduled',
            'starts_at' => $starts->format('Y-m-d H:i:s'),
            'ends_at' => $ends->format('Y-m-d H:i:s'),
            'location' => 'Main office',
            'meeting_type' => InHouseMeeting::TYPE_WEEKLY,
            'period_id' => $period->id,
        ])->assertRedirect();

        $meeting = InHouseMeeting::query()->firstOrFail();
        $this->assertSame(InHouseMeeting::TYPE_WEEKLY, $meeting->meeting_type);
        $this->assertSame((string) $period->id, (string) $meeting->period_id);
        $this->assertSame(2, $meeting->participants()->count());

        Notification::assertSentTo($staffA, InHouseMeetingInvitationNotification::class);
        Notification::assertSentTo($staffB, InHouseMeetingInvitationNotification::class);
    }

    public function test_invitee_can_rsvp_and_organizer_is_notified(): void
    {
        Notification::fake();

        /** @var User $organizer */
        $organizer = User::factory()->create(['status' => 'active']);
        /** @var User $invitee */
        $invitee = User::factory()->create(['status' => 'active']);

        $meeting = $this->createMeeting($organizer, [$invitee->id]);

        $this->actingAs($invitee)
            ->post(route('meetings.rsvp', $meeting), [
                'response' => InHouseMeetingParticipant::RESPONSE_ACCEPTED,
            ])
            ->assertRedirect(route('meetings.show', $meeting));

        $this->assertDatabaseHas('in_house_meeting_participants', [
            'meeting_id' => $meeting->id,
            'user_id' => $invitee->id,
            'response' => InHouseMeetingParticipant::RESPONSE_ACCEPTED,
        ]);

        Notification::assertSentTo($organizer, InHouseMeetingRsvpNotification::class);
    }

    public function test_non_participant_cannot_view_adhoc_meeting(): void
    {
        /** @var User $organizer */
        $organizer = User::factory()->create(['status' => 'active']);
        /** @var User $invitee */
        $invitee = User::factory()->create(['status' => 'active']);
        /** @var User $outsider */
        $outsider = User::factory()->create(['status' => 'active']);
        $outsider->assignRole('staff');

        $meeting = $this->createMeeting($organizer, [$invitee->id]);

        $this->actingAs($outsider)
            ->get(route('meetings.show', $meeting))
            ->assertForbidden();
    }

    public function test_organizer_can_update_and_cancel_meeting(): void
    {
        Notification::fake();

        /** @var User $organizer */
        $organizer = User::factory()->create(['status' => 'active']);
        /** @var User $invitee */
        $invitee = User::factory()->create(['status' => 'active']);

        $meeting = $this->createMeeting($organizer, [$invitee->id]);

        $starts = now()->addDays(2)->setTime(14, 0);
        $ends = (clone $starts)->setTime(15, 0);

        $this->actingAs($organizer)->put(route('meetings.update', $meeting), [
            'title' => 'Rescheduled sync',
            'agenda' => 'Updated agenda',
            'timing' => 'scheduled',
            'starts_at' => $starts->format('Y-m-d H:i:s'),
            'ends_at' => $ends->format('Y-m-d H:i:s'),
            'location' => 'Office',
            'meeting_type' => InHouseMeeting::TYPE_ADHOC,
            'participant_user_ids' => [$invitee->id],
        ])->assertRedirect(route('meetings.show', $meeting));

        Notification::assertSentTo($invitee, InHouseMeetingUpdatedNotification::class);

        $this->actingAs($organizer)
            ->post(route('meetings.cancel', $meeting))
            ->assertRedirect(route('meetings.show', $meeting));

        $this->assertSame(InHouseMeeting::STATUS_CANCELLED, $meeting->fresh()->status);
        Notification::assertSentTo($invitee, InHouseMeetingCancelledNotification::class);
    }

    public function test_staff_can_start_instant_meeting(): void
    {
        Notification::fake();

        /** @var User $organizer */
        $organizer = User::factory()->create(['status' => 'active']);
        $organizer->assignRole('staff');

        /** @var User $invitee */
        $invitee = User::factory()->create(['status' => 'active']);
        $invitee->assignRole('staff');

        $response = $this->actingAs($organizer)->post(route('meetings.store'), [
            'timing' => 'instant',
            'duration_minutes' => 30,
            'meeting_type' => InHouseMeeting::TYPE_ADHOC,
            'participant_user_ids' => [$invitee->id],
        ]);

        $meeting = InHouseMeeting::query()->firstOrFail();
        $response->assertRedirect(route('meetings.call', $meeting));
        $this->assertStringContainsString('Voice call', $meeting->title);
        $this->assertTrue($meeting->starts_at->between(now()->subMinute(), now()->addMinute()));
        $this->assertSame(30, (int) round($meeting->starts_at->diffInMinutes($meeting->ends_at)));

        Notification::assertSentTo($invitee, InHouseMeetingInvitationNotification::class);

        $this->actingAs($invitee)
            ->get(route('meetings.call', $meeting))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Meetings/Call')
                ->where('urls.index', route('meetings.index'))
                ->where('canEndCall', false));

        $this->actingAs($organizer)
            ->get(route('meetings.call', $meeting))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Meetings/Call')
                ->where('canEndCall', true)
                ->where('urls.end', route('meetings.call.end', $meeting)));
    }

    public function test_organizer_can_end_voice_call_for_everyone(): void
    {
        /** @var User $organizer */
        $organizer = User::factory()->create(['status' => 'active']);
        /** @var User $invitee */
        $invitee = User::factory()->create(['status' => 'active']);

        $meeting = $this->createMeeting($organizer, [$invitee->id], live: true);

        $this->actingAs($organizer)
            ->postJson(route('meetings.call.heartbeat', $meeting))
            ->assertOk();

        $this->actingAs($invitee)
            ->postJson(route('meetings.call.heartbeat', $meeting))
            ->assertOk();

        $this->actingAs($invitee)
            ->getJson(route('meetings.call.peers', $meeting))
            ->assertOk()
            ->assertJsonPath('ended', false);

        $this->actingAs($invitee)
            ->postJson(route('meetings.call.end', $meeting))
            ->assertForbidden();

        $this->actingAs($organizer)
            ->postJson(route('meetings.call.end', $meeting))
            ->assertOk()
            ->assertJson(['ok' => true, 'ended' => true]);

        $meeting->refresh();
        $this->assertSame(InHouseMeeting::STATUS_COMPLETED, $meeting->status);
        $this->assertTrue($meeting->ends_at->lte(now()));

        $this->actingAs($invitee)
            ->getJson(route('meetings.call.peers', $meeting))
            ->assertOk()
            ->assertJson(['ended' => true, 'peers' => []]);

        $this->actingAs($invitee)
            ->get(route('meetings.call', $meeting))
            ->assertRedirect(route('meetings.index'));

        $this->actingAs($invitee)
            ->postJson(route('meetings.call.leave', $meeting))
            ->assertOk();
    }

    public function test_outsider_cannot_join_voice_call(): void
    {
        /** @var User $organizer */
        $organizer = User::factory()->create(['status' => 'active']);
        /** @var User $invitee */
        $invitee = User::factory()->create(['status' => 'active']);
        /** @var User $outsider */
        $outsider = User::factory()->create(['status' => 'active']);
        $outsider->assignRole('staff');

        $meeting = $this->createMeeting($organizer, [$invitee->id]);

        $this->actingAs($outsider)
            ->get(route('meetings.call', $meeting))
            ->assertForbidden();
    }

    public function test_organizer_can_save_meeting_records(): void
    {
        /** @var User $organizer */
        $organizer = User::factory()->create(['status' => 'active']);
        /** @var User $invitee */
        $invitee = User::factory()->create(['status' => 'active']);

        $meeting = $this->createMeeting($organizer, [$invitee->id]);

        $this->actingAs($organizer)->put(route('meetings.records', $meeting), [
            'minutes_notes' => 'Brief summary of the discussion.',
        ])->assertRedirect(route('meetings.show', $meeting));

        $this->actingAs($organizer)->put(route('meetings.records', $meeting), [
            'action_items' => [
                [
                    'title' => 'Draft follow-up note',
                    'assignee_id' => $invitee->id,
                    'due_date' => now()->addWeek()->toDateString(),
                    'status' => 'open',
                ],
            ],
        ])->assertRedirect(route('meetings.show', $meeting));

        $meeting->refresh();
        $this->assertSame('Brief summary of the discussion.', $meeting->minutes_notes);
        $this->assertSame(1, $meeting->actionItems()->count());
        $this->assertSame('Draft follow-up note', $meeting->actionItems()->first()->title);
    }

    public function test_participant_can_save_minutes_during_call_without_leaving(): void
    {
        /** @var User $organizer */
        $organizer = User::factory()->create(['status' => 'active']);
        /** @var User $invitee */
        $invitee = User::factory()->create(['status' => 'active']);

        $meeting = $this->createMeeting($organizer, [$invitee->id]);

        $this->actingAs($invitee)
            ->postJson(route('meetings.call.minutes', $meeting), [
                'minutes_notes' => 'Live notes from the call.',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'minutes_notes' => 'Live notes from the call.',
            ]);

        $this->assertSame('Live notes from the call.', $meeting->fresh()->minutes_notes);
    }

    public function test_organizer_can_save_action_plan_during_call(): void
    {
        /** @var User $organizer */
        $organizer = User::factory()->create(['status' => 'active']);
        /** @var User $invitee */
        $invitee = User::factory()->create(['status' => 'active']);

        $meeting = $this->createMeeting($organizer, [$invitee->id]);

        $this->actingAs($organizer)
            ->postJson(route('meetings.call.actions', $meeting), [
                'action_items' => [
                    [
                        'title' => 'Send summary email',
                        'assignee_id' => $invitee->id,
                        'due_date' => now()->addDays(2)->toDateString(),
                        'status' => 'open',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('action_items.0.title', 'Send summary email');

        $this->assertSame(1, $meeting->actionItems()->count());
    }

    public function test_voice_is_relayed_to_the_other_participant(): void
    {
        /** @var User $organizer */
        $organizer = User::factory()->create(['status' => 'active']);
        /** @var User $invitee */
        $invitee = User::factory()->create(['status' => 'active']);
        /** @var User $outsider */
        $outsider = User::factory()->create(['status' => 'active']);
        $outsider->assignRole('staff');

        $meeting = $this->createMeeting($organizer, [$invitee->id]);
        $frame = base64_encode(random_bytes(320));

        $this->actingAs($organizer)
            ->postJson(route('meetings.call.audio.push', $meeting), [
                'chunks' => [
                    ['seq' => 1, 'audio' => $frame],
                ],
            ])
            ->assertOk();

        $this->actingAs($invitee)
            ->postJson(route('meetings.call.audio.pull', $meeting), [
                'after' => [],
            ])
            ->assertOk()
            ->assertJsonPath('chunks.0.user_id', (string) $organizer->id)
            ->assertJsonPath('chunks.0.seq', 1)
            ->assertJsonPath('chunks.0.audio', $frame);

        $this->actingAs($invitee)
            ->postJson(route('meetings.call.audio.pull', $meeting), [
                'after' => [(string) $organizer->id => 1],
            ])
            ->assertOk()
            ->assertJsonPath('chunks', []);

        $this->actingAs($outsider)
            ->postJson(route('meetings.call.audio.pull', $meeting), [])
            ->assertForbidden();
    }

    /**
     * @param  list<string>  $participantIds
     */
    private function createMeeting(User $organizer, array $participantIds, bool $live = false): InHouseMeeting
    {
        $starts = $live ? now()->subMinute() : now()->addDay()->setTime(10, 0);
        $ends = $live ? now()->addHour() : (clone $starts)->setTime(11, 0);

        $meeting = InHouseMeeting::create([
            'organizer_id' => $organizer->id,
            'title' => 'Team sync',
            'agenda' => 'Catch up',
            'starts_at' => $starts,
            'ends_at' => $ends,
            'location' => 'Office',
            'meeting_type' => InHouseMeeting::TYPE_ADHOC,
            'status' => InHouseMeeting::STATUS_SCHEDULED,
        ]);

        foreach ($participantIds as $userId) {
            $meeting->participants()->create([
                'user_id' => $userId,
                'response' => InHouseMeetingParticipant::RESPONSE_PENDING,
            ]);
        }

        return $meeting;
    }
}
