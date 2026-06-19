<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PettyRequest;
use App\Models\Request as FinanceRequest;
use App\Models\RequestApproval;
use App\Models\RequisitionRequest;
use App\Models\User;
use App\Notifications\RequisitionWorkflowNotification;
use App\Services\RequisitionNotificationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RequisitionEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Cache::flush();
    }

    public function test_submitted_notifies_reviewers_on_email_and_both_channels_only(): void
    {
        Notification::fake();

        $department = $this->createDepartment();
        $requester = $this->createRequester($department, 'email');
        $requisition = $this->createStaffRequisition($department, $requester, 'pending', false);

        $reviewerEmail = $this->userWithPermissions(['review staff requisition'], $department, 'email');
        $reviewerBoth = $this->userWithPermissions(['review staff requisition'], $department, 'both');
        $reviewerSmsOnly = $this->userWithPermissions(['review staff requisition'], $department, 'sms');
        $wrongDepartmentReviewer = $this->userWithPermissions(['review staff requisition'], $this->createDepartment('Finance'), 'email');

        app(RequisitionNotificationService::class)->notifyStageApprovers($requisition, 'submitted');

        Notification::assertSentTo($reviewerEmail, RequisitionWorkflowNotification::class);
        Notification::assertSentTo($reviewerBoth, RequisitionWorkflowNotification::class);
        Notification::assertNotSentTo($reviewerSmsOnly, RequisitionWorkflowNotification::class);
        Notification::assertNotSentTo($wrongDepartmentReviewer, RequisitionWorkflowNotification::class);
        Notification::assertNotSentTo($requester, RequisitionWorkflowNotification::class);
    }

    public function test_reviewed_notifies_approvers_in_department_scope(): void
    {
        Notification::fake();

        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $requisition = $this->createStaffRequisition($department, $requester, 'processing', false);

        RequestApproval::create([
            'request_id' => $requisition->id,
            'step' => 'review',
            'action' => 'approved',
            'actor_id' => $requester->id,
            'acted_at' => now(),
        ]);

        $approver = $this->userWithPermissions(['approve staff requisition'], $department, 'email');

        app(RequisitionNotificationService::class)->notifyStageApprovers($requisition->fresh(), 'reviewed');

        Notification::assertSentTo($approver, RequisitionWorkflowNotification::class);
    }

    public function test_approved_notifies_authorizers(): void
    {
        Notification::fake();

        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $requisition = $this->createStaffRequisition($department, $requester, 'processing', false);

        foreach (['review', 'approve'] as $step) {
            RequestApproval::create([
                'request_id' => $requisition->id,
                'step' => $step,
                'action' => 'approved',
                'actor_id' => $requester->id,
                'acted_at' => now(),
            ]);
        }

        $authorizer = $this->userWithPermissions(['authorize staff requisition'], $department, 'email');

        app(RequisitionNotificationService::class)->notifyStageApprovers($requisition->fresh(), 'approved');

        Notification::assertSentTo($authorizer, RequisitionWorkflowNotification::class);
    }

    public function test_authorized_notifies_requester(): void
    {
        Notification::fake();

        $department = $this->createDepartment();
        $requester = $this->createRequester($department, 'email');
        $requisition = $this->createStaffRequisition($department, $requester, 'approved', true);
        $authorizer = $this->userWithPermissions(['authorize staff requisition'], $department, 'email');

        app(RequisitionNotificationService::class)->notifyRequester($requisition->fresh(), 'authorized', $authorizer);

        Notification::assertSentTo($requester, RequisitionWorkflowNotification::class);
    }

    public function test_rejected_notifies_requester_with_comment_in_context(): void
    {
        Notification::fake();

        $department = $this->createDepartment();
        $requester = $this->createRequester($department, 'email');
        $requisition = $this->createStaffRequisition($department, $requester, 'processing', false);
        $reviewer = $this->userWithPermissions(['review staff requisition'], $department, 'email');
        $comment = 'Budget code is incorrect. Please update and resubmit.';

        app(RequisitionNotificationService::class)->notifyRequester($requisition, 'rejected', $reviewer, $comment);

        Notification::assertSentTo($requester, RequisitionWorkflowNotification::class);

        $context = app(RequisitionNotificationService::class)->buildContext($requisition, 'rejected', $reviewer, $comment);
        $this->assertSame($comment, $context->comment);
        $this->assertSame($reviewer->name, $context->actorName);
        $this->assertStringContainsString(route('requisition.show', $requisition), $context->actionUrl);
    }

    public function test_resubmission_requested_notifies_requester_with_edit_link(): void
    {
        Notification::fake();

        $department = $this->createDepartment();
        $requester = $this->createRequester($department, 'both');
        $requisition = $this->createStaffRequisition($department, $requester, 'processing', false);
        $approver = $this->userWithPermissions(['approve staff requisition'], $department, 'email');
        $comment = 'Please attach supporting documents.';

        app(RequisitionNotificationService::class)->notifyRequester($requisition, 'resubmission_requested', $approver, $comment);

        Notification::assertSentTo($requester, RequisitionWorkflowNotification::class);

        $context = app(RequisitionNotificationService::class)->buildContext($requisition, 'resubmission_requested', $approver, $comment);
        $this->assertSame($comment, $context->comment);
        $this->assertStringContainsString(route('requisition.edit', $requisition), $context->actionUrl);
    }

    public function test_submitted_context_includes_approval_action_url_with_stage(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $requisition = $this->createStaffRequisition($department, $requester, 'pending', false);

        $context = app(RequisitionNotificationService::class)->buildContext($requisition, 'submitted');

        $this->assertSame('Staff Requisition awaiting review', $context->subject);
        $this->assertStringContainsString('stage=review', $context->actionUrl);
        $this->assertSame('Review Requisition', $context->actionLabel);
        $this->assertSame('Training Materials', $context->summary);
    }

    public function test_petty_cash_submission_does_not_send_requisition_notification(): void
    {
        Notification::fake();

        $department = $this->createDepartment();
        $requester = $this->createRequester($department, 'email');
        $reviewer = $this->userWithPermissions(['first pettycash approval'], $department, 'email');

        $petty = FinanceRequest::create([
            'request_no' => 'PC123',
            'request_type' => 'Petty Cash',
            'user_id' => $requester->id,
            'department_id' => $department->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        PettyRequest::create([
            'request_id' => $petty->id,
            'request_for' => 'Office Supplies',
            'amount' => 50000,
            'reason' => 'Supplies',
        ]);

        app(RequisitionNotificationService::class)->notifyStageApprovers($petty->fresh(['pettyDetails']), 'submitted');

        Notification::assertNothingSent();
        Notification::assertNotSentTo($reviewer, RequisitionWorkflowNotification::class);
    }

    public function test_controller_review_sends_requisition_notification(): void
    {
        Notification::fake();

        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $requisition = $this->createStaffRequisition($department, $requester, 'pending', false);
        $reviewer = $this->userWithPermissions(['review staff requisition', 'view requested pettycash'], $department, 'email');
        $approver = $this->userWithPermissions(['approve staff requisition'], $department, 'email');

        $this->actingAs($reviewer)->post(route('f_approve.approve', $requisition->id))->assertRedirect();

        Notification::assertSentTo($approver, RequisitionWorkflowNotification::class);
    }

    public function test_controller_reject_sends_requisition_rejection_notification(): void
    {
        Notification::fake();

        $department = $this->createDepartment();
        $requester = $this->createRequester($department, 'email');
        $requisition = $this->createStaffRequisition($department, $requester, 'pending', false);
        $reviewer = $this->userWithPermissions(['review staff requisition', 'view requested pettycash'], $department, 'email');

        $this->actingAs($reviewer)->post(route('petty.reject', $requisition->id), [
            'comment' => 'Missing budget justification.',
            'action' => 'rejected',
        ])->assertRedirect();

        Notification::assertSentTo($requester, RequisitionWorkflowNotification::class);
    }

    private function createDepartment(string $name = 'Operations'): Department
    {
        return Department::create(['name' => $name, 'status' => 'active']);
    }

    private function createRequester(Department $department, string $channel = 'sms'): User
    {
        return User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'notification_channel' => $channel,
        ]);
    }

    private function userWithPermissions(array $permissions, ?Department $department = null, string $channel = 'sms'): User
    {
        $user = User::factory()->create([
            'department_id' => $department?->id,
            'status' => 'active',
            'notification_channel' => $channel,
            'signature' => 'signatures/test.png',
        ]);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function createStaffRequisition(
        Department $department,
        User $requester,
        string $status = 'pending',
        bool $fullyAuthorized = false,
        float $amount = 90000,
    ): FinanceRequest {
        $financeRequest = FinanceRequest::create([
            'request_no' => 'SR'.random_int(100, 999),
            'request_type' => 'Staff Requisition',
            'user_id' => $requester->id,
            'department_id' => $department->id,
            'status' => $status,
            'submitted_at' => now(),
        ]);

        RequisitionRequest::create([
            'request_id' => $financeRequest->id,
            'project_program' => 'Project Alpha',
            'activity_code' => 'ACT-01',
            'activity_title' => 'Training Materials',
            'budget_code' => 'BUD-100',
            'amount' => $amount,
            'reason' => 'Materials',
        ]);

        RequestApproval::create([
            'request_id' => $financeRequest->id,
            'step' => 'system',
            'action' => 'created',
            'actor_id' => $requester->id,
            'acted_at' => now(),
        ]);

        if ($fullyAuthorized) {
            foreach (['review', 'approve', 'authorize'] as $step) {
                RequestApproval::create([
                    'request_id' => $financeRequest->id,
                    'step' => $step,
                    'action' => 'approved',
                    'actor_id' => $requester->id,
                    'acted_at' => now(),
                ]);
            }
        }

        return $financeRequest->fresh(['user', 'department', 'requisitionDetails']);
    }
}
