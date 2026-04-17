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

class WeeklyBudgetReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Carbon::setTestNow(Carbon::create(2026, 4, 15, 10, 0, 0)); // Wednesday, Week 16
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createPeriods(): array
    {
        $current = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 16,
            'week_start_date' => '2026-04-13',
            'week_end_date' => '2026-04-19',
            'plan_deadline' => '2026-04-17',
            'report_deadline' => '2026-04-18',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $next = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 17,
            'week_start_date' => '2026-04-20',
            'week_end_date' => '2026-04-26',
            'plan_deadline' => '2026-04-18',
            'report_deadline' => '2026-04-25',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        return [$current, $next];
    }

    private function createDepartment(string $name = 'Engineering'): Department
    {
        return Department::create(['name' => $name, 'status' => 'active']);
    }

    private function createStaffUser(Department $department): User
    {
        $user = User::factory()->create(['department_id' => $department->id, 'status' => 'active']);
        $user->givePermissionTo(['create weekly plan', 'create weekly report']);

        return $user;
    }

    private function createReviewer(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo(['view weekly budgets', 'process weekly budgets', 'view all documents', 'approve documents']);

        return $user;
    }

    private function createPlanWithBudget(User $user, WorkPeriod $period, array $budgetRows): Document
    {
        // Ensure budget_uid is set on each row
        foreach ($budgetRows as &$row) {
            if (empty($row['budget_uid'])) {
                $row['budget_uid'] = (string) \Illuminate\Support\Str::ulid();
            }
        }
        unset($row);

        $content = [
            'objective' => ['description' => 'Test objective'],
            'planned_activities' => [],
            'key_meetings' => [],
            'budget' => $budgetRows,
        ];

        // Build initial review items (pending)
        $reviewItems = [];
        foreach ($budgetRows as $budgetRow) {
            $reviewItems[] = [
                'budget_uid' => $budgetRow['budget_uid'],
                'decision' => Document::BUDGET_REVIEW_PENDING,
                'provided_amount' => 0,
                'note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ];
        }

        return Document::create([
            'user_id' => $user->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Plan for week '.$period->week_number,
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Test target',
            'content' => $content,
            'budget_review_status' => Document::BUDGET_REVIEW_PENDING,
            'budget_review_items' => $reviewItems,
        ]);
    }

    // ------------------------------------------------------------------
    // 1. Default next-week filtering
    // ------------------------------------------------------------------

    public function test_budget_review_defaults_to_next_week_period(): void
    {
        [$current, $next] = $this->createPeriods();
        $dept = $this->createDepartment();
        $staff = $this->createStaffUser($dept);
        $reviewer = $this->createReviewer();

        // Plan for next week (the default) with budget
        $this->createPlanWithBudget($staff, $next, [
            ['item' => 'Laptop', 'description_purpose' => 'Dev', 'amount' => '1000'],
        ]);

        // Plan for current week (should NOT appear by default)
        $this->createPlanWithBudget($staff, $current, [
            ['item' => 'Desk', 'description_purpose' => 'Office', 'amount' => '500'],
        ]);

        // Verify period resolution works correctly
        $svc = app(\App\Services\WorkPeriodService::class);
        $resolvedCurrent = $svc->getCurrentPeriod();
        $resolvedNext = $svc->periodForWeeklyPlanSubmission();
        $this->assertNotNull($resolvedCurrent, 'Current period should be found');
        $this->assertNotNull($resolvedNext, 'Next period should be found');
        $this->assertEquals($next->id, $resolvedNext->id, 'Next period should be week 17');

        // Default view (no filters) should show only next-week plans
        $response = $this->actingAs($reviewer)->get(route('documents.weekly-budget-review'));
        $response->assertOk();
        $response->assertSee('Laptop');
        // 'Desk' should not appear as a budget item (note: 'DeskOra' appears in branding)
        $response->assertDontSee('>Desk</');
    }

    public function test_budget_review_shows_all_weeks_when_period_filter_applied(): void
    {
        [$current, $next] = $this->createPeriods();
        $dept = $this->createDepartment();
        $staff = $this->createStaffUser($dept);
        $reviewer = $this->createReviewer();

        $this->createPlanWithBudget($staff, $current, [
            ['item' => 'Desk', 'description_purpose' => 'Office', 'amount' => '500'],
        ]);

        $response = $this->actingAs($reviewer)->get(route('documents.weekly-budget-review', [
            'period_id' => $current->id,
        ]));
        $response->assertStatus(200);
        $response->assertSee('Desk');
    }

    public function test_budget_review_excludes_draft_weekly_plans_even_with_budget(): void
    {
        [, $next] = $this->createPeriods();
        $dept = $this->createDepartment();
        $staff = $this->createStaffUser($dept);
        $reviewer = $this->createReviewer();

        // Submitted plan should appear
        $this->createPlanWithBudget($staff, $next, [
            ['item' => 'Submitted Item', 'description_purpose' => 'Visible', 'amount' => '100'],
        ]);

        // Draft plan with budget should NOT appear
        Document::create([
            'user_id' => $staff->id,
            'period_id' => $next->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Draft plan with budget',
            'state' => Document::STATE_DRAFT,
            'main_target' => 'Draft target',
            'content' => [
                'objective' => ['description' => 'Draft objective'],
                'planned_activities' => [],
                'key_meetings' => [],
                'budget' => [
                    [
                        'budget_uid' => (string) \Illuminate\Support\Str::ulid(),
                        'item' => 'Draft Item',
                        'description_purpose' => 'Should be hidden',
                        'amount' => '200',
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($reviewer)->get(route('documents.weekly-budget-review'));
        $response->assertOk();
        $response->assertSee('Submitted Item');
        $response->assertDontSee('Draft Item');
    }

    public function test_pending_budget_review_sidebar_count_excludes_plans_with_only_empty_budget_rows(): void
    {
        [, $next] = $this->createPeriods();
        $dept = $this->createDepartment();
        $staff = $this->createStaffUser($dept);

        Document::create([
            'user_id' => $staff->id,
            'period_id' => $next->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Plan with placeholder budget row',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Target',
            'content' => [
                'objective' => [],
                'planned_activities' => [],
                'key_meetings' => [],
                'budget' => [
                    ['item' => '', 'description_purpose' => '', 'amount' => ''],
                ],
            ],
            'budget_review_status' => null,
        ]);

        $this->assertSame(0, Document::countPendingWeeklyBudgetReviewForPeriod($next->id));

        $this->createPlanWithBudget($staff, $next, [
            ['item' => 'Real line', 'description_purpose' => 'Yes', 'amount' => '1000'],
        ]);

        $this->assertSame(1, Document::countPendingWeeklyBudgetReviewForPeriod($next->id));
    }

    // ------------------------------------------------------------------
    // 2. Grouped by department -> staff -> items
    // ------------------------------------------------------------------

    public function test_budget_review_groups_by_department_and_staff(): void
    {
        [, $next] = $this->createPeriods();
        $deptA = $this->createDepartment('Finance');
        $deptB = $this->createDepartment('IT');
        $staffA = $this->createStaffUser($deptA);
        $staffB = $this->createStaffUser($deptB);
        $reviewer = $this->createReviewer();

        $this->createPlanWithBudget($staffA, $next, [
            ['item' => 'Printer', 'description_purpose' => 'Print receipts', 'amount' => '300'],
            ['item' => 'Ink', 'description_purpose' => 'Printer ink', 'amount' => '50'],
        ]);

        $this->createPlanWithBudget($staffB, $next, [
            ['item' => 'Server', 'description_purpose' => 'New host', 'amount' => '5000'],
        ]);

        $response = $this->actingAs($reviewer)->get(route('documents.weekly-budget-review'));
        $response->assertStatus(200);
        $response->assertSee('Finance');
        $response->assertSee('IT');
        $response->assertSee('Printer');
        $response->assertSee('Ink');
        $response->assertSee('Server');
    }

    // ------------------------------------------------------------------
    // 3. Item-level approval/rejection
    // ------------------------------------------------------------------

    public function test_approve_single_budget_item(): void
    {
        [, $next] = $this->createPeriods();
        $dept = $this->createDepartment();
        $staff = $this->createStaffUser($dept);
        $reviewer = $this->createReviewer();

        $plan = $this->createPlanWithBudget($staff, $next, [
            ['item' => 'ItemA', 'description_purpose' => 'A', 'amount' => '100'],
            ['item' => 'ItemB', 'description_purpose' => 'B', 'amount' => '200'],
        ]);

        $items = $plan->budgetItems();
        $uidA = $items[0]['budget_uid'];

        $response = $this->actingAs($reviewer)->post(
            route('documents.process-weekly-budget-review', $plan->id),
            [
                'budget_uid' => $uidA,
                'decision' => 'approved',
                'provided_amount' => 90,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $plan->refresh();
        $reviewsByUid = $plan->budgetReviewItemsByUid();
        $this->assertSame('approved', $reviewsByUid[$uidA]['decision']);
        $this->assertEquals(90.0, $reviewsByUid[$uidA]['provided_amount']);

        // ItemB should still be pending
        $uidB = $items[1]['budget_uid'];
        $this->assertSame('pending', $reviewsByUid[$uidB]['decision']);

        // Plan-level status should still be pending (not all reviewed)
        $this->assertSame(Document::BUDGET_REVIEW_PENDING, $plan->budget_review_status);
    }

    public function test_reject_single_budget_item(): void
    {
        [, $next] = $this->createPeriods();
        $dept = $this->createDepartment();
        $staff = $this->createStaffUser($dept);
        $reviewer = $this->createReviewer();

        $plan = $this->createPlanWithBudget($staff, $next, [
            ['item' => 'ItemA', 'description_purpose' => 'A', 'amount' => '100'],
        ]);

        $uid = $plan->budgetItems()[0]['budget_uid'];

        $response = $this->actingAs($reviewer)->post(
            route('documents.process-weekly-budget-review', $plan->id),
            [
                'budget_uid' => $uid,
                'decision' => 'rejected',
                'note' => 'Not in scope',
            ]
        );

        $response->assertRedirect();
        $plan->refresh();
        $reviewsByUid = $plan->budgetReviewItemsByUid();
        $this->assertSame('rejected', $reviewsByUid[$uid]['decision']);
        $this->assertSame('Not in scope', $reviewsByUid[$uid]['note']);
        $this->assertSame(Document::BUDGET_REVIEW_REJECTED, $plan->budget_review_status);
    }

    public function test_all_items_approved_sets_plan_status_approved(): void
    {
        [, $next] = $this->createPeriods();
        $dept = $this->createDepartment();
        $staff = $this->createStaffUser($dept);
        $reviewer = $this->createReviewer();

        $plan = $this->createPlanWithBudget($staff, $next, [
            ['item' => 'ItemA', 'description_purpose' => 'A', 'amount' => '100'],
            ['item' => 'ItemB', 'description_purpose' => 'B', 'amount' => '200'],
        ]);

        $items = $plan->budgetItems();

        // Approve both items
        foreach ($items as $item) {
            $this->actingAs($reviewer)->post(
                route('documents.process-weekly-budget-review', $plan->id),
                [
                    'budget_uid' => $item['budget_uid'],
                    'decision' => 'approved',
                    'provided_amount' => $item['amount_numeric'],
                ]
            );
        }

        $plan->refresh();
        $this->assertSame(Document::BUDGET_REVIEW_APPROVED, $plan->budget_review_status);
        $this->assertEquals(300.0, (float) $plan->budget_provided_amount);
    }

    public function test_mixed_decisions_result_in_approved_when_all_reviewed(): void
    {
        [, $next] = $this->createPeriods();
        $dept = $this->createDepartment();
        $staff = $this->createStaffUser($dept);
        $reviewer = $this->createReviewer();

        $plan = $this->createPlanWithBudget($staff, $next, [
            ['item' => 'ItemA', 'description_purpose' => 'A', 'amount' => '100'],
            ['item' => 'ItemB', 'description_purpose' => 'B', 'amount' => '200'],
        ]);

        $items = $plan->budgetItems();

        // Approve first, reject second
        $this->actingAs($reviewer)->post(
            route('documents.process-weekly-budget-review', $plan->id),
            ['budget_uid' => $items[0]['budget_uid'], 'decision' => 'approved', 'provided_amount' => 100]
        );
        $this->actingAs($reviewer)->post(
            route('documents.process-weekly-budget-review', $plan->id),
            ['budget_uid' => $items[1]['budget_uid'], 'decision' => 'rejected', 'note' => 'Too expensive']
        );

        $plan->refresh();
        // All items reviewed but mixed => approved at plan level (partial is ok)
        $this->assertSame(Document::BUDGET_REVIEW_APPROVED, $plan->budget_review_status);
        $this->assertEquals(100.0, (float) $plan->budget_provided_amount);
    }

    public function test_invalid_budget_uid_returns_error(): void
    {
        [, $next] = $this->createPeriods();
        $dept = $this->createDepartment();
        $staff = $this->createStaffUser($dept);
        $reviewer = $this->createReviewer();

        $plan = $this->createPlanWithBudget($staff, $next, [
            ['item' => 'ItemA', 'description_purpose' => 'A', 'amount' => '100'],
        ]);

        $response = $this->actingAs($reviewer)->post(
            route('documents.process-weekly-budget-review', $plan->id),
            ['budget_uid' => 'nonexistent_uid', 'decision' => 'approved', 'provided_amount' => 100]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Budget item not found in this plan.');
    }

    // ------------------------------------------------------------------
    // 4. Report prefill from budget review
    // ------------------------------------------------------------------

    public function test_plan_target_api_includes_review_metadata(): void
    {
        [, $next] = $this->createPeriods();
        $dept = $this->createDepartment();
        $staff = $this->createStaffUser($dept);
        $reviewer = $this->createReviewer();

        $plan = $this->createPlanWithBudget($staff, $next, [
            ['item' => 'Laptop', 'description_purpose' => 'Dev work', 'amount' => '1500'],
        ]);

        $uid = $plan->budgetItems()[0]['budget_uid'];

        // Approve the item
        $this->actingAs($reviewer)->post(
            route('documents.process-weekly-budget-review', $plan->id),
            ['budget_uid' => $uid, 'decision' => 'approved', 'provided_amount' => 1400]
        );

        // Fetch plan target as staff
        $response = $this->actingAs($staff)->getJson(route('documents.plan-target', [
            'period_id' => $next->id,
        ]));

        $response->assertOk();
        $data = $response->json();
        $this->assertTrue($data['plan_exists']);
        $this->assertNotEmpty($data['budget_items']);

        $firstItem = $data['budget_items'][0];
        $this->assertSame('Laptop', $firstItem['item']);
        $this->assertSame($uid, $firstItem['budget_uid']);
        $this->assertSame('approved', $firstItem['review_decision']);
        $this->assertEquals(1400, $firstItem['review_provided_amount']);
    }

    // ------------------------------------------------------------------
    // 5. Budget UID assignment on plan creation
    // ------------------------------------------------------------------

    public function test_budget_uid_assigned_on_plan_creation(): void
    {
        [, $next] = $this->createPeriods();
        $staff = $this->createStaffUser($this->createDepartment());

        $response = $this->actingAs($staff)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $next->id,
            'main_target' => 'Plan with budget',
            'content' => [
                'budget' => [
                    ['item' => 'Chair', 'description_purpose' => 'Ergonomic', 'amount' => '250'],
                ],
            ],
        ]);

        $response->assertRedirect();

        $plan = Document::where('user_id', $staff->id)
            ->where('period_id', $next->id)
            ->where('type', Document::TYPE_WEEKLY_PLAN)
            ->first();

        $this->assertNotNull($plan);
        $budget = $plan->content['budget'] ?? [];
        $this->assertCount(1, $budget);
        $this->assertNotEmpty($budget[0]['budget_uid']);
    }

    // ------------------------------------------------------------------
    // 6. Model helpers
    // ------------------------------------------------------------------

    public function test_budget_review_summary_counts_correctly(): void
    {
        [, $next] = $this->createPeriods();
        $dept = $this->createDepartment();
        $staff = $this->createStaffUser($dept);

        $plan = $this->createPlanWithBudget($staff, $next, [
            ['item' => 'A', 'description_purpose' => '', 'amount' => '100'],
            ['item' => 'B', 'description_purpose' => '', 'amount' => '200'],
            ['item' => 'C', 'description_purpose' => '', 'amount' => '300'],
        ]);

        $items = $plan->budgetItems();

        // Mark one approved, one rejected, leave one pending
        $reviews = $plan->budget_review_items;
        $reviews[0]['decision'] = 'approved';
        $reviews[0]['provided_amount'] = 80;
        $reviews[1]['decision'] = 'rejected';
        $plan->update(['budget_review_items' => $reviews]);
        $plan->refresh();

        $summary = $plan->budgetReviewSummary();
        $this->assertSame(3, $summary['items_total']);
        $this->assertSame(1, $summary['pending']);
        $this->assertSame(1, $summary['approved']);
        $this->assertSame(1, $summary['rejected']);
        $this->assertEquals(600.0, $summary['requested_total']);
        $this->assertEquals(80.0, $summary['provided_total']);
        $this->assertTrue($summary['is_partial']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
