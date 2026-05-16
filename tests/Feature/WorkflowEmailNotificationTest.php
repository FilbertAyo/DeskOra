<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Notifications\WeeklyActionLogSubmittedNotification;
use App\Notifications\WeeklyBudgetSubmissionNotification;
use App\Notifications\WeeklyDocumentReturnedToDraftNotification;
use App\Notifications\WeeklyDocumentSubmittedNotification;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkflowEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow(Carbon::create(2026, 4, 15, 10, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_weekly_submission_notifies_approvers_on_email_and_both_channels_only(): void
    {
        Notification::fake();

        $period = $this->createOpenPeriod();
        $owner = $this->createActiveUserWithChannel('email');
        $owner->givePermissionTo('create weekly report');

        $approverEmail = $this->createActiveUserWithChannel('email');
        $approverEmail->givePermissionTo('approve documents');

        $approverBoth = $this->createActiveUserWithChannel('both');
        $approverBoth->givePermissionTo('approve documents');

        $approverSmsOnly = $this->createActiveUserWithChannel('sms');
        $approverSmsOnly->givePermissionTo('approve documents');

        $document = Document::create([
            'user_id' => $owner->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Weekly report draft',
            'state' => Document::STATE_DRAFT,
            'content' => ['summary' => 'Draft summary'],
        ]);

        $this->actingAs($owner)->post(route('documents.submit', $document))
            ->assertRedirect();

        Notification::assertSentTo($approverEmail, WeeklyDocumentSubmittedNotification::class);
        Notification::assertSentTo($approverBoth, WeeklyDocumentSubmittedNotification::class);
        Notification::assertNotSentTo($approverSmsOnly, WeeklyDocumentSubmittedNotification::class);
    }

    public function test_weekly_plan_budget_submission_notifies_budget_reviewers_with_email_preference(): void
    {
        Notification::fake();

        $period = $this->createOpenPeriod();
        $owner = $this->createActiveUserWithChannel('email');
        $owner->givePermissionTo('create weekly plan');

        $budgetReviewerEmail = $this->createActiveUserWithChannel('email');
        $budgetReviewerEmail->givePermissionTo('process weekly budgets');

        $budgetReviewerBoth = $this->createActiveUserWithChannel('both');
        $budgetReviewerBoth->givePermissionTo('process weekly budgets');

        $budgetReviewerSms = $this->createActiveUserWithChannel('sms');
        $budgetReviewerSms->givePermissionTo('process weekly budgets');

        $document = Document::create([
            'user_id' => $owner->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Weekly plan draft',
            'state' => Document::STATE_DRAFT,
            'main_target' => 'Target',
            'content' => [
                'budget' => [
                    [
                        'budget_uid' => (string) \Illuminate\Support\Str::ulid(),
                        'item' => 'Fuel',
                        'description_purpose' => 'Field visit',
                        'amount' => '50000',
                    ],
                ],
            ],
            'budget_review_status' => Document::BUDGET_REVIEW_PENDING,
        ]);

        $this->actingAs($owner)->post(route('documents.submit', $document))
            ->assertRedirect();

        Notification::assertSentTo($budgetReviewerEmail, WeeklyBudgetSubmissionNotification::class);
        Notification::assertSentTo($budgetReviewerBoth, WeeklyBudgetSubmissionNotification::class);
        Notification::assertNotSentTo($budgetReviewerSms, WeeklyBudgetSubmissionNotification::class);
    }

    public function test_rejecting_weekly_document_notifies_owner_when_email_enabled(): void
    {
        Notification::fake();

        $period = $this->createOpenPeriod();
        $owner = $this->createActiveUserWithChannel('both');
        $reviewer = $this->createActiveUserWithChannel('email');
        $reviewer->givePermissionTo('approve documents');

        $document = Document::create([
            'user_id' => $owner->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Submitted weekly plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Target',
            'content' => [],
        ]);

        $this->actingAs($reviewer)->post(route('documents.reject', $document), [
            'reason' => 'Please provide more details in planned activities.',
        ])->assertRedirect();

        Notification::assertSentTo($owner, WeeklyDocumentReturnedToDraftNotification::class);
    }

    public function test_action_log_submission_notifies_all_active_users_with_email_or_both_channels(): void
    {
        Notification::fake();

        $period = $this->createOpenPeriod();

        $manager = $this->createActiveUserWithChannel('email');
        $manager->givePermissionTo('create action logs');

        $activeEmail = $this->createActiveUserWithChannel('email');
        $activeBoth = $this->createActiveUserWithChannel('both');
        $activeSms = $this->createActiveUserWithChannel('sms');
        $inactiveEmail = User::factory()->create([
            'status' => 'inactive',
            'notification_channel' => 'email',
            'email' => 'inactive@example.com',
        ]);

        $this->actingAs($manager)->post(route('action-logs.store'), [
            'period_id' => $period->id,
            'action' => 'submit',
            'content' => [
                'tasks' => [
                    [
                        'task_no' => 1,
                        'task' => 'Prepare weekly action update',
                        'progress' => 'In Progress',
                    ],
                ],
            ],
        ])->assertRedirect();

        Notification::assertSentTo($manager, WeeklyActionLogSubmittedNotification::class);
        Notification::assertSentTo($activeEmail, WeeklyActionLogSubmittedNotification::class);
        Notification::assertSentTo($activeBoth, WeeklyActionLogSubmittedNotification::class);
        Notification::assertNotSentTo($activeSms, WeeklyActionLogSubmittedNotification::class);
        Notification::assertNotSentTo($inactiveEmail, WeeklyActionLogSubmittedNotification::class);
    }

    public function test_document_approver_role_receives_submission_email_without_direct_permission_grant(): void
    {
        Notification::fake();

        $period = $this->createOpenPeriod();
        $owner = $this->createActiveUserWithChannel('email');
        $owner->givePermissionTo('create weekly report');

        $ceo = $this->createActiveUserWithChannel('email');
        $ceo->assignRole(Role::findByName('document_approver'));

        $document = Document::create([
            'user_id' => $owner->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Weekly report draft',
            'state' => Document::STATE_DRAFT,
            'content' => ['summary' => 'Draft summary'],
        ]);

        $this->actingAs($owner)->post(route('documents.submit', $document))
            ->assertRedirect();

        Notification::assertSentTo($ceo, WeeklyDocumentSubmittedNotification::class);
    }

    public function test_weekly_plan_without_budget_does_not_notify_budget_processors(): void
    {
        Notification::fake();

        $period = $this->createOpenPeriod();
        $owner = $this->createActiveUserWithChannel('email');
        $owner->givePermissionTo('create weekly plan');

        $finance = $this->createActiveUserWithChannel('email');
        $finance->givePermissionTo('process weekly budgets');

        $document = Document::create([
            'user_id' => $owner->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Plan without budget',
            'state' => Document::STATE_DRAFT,
            'main_target' => 'Target',
            'content' => [],
        ]);

        $this->actingAs($owner)->post(route('documents.submit', $document))
            ->assertRedirect();

        Notification::assertNotSentTo($finance, WeeklyBudgetSubmissionNotification::class);
    }

    public function test_monthly_report_submission_does_not_trigger_weekly_workflow_notifications(): void
    {
        Notification::fake();

        $owner = $this->createActiveUserWithChannel('email');
        $owner->givePermissionTo('create monthly report');

        $document = Document::create([
            'user_id' => $owner->id,
            'type' => Document::TYPE_MONTHLY_REPORT,
            'title' => 'Monthly draft',
            'state' => Document::STATE_DRAFT,
            'month' => 4,
            'year' => 2026,
            'content' => [],
        ]);

        $this->actingAs($owner)->post(route('documents.submit', $document))
            ->assertRedirect();

        Notification::assertNotSentTo($owner, WeeklyDocumentSubmittedNotification::class);
        Notification::assertNotSentTo($owner, WeeklyBudgetSubmissionNotification::class);
        Notification::assertNotSentTo($owner, WeeklyActionLogSubmittedNotification::class);
        Notification::assertNotSentTo($owner, WeeklyDocumentReturnedToDraftNotification::class);
    }

    private function createOpenPeriod(): WorkPeriod
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

    private function createActiveUserWithChannel(string $channel): User
    {
        $department = Department::first() ?? Department::create(['name' => 'Ops', 'status' => 'active']);

        return User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'notification_channel' => $channel,
        ]);
    }
}
