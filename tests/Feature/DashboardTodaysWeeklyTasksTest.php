<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\WeeklyActivityAssignmentService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTodaysWeeklyTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_todays_tasks_include_own_monday_activities_from_current_period_plan(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 4, 7, 10, 0, 0));
        [, $currentPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Programs']);
        $user = $this->createUserInDepartment($department, ['create weekly plan']);

        Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $currentPeriod->id,
            'user_id' => $user->id,
            'title' => 'My plan',
            'main_target' => 'Weekly target',
            'state' => Document::STATE_DRAFT,
            'content' => [
                'planned_activities' => [
                    [
                        'activity_uid' => (string) \Illuminate\Support\Str::ulid(),
                        'day' => 'Monday',
                        'activity' => 'Team sync',
                        'location' => 'Office',
                        'team_member_ids' => [],
                        'notes_inclusion' => '',
                    ],
                ],
            ],
        ]);

        $tasks = app(WeeklyActivityAssignmentService::class)->todaysTasksForUser(
            (string) $user->id,
            (string) $currentPeriod->id,
            'Monday',
        );

        $this->assertCount(1, $tasks);
        $this->assertSame('Team sync', $tasks->first()['activity']);
        $this->assertSame('Your plan', $tasks->first()['source_label']);
    }

    public function test_todays_tasks_include_assigned_monday_activities_from_other_users(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 4, 7, 10, 0, 0));
        [, $currentPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Programs']);
        $owner = $this->createUserInDepartment($department, ['create weekly plan']);
        $member = $this->createUserInDepartment($department, ['create weekly plan']);

        $ownerPlan = Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $currentPeriod->id,
            'user_id' => $owner->id,
            'title' => 'Owner plan',
            'main_target' => 'Owner target',
            'state' => Document::STATE_DRAFT,
            'content' => [
                'planned_activities' => [
                    [
                        'activity_uid' => (string) \Illuminate\Support\Str::ulid(),
                        'day' => 'Monday',
                        'activity' => 'Field visit',
                        'location' => 'Site A',
                        'team_member_ids' => [$member->id],
                        'notes_inclusion' => '',
                    ],
                ],
            ],
        ]);

        app(WeeklyActivityAssignmentService::class)->syncForWeeklyPlan(
            Document::fromDomainId((string) $ownerPlan->id)
        );

        $tasks = app(WeeklyActivityAssignmentService::class)->todaysTasksForUser(
            (string) $member->id,
            (string) $currentPeriod->id,
            'Monday',
        );

        $this->assertCount(1, $tasks);
        $this->assertSame('Field visit', $tasks->first()['activity']);
        $this->assertSame('From '.$owner->name, $tasks->first()['source_label']);
    }

    public function test_todays_tasks_exclude_other_day_activities(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 4, 7, 10, 0, 0));
        [, $currentPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Programs']);
        $user = $this->createUserInDepartment($department, ['create weekly plan']);

        Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $currentPeriod->id,
            'user_id' => $user->id,
            'title' => 'My plan',
            'main_target' => 'Weekly target',
            'state' => Document::STATE_DRAFT,
            'content' => [
                'planned_activities' => [
                    [
                        'activity_uid' => (string) \Illuminate\Support\Str::ulid(),
                        'day' => 'Tuesday',
                        'activity' => 'Tuesday only task',
                        'location' => 'Office',
                        'team_member_ids' => [],
                        'notes_inclusion' => '',
                    ],
                ],
            ],
        ]);

        $tasks = app(WeeklyActivityAssignmentService::class)->todaysTasksForUser(
            (string) $user->id,
            (string) $currentPeriod->id,
            'Monday',
        );

        $this->assertCount(0, $tasks);
    }

    public function test_todays_tasks_returns_empty_collection_on_sunday(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 4, 6, 10, 0, 0));
        [$currentPeriod] = $this->createCurrentAndNextPeriods();
        $department = Department::create(['name' => 'Programs']);
        $user = $this->createUserInDepartment($department, ['create weekly plan']);

        Document::create([
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => $currentPeriod->id,
            'user_id' => $user->id,
            'title' => 'My plan',
            'main_target' => 'Weekly target',
            'state' => Document::STATE_DRAFT,
            'content' => [
                'planned_activities' => [
                    [
                        'activity_uid' => (string) \Illuminate\Support\Str::ulid(),
                        'day' => 'Monday',
                        'activity' => 'Should not appear on Sunday',
                        'location' => 'Office',
                        'team_member_ids' => [],
                        'notes_inclusion' => '',
                    ],
                ],
            ],
        ]);

        $tasks = app(WeeklyActivityAssignmentService::class)->todaysTasksForUser(
            (string) $user->id,
            (string) $currentPeriod->id,
            'Sunday',
        );

        $this->assertCount(0, $tasks);
    }

    /**
     * @return array{0: WorkPeriod, 1: WorkPeriod}
     */
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

    /**
     * @param  array<int, string>  $permissions
     */
    private function createUserInDepartment(Department $department, array $permissions = []): User
    {
        $user = User::factory()->create([
            'department_id' => $department->id,
        ]);

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
