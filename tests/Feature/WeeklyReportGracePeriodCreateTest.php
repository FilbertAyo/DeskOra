<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\WeeklySubmissionWindowService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyReportGracePeriodCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_resolve_report_slot_honors_preferred_closed_period_in_report_grace(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 20, 10, 0, 0)); // Week 30

        $week29 = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 29,
            'week_start_date' => '2026-07-13',
            'week_end_date' => '2026-07-19',
            'plan_deadline' => '2026-07-11',
            'report_deadline' => '2026-07-22', // extended past default Sat 18
            'status' => WorkPeriod::STATUS_CLOSED,
        ]);

        WorkPeriod::create([
            'year' => 2026,
            'week_number' => 30,
            'week_start_date' => '2026-07-20',
            'week_end_date' => '2026-07-26',
            'plan_deadline' => '2026-07-22',
            'report_deadline' => '2026-07-25',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $this->assertTrue($week29->fresh()->isReportGracePeriodActive());
        $this->assertFalse($week29->fresh()->isOpen());

        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('create weekly report');

        $slot = app(WeeklySubmissionWindowService::class)->resolveReportSlot($user, $week29->fresh());

        $this->assertTrue($slot->showAction());
        $this->assertSame($week29->id, $slot->period->id);
        $this->assertTrue($slot->graceActive);
    }

    public function test_create_weekly_report_loads_plan_for_grace_period_not_current_week(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 20, 10, 0, 0)); // Week 30

        $week29 = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 29,
            'week_start_date' => '2026-07-13',
            'week_end_date' => '2026-07-19',
            'plan_deadline' => '2026-07-11',
            'report_deadline' => '2026-07-22',
            'status' => WorkPeriod::STATUS_CLOSED,
        ]);

        $week30 = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 30,
            'week_start_date' => '2026-07-20',
            'week_end_date' => '2026-07-26',
            'plan_deadline' => '2026-07-22',
            'report_deadline' => '2026-07-25',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('create weekly report');

        Document::create([
            'user_id' => $user->id,
            'period_id' => $week29->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Week 29 plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'UNIQUE_WEEK_29_PLAN_TARGET',
            'content' => [
                'planned_activities' => [
                    ['day' => 'Monday', 'activity' => 'Week 29 activity only'],
                ],
            ],
        ]);

        Document::create([
            'user_id' => $user->id,
            'period_id' => $week30->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Week 30 plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'UNIQUE_WEEK_30_PLAN_TARGET',
            'content' => [
                'planned_activities' => [
                    ['day' => 'Monday', 'activity' => 'Week 30 activity only'],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('documents.create', [
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $week29->id,
        ]));

        $response->assertOk();
        $response->assertDontSee('Account Deactivated', false);
        $response->assertSee('UNIQUE_WEEK_29_PLAN_TARGET', false);
        $response->assertDontSee('UNIQUE_WEEK_30_PLAN_TARGET', false);
        $response->assertSee('Week 29 activity only', false);
        $response->assertDontSee('Week 30 activity only', false);
        $response->assertSee('value="'.$week29->id.'"', false);
    }

    public function test_shared_plan_collaborator_does_not_see_report_grace_when_group_report_submitted(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 20, 10, 0, 0)); // Week 30

        $week29 = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 29,
            'week_start_date' => '2026-07-13',
            'week_end_date' => '2026-07-19',
            'plan_deadline' => '2026-07-11',
            'report_deadline' => '2026-07-22',
            'status' => WorkPeriod::STATUS_CLOSED,
        ]);

        WorkPeriod::create([
            'year' => 2026,
            'week_number' => 30,
            'week_start_date' => '2026-07-20',
            'week_end_date' => '2026-07-26',
            'plan_deadline' => '2026-07-22',
            'report_deadline' => '2026-07-25',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $owner = User::factory()->create(['status' => 'active']);
        $owner->givePermissionTo(['create weekly plan', 'create weekly report']);

        $collaborator = User::factory()->create(['status' => 'active']);
        $collaborator->givePermissionTo(['create weekly plan', 'create weekly report']);

        $plan = Document::create([
            'user_id' => $owner->id,
            'period_id' => $week29->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Shared week 29 plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Shared target',
            'content' => ['planned_activities' => []],
        ]);
        $plan->sharedUsers()->sync([$collaborator->id]);

        $report = Document::create([
            'user_id' => $owner->id,
            'period_id' => $week29->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Shared week 29 report',
            'state' => Document::STATE_SUBMITTED,
            'content' => [],
        ]);
        $report->sharedUsers()->sync([$collaborator->id]);

        $windows = app(WeeklySubmissionWindowService::class);

        $this->assertFalse($windows->graceReportEntries($collaborator)->isNotEmpty());
        $this->assertFalse($windows->reportSlotForPeriod($collaborator, $week29->fresh())->showAction());
        $this->assertFalse($windows->resolveReportSlot($collaborator, $week29->fresh())->showAction());
    }

    public function test_shared_plan_collaborator_does_not_see_plan_grace_when_group_plan_submitted(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 20, 10, 0, 0)); // Week 30

        $week30 = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 30,
            'week_start_date' => '2026-07-20',
            'week_end_date' => '2026-07-26',
            'plan_deadline' => '2026-07-22',
            'report_deadline' => '2026-07-25',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $this->assertTrue($week30->fresh()->isPlanGracePeriodActive());

        $owner = User::factory()->create(['status' => 'active']);
        $owner->givePermissionTo('create weekly plan');

        $collaborator = User::factory()->create(['status' => 'active']);
        $collaborator->givePermissionTo('create weekly plan');

        $plan = Document::create([
            'user_id' => $owner->id,
            'period_id' => $week30->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Shared week 30 plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Shared target',
            'content' => ['planned_activities' => []],
        ]);
        $plan->sharedUsers()->sync([$collaborator->id]);

        $windows = app(WeeklySubmissionWindowService::class);

        $this->assertFalse($windows->gracePlanEntries($collaborator)->isNotEmpty());
        $this->assertFalse($windows->planSlotForPeriod($collaborator, $week30->fresh())->showAction());
    }
}
