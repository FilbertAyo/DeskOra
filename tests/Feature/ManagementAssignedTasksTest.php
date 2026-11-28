<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\ManagementAssignedTask;
use App\Models\ManagementAssignedTaskRecipient;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\ManagementTaskAssignmentService;
use App\Support\PortalNav;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManagementAssignedTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Carbon::setTestNow(Carbon::create(2026, 4, 2, 10, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_unauthorized_user_cannot_access_assigned_tasks(): void
    {
        $staff = User::factory()->create(['status' => 'active']);
        $staff->givePermissionTo(['create weekly plan', 'create weekly report']);

        $this->actingAs($staff)->get(route('management.assigned-tasks.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('management.assigned-tasks.create'))->assertForbidden();
    }

    public function test_manager_with_permission_can_assign_task_to_multiple_staff(): void
    {
        $period = $this->createOpenPeriod();
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('task assignment');
        $staffA = User::factory()->create(['status' => 'active', 'name' => 'Staff A']);
        $staffB = User::factory()->create(['status' => 'active', 'name' => 'Staff B']);

        $response = $this->actingAs($manager)->post(route('management.assigned-tasks.store'), [
            'title' => 'Urgent field visit',
            'description' => 'Visit partner site',
            'period_id' => $period->id,
            'deadline' => '2026-04-04',
            'assignee_user_ids' => [$staffA->id, $staffB->id],
        ]);

        $task = ManagementAssignedTask::query()->firstOrFail();
        $response->assertRedirect(route('management.assigned-tasks.index', [
            'period_id' => $task->period_id,
        ]));

        $this->assertDatabaseHas('management_assigned_tasks', [
            'id' => $task->id,
            'title' => 'Urgent field visit',
            'assigned_by_user_id' => $manager->id,
            'period_id' => $period->id,
        ]);
        $this->assertDatabaseHas('management_assigned_task_recipients', [
            'management_assigned_task_id' => $task->id,
            'assignee_user_id' => $staffA->id,
            'status' => ManagementAssignedTaskRecipient::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('management_assigned_task_recipients', [
            'management_assigned_task_id' => $task->id,
            'assignee_user_id' => $staffB->id,
        ]);
    }

    public function test_assignee_sees_management_task_on_weekly_plan_show(): void
    {
        $period = $this->createOpenPeriod();
        $manager = User::factory()->create(['status' => 'active', 'name' => 'Manager One']);
        $manager->givePermissionTo('task assignment');
        $staff = User::factory()->create(['status' => 'active']);
        $staff->givePermissionTo('create weekly plan');

        $task = app(ManagementTaskAssignmentService::class)->assign(
            $manager,
            (string) $period->id,
            'Follow up with donors',
            'Call two partners',
            '2026-04-05',
            [(string) $staff->id]
        );

        $plan = Document::create([
            'user_id' => $staff->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Weekly Plan',
            'main_target' => 'Target',
            'state' => Document::STATE_DRAFT,
            'content' => [
                'planned_activities' => [
                    [
                        'day' => 'Monday',
                        'activity' => 'Own work',
                        'location' => 'Office',
                        'team_member_ids' => [$staff->id],
                    ],
                ],
            ],
        ]);

        $this->actingAs($staff)
            ->get(route('documents.show', $plan))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Documents/Show')
                ->has('managementAssignedTasks', 1)
                ->where('managementAssignedTasks.0.title', 'Follow up with donors')
                ->where('managementAssignedTasks.0.assigned_by', 'Manager One')
                ->where('managementAssignedTasks.0.task_id', (string) $task->id));
    }

    public function test_weekly_report_save_updates_recipient_progress_and_dashboard(): void
    {
        $period = $this->createOpenPeriod();
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('task assignment');
        $staff = User::factory()->create(['status' => 'active']);
        $staff->givePermissionTo('create weekly report');

        $task = app(ManagementTaskAssignmentService::class)->assign(
            $manager,
            (string) $period->id,
            'Prepare briefing note',
            null,
            null,
            [(string) $staff->id]
        );
        $recipient = $task->recipients()->firstOrFail();

        $this->actingAs($staff)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $period->id,
            'action' => 'draft',
            'content' => [
                'summary' => 'Busy week',
                'management_task_progress' => [
                    [
                        'management_task_recipient_id' => (string) $recipient->id,
                        'task_id' => (string) $task->id,
                        'title' => 'Prepare briefing note',
                        'progress' => 'In progress',
                        'report_feedback' => 'Draft started',
                    ],
                ],
            ],
        ])->assertRedirect();

        $recipient->refresh();
        $this->assertSame('In progress', $recipient->progress);
        $this->assertSame('Draft started', $recipient->report_feedback);
        $this->assertSame(ManagementAssignedTaskRecipient::STATUS_IN_PROGRESS, $recipient->status);
        $this->assertNotNull($recipient->weekly_report_id);

        $this->actingAs($manager)
            ->get(route('management.assigned-tasks.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Management/AssignedTasks/Index')
                ->has('rows', 1)
                ->where('rows.0.progress', 'In progress')
                ->where('rows.0.report_feedback', 'Draft started')
                ->where('rows.0.status', 'in_progress'));
    }

    public function test_overdue_status_when_deadline_passed_and_not_completed(): void
    {
        $period = $this->createOpenPeriod();
        $manager = User::factory()->create(['status' => 'active']);
        $staff = User::factory()->create(['status' => 'active']);

        $task = app(ManagementTaskAssignmentService::class)->assign(
            $manager,
            (string) $period->id,
            'Past due task',
            null,
            '2026-03-30',
            [(string) $staff->id]
        );

        $recipient = $task->recipients()->firstOrFail();
        $this->assertSame(ManagementAssignedTaskRecipient::STATUS_OVERDUE, $recipient->status);

        $service = app(ManagementTaskAssignmentService::class);
        $this->assertSame(
            ManagementAssignedTaskRecipient::STATUS_COMPLETED,
            $service->resolveStatus('Done', '2026-03-30')
        );
        $this->assertSame(
            ManagementAssignedTaskRecipient::STATUS_OVERDUE,
            $service->resolveStatus('In progress', '2026-03-30')
        );
    }

    public function test_management_nav_appears_with_only_task_assignment_permission(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('task assignment');

        $nav = PortalNav::forUser($user);
        $homeItems = $nav['groups'][0]['items'] ?? [];
        $management = collect($homeItems)->firstWhere('label', 'Management');

        $this->assertIsArray($management);
        $labels = collect($management['children'] ?? [])->pluck('label')->all();
        $this->assertContains('Assigned Tasks', $labels);
    }

    private function createOpenPeriod(): WorkPeriod
    {
        return WorkPeriod::create([
            'year' => 2026,
            'week_number' => 14,
            'week_start_date' => '2026-03-30',
            'week_end_date' => '2026-04-05',
            'plan_deadline' => '2026-04-05',
            'report_deadline' => '2026-04-05',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);
    }
}
