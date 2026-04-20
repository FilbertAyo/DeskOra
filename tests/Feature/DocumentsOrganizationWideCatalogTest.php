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
