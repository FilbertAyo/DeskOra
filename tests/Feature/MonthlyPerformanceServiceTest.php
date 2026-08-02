<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\MonthlyPerformanceService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyPerformanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow(Carbon::parse('2026-08-15 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_plans_rate_is_fifty_when_two_of_four_weeks_submitted(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo(['create weekly plan', 'create weekly report']);

        $weekStarts = [
            '2026-08-03',
            '2026-08-10',
            '2026-08-17',
            '2026-08-24',
        ];

        foreach ($weekStarts as $index => $start) {
            $period = WorkPeriod::factory()->create([
                'year' => 2026,
                'week_number' => 32 + $index,
                'week_start_date' => $start,
                'week_end_date' => Carbon::parse($start)->addDays(6)->toDateString(),
                'status' => WorkPeriod::STATUS_OPEN,
            ]);

            if ($index < 2) {
                Document::create([
                    'user_id' => $user->id,
                    'period_id' => $period->id,
                    'type' => Document::TYPE_WEEKLY_PLAN,
                    'title' => 'Plan '.$index,
                    'state' => Document::STATE_SUBMITTED,
                    'content' => ['main_target' => 'Target', 'planned_activities' => []],
                ]);
            }
        }

        $result = app(MonthlyPerformanceService::class)->forUser($user, 1);

        $plansSeries = collect($result['series'])->firstWhere('key', 'plans');
        $this->assertNotNull($plansSeries);
        $this->assertSame(50, $plansSeries['data'][0]);

        $reportsSeries = collect($result['series'])->firstWhere('key', 'reports');
        $this->assertNotNull($reportsSeries);
        $this->assertSame(0, $reportsSeries['data'][0]);

        $this->assertNotEmpty($result['tips']);
        $this->assertContains('Aug', $result['categories']);
    }

    public function test_performance_chart_partial_renders_for_user_payload(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo(['create weekly plan', 'create weekly report']);

        $monthlyPerformance = app(MonthlyPerformanceService::class)->forUser($user, 1);

        $html = view('dashboard.partials.performance-chart', [
            'monthlyPerformance' => $monthlyPerformance,
        ])->render();

        $this->assertStringContainsString('My monthly performance', $html);
        $this->assertStringContainsString('staffPerformanceChart', $html);
        $this->assertStringContainsString('Coach tips', $html);
    }
}
