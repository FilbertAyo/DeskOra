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

class WorkPeriodBulkReopenAndResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow(Carbon::create(2026, 5, 11, 10, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_manager_can_reopen_closed_periods_and_reset_weekly_documents_to_draft(): void
    {
        $manager = $this->createActiveUser();
        $manager->givePermissionTo('manage work periods');

        $closedPeriodA = $this->createPeriod(2026, 18, WorkPeriod::STATUS_CLOSED);
        $closedPeriodB = $this->createPeriod(2026, 19, WorkPeriod::STATUS_CLOSED);
        $openPeriod = $this->createPeriod(2026, 20, WorkPeriod::STATUS_OPEN);
        $archivedPeriod = $this->createPeriod(2026, 21, WorkPeriod::STATUS_ARCHIVED);

        $owner = $this->createActiveUser();

        $weeklyPlan = Document::create([
            'user_id' => $owner->id,
            'period_id' => $closedPeriodA->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Submitted weekly plan',
            'state' => Document::STATE_SUBMITTED,
            'review_rejection_reason' => 'Needs rewrite',
            'budget_review_status' => Document::BUDGET_REVIEW_APPROVED,
            'budget_reviewed_by' => $manager->id,
            'budget_reviewed_at' => now(),
            'budget_provided_amount' => 12000,
            'budget_review_note' => 'Approved previously',
            'budget_review_items' => [
                [
                    'budget_uid' => 'line_1',
                    'decision' => Document::BUDGET_REVIEW_APPROVED,
                ],
            ],
            'content' => [],
        ]);

        $weeklyReport = Document::create([
            'user_id' => $owner->id,
            'period_id' => $closedPeriodB->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Submitted weekly report',
            'state' => Document::STATE_SUBMITTED,
            'review_rejection_reason' => 'Fix narrative',
            'content' => [],
        ]);

        $monthlyReport = Document::create([
            'user_id' => $owner->id,
            'type' => Document::TYPE_MONTHLY_REPORT,
            'title' => 'Monthly report',
            'month' => 5,
            'year' => 2026,
            'state' => Document::STATE_SUBMITTED,
            'content' => [],
        ]);

        $response = $this->actingAs($manager)
            ->post(route('work-periods.reopen-and-reset'));

        $response->assertRedirect(route('work-periods.index'));

        $this->assertDatabaseHas('work_periods', [
            'id' => $closedPeriodA->id,
            'status' => WorkPeriod::STATUS_OPEN,
        ]);
        $this->assertDatabaseHas('work_periods', [
            'id' => $closedPeriodB->id,
            'status' => WorkPeriod::STATUS_OPEN,
        ]);
        $this->assertDatabaseHas('work_periods', [
            'id' => $openPeriod->id,
            'status' => WorkPeriod::STATUS_OPEN,
        ]);
        $this->assertDatabaseHas('work_periods', [
            'id' => $archivedPeriod->id,
            'status' => WorkPeriod::STATUS_ARCHIVED,
        ]);

        $this->assertDatabaseHas('documents', [
            'id' => $weeklyPlan->id,
            'state' => Document::STATE_DRAFT,
            'review_rejection_reason' => null,
            'budget_review_status' => null,
            'budget_reviewed_by' => null,
            'budget_reviewed_at' => null,
            'budget_provided_amount' => null,
            'budget_review_note' => null,
            'budget_review_items' => null,
        ]);

        $this->assertDatabaseHas('documents', [
            'id' => $weeklyReport->id,
            'state' => Document::STATE_DRAFT,
            'review_rejection_reason' => null,
        ]);

        $this->assertDatabaseHas('documents', [
            'id' => $monthlyReport->id,
            'state' => Document::STATE_SUBMITTED,
        ]);
    }

    public function test_user_without_manage_work_periods_permission_cannot_run_bulk_reset(): void
    {
        $user = $this->createActiveUser();
        $closedPeriod = $this->createPeriod(2026, 22, WorkPeriod::STATUS_CLOSED);

        $response = $this->actingAs($user)->post(route('work-periods.reopen-and-reset'));

        $response->assertForbidden();
        $this->assertDatabaseHas('work_periods', [
            'id' => $closedPeriod->id,
            'status' => WorkPeriod::STATUS_CLOSED,
        ]);
    }

    private function createActiveUser(): User
    {
        $department = Department::first() ?? Department::create([
            'name' => 'Operations',
            'status' => 'active',
        ]);

        return User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
        ]);
    }

    private function createPeriod(int $year, int $weekNumber, string $status): WorkPeriod
    {
        $weekStart = Carbon::now()->setISODate($year, $weekNumber)->startOfWeek(Carbon::MONDAY);

        return WorkPeriod::create([
            'year' => $year,
            'week_number' => $weekNumber,
            'week_start_date' => $weekStart->toDateString(),
            'week_end_date' => $weekStart->copy()->addDays(6)->toDateString(),
            'plan_deadline' => $weekStart->copy()->subDays(2)->toDateString(),
            'report_deadline' => $weekStart->copy()->addDays(5)->toDateString(),
            'status' => $status,
        ]);
    }
}
