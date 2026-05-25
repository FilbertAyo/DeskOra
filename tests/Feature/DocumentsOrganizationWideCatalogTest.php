<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentsOrganizationWideCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow(Carbon::create(2026, 4, 15, 10, 0, 0));
    }

    public function test_documents_index_lists_other_users_submitted_documents(): void
    {
        $period = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 16,
            'week_start_date' => '2026-04-13',
            'week_end_date' => '2026-04-19',
            'plan_deadline' => '2026-04-17',
            'report_deadline' => '2026-04-18',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $dept = Department::create(['name' => 'Programs', 'status' => 'active']);
        $author = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $author->givePermissionTo(['create weekly plan', 'create weekly report']);

        Document::create([
            'user_id' => $author->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Colleague Plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Ship feature',
            'content' => [],
        ]);

        $viewer = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $viewer->givePermissionTo(['create weekly plan', 'create weekly report']);

        $response = $this->actingAs($viewer)->get(route('documents.index'));
        $response->assertOk();
        $response->assertSee('Colleague Plan', false);
        $response->assertSee(e($author->name), false);
    }

    public function test_staff_can_view_another_users_submitted_weekly_plan(): void
    {
        $period = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 16,
            'week_start_date' => '2026-04-13',
            'week_end_date' => '2026-04-19',
            'plan_deadline' => '2026-04-17',
            'report_deadline' => '2026-04-18',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $dept = Department::create(['name' => 'Programs', 'status' => 'active']);
        $author = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $author->givePermissionTo(['create weekly plan']);

        $plan = Document::create([
            'user_id' => $author->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Shared visibility plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Milestone A',
            'content' => [],
        ]);

        $viewer = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $viewer->givePermissionTo(['create weekly report']);

        $this->actingAs($viewer)
            ->get(route('documents.show', $plan))
            ->assertOk();
    }

    public function test_non_author_does_not_see_related_create_action_on_weekly_plan(): void
    {
        $period = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 16,
            'week_start_date' => '2026-04-13',
            'week_end_date' => '2026-04-19',
            'plan_deadline' => '2026-04-17',
            'report_deadline' => '2026-04-25 23:59:00',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $dept = Department::create(['name' => 'Programs', 'status' => 'active']);
        $author = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $author->givePermissionTo(['create weekly plan', 'create weekly report']);

        $plan = Document::create([
            'user_id' => $author->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Author Plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Milestone A',
            'content' => [],
        ]);

        $viewer = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $viewer->givePermissionTo(['create weekly plan', 'create weekly report']);

        $this->actingAs($viewer)
            ->get(route('documents.show', $plan))
            ->assertOk()
            ->assertDontSee('Create Weekly Report for this Week', false);
    }

    public function test_documents_show_redirects_action_logs_to_action_logs_show(): void
    {
        $period = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 16,
            'week_start_date' => '2026-04-13',
            'week_end_date' => '2026-04-19',
            'plan_deadline' => '2026-04-17',
            'report_deadline' => '2026-04-18',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $dept = Department::create(['name' => 'Programs', 'status' => 'active']);
        $manager = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $manager->givePermissionTo(['create action logs']);

        $log = Document::create([
            'user_id' => $manager->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_ACTION_LOG,
            'title' => 'Week 16 actions',
            'state' => Document::STATE_SUBMITTED,
            'content' => ['tasks' => []],
        ]);

        $viewer = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $viewer->givePermissionTo(['create weekly report']);

        $this->actingAs($viewer)
            ->get(route('documents.show', $log))
            ->assertRedirect(route('action-logs.show', $log));
    }

    public function test_documents_index_pairs_submitted_plan_and_report_on_one_row_for_same_staff(): void
    {
        $period = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 16,
            'week_start_date' => '2026-04-13',
            'week_end_date' => '2026-04-19',
            'plan_deadline' => '2026-04-17',
            'report_deadline' => '2026-04-18',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $dept = Department::create(['name' => 'Programs', 'status' => 'active']);
        $staff = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $staff->givePermissionTo(['create weekly plan', 'create weekly report', 'view all documents']);

        Document::create([
            'user_id' => $staff->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Staff Week 16 Plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Deliver training',
            'content' => [],
        ]);

        Document::create([
            'user_id' => $staff->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Staff Week 16 Report',
            'state' => Document::STATE_SUBMITTED,
            'content' => ['summary' => 'Completed'],
        ]);

        $this->actingAs($staff)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertSee('Staff Week 16 Plan', false)
            ->assertSee('Staff Week 16 Report', false);
    }

    public function test_documents_index_pairs_collaborator_report_with_plan_owner_plan(): void
    {
        $period = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 16,
            'week_start_date' => '2026-04-13',
            'week_end_date' => '2026-04-19',
            'plan_deadline' => '2026-04-17',
            'report_deadline' => '2026-04-18',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $dept = Department::create(['name' => 'Programs', 'status' => 'active']);
        $planOwner = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $planOwner->givePermissionTo(['create weekly plan', 'create weekly report', 'view all documents']);
        $collaborator = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $collaborator->givePermissionTo(['create weekly report']);

        $plan = Document::create([
            'user_id' => $planOwner->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Team Week 16 Plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Joint delivery',
            'content' => [],
        ]);
        $plan->sharedUsers()->sync([$collaborator->id]);

        Document::create([
            'user_id' => $collaborator->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Collaborator Week 16 Report',
            'state' => Document::STATE_SUBMITTED,
            'content' => ['summary' => 'Done'],
        ]);

        $response = $this->actingAs($planOwner)->get(route('documents.index'));
        $response->assertOk();
        $response->assertSee('Team Week 16 Plan', false);
        $response->assertSee('Collaborator Week 16 Report', false);
        $response->assertSee(e($planOwner->name), false);
        $response->assertSee(e($collaborator->name), false);
    }

    public function test_staff_cannot_view_another_users_draft_weekly_plan(): void
    {
        $period = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 16,
            'week_start_date' => '2026-04-13',
            'week_end_date' => '2026-04-19',
            'plan_deadline' => '2026-04-17',
            'report_deadline' => '2026-04-18',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $dept = Department::create(['name' => 'Programs', 'status' => 'active']);
        $author = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $author->givePermissionTo(['create weekly plan']);

        $plan = Document::create([
            'user_id' => $author->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Private draft',
            'state' => Document::STATE_DRAFT,
            'main_target' => 'Secret',
            'content' => [],
        ]);

        $viewer = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $viewer->givePermissionTo(['create weekly report']);

        $this->actingAs($viewer)
            ->get(route('documents.show', $plan))
            ->assertForbidden();
    }
}
