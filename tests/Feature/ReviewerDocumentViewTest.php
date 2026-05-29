<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Models\WeeklyActivityAssignment;
use App\Models\WorkPeriod;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewerDocumentViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Carbon::setTestNow(Carbon::create(2026, 4, 15, 10, 0, 0));
    }

    public function test_reviewer_show_shows_related_weekly_card_when_paired_document_exists(): void
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

        $dept = Department::create(['name' => 'QA', 'status' => 'active']);
        $staff = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $staff->givePermissionTo(['create weekly plan', 'create weekly report']);

        $plan = Document::create([
            'user_id' => $staff->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Target',
            'content' => [
                'objective' => [],
                'planned_activities' => [],
                'key_meetings' => [],
                'budget' => [],
            ],
        ]);

        Document::create([
            'user_id' => $staff->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Report',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => null,
            'content' => ['summary' => 'Done'],
        ]);

        $reviewer = User::factory()->create(['status' => 'active']);
        $reviewer->givePermissionTo('view all documents');

        $reviewerResponse = $this->actingAs($reviewer)->get(route('documents.reviewer-show', $plan->id));
        $reviewerResponse->assertOk();
        $reviewerResponse->assertSee('Related Weekly Report', false);
        $reviewerResponse->assertSee('Return to Draft', false);

        $standardResponse = $this->actingAs($reviewer)->get(route('documents.show', $plan->id));
        $standardResponse->assertOk();
        $standardResponse->assertSee('Related Weekly Report', false);
        $standardResponse->assertDontSee('Return to Draft', false);

        $report = Document::query()
            ->where('period_id', $period->id)
            ->where('type', Document::TYPE_WEEKLY_REPORT)
            ->firstOrFail();

        $this->actingAs($reviewer)
            ->get(route('documents.reviewer-show', $report->id))
            ->assertOk()
            ->assertSee('Related Weekly Plan', false);
    }

    public function test_reviewer_show_includes_assigned_activities_for_weekly_plan(): void
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
        $owner = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $owner->givePermissionTo('create weekly plan');
        $assignee = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $assignee->givePermissionTo('create weekly plan');

        $plan = Document::create([
            'user_id' => $assignee->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Assignee plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Target',
            'content' => [],
        ]);

        WeeklyActivityAssignment::create([
            'source_document_id' => $plan->id,
            'source_user_id' => $owner->id,
            'assignee_user_id' => $assignee->id,
            'period_id' => $period->id,
            'activity_uid' => (string) \Illuminate\Support\Str::ulid(),
            'status' => WeeklyActivityAssignment::STATUS_PREVIEW,
            'activity_payload' => [
                'day' => 'Tuesday',
                'activity' => 'Reviewer-visible assigned task',
                'location' => 'Field',
            ],
        ]);

        $reviewer = User::factory()->create(['status' => 'active']);
        $reviewer->givePermissionTo('view all documents');

        $this->actingAs($reviewer)
            ->get(route('documents.reviewer-show', $plan->id))
            ->assertOk()
            ->assertSee('Reviewer-visible assigned task', false);

        $this->actingAs($assignee)
            ->get(route('documents.show', $plan->id))
            ->assertOk()
            ->assertSee('Reviewer-visible assigned task', false);
    }

    public function test_reviewer_show_shows_related_panel_when_paired_document_missing(): void
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

        $dept = Department::create(['name' => 'QA', 'status' => 'active']);
        $staff = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $staff->givePermissionTo('create weekly plan');

        $plan = Document::create([
            'user_id' => $staff->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Plan only',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Target',
            'content' => [],
        ]);

        $reviewer = User::factory()->create(['status' => 'active']);
        $reviewer->givePermissionTo('view all documents');

        $this->actingAs($reviewer)
            ->get(route('documents.reviewer-show', $plan->id))
            ->assertOk()
            ->assertSee('Related Weekly Report', false)
            ->assertSee('Not Created', false);
    }

    public function test_reviewer_show_returns_403_for_monthly_report(): void
    {
        $dept = Department::create(['name' => 'Finance', 'status' => 'active']);
        $staff = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $staff->givePermissionTo('create monthly report');

        $monthly = Document::create([
            'user_id' => $staff->id,
            'period_id' => null,
            'type' => Document::TYPE_MONTHLY_REPORT,
            'title' => 'April report',
            'state' => Document::STATE_SUBMITTED,
            'month' => 4,
            'year' => 2026,
            'content' => [],
        ]);

        $reviewer = User::factory()->create(['status' => 'active']);
        $reviewer->givePermissionTo('view all documents');

        $this->actingAs($reviewer)
            ->get(route('documents.reviewer-show', $monthly->id))
            ->assertForbidden();
    }

    public function test_reviewer_show_requires_view_all_documents(): void
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

        $dept = Department::create(['name' => 'Ops', 'status' => 'active']);
        $staff = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $staff->givePermissionTo('create weekly plan');

        $plan = Document::create([
            'user_id' => $staff->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Own plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'T',
            'content' => [],
        ]);

        $this->actingAs($staff)
            ->get(route('documents.reviewer-show', $plan->id))
            ->assertForbidden();
    }

    public function test_reviewer_dashboard_only_includes_documents_from_two_latest_periods(): void
    {
        $week14 = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 14,
            'week_start_date' => '2026-03-31',
            'week_end_date' => '2026-04-06',
            'plan_deadline' => '2026-04-03',
            'report_deadline' => '2026-04-04',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $week15 = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 15,
            'week_start_date' => '2026-04-07',
            'week_end_date' => '2026-04-13',
            'plan_deadline' => '2026-04-11',
            'report_deadline' => '2026-04-12',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $week16 = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 16,
            'week_start_date' => '2026-04-14',
            'week_end_date' => '2026-04-20',
            'plan_deadline' => '2026-04-18',
            'report_deadline' => '2026-04-19',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $dept = Department::create(['name' => 'Dept', 'status' => 'active']);
        $staff = User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);
        $staff->givePermissionTo(['create weekly plan', 'create weekly report']);

        Document::create([
            'user_id' => $staff->id,
            'period_id' => $week14->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Older week fourteen report',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => null,
            'content' => ['summary' => 'Old'],
        ]);

        Document::create([
            'user_id' => $staff->id,
            'period_id' => $week15->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Middle week fifteen report',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => null,
            'content' => ['summary' => 'Mid'],
        ]);

        Document::create([
            'user_id' => $staff->id,
            'period_id' => $week16->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Latest week sixteen report',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => null,
            'content' => ['summary' => 'New'],
        ]);

        $reviewer = User::factory()->create(['status' => 'active']);
        $reviewer->givePermissionTo('view all documents');

        $this->actingAs($reviewer)->get(route('documents.reviewer-dashboard'))->assertOk()
            ->assertSee('Latest week sixteen report', false)
            ->assertSee('Middle week fifteen report', false)
            ->assertDontSee('Older week fourteen report', false);

        $this->actingAs($reviewer)
            ->get(route('documents.reviewer-dashboard', ['work_period_id' => $week15->id]))
            ->assertOk()
            ->assertSee('Middle week fifteen report', false)
            ->assertDontSee('Older week fourteen report', false);

        $this->actingAs($reviewer)
            ->get(route('documents.reviewer-dashboard', [
                'work_period_id' => $week14->id,
            ]))
            ->assertOk()
            ->assertDontSee('Older week fourteen report', false);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
