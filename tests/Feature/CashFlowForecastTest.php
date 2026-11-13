<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Deposit;
use App\Models\PettyRequest;
use App\Models\Request as FinanceRequest;
use App\Models\User;
use App\Services\CashFlowForecastService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CashFlowForecastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_forecast_subtracts_authorized_unpaid_petty(): void
    {
        $department = Department::create(['name' => 'Finance', 'status' => 'active']);
        $user = User::factory()->create([
            'status' => 'active',
            'department_id' => $department->id,
        ]);
        $user->givePermissionTo(['view cashflow movements', 'approve pettycash payments']);

        Deposit::create([
            'department_id' => $department->id,
            'user_id' => $user->id,
            'deposit' => 100000,
            'remaining' => 100000,
        ]);

        $request = FinanceRequest::create([
            'request_no' => 'RQ111',
            'request_type' => 'Petty Cash',
            'user_id' => $user->id,
            'department_id' => $department->id,
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);
        PettyRequest::create([
            'request_id' => $request->id,
            'request_for' => 'Fuel',
            'amount' => 25000,
            'reason' => 'Travel',
        ]);

        $forecast = app(CashFlowForecastService::class)->forUser($user);

        $this->assertSame(100000.0, $forecast['available']);
        $this->assertSame(25000.0, $forecast['committed']);
        $this->assertSame(75000.0, $forecast['projected']);
        $this->assertSame(1, $forecast['awaiting_payment_count']);
        $this->assertFalse($forecast['shortage']);
    }

    public function test_cash_management_page_shows_forecast_strip(): void
    {
        $department = Department::create(['name' => 'Ops', 'status' => 'active']);
        $user = User::factory()->create([
            'status' => 'active',
            'department_id' => $department->id,
        ]);
        $user->givePermissionTo('view cashflow movements');

        Deposit::create([
            'department_id' => $department->id,
            'user_id' => $user->id,
            'deposit' => 50000,
            'remaining' => 50000,
        ]);

        $this->actingAs($user)
            ->get(route('deposit.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Finance/Deposits')
                ->where('cashFlowForecast.available', 50000)
                ->where('cashFlowForecast.committed', 0));
    }
}
