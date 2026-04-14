<?php

namespace Tests\Feature;

use App\Models\WorkPeriod;
use App\Services\WorkPeriodService;
use Carbon\Carbon;
use Database\Seeders\WorkPeriodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkPeriodServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_week_one_for_new_year(): void
    {
        Carbon::setTestNow(Carbon::create(2024, 12, 23, 10, 0, 0));
        $service = app(WorkPeriodService::class);

        WorkPeriod::create([
            'year' => 2024,
            'week_number' => 52,
            'week_start_date' => '2024-12-23',
            'week_end_date' => '2024-12-29',
            'plan_deadline' => '2024-12-27',
            'report_deadline' => '2024-12-28',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $newPeriod = $service->createNextWeek();

        $this->assertNotNull($newPeriod);
        $this->assertEquals(2025, $newPeriod->year);
        $this->assertEquals(1, $newPeriod->week_number);
    }

    public function test_automation_closes_ended_weeks_and_creates_next(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 6, 9, 0, 0)); // Monday
        $service = app(WorkPeriodService::class);

        $endedWeek = WorkPeriod::create([
            'year' => 2025,
            'week_number' => 1,
            'week_start_date' => '2024-12-30',
            'week_end_date' => '2025-01-05',
            'plan_deadline' => '2025-01-03',
            'report_deadline' => '2025-01-04',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        WorkPeriod::create([
            'year' => 2025,
            'week_number' => 2,
            'week_start_date' => '2025-01-06',
            'week_end_date' => '2025-01-12',
            'plan_deadline' => '2025-01-10',
            'report_deadline' => '2025-01-11',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $results = $service->processWeeklyAutomation();

        $this->assertTrue($results['closed']);
        $this->assertTrue($results['created']);

        $endedWeek->refresh();
        $this->assertEquals(WorkPeriod::STATUS_CLOSED, $endedWeek->status);

        $this->assertNotNull($results['created_period']);
        $expectedNextWeek = Carbon::create(2025, 1, 13); // Week 3 start

        $this->assertEquals($expectedNextWeek->isoWeek(), $results['created_period']->week_number);
        $this->assertEquals($expectedNextWeek->isoWeekYear(), $results['created_period']->year);
        $this->assertEquals(WorkPeriod::STATUS_OPEN, $results['created_period']->status);
    }

    public function test_period_submission_helpers_match_business_rule(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 4, 2, 12, 0, 0)); // Week 14
        $service = app(WorkPeriodService::class);

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

        $this->assertSame($current->id, $service->periodForWeeklyReportSubmission()?->id);
        $this->assertSame($next->id, $service->periodForWeeklyPlanSubmission()?->id);
    }

    public function test_work_period_seeder_creates_current_and_next_weeks(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 4, 2, 8, 0, 0)); // Week 14

        $this->seed(WorkPeriodSeeder::class);

        $currentWeekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $nextWeekStart = $currentWeekStart->copy()->addWeek();

        $this->assertDatabaseHas('work_periods', [
            'year' => $currentWeekStart->isoWeekYear(),
            'week_number' => $currentWeekStart->isoWeek(),
        ]);

        $this->assertDatabaseHas('work_periods', [
            'year' => $nextWeekStart->isoWeekYear(),
            'week_number' => $nextWeekStart->isoWeek(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
