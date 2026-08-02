<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\Request as FinanceRequest;
use App\Models\User;
use App\Models\WorkPeriod;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactorySmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_core_factories_create_records(): void
    {
        $department = Department::factory()->create();
        $period = WorkPeriod::factory()->create();
        $user = User::factory()->create(['status' => 'active', 'department_id' => $department->id]);
        $user->givePermissionTo(['create weekly plan']);

        $document = Document::factory()->weeklyPlan()->create([
            'user_id' => $user->id,
            'period_id' => $period->id,
        ]);

        $request = FinanceRequest::factory()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
        ]);

        $this->assertDatabaseHas('departments', ['id' => $department->id]);
        $this->assertDatabaseHas('work_periods', ['id' => $period->id]);
        $this->assertNotNull($document->id);
        $this->assertSame(Document::TYPE_WEEKLY_PLAN, $document->type);
        $this->assertDatabaseHas('requests', ['id' => $request->id]);
    }
}
