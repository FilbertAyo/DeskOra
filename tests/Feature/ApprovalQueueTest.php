<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PettyRequest;
use App\Models\Request as FinanceRequest;
use App\Models\RequestApproval;
use App\Models\User;
use App\Services\ApprovalQueueService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ApprovalQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::flush();
    }

    private function createPendingPettyForReview(): FinanceRequest
    {
        $department = Department::create(['name' => 'Ops', 'status' => 'active']);
        $requester = User::factory()->create([
            'department_id' => $department->id,
            'status' => 'active',
        ]);

        $financeRequest = FinanceRequest::create([
            'request_no' => 'RQ'.random_int(100, 999),
            'request_type' => 'Petty Cash',
            'user_id' => $requester->id,
            'department_id' => $department->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        PettyRequest::create([
            'request_id' => $financeRequest->id,
            'request_for' => 'Office Supplies',
            'amount' => 25000,
            'reason' => 'Supplies',
        ]);

        RequestApproval::create([
            'request_id' => $financeRequest->id,
            'step' => 'system',
            'action' => 'created',
            'actor_id' => $requester->id,
            'acted_at' => now(),
        ]);

        return $financeRequest->fresh(['pettyDetails', 'user']);
    }

    public function test_guest_cannot_open_approval_queue(): void
    {
        $this->get(route('approvals.queue'))->assertRedirect();
    }

    public function test_user_without_approval_permissions_is_forbidden(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->get(route('approvals.queue'))
            ->assertForbidden();
    }

    public function test_finance_reviewer_sees_queue_and_pending_item(): void
    {
        $this->createPendingPettyForReview();
        $reviewer = User::factory()->create(['status' => 'active']);
        $reviewer->givePermissionTo(['review pettycash', 'view requested pettycash']);

        $response = $this->actingAs($reviewer)->get(route('approvals.queue'));

        $response->assertOk();
        $response->assertSee('My Approval Queue');
        $response->assertSee('Office Supplies');
        $response->assertSee('Finance');
    }

    public function test_budget_viewer_can_open_queue(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('view weekly budgets');

        $this->actingAs($user)
            ->get(route('approvals.queue', ['filter' => 'budget']))
            ->assertOk()
            ->assertSee('My Approval Queue');
    }

    public function test_counts_endpoint_returns_summary_for_authorized_user(): void
    {
        $this->createPendingPettyForReview();
        $reviewer = User::factory()->create(['status' => 'active']);
        $reviewer->givePermissionTo(['review pettycash', 'view requested pettycash']);

        $response = $this->actingAs($reviewer)->getJson(route('approvals.queue.counts'));

        $response->assertOk();
        $response->assertJsonStructure(['finance', 'budget', 'total']);
        $this->assertGreaterThanOrEqual(1, (int) $response->json('finance'));
        $this->assertSame(
            (int) $response->json('finance') + (int) $response->json('budget'),
            (int) $response->json('total')
        );
    }

    public function test_counts_endpoint_returns_zeros_without_permission(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->getJson(route('approvals.queue.counts'))
            ->assertOk()
            ->assertJson([
                'finance' => 0,
                'budget' => 0,
                'total' => 0,
            ]);
    }

    public function test_summary_service_includes_finance_items(): void
    {
        $this->createPendingPettyForReview();
        $reviewer = User::factory()->create(['status' => 'active']);
        $reviewer->givePermissionTo(['review pettycash', 'view requested pettycash']);

        $service = app(ApprovalQueueService::class);
        $summary = $service->summaryCounts($reviewer);
        $items = $service->itemsFor($reviewer, 'finance');

        $this->assertGreaterThanOrEqual(1, $summary['finance']);
        $this->assertNotEmpty($items);
        $this->assertSame('finance', $items->first()['kind']);
        $this->assertSame('Office Supplies', $items->first()['title']);
    }
}
