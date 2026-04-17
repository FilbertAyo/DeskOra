<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
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

    public function test_reviewer_show_hides_related_weekly_card_when_report_exists(): void
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
        $reviewerResponse->assertDontSee('Related Weekly Report', false);
        $reviewerResponse->assertSee('Return to Draft', false);

        $standardResponse = $this->actingAs($reviewer)->get(route('documents.show', $plan->id));
        $standardResponse->assertOk();
        $standardResponse->assertSee('Related Weekly Report', false);
        $standardResponse->assertDontSee('Return to Draft', false);
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
}
