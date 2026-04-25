<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Models\WeeklyActivityAssignment;
use App\Models\WorkPeriod;
use App\Services\WeeklyActivityAssignmentService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyDocumentSharingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware();
        Carbon::setTestNow(Carbon::create(2025, 4, 2, 10, 0, 0));
    }

    public function test_owner_can_share_weekly_plan_with_same_department_staff(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);
        $member = $this->createUserInDepartment($department, ['create weekly plan']);

        $response = $this->actingAs($owner)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'main_target' => 'Shared target',
            'shared_user_ids' => [$member->id],
        ]);

        $response->assertRedirect();
        $document = Document::query()->where('user_id', $owner->id)->where('type', Document::TYPE_WEEKLY_PLAN)->firstOrFail();

        $this->assertDatabaseHas('document_user_shares', [
            'document_id' => $document->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_weekly_report_inherits_collaborators_from_weekly_plan(): void
    {
        [$currentPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Operations']);
        $owner = $this->createUserInDepartment($department, ['create weekly report']);
        $member = $this->createUserInDepartment($department);

        $plan = Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $currentPeriod->id,
            'user_id' => $owner->id,
            'main_target' => 'Shared weekly plan target',
            'title' => 'Plan',
            'state' => Document::STATE_DRAFT,
        ]);
        $plan->sharedUsers()->sync([$member->id]);

        $response = $this->actingAs($owner)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $currentPeriod->id,
        ]);

        $response->assertRedirect();
        $document = Document::query()->where('user_id', $owner->id)->where('type', Document::TYPE_WEEKLY_REPORT)->firstOrFail();

        $this->assertDatabaseHas('document_user_shares', [
            'document_id' => $document->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_weekly_report_ignores_manual_collaborator_payload_and_uses_plan_group(): void
    {
        [$currentPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Operations']);
        $owner = $this->createUserInDepartment($department, ['create weekly report', 'create weekly plan']);
        $planCollaborator = $this->createUserInDepartment($department);
        $manualPayloadCollaborator = $this->createUserInDepartment($department);

        $plan = Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $currentPeriod->id,
            'user_id' => $owner->id,
            'main_target' => 'Shared weekly plan target',
            'title' => 'Plan',
            'state' => Document::STATE_DRAFT,
        ]);
        $plan->sharedUsers()->sync([$planCollaborator->id]);

        $response = $this->actingAs($owner)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $currentPeriod->id,
            'shared_user_ids' => [$manualPayloadCollaborator->id],
        ]);

        $response->assertRedirect();
        $document = Document::query()->where('user_id', $owner->id)->where('type', Document::TYPE_WEEKLY_REPORT)->firstOrFail();

        $this->assertDatabaseHas('document_user_shares', [
            'document_id' => $document->id,
            'user_id' => $planCollaborator->id,
        ]);
        $this->assertDatabaseMissing('document_user_shares', [
            'document_id' => $document->id,
            'user_id' => $manualPayloadCollaborator->id,
        ]);
    }

    public function test_collaborator_can_edit_weekly_report_draft_without_missing_plan_message(): void
    {
        [$currentPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);
        $collaborator = $this->createUserInDepartment($department, ['create weekly report']);

        $plan = Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $currentPeriod->id,
            'user_id' => $owner->id,
            'main_target' => 'Shared target for collaborators',
            'title' => 'Plan',
            'state' => Document::STATE_DRAFT,
        ]);
        $plan->sharedUsers()->sync([$collaborator->id]);

        $this->actingAs($collaborator)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $currentPeriod->id,
            'action' => 'draft',
        ]);

        $report = Document::query()
            ->where('user_id', $collaborator->id)
            ->where('type', Document::TYPE_WEEKLY_REPORT)
            ->firstOrFail();

        $this->assertSame(Document::STATE_DRAFT, $report->state);
        $this->assertTrue($collaborator->can('update', $report));

        $response = $this->actingAs($collaborator)->view('documents.partials.edit.weekly-report-fields', [
            'document' => $report->load('sharedUsers'),
            'shareableUsers' => collect(),
            'errors' => new \Illuminate\Support\ViewErrorBag,
        ]);
        $response->assertDontSeeText('No weekly plan found for this work period.');
        $response->assertSeeText('Shared target for collaborators');
    }

    public function test_cross_department_share_is_rejected(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();
        $ownerDepartment = Department::create(['name' => 'Finance']);
        $otherDepartment = Department::create(['name' => 'Admin']);
        $owner = $this->createUserInDepartment($ownerDepartment, ['create weekly plan']);
        $outsideUser = $this->createUserInDepartment($otherDepartment);

        $response = $this->actingAs($owner)->from(route('documents.create', ['type' => 'weekly_plan']))->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'main_target' => 'Target',
            'shared_user_ids' => [$outsideUser->id],
        ]);

        $response->assertSessionHasErrors(['shared_user_ids']);
        $this->assertDatabaseCount('document_user_shares', 0);
    }

    public function test_shared_member_has_edit_and_submit_permissions_for_weekly_plan(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'ICT']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);
        $member = $this->createUserInDepartment($department);

        $this->actingAs($owner)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'main_target' => 'Initial target',
            'shared_user_ids' => [$member->id],
        ]);

        $document = Document::query()->where('user_id', $owner->id)->where('type', Document::TYPE_WEEKLY_PLAN)->firstOrFail();
        $this->assertDatabaseHas('document_user_shares', [
            'document_id' => $document->id,
            'user_id' => $member->id,
        ]);
        $this->assertSame(Document::STATE_DRAFT, $document->state);
        $this->assertTrue($document->fresh()->isSharedWith($member->id));
        $this->assertTrue($member->can('update', $document->fresh()));
        $this->assertTrue($member->can('submit', $document->fresh()));
    }

    public function test_unmerged_member_loses_access_to_weekly_plan(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Research']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);
        $member = $this->createUserInDepartment($department, ['create weekly plan']);

        $this->actingAs($owner)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'main_target' => 'Shared target',
            'shared_user_ids' => [$member->id],
        ]);

        $document = Document::query()->where('user_id', $owner->id)->where('type', Document::TYPE_WEEKLY_PLAN)->firstOrFail();

        $this->actingAs($owner)->put(route('documents.update', $document), [
            'action' => 'draft',
            'main_target' => 'Still draft',
            'shared_user_ids' => [],
        ]);

        $showResponse = $this->actingAs($member)->get(route('documents.show', $document));
        $showResponse->assertForbidden();
    }

    public function test_shared_weekly_plan_is_visible_in_member_listing(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Monitoring']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);
        $member = $this->createUserInDepartment($department);

        $this->actingAs($owner)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'main_target' => 'Shared listing target',
            'shared_user_ids' => [$member->id],
        ]);

        $response = $this->actingAs($member)->get(route('documents.weekly-plans'));
        $response->assertOk();
        $document = Document::query()->where('user_id', $owner->id)->where('type', Document::TYPE_WEEKLY_PLAN)->firstOrFail();
        $this->assertTrue(Document::query()->forOwnerOrSharedUser($member->id)->whereKey($document->id)->exists());
    }

    public function test_legacy_weekly_report_is_accessible_through_shared_plan_scope(): void
    {
        [$currentPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Programs']);
        $planOwner = $this->createUserInDepartment($department, ['create weekly plan', 'create weekly report']);
        $reportAuthor = $this->createUserInDepartment($department, ['create weekly report']);
        $otherCollaborator = $this->createUserInDepartment($department);

        $plan = Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $currentPeriod->id,
            'user_id' => $planOwner->id,
            'main_target' => 'Shared plan target',
            'title' => 'Shared Plan',
            'state' => Document::STATE_DRAFT,
        ]);
        $plan->sharedUsers()->sync([$reportAuthor->id, $otherCollaborator->id]);

        $report = Document::create([
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $currentPeriod->id,
            'user_id' => $reportAuthor->id,
            'title' => 'Legacy shared report draft',
            'state' => Document::STATE_DRAFT,
        ]);

        $isVisibleInWeeklyReportScope = Document::query()
            ->ofType(Document::TYPE_WEEKLY_REPORT)
            ->forOwnerSharedOrPlanCollaboratorOnWeeklyReport($planOwner->id)
            ->whereKey($report->id)
            ->exists();

        $this->assertTrue($isVisibleInWeeklyReportScope);
    }

    public function test_dashboard_pending_documents_include_shared_plan_weekly_report_drafts(): void
    {
        [$currentPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Programs']);
        $planOwner = $this->createUserInDepartment($department, ['create weekly plan', 'create weekly report']);
        $reportAuthor = $this->createUserInDepartment($department, ['create weekly report']);
        $otherCollaborator = $this->createUserInDepartment($department);

        $plan = Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $currentPeriod->id,
            'user_id' => $planOwner->id,
            'main_target' => 'Shared plan target',
            'title' => 'Shared Plan',
            'state' => Document::STATE_DRAFT,
        ]);
        $plan->sharedUsers()->sync([$reportAuthor->id, $otherCollaborator->id]);

        $report = Document::create([
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $currentPeriod->id,
            'user_id' => $reportAuthor->id,
            'title' => 'Focus shared report draft',
            'state' => Document::STATE_DRAFT,
        ]);

        $pendingForDashboard = Document::query()
            ->forOwnerSharedOrPlanCollaboratorOnWeeklyReport($planOwner->id)
            ->drafts()
            ->whereKey($report->id)
            ->exists();

        $this->assertTrue($pendingForDashboard);
    }

    public function test_plan_collaborator_scope_does_not_include_unrelated_weekly_reports_from_same_period(): void
    {
        [$currentPeriod] = $this->createCurrentAndNextPeriods();
        $programs = Department::create(['name' => 'Programs']);
        $finance = Department::create(['name' => 'Finance']);

        $planOwner = $this->createUserInDepartment($programs, ['create weekly plan', 'create weekly report']);
        $planMember = $this->createUserInDepartment($programs, ['create weekly report']);
        $outsideAuthor = $this->createUserInDepartment($finance, ['create weekly report']);

        $plan = Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $currentPeriod->id,
            'user_id' => $planOwner->id,
            'main_target' => 'Programs plan',
            'title' => 'Programs Shared Plan',
            'state' => Document::STATE_DRAFT,
        ]);
        $plan->sharedUsers()->sync([$planMember->id]);

        $relatedReport = Document::create([
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $currentPeriod->id,
            'user_id' => $planOwner->id,
            'title' => 'Programs report',
            'state' => Document::STATE_DRAFT,
        ]);

        $outsideReport = Document::create([
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $currentPeriod->id,
            'user_id' => $outsideAuthor->id,
            'title' => 'Finance-only report',
            'state' => Document::STATE_DRAFT,
        ]);

        $this->assertTrue(Document::query()
            ->ofType(Document::TYPE_WEEKLY_REPORT)
            ->forOwnerSharedOrPlanCollaboratorOnWeeklyReport($planMember->id)
            ->whereKey($relatedReport->id)
            ->exists());

        $this->assertFalse(Document::query()
            ->ofType(Document::TYPE_WEEKLY_REPORT)
            ->forOwnerSharedOrPlanCollaboratorOnWeeklyReport($planMember->id)
            ->whereKey($outsideReport->id)
            ->exists());
    }

    public function test_weekly_reports_page_ignores_view_all_permission_for_unrelated_reports(): void
    {
        [$currentPeriod] = $this->createCurrentAndNextPeriods();
        $programs = Department::create(['name' => 'Programs']);
        $finance = Department::create(['name' => 'Finance']);

        $viewer = $this->createUserInDepartment($programs);
        $viewer->givePermissionTo(['view all documents', 'create weekly report']);
        $outsideAuthor = $this->createUserInDepartment($finance, ['create weekly report']);

        Document::create([
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $currentPeriod->id,
            'user_id' => $outsideAuthor->id,
            'title' => 'Finance weekly report',
            'state' => Document::STATE_DRAFT,
        ]);

        $response = $this->actingAs($viewer)->get(route('documents.weekly-reports'));

        $response->assertOk();
        $response->assertDontSee('Finance weekly report', false);
    }

    public function test_weekly_plan_assignments_are_projected_as_preview_on_draft_save(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);
        $member = $this->createUserInDepartment($department);

        $this->actingAs($owner)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'main_target' => 'Shared target',
            'shared_user_ids' => [$member->id],
            'content' => [
                'planned_activities' => [
                    [
                        'day' => 'Monday',
                        'activity' => 'Joint activity',
                        'location' => 'Office',
                        'team_member_ids' => [$owner->id, $member->id],
                        'notes_inclusion' => 'Read-only for assignees',
                    ],
                ],
            ],
        ])->assertRedirect();

        $document = Document::query()
            ->where('user_id', $owner->id)
            ->where('type', Document::TYPE_WEEKLY_PLAN)
            ->firstOrFail();

        $this->assertDatabaseHas('weekly_activity_assignments', [
            'source_document_id' => $document->id,
            'assignee_user_id' => $owner->id,
            'status' => WeeklyActivityAssignment::STATUS_PREVIEW,
        ]);
        $this->assertDatabaseHas('weekly_activity_assignments', [
            'source_document_id' => $document->id,
            'assignee_user_id' => $member->id,
            'status' => WeeklyActivityAssignment::STATUS_PREVIEW,
        ]);
    }

    public function test_weekly_plan_assignments_are_locked_after_submit(): void
    {
        [$currentPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);
        $member = $this->createUserInDepartment($department);

        $document = Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $currentPeriod->id,
            'user_id' => $owner->id,
            'title' => 'Draft plan',
            'main_target' => 'Shared target',
            'state' => Document::STATE_DRAFT,
            'content' => [
                'planned_activities' => [
                    [
                        'activity_uid' => (string) \Illuminate\Support\Str::ulid(),
                        'day' => 'Monday',
                        'activity' => 'Joint activity',
                        'location' => 'Office',
                        'team_member_ids' => [$member->id],
                        'notes_inclusion' => '',
                    ],
                ],
            ],
        ]);
        $document->sharedUsers()->sync([$member->id]);
        $activityUid = (string) data_get($document->content, 'planned_activities.0.activity_uid');

        $document->update(['state' => Document::STATE_SUBMITTED]);
        app(WeeklyActivityAssignmentService::class)->syncForWeeklyPlan($document->fresh());

        $this->assertDatabaseHas('weekly_activity_assignments', [
            'source_document_id' => $document->id,
            'assignee_user_id' => $member->id,
            'status' => WeeklyActivityAssignment::STATUS_LOCKED,
        ]);
    }

    public function test_weekly_plan_assignment_rows_are_removed_when_activity_no_longer_assigned(): void
    {
        [$currentPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);
        $member = $this->createUserInDepartment($department);

        $document = Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $currentPeriod->id,
            'user_id' => $owner->id,
            'title' => 'Draft plan',
            'main_target' => 'Shared target',
            'state' => Document::STATE_DRAFT,
            'content' => [
                'planned_activities' => [
                    [
                        'activity_uid' => (string) \Illuminate\Support\Str::ulid(),
                        'day' => 'Monday',
                        'activity' => 'Joint activity',
                        'location' => 'Office',
                        'team_member_ids' => [$member->id],
                        'notes_inclusion' => '',
                    ],
                ],
            ],
        ]);
        $document->sharedUsers()->sync([$member->id]);
        $activityUid = (string) data_get($document->content, 'planned_activities.0.activity_uid');

        app(WeeklyActivityAssignmentService::class)->syncForWeeklyPlan($document->fresh());

        $this->assertDatabaseHas('weekly_activity_assignments', [
            'source_document_id' => $document->id,
            'assignee_user_id' => $member->id,
            'activity_uid' => $activityUid,
        ]);

        $document->update([
            'content' => [
                'planned_activities' => [
                    [
                        'activity_uid' => $activityUid,
                        'day' => 'Monday',
                        'activity' => 'Joint activity',
                        'location' => 'Office',
                        'team_member_ids' => [],
                        'notes_inclusion' => '',
                    ],
                ],
            ],
        ]);
        app(WeeklyActivityAssignmentService::class)->syncForWeeklyPlan($document->fresh());

        $this->assertDatabaseMissing('weekly_activity_assignments', [
            'source_document_id' => $document->id,
            'assignee_user_id' => $member->id,
            'activity_uid' => $activityUid,
        ]);
    }

    public function test_assigned_activities_show_as_read_only_reference_in_assignee_weekly_plan_context(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);
        $member = $this->createUserInDepartment($department, ['create weekly plan']);

        $this->actingAs($owner)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'main_target' => 'Owner target',
            'shared_user_ids' => [$member->id],
            'content' => [
                'planned_activities' => [
                    [
                        'day' => 'Tuesday',
                        'activity' => 'Assigned partner activity',
                        'location' => 'Field',
                        'team_member_ids' => [$member->id],
                        'notes_inclusion' => 'Reference only',
                    ],
                ],
            ],
        ])->assertRedirect();

        $assignedRows = WeeklyActivityAssignment::query()
            ->where('assignee_user_id', $member->id)
            ->where('period_id', $nextPeriod->id)
            ->with(['sourceUser:id,name'])
            ->get();

        $this->actingAs($member)
            ->view('documents.partials.weekly-assigned-activities-card', [
                'assignedWeeklyActivities' => $assignedRows,
                'errors' => new \Illuminate\Support\ViewErrorBag,
            ])
            ->assertSeeText('Assigned by Others')
            ->assertSeeText('Tasks from shared weekly plans where another staff member assigned you')
            ->assertSeeText('Assigned partner activity');
    }

    public function test_assignments_for_user_excludes_self_assigned_rows(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);
        $member = $this->createUserInDepartment($department, ['create weekly plan']);

        $ownerPlan = Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'user_id' => $owner->id,
            'title' => 'Owner plan',
            'main_target' => 'Owner target',
            'state' => Document::STATE_DRAFT,
            'content' => [],
        ]);

        $memberPlan = Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'user_id' => $member->id,
            'title' => 'Member plan',
            'main_target' => 'Member target',
            'state' => Document::STATE_DRAFT,
            'content' => [],
        ]);

        WeeklyActivityAssignment::create([
            'source_document_id' => $ownerPlan->id,
            'source_user_id' => $owner->id,
            'assignee_user_id' => $member->id,
            'period_id' => $nextPeriod->id,
            'activity_uid' => (string) \Illuminate\Support\Str::ulid(),
            'status' => WeeklyActivityAssignment::STATUS_PREVIEW,
            'activity_payload' => [
                'day' => 'Monday',
                'activity' => 'From owner plan',
                'location' => 'Office',
            ],
        ]);

        WeeklyActivityAssignment::create([
            'source_document_id' => $memberPlan->id,
            'source_user_id' => $member->id,
            'assignee_user_id' => $member->id,
            'period_id' => $nextPeriod->id,
            'activity_uid' => (string) \Illuminate\Support\Str::ulid(),
            'status' => WeeklyActivityAssignment::STATUS_PREVIEW,
            'activity_payload' => [
                'day' => 'Tuesday',
                'activity' => 'Self assigned',
                'location' => 'Field',
            ],
        ]);

        $rows = app(WeeklyActivityAssignmentService::class)
            ->assignmentsForUser((string) $member->id, (string) $nextPeriod->id);

        $this->assertCount(1, $rows);
        $this->assertSame((string) $owner->id, (string) $rows->first()->source_user_id);
        $this->assertSame('From owner plan', data_get($rows->first()->activity_payload, 'activity'));
    }

    public function test_assigned_user_gets_projection_even_when_not_shared_collaborator(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);
        $member = $this->createUserInDepartment($department, ['create weekly plan']);
        $outsider = User::factory()->create();

        $this->actingAs($owner)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'main_target' => 'Owner target',
            'shared_user_ids' => [$member->id],
            'content' => [
                'planned_activities' => [
                    [
                        'day' => 'Wednesday',
                        'activity' => 'Department assignment only',
                        'location' => 'Office',
                        'team_member_ids' => [$member->id, $outsider->id],
                        'notes_inclusion' => '',
                    ],
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('weekly_activity_assignments', [
            'assignee_user_id' => $member->id,
        ]);
        $this->assertDatabaseHas('weekly_activity_assignments', [
            'assignee_user_id' => $outsider->id,
        ]);
    }

    public function test_weekly_report_edit_prefills_target_accomplished_dropdown_for_zero_value(): void
    {
        [$currentPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly report']);

        $report = Document::create([
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $currentPeriod->id,
            'user_id' => $owner->id,
            'title' => 'Draft report',
            'state' => Document::STATE_DRAFT,
            'target_accomplished' => 0,
            'target_explanation' => 'Pending dependencies',
        ]);

        $response = $this->actingAs($owner)->view('documents.partials.edit.weekly-report-fields', [
            'document' => $report->load('sharedUsers'),
            'shareableUsers' => collect(),
            'errors' => new \Illuminate\Support\ViewErrorBag,
        ]);

        $response->assertSee('option value="0" selected', false);
        $response->assertSeeText('Pending dependencies');
    }

    private function createCurrentAndNextPeriods(): array
    {
        $current = WorkPeriod::create([
            'year' => 2025,
            'week_number' => 14,
            'week_start_date' => '2025-03-31',
            'week_end_date' => '2025-04-06',
            'plan_deadline' => '2025-04-04',
            'report_deadline' => '2025-04-05',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $next = WorkPeriod::create([
            'year' => 2025,
            'week_number' => 15,
            'week_start_date' => '2025-04-07',
            'week_end_date' => '2025-04-13',
            'plan_deadline' => '2025-04-11',
            'report_deadline' => '2025-04-12',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        return [$current, $next];
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function createUserInDepartment(Department $department, array $permissions = []): User
    {
        $user = User::factory()->create([
            'department_id' => $department->id,
        ]);

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
