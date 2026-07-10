<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\DocumentAiSummaryBuilder;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentAiSummaryBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_weekly_plan_includes_team_participants_collaboration_and_carry_over(): void
    {
        $department = Department::create(['name' => 'Programs']);
        $owner = User::factory()->create(['department_id' => $department->id, 'name' => 'Plan Owner']);
        $teammate = User::factory()->create(['department_id' => $department->id, 'name' => 'Team Mate']);
        $collaborator = User::factory()->create(['department_id' => $department->id, 'name' => 'Collab Staff']);
        $participant = User::factory()->create(['department_id' => $department->id, 'name' => 'Meeting Guest']);

        $period = $this->createPeriod();

        $priorReport = Document::create([
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $period->id,
            'user_id' => $owner->id,
            'title' => 'Prior report',
            'main_target' => 'Earlier target',
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'summary' => 'Prior week',
            ],
        ]);

        $plan = Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $period->id,
            'user_id' => $owner->id,
            'title' => 'Plan',
            'main_target' => 'Ship the feature',
            'state' => Document::STATE_DRAFT,
            'content' => [
                'objective' => ['reflection_progress' => 'Halfway there'],
                'planned_activities' => [
                    [
                        'day' => 'Monday',
                        'activity' => 'Write tests',
                        'location' => 'Office',
                        'team_member_ids' => [$teammate->id],
                        'notes_inclusion' => 'Focus on AI',
                        'carry_over_source_weekly_report_id' => $priorReport->id,
                    ],
                ],
                'key_meetings' => [
                    [
                        'date' => '2025-04-08',
                        'meeting' => 'Standup',
                        'location' => 'Virtual',
                        'participant_user_ids' => [$participant->id],
                        'reactions_notes' => 'Good energy',
                    ],
                ],
                'budget' => [
                    [
                        'item' => 'Transport',
                        'description_purpose' => 'Field visit',
                        'amount' => '50000',
                    ],
                ],
            ],
        ]);

        $plan->sharedUsers()->sync([$collaborator->id]);
        $plan->load(['user', 'period', 'sharedUsers']);

        $text = app(DocumentAiSummaryBuilder::class)->buildCombined($plan, null);

        $this->assertStringContainsString('Main objective: Ship the feature', $text);
        $this->assertStringContainsString('Collaboration staff: Collab Staff', $text);
        $this->assertStringContainsString('Write tests', $text);
        $this->assertStringContainsString('Team: Team Mate', $text);
        $this->assertStringContainsString('Carried from previous report', $text);
        $this->assertStringContainsString('Participants: Meeting Guest', $text);
        $this->assertStringContainsString('Transport', $text);
        $this->assertStringContainsString('50000', $text);
    }

    public function test_weekly_report_includes_target_explanation_meeting_status_and_off_plan(): void
    {
        $department = Department::create(['name' => 'Programs']);
        $owner = User::factory()->create(['department_id' => $department->id, 'name' => 'Report Owner']);
        $period = $this->createPeriod();

        $plan = Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $period->id,
            'user_id' => $owner->id,
            'title' => 'Plan',
            'main_target' => 'Ship the feature',
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'budget' => [
                    ['item' => 'Fuel', 'description_purpose' => 'Travel', 'amount' => '20000'],
                ],
            ],
            'budget_review_status' => Document::BUDGET_REVIEW_APPROVED,
        ]);

        $report = Document::create([
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $period->id,
            'user_id' => $owner->id,
            'title' => 'Report',
            'main_target' => 'Ship the feature',
            'state' => Document::STATE_DRAFT,
            'target_accomplished_status' => 'partially_completed',
            'target_explanation' => 'Blocked by dependency',
            'content' => [
                'summary' => 'Busy week',
                'target_accomplished_status' => 'partially_completed',
                'target_explanation' => 'Blocked by dependency',
                'activity_progress' => [
                    [
                        'activity_output' => '__off_plan__',
                        'off_plan_activity' => 'Emergency support call',
                        'progress' => 'Done',
                        'remarks_challenges' => 'Urgent',
                        'next_step' => 'Document it',
                    ],
                ],
                'key_meetings' => [
                    [
                        'date' => '2025-04-09',
                        'meeting' => 'Client sync',
                        'status' => 'Done',
                        'participant_user_ids' => [],
                    ],
                ],
                'key_achievements' => ['Closed a ticket'],
                'challenges_mitigation' => [
                    ['challenge' => 'Late vendor', 'action_taken_planned' => 'Escalated'],
                ],
            ],
        ]);

        $text = app(DocumentAiSummaryBuilder::class)->buildCombined($plan, $report);

        $this->assertStringContainsString('Main target status: partially completed', $text);
        $this->assertStringContainsString('Target explanation: Blocked by dependency', $text);
        $this->assertStringContainsString('Off-plan: Emergency support call', $text);
        $this->assertStringContainsString('Status: Done', $text);
        $this->assertStringContainsString('Closed a ticket', $text);
        $this->assertStringContainsString('Late vendor', $text);
        $this->assertStringContainsString('Fuel', $text);
        $this->assertStringContainsString('Review: Approved', $text);
    }

    private function createPeriod(): WorkPeriod
    {
        $start = Carbon::create(2025, 4, 7)->startOfWeek(Carbon::MONDAY);

        return WorkPeriod::create([
            'year' => $start->isoWeekYear(),
            'week_number' => $start->isoWeek(),
            'week_start_date' => $start->format('Y-m-d'),
            'week_end_date' => $start->copy()->addDays(6)->format('Y-m-d'),
            'plan_deadline' => $start->copy()->subDays(2)->format('Y-m-d'),
            'report_deadline' => $start->copy()->addDays(5)->format('Y-m-d'),
            'status' => WorkPeriod::STATUS_OPEN,
        ]);
    }
}
