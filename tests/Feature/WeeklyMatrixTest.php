<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Models\WeeklyMatrix;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanActivity;
use App\Models\WorkPeriod;
use App\Models\YearPlan;
use App\Models\YearPlanActivity;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WeeklyMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_calendar_index_loads_periods(): void
    {
        [$period] = $this->createPeriodAndSubDepartment();
        $user = $this->createActiveUser();

        $response = $this->actingAs($user)->get(route('weekly-matrix.index', ['year' => $period->year]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('WeeklyMatrix/Index')
            ->where('year', $period->year)
            ->where('periodCards.0.period.weekNumber', $period->week_number)
            ->where('periodCards.0.period.dateRange', $period->date_range));
    }

    public function test_create_update_and_delete_matrix_for_sub_department(): void
    {
        [$period, $subDepartment, $yearPlanActivity] = $this->createPeriodAndSubDepartment(withYearPlan: true);
        $user = $this->createActiveUser();

        $payload = [
            'entries' => [
                [
                    'key_activity' => "Conduct site visit\n-81 STEM club follow up",
                    'output' => '-81 STEM clubs registered',
                    'outcome' => '',
                    'impact' => '',
                    'indicators' => '',
                    'baseline' => '',
                    'target' => '',
                    'means_of_verification' => '',
                    'year_plan_activity_ids' => [(string) $yearPlanActivity->id],
                ],
            ],
        ];

        $create = $this->actingAs($user)->post(
            route('weekly-matrix.create.store', $period),
            [
                'department_id' => $subDepartment->id,
                'entry' => $payload['entries'][0],
            ]
        );

        $create->assertRedirect(route('weekly-matrix.period', $period));
        $create->assertSessionHas('success');

        $this->assertDatabaseHas('weekly_matrices', [
            'period_id' => $period->id,
            'department_id' => $subDepartment->id,
        ]);

        $matrix = WeeklyMatrix::query()
            ->where('period_id', $period->id)
            ->where('department_id', $subDepartment->id)
            ->firstOrFail();

        $this->assertDatabaseHas('weekly_matrix_entries', [
            'weekly_matrix_id' => $matrix->id,
            'key_activity' => "Conduct site visit\n-81 STEM club follow up",
        ]);

        $this->assertDatabaseHas('weekly_matrix_entry_year_plan_activity', [
            'weekly_matrix_entry_id' => $matrix->entries()->first()->id,
            'year_plan_activity_id' => $yearPlanActivity->id,
        ]);

        $updatePayload = [
            'entries' => [
                [
                    'key_activity' => 'Updated activity',
                    'output' => 'Updated output',
                    'outcome' => '',
                    'impact' => '',
                    'indicators' => '',
                    'baseline' => '',
                    'target' => '',
                    'means_of_verification' => '',
                    'year_plan_activity_ids' => [],
                ],
            ],
        ];

        $update = $this->actingAs($user)->put(
            route('weekly-matrix.update', [$period, $subDepartment]),
            $updatePayload
        );

        $update->assertRedirect(route('weekly-matrix.period', $period));
        $this->assertDatabaseHas('weekly_matrix_entries', [
            'weekly_matrix_id' => $matrix->id,
            'key_activity' => 'Updated activity',
            'output' => 'Updated output',
        ]);

        $delete = $this->actingAs($user)->delete(
            route('weekly-matrix.destroy', [$period, $subDepartment])
        );

        $delete->assertRedirect(route('weekly-matrix.period', $period));
        $this->assertSoftDeleted('weekly_matrices', ['id' => $matrix->id]);
    }

    public function test_text_formatting_is_preserved_in_view_mode(): void
    {
        [$period, $subDepartment] = $this->createPeriodAndSubDepartment();
        $user = $this->createActiveUser();

        $formattedText = "- Item one\n- Item two\n• Bullet three";

        $this->actingAs($user)->post(route('weekly-matrix.create.store', $period), [
            'department_id' => $subDepartment->id,
            'entry' => [
                'key_activity' => $formattedText,
                'output' => '',
                'outcome' => '',
                'impact' => '',
                'indicators' => '',
                'baseline' => '',
                'target' => '',
                'means_of_verification' => '',
                'year_plan_activity_ids' => [],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('weekly_matrix_entries', [
            'key_activity' => $formattedText,
        ]);

        $view = $this->actingAs($user)->get(
            route('weekly-matrix.edit', ['period' => $period, 'department' => $subDepartment, 'view' => 1])
        );

        $view->assertOk();
        $view->assertInertia(fn (Assert $page) => $page
            ->component('WeeklyMatrix/Form')
            ->where('viewMode', true)
            ->where('entryDefaults.0.key_activity', $formattedText));
    }

    public function test_suggestions_endpoint_returns_weekly_plan_activities(): void
    {
        [$period, $subDepartment] = $this->createPeriodAndSubDepartment();
        $staff = User::factory()->create(['status' => 'active', 'department_id' => $subDepartment->id]);
        $viewer = $this->createActiveUser();

        $plan = WeeklyPlan::create([
            'user_id' => $staff->id,
            'period_id' => $period->id,
            'title' => 'Plan',
            'state' => WeeklyPlan::STATE_SUBMITTED,
            'main_target' => 'Target',
        ]);

        WeeklyPlanActivity::create([
            'weekly_plan_id' => $plan->id,
            'activity_uid' => 'act-1',
            'day' => 'Monday',
            'activity' => 'STEM club follow up',
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($viewer)->getJson(
            route('weekly-matrix.suggestions', [$period, $subDepartment])
        );

        $response->assertOk();
        $response->assertJsonPath('suggestions.0.activity', 'STEM club follow up');
        $response->assertJsonPath('suggestions.0.staff_name', $staff->name);
    }

    public function test_cannot_create_duplicate_matrix_for_same_period_and_sub_department(): void
    {
        [$period, $subDepartment] = $this->createPeriodAndSubDepartment();
        $user = $this->createActiveUser();

        $payload = [
            'entries' => [
                [
                    'key_activity' => 'First row',
                    'output' => '',
                    'outcome' => '',
                    'impact' => '',
                    'indicators' => '',
                    'baseline' => '',
                    'target' => '',
                    'means_of_verification' => '',
                    'year_plan_activity_ids' => [],
                ],
            ],
        ];

        $this->actingAs($user)->post(route('weekly-matrix.create.store', $period), [
            'department_id' => $subDepartment->id,
            'entry' => $payload['entries'][0],
        ]);

        $duplicate = $this->actingAs($user)->post(
            route('weekly-matrix.create.store', $period),
            [
                'department_id' => $subDepartment->id,
                'entry' => $payload['entries'][0],
            ]
        );

        $duplicate->assertSessionHasErrors('department_id');
    }

    public function test_period_page_shows_empty_table_before_any_matrix(): void
    {
        [$period] = $this->createPeriodAndSubDepartment();
        $user = $this->createActiveUser();

        $response = $this->actingAs($user)->get(route('weekly-matrix.period', $period));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('WeeklyMatrix/Period')
            ->has('matrices', 0)
            ->where('canCreate', true)
            ->has('createUrl'));
    }

    public function test_period_table_endpoint_returns_matrix_html(): void
    {
        [$period, $subDepartment, $yearPlanActivity] = $this->createPeriodAndSubDepartment(withYearPlan: true);
        $user = $this->createActiveUser();

        $this->actingAs($user)->post(route('weekly-matrix.create.store', $period), [
            'department_id' => $subDepartment->id,
            'entry' => [
                'key_activity' => 'Consolidated key activity',
                'output' => '',
                'outcome' => '',
                'impact' => '',
                'indicators' => '',
                'baseline' => '',
                'target' => '',
                'means_of_verification' => '',
                'year_plan_activity_ids' => [(string) $yearPlanActivity->id],
            ],
        ])->assertRedirect();

        $response = $this->actingAs($user)->getJson(route('weekly-matrix.period.table', $period));

        $response->assertOk();
        $response->assertJsonStructure(['html', 'week_number', 'year', 'date_range']);
        $this->assertStringContainsString('Consolidated key activity', $response->json('html'));
        $this->assertStringContainsString($yearPlanActivity->displayCode(), $response->json('html'));
    }

    public function test_matrix_pdf_download_returns_pdf(): void
    {
        [$period, $subDepartment] = $this->createPeriodAndSubDepartment();
        $user = $this->createActiveUser();

        $this->actingAs($user)->post(route('weekly-matrix.create.store', $period), [
            'department_id' => $subDepartment->id,
            'entry' => [
                'key_activity' => 'PDF key activity',
                'output' => '',
                'outcome' => '',
                'impact' => '',
                'indicators' => '',
                'baseline' => '',
                'target' => '',
                'means_of_verification' => '',
                'year_plan_activity_ids' => [],
            ],
        ])->assertRedirect();

        $response = $this->actingAs($user)->get(route('weekly-matrix.download', $period));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_parent_department_is_rejected_for_matrix_edit(): void
    {
        [$period, $subDepartment] = $this->createPeriodAndSubDepartment();
        $parent = Department::findOrFail($subDepartment->parent_id);
        $user = $this->createActiveUser();

        $response = $this->actingAs($user)->get(route('weekly-matrix.edit', [$period, $parent]));

        $response->assertNotFound();
    }

    /**
     * @return array{0: WorkPeriod, 1: Department, 2?: YearPlanActivity}
     */
    private function createPeriodAndSubDepartment(bool $withYearPlan = false): array
    {
        $parent = Department::create(['name' => 'Programs']);
        $subDepartment = Department::create([
            'name' => 'STEM Outreach',
            'parent_id' => $parent->id,
            'plan_section_number' => 4,
        ]);

        $period = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 27,
            'week_start_date' => '2026-06-29',
            'week_end_date' => '2026-07-05',
            'plan_deadline' => '2026-06-27',
            'report_deadline' => '2026-07-04',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        if (! $withYearPlan) {
            return [$period, $subDepartment];
        }

        $yearPlan = YearPlan::create([
            'year' => 2026,
            'created_by' => $this->createActiveUser()->id,
        ]);

        $activity = YearPlanActivity::create([
            'year_plan_id' => $yearPlan->id,
            'department_id' => $subDepartment->id,
            'sort_order' => 1,
            'description' => 'STEM club competitions',
            'frequency' => 1,
            'unit_count' => 1,
            'unit_value_tzs' => 1000,
            'planned_months' => [6, 7],
        ]);

        return [$period, $subDepartment, $activity];
    }

    private function createActiveUser(): User
    {
        return User::factory()->create(['status' => 'active']);
    }
}
