<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\Request as FinanceRequest;
use App\Models\RequestApproval;
use App\Models\RequisitionItem;
use App\Models\RequisitionRequest;
use App\Models\User;
use App\Models\WeeklyPlan;
use App\Models\WeeklyPlanBudgetItem;
use App\Models\WeeklyPlanBudgetReview;
use App\Models\WorkPeriod;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RequisitionWeeklyPlanLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Carbon::setTestNow(Carbon::create(2026, 4, 15, 10, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_create_requisition_offers_the_staff_members_plans_and_current_week_lines(): void
    {
        $period = $this->openPeriod();
        $department = $this->department();
        $staff = $this->staff($department);
        $other = $this->staff($department);
        $this->planFor($staff, $period, [[
            'budget_uid' => 'own1',
            'item' => 'Printing',
            'description_purpose' => 'Books for the clinic',
            'unit' => 'reams',
            'amount' => '25000',
        ]], Document::STATE_DRAFT);
        $this->planFor($other, $period, [[
            'item' => 'Secret',
            'description_purpose' => 'Not yours',
            'unit' => 'box',
            'amount' => '10',
        ]], Document::STATE_DRAFT);

        $this->actingAs($staff)
            ->get(route('requisition.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Finance/Requisition/Form')
                ->has('weeklyPlanBudgets', 1)
                ->where('weeklyPlanBudgets.0.isCurrentWeek', true)
                ->where('weeklyPlanBudgets.0.items.0.description', 'Printing : Books for the clinic')
                ->where('weeklyPlanBudgets.0.items.0.unit', 'reams')
                ->where('weeklyPlanBudgets.0.items.0.cost', 25000));
    }

    public function test_staff_cannot_link_another_users_plan_and_can_save_edited_lines(): void
    {
        $period = $this->openPeriod();
        $department = $this->department();
        $staff = $this->staff($department);
        $other = $this->staff($department);
        $ownPlan = $this->planFor($staff, $period, [[
            'budget_uid' => 'own1',
            'item' => 'Printing',
            'description_purpose' => 'Books',
            'unit' => 'reams',
            'amount' => '25000',
        ]], Document::STATE_DRAFT);
        $otherPlan = $this->planFor($other, $period, [[
            'item' => 'Secret',
            'description_purpose' => 'Not yours',
            'amount' => '10',
        ]], Document::STATE_DRAFT);

        $this->actingAs($staff)
            ->post(route('requisition.store'), $this->draftPayload($department, $staff, $otherPlan->id))
            ->assertSessionHasErrors('weekly_plan_id');
        $this->assertSame(0, FinanceRequest::query()->count());

        $this->actingAs($staff)
            ->post(route('requisition.store'), array_merge(
                $this->draftPayload($department, $staff, $ownPlan->id),
                [
                    'items' => [
                        ['description' => 'Printing — Books', 'unit' => 'packs', 'cost' => 20000],
                        ['description' => 'Extra fuel', 'unit' => 'litres', 'cost' => 5000],
                    ],
                ]
            ))
            ->assertRedirect();

        $details = RequisitionRequest::query()->first();
        $this->assertNotNull($details);
        $this->assertSame((string) $ownPlan->id, (string) $details->weekly_plan_id);
        $items = RequisitionItem::query()->where('request_id', $details->request_id)->orderBy('serial_no')->get();
        $this->assertCount(2, $items);
        $this->assertSame('packs', $items[0]->unit);
        $this->assertEquals(20000, (float) $items[0]->cost);
        $this->assertSame('Extra fuel', $items[1]->description);
        $this->assertSame(Document::BUDGET_REVIEW_PENDING, WeeklyPlan::query()->find($ownPlan->id)?->budget_review_status);
    }

    public function test_authorizing_a_linked_requisition_completes_the_plan_budget_and_rejection_does_not(): void
    {
        Notification::fake();
        $period = $this->openPeriod();
        $department = $this->department();
        $staff = $this->staff($department);
        $plan = $this->planFor($staff, $period, [
            [
                'budget_uid' => 'line-a',
                'item' => 'Printing',
                'description_purpose' => 'Books',
                'unit' => 'reams',
                'amount' => '25000',
            ],
            [
                'budget_uid' => 'line-b',
                'item' => 'Transport',
                'description_purpose' => 'Field',
                'unit' => 'days',
                'amount' => '10000',
            ],
        ], Document::STATE_DRAFT);

        $rejected = $this->requisition($department, $staff, $plan->id, 'pending');
        $rejector = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'signature' => 'signatures/test.png',
        ]);
        $rejector->givePermissionTo(['view requested pettycash', 'review staff requisition']);

        $this->actingAs($rejector)
            ->post(route('petty.reject', $rejected), [
                'action' => 'rejected',
                'comment' => 'Not this week',
            ])
            ->assertRedirect();

        $this->assertSame(Document::BUDGET_REVIEW_PENDING, WeeklyPlan::query()->find($plan->id)?->budget_review_status);
        $this->assertSame(
            Document::BUDGET_REVIEW_STAGE_PENDING,
            WeeklyPlanBudgetItem::query()->where('budget_uid', 'line-a')->first()?->review?->approval_stage
        );

        $authorized = $this->requisition($department, $staff, $plan->id, 'processing');
        RequestApproval::query()->create([
            'request_id' => $authorized->id,
            'step' => 'review',
            'action' => 'approved',
            'actor_id' => $rejector->id,
            'acted_at' => now(),
        ]);
        RequestApproval::query()->create([
            'request_id' => $authorized->id,
            'step' => 'approve',
            'action' => 'approved',
            'actor_id' => $rejector->id,
            'acted_at' => now(),
        ]);
        $authorizer = User::factory()->create([
            'status' => 'active',
            'signature' => 'signatures/test.png',
        ]);
        $authorizer->givePermissionTo('authorize staff requisition');

        $this->actingAs($authorizer)
            ->post(route('requests.authorize', $authorized))
            ->assertRedirect();

        $weeklyPlan = WeeklyPlan::query()->findOrFail($plan->id);
        $this->assertSame(Document::BUDGET_REVIEW_COMPLETED, $weeklyPlan->budget_review_status);
        $this->assertSame(Document::STATE_DRAFT, $weeklyPlan->state);
        $stages = WeeklyPlanBudgetReview::query()
            ->whereIn('weekly_plan_budget_item_id', $weeklyPlan->budgetItems()->pluck('id'))
            ->pluck('approval_stage')
            ->unique()
            ->values()
            ->all();
        $this->assertSame([Document::BUDGET_REVIEW_STAGE_COMPLETED], $stages);

        $this->actingAs($staff)
            ->put(route('documents.update', $plan->id), [
                'action' => 'draft',
                'main_target' => 'Still the same week',
                'content' => [
                    'budget' => [
                        [
                            'budget_uid' => 'line-a',
                            'item' => 'Printing',
                            'description_purpose' => 'Books',
                            'unit' => 'boxes',
                            'amount' => '25000',
                        ],
                        [
                            'budget_uid' => 'line-b',
                            'item' => 'Transport',
                            'description_purpose' => 'Field',
                            'unit' => 'days',
                            'amount' => '10000',
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $weeklyPlan->refresh();
        $this->assertSame(Document::BUDGET_REVIEW_COMPLETED, $weeklyPlan->budget_review_status);
        $this->assertSame(Document::STATE_DRAFT, $weeklyPlan->state);
        $saved = WeeklyPlanBudgetItem::query()->where('weekly_plan_id', $weeklyPlan->id)->get()->keyBy('budget_uid');
        $this->assertSame('boxes', $saved['line-a']->unit);
        $this->assertSame(
            Document::BUDGET_REVIEW_STAGE_COMPLETED,
            $saved['line-a']->review?->approval_stage
        );
        $this->assertSame(
            Document::BUDGET_REVIEW_STAGE_COMPLETED,
            $saved['line-b']->review?->approval_stage
        );

        $this->actingAs($staff)
            ->get(route('documents.show', $plan->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('document.budgetReviewStatus', 'Completed'));
    }

    private function department(): Department
    {
        return Department::create(['name' => 'Programs', 'status' => 'active']);
    }

    private function staff(Department $department): User
    {
        $user = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'signature' => 'signatures/test.png',
        ]);
        $user->givePermissionTo(['request pettycash', 'create weekly plan']);

        return $user;
    }

    private function openPeriod(): WorkPeriod
    {
        return WorkPeriod::create([
            'year' => 2026,
            'week_number' => 16,
            'week_start_date' => '2026-04-13',
            'week_end_date' => '2026-04-19',
            'plan_deadline' => '2026-04-17',
            'report_deadline' => '2026-04-18',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);
    }

    /**
     * @param  list<array<string, string>>  $budget
     */
    private function planFor(User $user, WorkPeriod $period, array $budget, string $state): Document
    {
        return Document::create([
            'user_id' => $user->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Week plan',
            'state' => $state,
            'main_target' => 'Target',
            'content' => [
                'budget' => $budget,
            ],
            'budget_review_status' => Document::BUDGET_REVIEW_PENDING,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function draftPayload(Department $department, User $staff, string $planId): array
    {
        return [
            'user_id' => $staff->id,
            'department_id' => $department->id,
            'request_type' => 'Staff Requisition',
            'action' => 'draft',
            'weekly_plan_id' => $planId,
            'project_program' => 'Clinic',
            'items' => [
                ['description' => 'Printing', 'unit' => 'reams', 'cost' => 25000],
            ],
        ];
    }

    private function requisition(Department $department, User $staff, string $planId, string $status): FinanceRequest
    {
        $request = FinanceRequest::create([
            'request_no' => 'RQ'.random_int(1000, 9999),
            'request_type' => 'Staff Requisition',
            'user_id' => $staff->id,
            'department_id' => $department->id,
            'status' => $status,
            'submitted_at' => now(),
        ]);
        RequisitionRequest::create([
            'request_id' => $request->id,
            'weekly_plan_id' => $planId,
            'project_program' => 'Clinic',
            'activity_code' => '9.1',
            'activity_title' => 'Clinic visit',
            'budget_code' => '2026',
            'is_off_plan' => true,
            'amount' => 25000,
        ]);
        RequisitionItem::create([
            'request_id' => $request->id,
            'serial_no' => 1,
            'description' => 'Printing — Books',
            'unit' => 'reams',
            'cost' => 25000,
        ]);

        return $request;
    }
}
