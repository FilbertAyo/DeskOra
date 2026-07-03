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
}
