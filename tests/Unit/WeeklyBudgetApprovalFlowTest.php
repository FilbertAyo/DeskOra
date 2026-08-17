<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Models\User;
use App\Support\WeeklyBudgetApprovalFlow;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyBudgetApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_pending_stage_resolves_review_before_authorize(): void
    {
        $reviewer = $this->userWithPermissions(['review weekly budgets', 'authorize weekly budgets']);

        $this->assertSame(
            WeeklyBudgetApprovalFlow::STAGE_REVIEW,
            WeeklyBudgetApprovalFlow::resolveNextActionStage(Document::BUDGET_REVIEW_STAGE_PENDING, $reviewer)
        );
    }

    public function test_pending_stage_allows_direct_authorize_for_authorizer_only(): void
    {
        $authorizer = $this->userWithPermissions(['authorize weekly budgets']);

        $this->assertSame(
            WeeklyBudgetApprovalFlow::STAGE_AUTHORIZE,
            WeeklyBudgetApprovalFlow::resolveNextActionStage(Document::BUDGET_REVIEW_STAGE_PENDING, $authorizer)
        );
    }

    public function test_reviewed_stage_resolves_approve_before_authorize(): void
    {
        $approver = $this->userWithPermissions(['approve weekly budgets', 'authorize weekly budgets']);

        $this->assertSame(
            WeeklyBudgetApprovalFlow::STAGE_APPROVE,
            WeeklyBudgetApprovalFlow::resolveNextActionStage(Document::BUDGET_REVIEW_STAGE_REVIEWED, $approver)
        );
    }

    public function test_reviewed_stage_allows_direct_authorize_for_authorizer_only(): void
    {
        $authorizer = $this->userWithPermissions(['authorize weekly budgets']);

        $this->assertSame(
            WeeklyBudgetApprovalFlow::STAGE_AUTHORIZE,
            WeeklyBudgetApprovalFlow::resolveNextActionStage(Document::BUDGET_REVIEW_STAGE_REVIEWED, $authorizer)
        );
    }

    public function test_approver_without_permission_gets_no_action(): void
    {
        $approver = $this->userWithPermissions(['approve weekly budgets']);

        $this->assertNull(
            WeeklyBudgetApprovalFlow::resolveNextActionStage(Document::BUDGET_REVIEW_STAGE_PENDING, $approver)
        );
    }

    public function test_queue_visibility_matches_each_role_stage(): void
    {
        $reviewer = $this->userWithPermissions(['review weekly budgets']);
        $approver = $this->userWithPermissions(['approve weekly budgets']);
        $authorizer = $this->userWithPermissions(['authorize weekly budgets']);

        $pending = ['decision' => Document::BUDGET_REVIEW_PENDING, 'approval_stage' => Document::BUDGET_REVIEW_STAGE_PENDING];
        $reviewed = ['decision' => Document::BUDGET_REVIEW_APPROVED, 'approval_stage' => Document::BUDGET_REVIEW_STAGE_REVIEWED];
        $approved = ['decision' => Document::BUDGET_REVIEW_APPROVED, 'approval_stage' => Document::BUDGET_REVIEW_STAGE_APPROVED];
        $authorized = ['decision' => Document::BUDGET_REVIEW_APPROVED, 'approval_stage' => Document::BUDGET_REVIEW_STAGE_AUTHORIZED];

        $this->assertTrue(WeeklyBudgetApprovalFlow::itemIsInUserQueue($pending, $reviewer));
        $this->assertFalse(WeeklyBudgetApprovalFlow::itemIsInUserQueue($reviewed, $reviewer));
        $this->assertFalse(WeeklyBudgetApprovalFlow::itemIsInUserQueue($approved, $reviewer));

        $this->assertFalse(WeeklyBudgetApprovalFlow::itemIsInUserQueue($pending, $approver));
        $this->assertTrue(WeeklyBudgetApprovalFlow::itemIsInUserQueue($reviewed, $approver));
        $this->assertFalse(WeeklyBudgetApprovalFlow::itemIsInUserQueue($approved, $approver));

        $this->assertFalse(WeeklyBudgetApprovalFlow::itemIsInUserQueue($pending, $authorizer));
        $this->assertFalse(WeeklyBudgetApprovalFlow::itemIsInUserQueue($reviewed, $authorizer));
        $this->assertTrue(WeeklyBudgetApprovalFlow::itemIsInUserQueue($approved, $authorizer));
        $this->assertFalse(WeeklyBudgetApprovalFlow::itemIsInUserQueue($authorized, $authorizer));
    }
}
