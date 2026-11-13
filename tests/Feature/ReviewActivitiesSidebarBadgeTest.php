<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewActivitiesSidebarBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow(Carbon::create(2026, 5, 6, 10, 0, 0)); // Week 19
    }

    public function test_submissions_badge_counts_current_reports_and_next_plans_only(): void
    {
        [$previous, $current, $next] = $this->createPeriods();
        $reviewer = $this->createReviewer();
        $author = User::factory()->create(['status' => 'active']);

        Document::create([
            'user_id' => $author->id,
            'period_id' => $current->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Current week report',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Report target',
            'content' => ['summary' => 'Done'],
        ]);

        Document::create([
            'user_id' => $author->id,
            'period_id' => $next->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Next week plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Plan target',
            'content' => ['planned_activities' => []],
        ]);

        // These should not contribute to the badge count.
        Document::create([
            'user_id' => $author->id,
            'period_id' => $previous->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Previous week report',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Old report',
            'content' => ['summary' => 'Old'],
        ]);
        Document::create([
            'user_id' => $author->id,
            'period_id' => $current->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Current week plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Current plan',
            'content' => ['planned_activities' => []],
        ]);
        Document::create([
            'user_id' => $author->id,
            'period_id' => $next->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Next week report',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Future report',
            'content' => ['summary' => 'Future'],
        ]);

        $response = $this->actingAs($reviewer)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Dashboard/Index'));
        $this->assertSame('2', $this->navBadge($response->inertiaPage()['props']['nav'], 'Submissions'));
    }

    public function test_weekly_budget_badge_keeps_carry_over_pending_items(): void
    {
        [$previous, $current, $next] = $this->createPeriods();
        /** @var User $reviewer */
        $reviewer = User::factory()->create(['status' => 'active']);
        $reviewer->givePermissionTo('view weekly budgets');
        /** @var User $author */
        $author = User::factory()->create(['status' => 'active']);

        $this->createPendingBudgetPlan($author, $previous, 'Old carry-over budget');
        $this->createPendingBudgetPlan($author, $next, 'Next week budget');

        // Non-budget weekly report should not affect budget count.
        Document::create([
            'user_id' => $author->id,
            'period_id' => $current->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Current report',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Report target',
            'content' => ['summary' => 'Done'],
        ]);

        $response = $this->actingAs($reviewer)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Dashboard/Index'));
        $this->assertSame('2', $this->navBadge($response->inertiaPage()['props']['nav'], 'Weekly Budget'));
    }

    /**
     * @param  array<string, mixed>  $nav
     */
    private function navBadge(array $nav, string $label): ?string
    {
        $found = null;

        $visit = function (array $items) use (&$visit, &$found, $label): void {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                if (($item['label'] ?? null) === $label) {
                    $found = $item['badge'] ?? null;
                }

                if (is_array($item['children'] ?? null)) {
                    $visit($item['children']);
                }
            }
        };

        foreach ($nav['groups'] ?? [] as $group) {
            if (is_array($group) && is_array($group['items'] ?? null)) {
                $visit($group['items']);
            }
        }

        return is_string($found) ? $found : null;
    }

    private function createPeriods(): array
    {
        $previous = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 18,
            'week_start_date' => '2026-04-27',
            'week_end_date' => '2026-05-03',
            'plan_deadline' => '2026-05-01',
            'report_deadline' => '2026-05-02',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $current = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 19,
            'week_start_date' => '2026-05-04',
            'week_end_date' => '2026-05-10',
            'plan_deadline' => '2026-05-08',
            'report_deadline' => '2026-05-09',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $next = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 20,
            'week_start_date' => '2026-05-11',
            'week_end_date' => '2026-05-17',
            'plan_deadline' => '2026-05-15',
            'report_deadline' => '2026-05-16',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        return [$previous, $current, $next];
    }

    private function createReviewer(): User
    {
        $reviewer = User::factory()->create(['status' => 'active']);
        $reviewer->givePermissionTo('approve documents');

        return $reviewer;
    }

    private function createPendingBudgetPlan(User $author, WorkPeriod $period, string $itemLabel): void
    {
        Document::create([
            'user_id' => $author->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Plan for week '.$period->week_number,
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Budget target',
            'content' => [
                'objective' => ['description' => 'Objective'],
                'planned_activities' => [],
                'key_meetings' => [],
                'budget' => [
                    [
                        'budget_uid' => (string) \Illuminate\Support\Str::ulid(),
                        'item' => $itemLabel,
                        'description_purpose' => 'Needed',
                        'amount' => '1000',
                    ],
                ],
            ],
            'budget_review_status' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
