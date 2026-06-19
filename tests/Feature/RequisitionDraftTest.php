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

    public function test_staff_can_submit_saved_draft_and_notify_reviewers(): void
    {
        Notification::fake();

        $department = Department::create(['name' => 'Operations', 'status' => 'active']);
        $requester = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
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
