<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Support\ActionLogTaskSupport;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActionLogBoardUpdatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_staff_sees_pending_and_updated_tasks_on_the_board(): void
    {
        [$staff, $period, $document] = $this->assignedLog();

        $this->actingAs($staff)
            ->get(route('action-logs.board', ['period_id' => $period->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ActionLogs/Board')
                ->where('board.summary.needsUpdate', 1)
                ->where('board.summary.updatedByYou', 0)
                ->where('board.mine.0.update_state', ActionLogTaskSupport::STATE_NEEDS_UPDATE)
                ->where('board.mine.0.editable', true)
                ->missing('actionTasks'));

        $this->actingAs($staff)
            ->put(route('action-logs.quick-update', $document), [
                'task_uid' => 'task-staff-week',
                'progress' => ActionLogTaskSupport::PROGRESS_IN_PROGRESS,
                'notes_progress' => 'Called the partner and sent the brief.',
            ])
            ->assertRedirect();

        $this->actingAs($staff)
            ->get(route('action-logs.board', ['period_id' => $period->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('board.summary.needsUpdate', 0)
                ->where('board.summary.updatedByYou', 1)
                ->where('board.mine.0.update_state', ActionLogTaskSupport::STATE_UPDATED_BY_YOU)
                ->where('overlays.actionLogReminder', null));
    }

    public function test_reminder_appears_until_the_staff_member_updates_or_snoozes(): void
    {
        [$staff] = $this->assignedLog();
        $outsider = User::factory()->create(['status' => 'active']);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('actionTasks')
                ->where('overlays.actionLogReminder.pending', 1)
                ->where('overlays.actionLogReminder.tasks.0.task', 'Prepare partner brief'));

        $this->actingAs($outsider)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('overlays.actionLogReminder', null));

        $this->actingAs($staff)
            ->postJson(route('action-logs.remind-later'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('overlays.actionLogReminder', null));
    }

    /**
     * @return array{0: User, 1: WorkPeriod, 2: Document}
     */
    private function assignedLog(): array
    {
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('create action logs');
        $staff = User::factory()->create(['status' => 'active']);
        $period = WorkPeriod::factory()->forWeek(Carbon::now())->create([
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $document = Document::create([
            'user_id' => $manager->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_ACTION_LOG,
            'title' => 'Action Log',
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'tasks' => [[
                    'task_uid' => 'task-staff-week',
                    'task_no' => 1,
                    'task' => 'Prepare partner brief',
                    'progress' => ActionLogTaskSupport::PROGRESS_NOT_STARTED,
                    'notes_progress' => '',
                    'responsible_user_ids' => [(string) $staff->id],
                ]],
            ],
        ]);

        return [$staff, $period, $document];
    }
}
