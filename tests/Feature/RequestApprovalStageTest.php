<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PettyRequest;
use App\Models\Request as FinanceRequest;
use App\Models\RequestApproval;
use App\Models\User;
use App\Services\RequestApprovalNavService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RequestApprovalStageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Cache::flush();
    }

    private function createDepartment(string $name = 'Operations'): Department
    {
        return Department::create(['name' => $name, 'status' => 'active']);
    }

    private function createRequester(Department $department): User
    {
        return User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
        ]);
    }

    private function createFinanceRequest(
        Department $department,
        User $requester,
        string $requestType = 'Petty Cash',
        string $status = 'pending',
        float $amount = 50000
    ): FinanceRequest {
        $financeRequest = FinanceRequest::create([
            'request_no' => 'RQ'.random_int(100, 999),
            'request_type' => $requestType,
            'user_id' => $requester->id,
            'department_id' => $department->id,
            'status' => $status,
            'submitted_at' => now(),
        ]);

        PettyRequest::create([
            'request_id' => $financeRequest->id,
            'request_for' => 'Office Supplies',
            'amount' => $amount,
            'reason' => 'Supplies',
        ]);

        RequestApproval::create([
            'request_id' => $financeRequest->id,
            'step' => 'system',
            'action' => 'created',
            'actor_id' => $requester->id,
            'acted_at' => now(),
        ]);

        return $financeRequest->fresh(['pettyDetails', 'approvals']);
    }

    private function addStageApproval(FinanceRequest $financeRequest, string $step, ?User $actor = null): void
    {
        RequestApproval::create([
            'request_id' => $financeRequest->id,
            'step' => $step,
            'action' => 'approved',
            'actor_id' => ($actor ?? User::factory()->create())->id,
            'acted_at' => now(),
        ]);
    }

    private function userWithPermissions(array $permissions, ?Department $department = null): User
    {
        $user = User::factory()->create([
            'department_id' => $department?->id,
            'status' => 'active',
            'signature' => 'signatures/test.png',
        ]);
        $user->givePermissionTo(array_merge(['view requested pettycash'], $permissions));

        return $user;
    }

    public function test_bare_approvals_route_redirects_to_first_accessible_stage(): void
    {
        $department = $this->createDepartment();
        $reviewer = $this->userWithPermissions(['review pettycash'], $department);

        $response = $this->actingAs($reviewer)->get(route('petty.list'));

        $response->assertRedirect(route('petty.list', ['stage' => 'review']));
    }

    public function test_reviewer_sees_completed_reviews_they_handled(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $reviewer = $this->userWithPermissions(['review pettycash'], $department);

        $completed = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'processing', 60000);
        $this->addStageApproval($completed, 'review', $reviewer);

        $response = $this->actingAs($reviewer)->get(route('petty.list', ['stage' => 'review']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Approvals/Index')
            ->where('requests.0.amount', 60000)
            ->where('requests.0.stageApproved', true));
    }

    public function test_actionable_requests_are_listed_before_completed_ones(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $reviewer = $this->userWithPermissions(['review pettycash'], $department);

        $completed = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'processing', 60000);
        $this->addStageApproval($completed, 'review', $reviewer);

        $pending = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'pending', 50000);

        $response = $this->actingAs($reviewer)->get(route('petty.list', ['stage' => 'review']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('requests.0.amount', 50000)
            ->where('requests.1.amount', 60000));
    }

    public function test_reviewer_sees_pending_and_already_reviewed_items(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $reviewer = $this->userWithPermissions(['review pettycash'], $department);
        $otherReviewer = $this->userWithPermissions(['review pettycash'], $department);

        $pendingReview = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'pending', 50000);
        $awaitingApprove = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'processing', 75000);
        $this->addStageApproval($awaitingApprove, 'review', $otherReviewer);

        $response = $this->actingAs($reviewer)->get(route('petty.list', ['stage' => 'review']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('requests', 2)
            ->where('requests.0.canAct', true)
            ->where('requests.1.stageApproved', true));
    }

    public function test_rejected_request_stays_on_stage_list_but_is_not_counted(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $approver = $this->userWithPermissions(['approve pettycash'], $department);

        $rejectedAtApprove = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'rejected', 88000);
        $this->addStageApproval($rejectedAtApprove, 'review');
        RequestApproval::create([
            'request_id' => $rejectedAtApprove->id,
            'step' => 'system',
            'action' => 'rejected',
            'actor_id' => $approver->id,
            'acted_at' => now(),
        ]);

        $pendingApprove = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'processing', 44000);
        $this->addStageApproval($pendingApprove, 'review');

        $count = app(RequestApprovalNavService::class)
            ->countForStageAndTypes($approver, 'approve', ['Petty Cash', 'Reimbursement']);

        $this->assertSame(1, $count);

        $response = $this->actingAs($approver)->get(route('petty.list', ['stage' => 'approve']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('requests', 2)
            ->where('requests.0.amount', 44000)
            ->where('requests.1.amount', 88000));
    }

    public function test_reject_endpoint_logs_current_stage_and_clears_actionable_count(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $approver = $this->userWithPermissions(['approve pettycash'], $department);

        $request = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'processing', 33000);
        $this->addStageApproval($request, 'review');

        Cache::flush();
        $this->assertSame(
            1,
            app(RequestApprovalNavService::class)->countForStageAndTypes($approver, 'approve', ['Petty Cash'])
        );

        $response = $this->actingAs($approver)->post(route('petty.reject', $request), [
            'comment' => 'Not justified',
            'action' => 'rejected',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('request_approvals', [
            'request_id' => $request->id,
            'step' => 'approve',
            'action' => 'rejected',
            'actor_id' => $approver->id,
        ]);
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertSame(
            0,
            app(RequestApprovalNavService::class)->countForStageAndTypes($approver, 'approve', ['Petty Cash'])
        );
    }

    public function test_approver_sees_only_post_review_items(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $approver = $this->userWithPermissions(['approve pettycash'], $department);

        $pendingReview = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'pending', 50000);
        $awaitingApprove = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'processing', 75000);
        $this->addStageApproval($awaitingApprove, 'review');

        $response = $this->actingAs($approver)->get(route('petty.list', ['stage' => 'approve']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('requests', 1)
            ->where('requests.0.amount', 75000));
    }

    public function test_authorizer_sees_only_post_approve_items(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $authorizer = $this->userWithPermissions(['authorize pettycash'], $department);

        $awaitingApprove = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'processing', 75000);
        $this->addStageApproval($awaitingApprove, 'review');

        $awaitingAuthorize = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'processing', 90000);
        $this->addStageApproval($awaitingAuthorize, 'review');
        $this->addStageApproval($awaitingAuthorize, 'approve');

        $response = $this->actingAs($authorizer)->get(route('petty.list', ['stage' => 'authorize']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('requests', 1)
            ->where('requests.0.amount', 90000));
    }

    public function test_user_without_stage_permission_gets_forbidden(): void
    {
        $department = $this->createDepartment();
        $reviewer = $this->userWithPermissions(['review pettycash'], $department);

        $response = $this->actingAs($reviewer)->get(route('petty.list', ['stage' => 'authorize']));

        $response->assertForbidden();
    }

    public function test_nav_counts_match_actionable_queues(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $reviewer = $this->userWithPermissions(['review pettycash'], $department);

        $this->createFinanceRequest($department, $requester);
        $this->createFinanceRequest($department, $requester);

        $counts = app(RequestApprovalNavService::class)->countsFor($reviewer);

        $this->assertSame(2, $counts['review']);
        $this->assertSame(0, $counts['approve']);
        $this->assertSame(2, $counts['total']);
    }

    public function test_detail_page_hides_action_for_wrong_stage_user(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $reviewer = $this->userWithPermissions(['review pettycash'], $department);

        $awaitingAuthorize = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'processing');
        $this->addStageApproval($awaitingAuthorize, 'review');
        $this->addStageApproval($awaitingAuthorize, 'approve');

        $response = $this->actingAs($reviewer)->get(route('petty.details', [
            'petty' => $awaitingAuthorize->id,
            'stage' => 'review',
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Approvals/Show')
            ->where('canActOnRequest', false));
    }

    public function test_requisition_approvals_route_redirects_to_review_queue(): void
    {
        $department = $this->createDepartment();
        $reviewer = $this->userWithPermissions(['review staff requisition'], $department);

        $response = $this->actingAs($reviewer)->get(route('petty.requisition.approvals'));

        $response->assertRedirect(route('petty.list', ['stage' => 'review']));
    }

    public function test_staff_requisition_authorizer_sees_requests_across_departments(): void
    {
        $authorizerDepartment = $this->createDepartment('Finance');
        $requesterDepartment = $this->createDepartment('Programs');
        $requester = $this->createRequester($requesterDepartment);
        $authorizer = $this->userWithPermissions(['authorize staff requisition'], $authorizerDepartment);

        $awaitingAuthorize = $this->createFinanceRequest(
            $requesterDepartment,
            $requester,
            'Staff Requisition',
            'processing',
            150000
        );
        $this->addStageApproval($awaitingAuthorize, 'review');
        $this->addStageApproval($awaitingAuthorize, 'approve');

        $otherDeptPetty = $this->createFinanceRequest(
            $requesterDepartment,
            $requester,
            'Petty Cash',
            'processing',
            25000
        );
        $this->addStageApproval($otherDeptPetty, 'review');
        $this->addStageApproval($otherDeptPetty, 'approve');

        $count = app(RequestApprovalNavService::class)
            ->countForStageAndTypes($authorizer, 'authorize', ['Staff Requisition']);

        $this->assertSame(1, $count);

        $response = $this->actingAs($authorizer)->get(route('petty.list', [
            'stage' => 'authorize',
            'type' => 'requisition',
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('requests', 1)
            ->where('requests.0.requestType', 'Staff Requisition')
            ->where('requests.0.canAct', true));
    }
}
