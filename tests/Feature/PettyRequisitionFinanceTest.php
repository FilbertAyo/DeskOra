<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Deposit;
use App\Models\PettyRequest;
use App\Models\Request as FinanceRequest;
use App\Models\RequestApproval;
use App\Models\RequisitionRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PettyRequisitionFinanceTest extends TestCase
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

    private function userWithPermissions(array $permissions, ?Department $department = null): User
    {
        $user = User::factory()->create([
            'department_id' => $department?->id,
            'status' => 'active',
            'signature' => 'signatures/test.png',
        ]);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function createPettyRequest(
        Department $department,
        User $requester,
        string $status = 'approved',
        float $amount = 50000,
        bool $fullyAuthorized = true
    ): FinanceRequest {
        $financeRequest = FinanceRequest::create([
            'request_no' => 'PC'.random_int(100, 999),
            'request_type' => 'Petty Cash',
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

        return $financeRequest->fresh(['pettyDetails', 'approvals']);
    }

    private function createStaffRequisition(
        Department $department,
        User $requester,
        string $status = 'approved',
        float $amount = 120000,
        bool $fullyAuthorized = true
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

        return $financeRequest->fresh(['requisitionDetails', 'approvals']);
    }

    public function test_payments_queue_excludes_staff_requisitions(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $cashier = $this->userWithPermissions(['view cashflow movements'], $department);

        $this->createPettyRequest($department, $requester, 'approved', 50000);
        $this->createStaffRequisition($department, $requester, 'approved', 120000);

        $response = $this->actingAs($cashier)->get(route('petty.cashier'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Payments')
            ->has('requests', 1)
            ->where('requests.0.amount', 50000)
            ->where('requests.0.requestType', 'Petty Cash'));
    }

    public function test_c_approve_rejects_staff_requisition(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $payer = $this->userWithPermissions(['approve pettycash payments'], $department);

        Deposit::create([
            'deposit' => 500000,
            'user_id' => $payer->id,
            'department_id' => $department->id,
            'remaining' => 500000,
        ]);

        $requisition = $this->createStaffRequisition($department, $requester);

        $response = $this->actingAs($payer)->post(route('c_approve.approve', $requisition->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame('approved', $requisition->fresh()->status);
    }

    public function test_requisition_records_lists_only_authorized_requisitions(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $viewer = $this->userWithPermissions(['authorize staff requisition'], $department);

        $authorized = $this->createStaffRequisition($department, $requester, 'approved', 120000, true);
        $this->createStaffRequisition($department, $requester, 'processing', 80000, false);

        $response = $this->actingAs($viewer)->get(route('requisition.records'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Finance/RequisitionRecords')
            ->has('transactions', 1)
            ->where('transactions.0.amount', 120000)
            ->where('transactions.0.activity', 'Training Materials')
            ->where('transactions.0.reference', (string) $authorized->code));
    }

    public function test_review_pettycash_permission_does_not_allow_staff_requisition_review(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $reviewer = $this->userWithPermissions(['review pettycash'], $department);

        $requisition = $this->createStaffRequisition($department, $requester, 'pending', 90000, false);

        $this->assertFalse($requisition->userCanActAtStage($reviewer, 'review'));

        $response = $this->actingAs($reviewer)->post(route('f_approve.approve', $requisition->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_approved_petties_count_excludes_staff_requisitions(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $cashier = $this->userWithPermissions(['view cashflow movements'], $department);

        $this->createPettyRequest($department, $requester, 'approved', 50000);
        $this->createStaffRequisition($department, $requester, 'approved', 120000, true);

        $count = FinanceRequest::query()
            ->whereIn('department_id', $cashier->departmentScopeIds())
            ->whereIn('request_type', ['Petty Cash', 'Reimbursement'])
            ->where('status', 'approved')
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_type_filter_limits_approval_queue_to_requisitions(): void
    {
        $department = $this->createDepartment();
        $requester = $this->createRequester($department);
        $reviewer = $this->userWithPermissions(['review staff requisition', 'view requested pettycash'], $department);

        $this->createPettyRequest($department, $requester, 'pending', 50000, false);
        $requisition = $this->createStaffRequisition($department, $requester, 'pending', 90000, false);

        $response = $this->actingAs($reviewer)->get(route('petty.list', [
            'stage' => 'review',
            'type' => 'requisition',
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Approvals/Index')
            ->where('title', 'Review Requisitions')
            ->has('requests', 1)
            ->where('requests.0.amount', 90000)
            ->where('requests.0.activityTitle', 'Training Materials'));
    }
}
