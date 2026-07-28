<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Request as FinanceRequest;
use App\Models\RequisitionRequest;
use App\Models\User;
use App\Notifications\RequisitionWorkflowNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RequisitionDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Cache::flush();
    }

    public function test_staff_can_save_requisition_as_draft_without_full_details(): void
    {
        Notification::fake();

        $department = Department::create(['name' => 'Operations', 'status' => 'active']);
        $requester = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'signature' => 'signatures/test.png',
        ]);
        $requester->givePermissionTo('request pettycash');

        $response = $this->actingAs($requester)->post(route('requisition.store'), [
            'user_id' => $requester->id,
            'department_id' => $department->id,
            'request_type' => 'Staff Requisition',
            'action' => 'draft',
            'project_program' => 'Partial project name',
            'items' => [
                ['description' => '', 'unit' => '', 'cost' => ''],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $draft = FinanceRequest::query()->where('user_id', $requester->id)->first();
        $this->assertNotNull($draft);
        $this->assertSame('draft', $draft->status);
        $this->assertNull($draft->submitted_at);
        $this->assertSame('Partial project name', $draft->fresh('requisitionDetails')->requisitionDetails?->project_program);

        Notification::assertNothingSent();
    }

    public function test_staff_without_signature_cannot_create_requisition(): void
    {
        Notification::fake();

        $department = Department::create(['name' => 'Operations', 'status' => 'active']);
        $requester = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'signature' => null,
        ]);
        $requester->givePermissionTo('request pettycash');

        $this->actingAs($requester)
            ->get(route('requisition.create'))
            ->assertOk()
            ->assertSee('Signature required')
            ->assertDontSee('Submit Requisition')
            ->assertDontSee('Save as Draft');

        $response = $this->actingAs($requester)->from(route('requisition.create'))->post(route('requisition.store'), [
            'user_id' => $requester->id,
            'department_id' => $department->id,
            'request_type' => 'Staff Requisition',
            'action' => 'submit',
            'project_program' => 'Project Alpha',
            'activity_code' => 'ACT-01',
            'activity_title' => 'Training Materials',
            'budget_code' => '2026',
            'items' => [
                ['description' => 'Books', 'unit' => 'pcs', 'cost' => '50000'],
            ],
        ]);

        $response->assertRedirect(route('requisition.create'));
        $response->assertSessionHas('error');
        $this->assertSame(0, FinanceRequest::query()->count());
        Notification::assertNothingSent();
    }

    public function test_approver_without_signature_cannot_review_requisition(): void
    {
        $department = Department::create(['name' => 'Operations', 'status' => 'active']);
        $requester = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'signature' => 'signatures/requester.png',
        ]);
        $reviewer = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'signature' => null,
        ]);
        $reviewer->givePermissionTo(['review staff requisition', 'view requested pettycash']);

        $requisition = FinanceRequest::create([
            'request_no' => 'SR100',
            'request_type' => 'Staff Requisition',
            'user_id' => $requester->id,
            'department_id' => $department->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        RequisitionRequest::create([
            'request_id' => $requisition->id,
            'project_program' => 'Project Alpha',
            'activity_code' => 'ACT-01',
            'activity_title' => 'Training Materials',
            'budget_code' => '2026',
            'amount' => 1000,
        ]);

        $this->actingAs($reviewer)
            ->from(route('petty.details', $requisition->id))
            ->post(route('f_approve.approve', $requisition->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('pending', $requisition->fresh()->status);
        $this->assertNull($requisition->fresh()->reviewed_at);
    }

    public function test_staff_can_submit_requisition_and_notify_reviewers(): void
    {
        Notification::fake();

        $department = Department::create(['name' => 'Operations', 'status' => 'active']);
        $requester = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'signature' => 'signatures/test.png',
        ]);
        $requester->givePermissionTo('request pettycash');
        $reviewer = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'notification_channel' => 'email',
        ]);
        $reviewer->givePermissionTo('review staff requisition');

        $response = $this->actingAs($requester)->post(route('requisition.store'), [
            'user_id' => $requester->id,
            'department_id' => $department->id,
            'request_type' => 'Staff Requisition',
            'action' => 'submit',
            'project_program' => 'Project Alpha',
            'activity_code' => 'ACT-01',
            'activity_title' => 'Training Materials',
            'budget_code' => '2026',
            'items' => [
                ['description' => 'Books', 'unit' => 'pcs', 'cost' => '50000'],
            ],
        ]);

        $response->assertRedirect(route('requisition.index'));

        $requisition = FinanceRequest::query()->where('user_id', $requester->id)->first();
        $this->assertNotNull($requisition);
        $this->assertSame('pending', $requisition->status);
        $this->assertNotNull($requisition->submitted_at);

        Notification::assertSentTo($reviewer, RequisitionWorkflowNotification::class);
    }

    public function test_store_requires_explicit_action_and_does_not_default_to_submit(): void
    {
        Notification::fake();

        $department = Department::create(['name' => 'Operations', 'status' => 'active']);
        $requester = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'signature' => 'signatures/test.png',
        ]);
        $requester->givePermissionTo('request pettycash');

        $response = $this->actingAs($requester)->from(route('requisition.create'))->post(route('requisition.store'), [
            'user_id' => $requester->id,
            'department_id' => $department->id,
            'request_type' => 'Staff Requisition',
            'project_program' => 'Project Alpha',
            'activity_code' => 'ACT-01',
            'activity_title' => 'Training Materials',
            'budget_code' => '2026',
            'items' => [
                ['description' => 'Books', 'unit' => 'pcs', 'cost' => '50000'],
            ],
        ]);

        $response->assertRedirect(route('requisition.create'));
        $response->assertSessionHasErrors('action');
        $this->assertSame(0, FinanceRequest::query()->count());
        Notification::assertNothingSent();
    }

    public function test_staff_can_submit_saved_draft_and_notify_reviewers(): void
    {
        Notification::fake();

        $department = Department::create(['name' => 'Operations', 'status' => 'active']);
        $requester = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'signature' => 'signatures/test.png',
        ]);
        $requester->givePermissionTo('request pettycash');
        $reviewer = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'notification_channel' => 'email',
        ]);
        $reviewer->givePermissionTo('review staff requisition');

        $draft = FinanceRequest::create([
            'request_no' => 'DR001',
            'request_type' => 'Staff Requisition',
            'user_id' => $requester->id,
            'department_id' => $department->id,
            'status' => 'draft',
            'submitted_at' => null,
        ]);

        RequisitionRequest::create([
            'request_id' => $draft->id,
            'project_program' => 'Project Alpha',
            'activity_code' => 'ACT-01',
            'activity_title' => 'Training Materials',
            'budget_code' => '2026',
            'amount' => 0,
        ]);

        $response = $this->actingAs($requester)->put(route('requisition.update', $draft->id), [
            'action' => 'submit',
            'project_program' => 'Project Alpha',
            'activity_code' => 'ACT-01',
            'activity_title' => 'Training Materials',
            'budget_code' => '2026',
            'items' => [
                ['description' => 'Books', 'unit' => 'pcs', 'cost' => '50000'],
            ],
        ]);

        $response->assertRedirect(route('requisition.index'));
        $this->assertSame('pending', $draft->fresh()->status);
        $this->assertNotNull($draft->fresh()->submitted_at);

        Notification::assertSentTo($reviewer, RequisitionWorkflowNotification::class);
    }

    public function test_submitted_requisition_cannot_be_edited_or_deleted(): void
    {
        $department = Department::create(['name' => 'Operations', 'status' => 'active']);
        $requester = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'signature' => 'signatures/test.png',
        ]);
        $requester->givePermissionTo('request pettycash');

        $submitted = FinanceRequest::create([
            'request_no' => 'SR001',
            'request_type' => 'Staff Requisition',
            'user_id' => $requester->id,
            'department_id' => $department->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->actingAs($requester)
            ->get(route('requisition.edit', $submitted->id))
            ->assertRedirect(route('requisition.show', $submitted->id));

        $this->actingAs($requester)
            ->put(route('requisition.update', $submitted->id), [
                'action' => 'submit',
                'project_program' => 'Changed',
                'activity_code' => 'ACT-01',
                'activity_title' => 'Title',
                'budget_code' => '2026',
                'items' => [
                    ['description' => 'Item', 'unit' => 'pcs', 'cost' => '100'],
                ],
            ])
            ->assertRedirect(route('requisition.show', $submitted->id));

        $this->actingAs($requester)
            ->delete(route('requisition.destroy', $submitted->id))
            ->assertRedirect();

        $this->assertDatabaseHas('requests', [
            'id' => $submitted->id,
            'status' => 'pending',
        ]);
    }

    public function test_draft_requisition_can_be_deleted(): void
    {
        $department = Department::create(['name' => 'Operations', 'status' => 'active']);
        $requester = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'signature' => 'signatures/test.png',
        ]);
        $requester->givePermissionTo('request pettycash');

        $draft = FinanceRequest::create([
            'request_no' => 'DR003',
            'request_type' => 'Staff Requisition',
            'user_id' => $requester->id,
            'department_id' => $department->id,
            'status' => 'draft',
            'submitted_at' => null,
        ]);

        $this->actingAs($requester)
            ->delete(route('requisition.destroy', $draft->id))
            ->assertRedirect(route('requisition.index'));

        $this->assertDatabaseMissing('requests', ['id' => $draft->id]);
    }

    public function test_drafts_do_not_appear_in_review_queue(): void
    {
        $department = Department::create(['name' => 'Operations', 'status' => 'active']);
        $requester = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'signature' => 'signatures/test.png',
        ]);
        $reviewer = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
            'signature' => 'signatures/test.png',
        ]);
        $reviewer->givePermissionTo(['review staff requisition', 'view requested pettycash']);

        FinanceRequest::create([
            'request_no' => 'DR002',
            'request_type' => 'Staff Requisition',
            'user_id' => $requester->id,
            'department_id' => $department->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($reviewer)->get(route('petty.list', [
            'stage' => 'review',
            'type' => 'requisition',
        ]));

        $response->assertOk();
        $response->assertDontSee('DR002');
    }
}
