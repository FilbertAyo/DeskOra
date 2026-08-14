<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\Request as FinanceRequest;
use App\Models\RequestApproval;
use App\Models\RequisitionRequest;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\Documents\DocumentDomainSyncService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PendingApprovalGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Cache::flush();
        Carbon::setTestNow(Carbon::create(2026, 4, 15, 10, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createDepartment(string $name = 'Operations'): Department
    {
        return Department::create(['name' => $name, 'status' => 'active']);
    }

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

    private function userWithPermissions(array $permissions, ?Department $department = null): User
    {
        $user = User::factory()->create([
            'department_id' => $department?->id,
            'status' => 'active',
            'signature' => 'signatures/test.png',
        ]);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function createStaffRequisition(Department $department, User $requester): FinanceRequest
    {
        $financeRequest = FinanceRequest::create([
            'request_no' => 'SR'.random_int(100, 999),
            'request_type' => 'Staff Requisition',
            'user_id' => $requester->id,
            'department_id' => $department->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        RequisitionRequest::create([
            'request_id' => $financeRequest->id,
            'project_program' => 'Project Alpha',
            'activity_code' => 'ACT-01',
            'activity_title' => 'Training Materials',
            'budget_code' => 'BUD-100',
            'amount' => 90000,
            'reason' => 'Materials',
        ]);

        RequestApproval::create([
            'request_id' => $financeRequest->id,
            'step' => 'system',
            'action' => 'created',
            'actor_id' => $requester->id,
            'acted_at' => now(),
        ]);

        return $financeRequest->fresh(['requisitionDetails', 'approvals']);
    }

    private function createPlanWithBudget(User $user, WorkPeriod $period, string $itemName): Document
    {
        $document = Document::create([
            'user_id' => $user->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Plan for week '.$period->week_number,
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Test target',
            'content' => [
                'objective' => ['description' => 'Test objective'],
                'planned_activities' => [],
                'key_meetings' => [],
                'budget' => [[
                    'budget_uid' => (string) \Illuminate\Support\Str::ulid(),
                    'item' => $itemName,
                    'description_purpose' => 'Catch-up',
                    'amount' => '100',
                ]],
            ],
            'budget_review_status' => Document::BUDGET_REVIEW_PENDING,
        ]);

        app(DocumentDomainSyncService::class)->syncFromDocument($document);

        return $document->fresh() ?? $document;
    }

    public function test_pending_work_shows_session_popup_without_blocking_other_pages(): void
    {
        $department = $this->createDepartment();
        $requester = User::factory()->create(['department_id' => $department->id, 'status' => 'active']);
        $reviewer = $this->userWithPermissions(['review staff requisition', 'view requested pettycash'], $department);
        $this->createStaffRequisition($department, $requester);

        $this->actingAs($reviewer)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('id="pendingApprovalGateModal"', false)
            ->assertSee('Remind me later')
            ->assertSee('Open requisitions')
            ->assertDontSee('Profile / signature');

        $snapshot = $this->actingAs($reviewer)->getJson(route('approval-gate.snapshot'));
        $snapshot->assertOk();
        $snapshot->assertJsonPath('show_modal', true);
        $snapshot->assertJsonPath('primary_queue', 'requisition');
        $snapshot->assertJsonPath('requisition_count', 1);
        $this->assertSame('Training Materials', $snapshot->json('requisitions.0.summary'));
    }

    public function test_requisition_reviewer_can_open_the_approval_detail_page_without_the_popup(): void
    {
        $department = $this->createDepartment();
        $requester = User::factory()->create(['department_id' => $department->id, 'status' => 'active']);
        $reviewer = $this->userWithPermissions(['review staff requisition', 'view requested pettycash'], $department);
        $requisition = $this->createStaffRequisition($department, $requester);

        $this->actingAs($reviewer)
            ->get(route('petty.details', ['petty' => $requisition->id, 'stage' => 'review', 'type' => 'requisition']))
            ->assertOk()
            ->assertDontSee('id="pendingApprovalGateModal"', false);
    }

    public function test_remind_me_later_hides_the_popup_for_the_rest_of_the_session(): void
    {
        $department = $this->createDepartment();
        $requester = User::factory()->create(['department_id' => $department->id, 'status' => 'active']);
        $reviewer = $this->userWithPermissions([
            'review staff requisition',
            'view requested pettycash',
            'review weekly budgets',
            'view weekly budgets',
        ], $department);
        $this->createStaffRequisition($department, $requester);

        [$current] = $this->createPeriods();
        $staff = User::factory()->create(['department_id' => $department->id, 'status' => 'active']);
        $this->createPlanWithBudget($staff, $current, 'Old paper');

        $this->actingAs($reviewer)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Open requisitions')
            ->assertSee('Open weekly budgets');

        $this->actingAs($reviewer)
            ->postJson(route('approval-gate.remind-later'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertSessionHas(config('approval_gate.session_snooze_key'));

        $this->actingAs($reviewer)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('id="pendingApprovalGateModal"', false);

        $this->actingAs($reviewer)
            ->getJson(route('approval-gate.snapshot'))
            ->assertOk()
            ->assertJsonPath('show_modal', false)
            ->assertJsonPath('requisition_count', 1)
            ->assertJsonPath('budget_remaining', 1);
    }

    public function test_weekly_budget_popup_does_not_block_other_work(): void
    {
        [$current] = $this->createPeriods();
        $department = $this->createDepartment();
        $staff = User::factory()->create(['department_id' => $department->id, 'status' => 'active']);
        $reviewer = $this->userWithPermissions(['review weekly budgets', 'view weekly budgets']);
        $this->createPlanWithBudget($staff, $current, 'Single leftover');

        $this->actingAs($reviewer)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('id="pendingApprovalGateModal"', false)
            ->assertSee('Open weekly budgets')
            ->assertSee('Remind me later');
    }

    public function test_unrelated_staff_are_not_prompted(): void
    {
        $department = $this->createDepartment();
        $requester = User::factory()->create(['department_id' => $department->id, 'status' => 'active']);
        $this->createStaffRequisition($department, $requester);
        $staff = User::factory()->create(['status' => 'active']);
        $staff->givePermissionTo(['create weekly plan']);

        $this->actingAs($staff)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('id="pendingApprovalGateModal"', false);
    }
}
