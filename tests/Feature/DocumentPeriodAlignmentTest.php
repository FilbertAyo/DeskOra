<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentPeriodAlignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow(Carbon::create(2025, 4, 2, 10, 0, 0)); // Week 14
    }

    public function test_weekly_plan_cannot_be_submitted_for_current_week(): void
    {
        [$currentPeriod] = $this->createCurrentAndNextPeriods();
        $user = $this->createPlannerUser();

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $currentPeriod->id,
            'main_target' => 'Plan target',
        ]);

        $response->assertSessionHas('error', 'Weekly plan must be submitted for next week.');
        $this->assertDatabaseMissing('documents', [
            'user_id' => $user->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
        ]);
    }

    public function test_weekly_report_cannot_be_submitted_for_next_week(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();
        $user = $this->createReporterUser();

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $nextPeriod->id,
        ]);

        $response->assertSessionHas('error', 'Weekly report must be submitted for the current week.');
        $this->assertDatabaseMissing('documents', [
            'user_id' => $user->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
        ]);
    }

    public function test_weekly_plan_targets_next_week_when_valid_period_selected(): void
    {
        [, $nextPeriod] = $this->createCurrentAndNextPeriods();
        $user = $this->createPlannerUser();

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
            'main_target' => 'Deliver plan for week 15',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('documents', [
            'user_id' => $user->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $nextPeriod->id,
        ]);
    }

    public function test_weekly_report_targets_current_week_when_valid_period_selected(): void
    {
        [$currentPeriod] = $this->createCurrentAndNextPeriods();
        $user = $this->createReporterUser();

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $currentPeriod->id,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('documents', [
            'user_id' => $user->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $currentPeriod->id,
        ]);
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

    private function createPlannerUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create weekly plan');

        return $user;
    }

    private function createReporterUser(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('create weekly report');

        return $user;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
