<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyDocumentPdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow(Carbon::create(2025, 4, 2, 10, 0, 0));
    }

    public function test_submitted_weekly_plan_download_returns_pdf(): void
    {
        $period = $this->createPeriod();
        $owner = $this->createWeeklyUser();
        $viewer = User::factory()->create();

        $plan = Document::create([
            'user_id' => $owner->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Deliver quarterly review',
            'content' => [
                'objective' => [
                    'reflection_progress' => 'On track',
                ],
                'planned_activities' => [
                    [
                        'day' => 'Monday',
                        'activity' => 'Team sync',
                        'location' => 'Office',
                        'notes_inclusion' => '',
                        'team_member_ids' => [],
                    ],
                ],
                'key_meetings' => [],
                'budget' => [],
            ],
        ]);

        $response = $this->actingAs($viewer)->get(route('documents.download', $plan));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition');
    }

    public function test_submitted_weekly_report_download_returns_pdf(): void
    {
        $period = $this->createPeriod();
        $owner = $this->createWeeklyUser();
        $viewer = User::factory()->create();

        Document::create([
            'user_id' => $owner->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Deliver quarterly review',
            'content' => [
                'planned_activities' => [],
                'key_meetings' => [],
                'budget' => [],
            ],
        ]);

        $report = Document::create([
            'user_id' => $owner->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'summary' => 'Productive week overall.',
                'target_accomplished_status' => 'completed',
                'activity_progress' => [
                    [
                        'activity_output' => 'Monday - Team sync',
                        'off_plan_activity' => '',
                        'progress' => 'Done',
                        'remarks_challenges' => '',
                        'next_step' => '',
                    ],
                ],
                'key_achievements' => [
                    ['achievement' => 'Completed team sync'],
                ],
                'key_meetings' => [],
                'challenges_mitigation' => [],
                'budget_statuses' => [],
            ],
        ]);

        $response = $this->actingAs($viewer)->get(route('documents.download', $report));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition');
    }

    public function test_download_returns_404_for_non_weekly_document_types(): void
    {
        $period = $this->createPeriod();
        $user = User::factory()->create();
        $user->givePermissionTo('manage action logs');

        $actionLog = Document::create([
            'user_id' => $user->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_ACTION_LOG,
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'tasks' => [],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('documents.download', $actionLog));

        $response->assertNotFound();
    }

    public function test_download_draft_weekly_plan_forbidden_for_other_users(): void
    {
        $period = $this->createPeriod();
        $owner = $this->createWeeklyUser();
        $otherUser = User::factory()->create();

        $plan = Document::create([
            'user_id' => $owner->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'state' => Document::STATE_DRAFT,
            'main_target' => 'Draft target',
            'content' => [
                'planned_activities' => [],
                'key_meetings' => [],
                'budget' => [],
            ],
        ]);

        $response = $this->actingAs($otherUser)->get(route('documents.download', $plan));

        $response->assertForbidden();
    }

    private function createPeriod(): WorkPeriod
    {
        return WorkPeriod::create([
            'year' => 2025,
            'week_number' => 14,
            'week_start_date' => '2025-03-31',
            'week_end_date' => '2025-04-06',
            'plan_deadline' => '2025-04-04',
            'report_deadline' => '2025-04-05',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);
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
