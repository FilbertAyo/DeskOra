<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
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
