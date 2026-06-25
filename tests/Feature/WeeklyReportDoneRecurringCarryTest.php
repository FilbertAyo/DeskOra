<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WeeklyReportDoneRecurringCarryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow(Carbon::create(2025, 4, 2, 10, 0, 0));
    }

    public function test_done_and_carry_sets_carry_flag_and_syncs_to_next_week_plan(): void
    {
        [$currentPeriod, $nextPeriod] = $this->createCurrentAndNextPeriods();
        $user = $this->createWeeklyUser();
        $activityUid = (string) Str::ulid();

        Document::create([
            'user_id' => $user->id,
            'period_id' => $currentPeriod->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'state' => Document::STATE_DRAFT,
            'main_target' => 'Current week plan',
            'content' => [
                'planned_activities' => [
                    [
                        'activity_uid' => $activityUid,
                        'day' => 'Monday',
                        'activity' => 'Weekly team sync',
                        'location' => 'Office',
                        'notes_inclusion' => '',
                        'team_member_ids' => [],
                    ],
                ],
                'key_meetings' => [],
                'budget' => [],
            ],
        ]);

        $report = Document::create([
            'user_id' => $user->id,
            'period_id' => $currentPeriod->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'state' => Document::STATE_DRAFT,
            'content' => [
                'activity_progress' => [],
                'key_meetings' => [],
                'budget_statuses' => [],
            ],
        ]);

        $nextPlan = Document::create([
            'user_id' => $user->id,
            'period_id' => $nextPeriod->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'state' => Document::STATE_DRAFT,
            'main_target' => 'Next week plan',
            'content' => [
                'planned_activities' => [],
                'key_meetings' => [],
                'budget' => [],
            ],
        ]);

        $response = $this->actingAs($user)->put(route('documents.update', $report), [
            'action' => 'draft',
            'content' => [
                'activity_progress' => [
                    [
                        'activity_output' => 'Monday - Weekly team sync',
                        'off_plan_activity' => '',
                        'progress' => 'Done and carry',
                        'remarks_challenges' => '',
                        'next_step' => '',
                    ],
                ],
                'key_meetings' => [],
                'budget_statuses' => [],
            ],
        ]);

        $response->assertRedirect(route('documents.edit', $report));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('weekly_report_activity_progress', [
            'weekly_report_id' => $report->id,
            'progress' => 'Done and carry',
            'carry_to_next_plan' => true,
        ]);

        $nextPlan->refresh();
        $plannedActivities = $nextPlan->content['planned_activities'] ?? [];
        $this->assertNotEmpty($plannedActivities);
        $this->assertSame('Weekly team sync', $plannedActivities[0]['activity'] ?? null);
        $this->assertSame('Monday', $plannedActivities[0]['day'] ?? null);
        $this->assertSame($activityUid, $plannedActivities[0]['activity_uid'] ?? null);
    }

    public function test_plain_done_does_not_carry_to_next_week_plan(): void
    {
        [$currentPeriod, $nextPeriod] = $this->createCurrentAndNextPeriods();
        $user = $this->createWeeklyUser();

        Document::create([
            'user_id' => $user->id,
            'period_id' => $currentPeriod->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'state' => Document::STATE_DRAFT,
            'main_target' => 'Current week plan',
            'content' => [
                'planned_activities' => [
                    [
                        'activity_uid' => (string) Str::ulid(),
                        'day' => 'Tuesday',
                        'activity' => 'One-off delivery',
                        'location' => 'Office',
                        'notes_inclusion' => '',
                        'team_member_ids' => [],
                    ],
                ],
                'key_meetings' => [],
                'budget' => [],
            ],
        ]);

        $report = Document::create([
            'user_id' => $user->id,
            'period_id' => $currentPeriod->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'state' => Document::STATE_DRAFT,
            'content' => [
                'activity_progress' => [],
                'key_meetings' => [],
                'budget_statuses' => [],
            ],
        ]);

        $nextPlan = Document::create([
            'user_id' => $user->id,
            'period_id' => $nextPeriod->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'state' => Document::STATE_DRAFT,
            'main_target' => 'Next week plan',
            'content' => [
                'planned_activities' => [],
                'key_meetings' => [],
                'budget' => [],
            ],
        ]);

        $response = $this->actingAs($user)->put(route('documents.update', $report), [
            'action' => 'draft',
            'content' => [
                'activity_progress' => [
                    [
                        'activity_output' => 'Tuesday - One-off delivery',
                        'off_plan_activity' => '',
                        'progress' => 'Done',
                        'remarks_challenges' => '',
                        'next_step' => '',
                    ],
                ],
                'key_meetings' => [],
                'budget_statuses' => [],
            ],
        ]);

        $response->assertRedirect(route('documents.edit', $report));

        $this->assertDatabaseHas('weekly_report_activity_progress', [
            'weekly_report_id' => $report->id,
            'progress' => 'Done',
            'carry_to_next_plan' => false,
        ]);

        $nextPlan->refresh();
        $this->assertSame([], $nextPlan->content['planned_activities'] ?? []);
    }

    /**
     * @return array{0: WorkPeriod, 1: WorkPeriod}
     */
    private function createCurrentAndNextPeriods(): array
    {
        $current = WorkPeriod::create([
            'year' => 2025,
            'week_number' => 14,
            'week_start_date' => '2025-03-31',
            'week_end_date' => '2025-04-06',
            'plan_deadline' => '2025-04-04',
            'report_deadline' => '2025-04-05',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $next = WorkPeriod::create([
            'year' => 2025,
            'week_number' => 15,
            'week_start_date' => '2025-04-07',
            'week_end_date' => '2025-04-13',
            'plan_deadline' => '2025-04-11',
            'report_deadline' => '2025-04-12',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        return [$current, $next];
    }

    private function createWeeklyUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['create weekly plan', 'create weekly report']);

        return $user;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
