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
        $reviewer = $this->userWithPermissions(['first pettycash approval'], $department);

        $response = $this->actingAs($reviewer)->get(route('petty.list'));

        $response->assertRedirect(route('petty.list', ['stage' => 'review']));
    }

    public function test_reviewer_sees_completed_reviews_they_handled(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $reviewer = $this->userWithPermissions(['first pettycash approval'], $department);

        $completed = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'processing', 60000);
        $this->addStageApproval($completed, 'review', $reviewer);

        $response = $this->actingAs($reviewer)->get(route('petty.list', ['stage' => 'review']));

        $response->assertOk();
        $response->assertSee('60,000.00');
        $response->assertSee('Completed', false);
    }

    public function test_actionable_requests_are_listed_before_completed_ones(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $reviewer = $this->userWithPermissions(['first pettycash approval'], $department);

        $completed = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'processing', 60000);
        $this->addStageApproval($completed, 'review', $reviewer);

        $pending = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'pending', 50000);

        $response = $this->actingAs($reviewer)->get(route('petty.list', ['stage' => 'review']));

        $response->assertOk();
        $this->assertTrue(
            strpos($response->getContent(), '50,000.00') < strpos($response->getContent(), '60,000.00')
        );
    }

    public function test_reviewer_sees_only_pending_review_items(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $reviewer = $this->userWithPermissions(['first pettycash approval'], $department);

        $pendingReview = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'pending', 50000);
        $awaitingApprove = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'processing', 75000);
        $this->addStageApproval($awaitingApprove, 'review');

        $response = $this->actingAs($reviewer)->get(route('petty.list', ['stage' => 'review']));

        $response->assertOk();
        $response->assertSee('50,000.00');
        $response->assertDontSee('75,000.00');
    }

    public function test_approver_sees_only_post_review_items(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $approver = $this->userWithPermissions(['last pettycash approval'], $department);

        $pendingReview = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'pending', 50000);
        $awaitingApprove = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'processing', 75000);
        $this->addStageApproval($awaitingApprove, 'review');

        $response = $this->actingAs($approver)->get(route('petty.list', ['stage' => 'approve']));

        $response->assertOk();
        $response->assertSee('75,000.00');
        $response->assertDontSee('50,000.00');
    }

    public function test_authorizer_sees_only_post_approve_items(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $authorizer = $this->userWithPermissions(['authorize requests'], $department);

        $awaitingApprove = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'processing', 75000);
        $this->addStageApproval($awaitingApprove, 'review');

        $awaitingAuthorize = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'processing', 90000);
        $this->addStageApproval($awaitingAuthorize, 'review');
        $this->addStageApproval($awaitingAuthorize, 'approve');

        $response = $this->actingAs($authorizer)->get(route('petty.list', ['stage' => 'authorize']));

        $response->assertOk();
        $response->assertSee('90,000.00');
        $response->assertDontSee('75,000.00');
    }

    public function test_user_without_stage_permission_gets_forbidden(): void
    {
        $department = $this->createDepartment();
        $reviewer = $this->userWithPermissions(['first pettycash approval'], $department);

        $response = $this->actingAs($reviewer)->get(route('petty.list', ['stage' => 'authorize']));

        $response->assertForbidden();
    }

    public function test_nav_counts_match_actionable_queues(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $reviewer = $this->userWithPermissions(['first pettycash approval'], $department);

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
        $reviewer = $this->userWithPermissions(['first pettycash approval'], $department);

        $awaitingAuthorize = $this->createFinanceRequest($department, $requester, 'Petty Cash', 'processing');
        $this->addStageApproval($awaitingAuthorize, 'review');
        $this->addStageApproval($awaitingAuthorize, 'approve');

        $response = $this->actingAs($reviewer)->get(route('petty.details', [
            'petty' => $awaitingAuthorize->id,
            'stage' => 'review',
        ]));

        $response->assertOk();
        $response->assertSee('View only', false);
        $response->assertDontSee('data-bs-target="#approvalModal"', false);
    }

    public function test_requisition_approvals_route_redirects_to_review_queue(): void
    {
        $department = $this->createDepartment();
        $reviewer = $this->userWithPermissions(['review staff requisition'], $department);

        $response = $this->actingAs($reviewer)->get(route('petty.requisition.approvals'));

        $response->assertRedirect(route('petty.list', ['stage' => 'review']));
    }
}
