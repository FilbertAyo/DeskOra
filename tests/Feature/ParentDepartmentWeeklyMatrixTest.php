<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Models\WeeklyDepartmentMatrix;
use App\Models\WorkPeriod;
use App\Models\YearPlan;
use App\Models\YearPlanActivity;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ParentDepartmentWeeklyMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_store_rejects_sub_department_selection(): void
    {
        $fixture = $this->createParentDepartmentFixture();

        $response = $this->actingAs($fixture['manager'])->post(route('matrix-report.store'), [
            'department_id' => $fixture['subDepartmentA']->id,
            'period_id' => $fixture['period']->id,
        ]);

        $response->assertSessionHasErrors('department_id');
    }

    public function test_week_21_matrix_aggregates_submitted_plans_from_sub_departments(): void
    {
        $fixture = $this->createParentDepartmentFixture();

        $storeResponse = $this->actingAs($fixture['manager'])->post(route('matrix-report.store'), [
            'department_id' => $fixture['parentDepartment']->id,
            'period_id' => $fixture['period']->id,
        ]);

        $matrix = WeeklyDepartmentMatrix::query()
            ->where('department_id', $fixture['parentDepartment']->id)
            ->where('period_id', $fixture['period']->id)
            ->firstOrFail();

        $storeResponse->assertRedirect(route('matrix-report.edit', $matrix));

        $editResponse = $this->actingAs($fixture['manager'])->get(route('matrix-report.edit', $matrix));
        $editResponse->assertOk();
        $editResponse->assertSee('Finance');
        $editResponse->assertSee('Week 21');
        $editResponse->assertSee($fixture['staffA']->name.' (Accounting)');
        $editResponse->assertSee($fixture['staffB']->name.' (Payroll)');
        $editResponse->assertSee('Prepare payroll files');
        $editResponse->assertSee('Reconcile accounts');
        $editResponse->assertSee('1,500.00 TZS');

        $activityKey = $fixture['planA']->id.'::'.$fixture['activityUidA'];

        $updateResponse = $this->actingAs($fixture['manager'])->put(route('matrix-report.update', $matrix), [
            'overall_budget' => 1500,
            'notes' => 'Week 21 finance rollup',
            'entries' => [
                [
                    'activity_key' => $activityKey,
                    'input' => 'Ledger review',
                    'output' => 'Accounts reconciled',
                    'impact_year_plan_activity_id' => $fixture['impactA']->id,
                ],
            ],
        ]);

        $updateResponse->assertRedirect(route('matrix-report.index'));

        $matrix->refresh();
        $this->assertSame(1500.0, (float) $matrix->overall_budget);
        $this->assertCount(1, $matrix->entries);
        $this->assertSame('Ledger review', $matrix->entries[0]['input']);
        $this->assertSame('Accounts reconciled', $matrix->entries[0]['output']);
        $this->assertSame((string) $fixture['impactA']->id, $matrix->entries[0]['impact_year_plan_activity_id']);
    }

    public function test_index_lists_parent_matrices_with_submission_summary(): void
    {
        $fixture = $this->createParentDepartmentFixture();

        WeeklyDepartmentMatrix::query()->create([
            'period_id' => $fixture['period']->id,
            'department_id' => $fixture['parentDepartment']->id,
            'overall_budget' => null,
            'notes' => null,
            'entries' => [],
            'created_by' => $fixture['manager']->id,
            'updated_by' => $fixture['manager']->id,
        ]);

        $response = $this->actingAs($fixture['manager'])->get(route('matrix-report.index'));

        $response->assertOk();
        $response->assertSee('Parent Department Weekly Matrices');
        $response->assertSee('Finance');
        $response->assertSee('Week 21');
        $response->assertSee('2 / 2', false);
    }

    /**
     * @return array{
     *     parentDepartment: Department,
     *     subDepartmentA: Department,
     *     subDepartmentB: Department,
     *     period: WorkPeriod,
     *     manager: User,
     *     staffA: User,
     *     staffB: User,
     *     planA: Document,
     *     planB: Document,
     *     activityUidA: string,
     *     impactA: YearPlanActivity,
     * }
     */
    private function createParentDepartmentFixture(): array
    {
        $parentDepartment = Department::create([
            'name' => 'Finance',
            'status' => 'active',
            'plan_section_number' => 1,
        ]);

        $subDepartmentA = Department::create([
            'name' => 'Accounting',
            'status' => 'active',
            'parent_id' => $parentDepartment->id,
            'plan_section_number' => 2,
        ]);

        $subDepartmentB = Department::create([
            'name' => 'Payroll',
            'status' => 'active',
            'parent_id' => $parentDepartment->id,
            'plan_section_number' => 3,
        ]);

        $period = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 21,
            'week_start_date' => '2026-05-18',
            'week_end_date' => '2026-05-24',
            'plan_deadline' => '2026-05-22',
            'report_deadline' => '2026-05-23',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $manager = User::factory()->create([
            'department_id' => $parentDepartment->id,
            'status' => 'active',
        ]);

        $staffA = User::factory()->create([
            'department_id' => $subDepartmentA->id,
            'status' => 'active',
        ]);
        $staffA->givePermissionTo('create weekly plan');

        $staffB = User::factory()->create([
            'department_id' => $subDepartmentB->id,
            'status' => 'active',
        ]);
        $staffB->givePermissionTo('create weekly plan');

        $activityUidA = (string) Str::ulid();

        $planA = Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $period->id,
            'user_id' => $staffA->id,
            'title' => 'Plan A',
            'main_target' => 'Accounting target',
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'planned_activities' => [
                    [
                        'activity_uid' => $activityUidA,
                        'activity' => 'Reconcile accounts',
                    ],
                ],
                'budget' => [
                    [
                        'budget_uid' => (string) Str::ulid(),
                        'item' => 'Stationery',
                        'amount' => '1500',
                        'amount_numeric' => 1500,
                    ],
                ],
            ],
        ]);

        Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $period->id,
            'user_id' => $staffB->id,
            'title' => 'Plan B',
            'main_target' => 'Payroll target',
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'planned_activities' => [
                    [
                        'activity_uid' => (string) Str::ulid(),
                        'activity' => 'Prepare payroll files',
                    ],
                ],
            ],
        ]);

        $yearPlan = YearPlan::create(['year' => 2026]);

        $impactA = YearPlanActivity::create([
            'year_plan_id' => $yearPlan->id,
            'department_id' => $subDepartmentA->id,
            'sort_order' => 1,
            'description' => 'Improve financial controls',
            'frequency' => 1,
            'unit_count' => 1,
            'unit_value_tzs' => 1000,
            'planned_months' => [5],
        ]);

        return compact(
            'parentDepartment',
            'subDepartmentA',
            'subDepartmentB',
            'period',
            'manager',
            'staffA',
            'staffB',
            'planA',
            'activityUidA',
            'impactA',
        );
    }
}
