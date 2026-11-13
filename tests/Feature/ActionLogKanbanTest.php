<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Support\ActionLogTaskSupport;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActionLogKanbanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_board_page_renders_columns_for_period(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('create action logs');
        $assignee = User::factory()->create(['status' => 'active']);
        $period = WorkPeriod::factory()->create(['status' => WorkPeriod::STATUS_OPEN]);

        Document::create([
            'user_id' => $manager->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_ACTION_LOG,
            'title' => 'Action Log',
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'tasks' => [[
                    'task_uid' => 'task-board-1',
                    'task_no' => 1,
                    'task' => 'Prepare partner brief',
                    'progress' => ActionLogTaskSupport::PROGRESS_IN_PROGRESS,
                    'notes_progress' => 'Draft started',
                    'responsible_user_ids' => [(string) $assignee->id],
                ]],
            ],
        ]);

        $this->actingAs($manager)
            ->get(route('action-logs.board', ['period_id' => $period->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ActionLogs/Board')
                ->where('progressOptions', fn ($options) => collect($options)->intersect(['Not Started', 'In Progress', 'Done', 'Cancelled'])->count() === 4)
                ->where('board.columns', fn ($columns) => collect($columns)->flatten(1)->contains(fn ($task) => data_get($task, 'task') === 'Prepare partner brief')));
    }

    public function test_board_move_updates_task_progress(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('create action logs');
        $period = WorkPeriod::factory()->create(['status' => WorkPeriod::STATUS_OPEN]);

        $document = Document::create([
            'user_id' => $manager->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_ACTION_LOG,
            'title' => 'Action Log',
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'tasks' => [[
                    'task_uid' => 'task-board-2',
                    'task_no' => 1,
                    'task' => 'Close field visit',
                    'progress' => ActionLogTaskSupport::PROGRESS_NOT_STARTED,
                    'notes_progress' => '',
                    'responsible_user_ids' => [(string) $manager->id],
                ]],
            ],
        ]);

        $this->actingAs($manager)
            ->patchJson(route('action-logs.board-move', $document), [
                'task_uid' => 'task-board-2',
                'progress' => ActionLogTaskSupport::PROGRESS_DONE,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $document = Document::fromDomainId((string) $document->id);
        $this->assertSame(
            ActionLogTaskSupport::PROGRESS_DONE,
            $document->content['tasks'][0]['progress'] ?? null
        );
    }

    public function test_assignee_without_permission_cannot_move_others_task(): void
    {
        $manager = User::factory()->create(['status' => 'active']);
        $manager->givePermissionTo('create action logs');
        $assignee = User::factory()->create(['status' => 'active']);
        $outsider = User::factory()->create(['status' => 'active']);
        $period = WorkPeriod::factory()->create(['status' => WorkPeriod::STATUS_OPEN]);

        $document = Document::create([
            'user_id' => $manager->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_ACTION_LOG,
            'title' => 'Action Log',
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'tasks' => [[
                    'task_uid' => 'task-board-3',
                    'task_no' => 1,
                    'task' => 'Owned by assignee',
                    'progress' => ActionLogTaskSupport::PROGRESS_NOT_STARTED,
                    'notes_progress' => '',
                    'responsible_user_ids' => [(string) $assignee->id],
                ]],
            ],
        ]);

        $this->actingAs($outsider)
            ->patchJson(route('action-logs.board-move', $document), [
                'task_uid' => 'task-board-3',
                'progress' => ActionLogTaskSupport::PROGRESS_DONE,
            ])
            ->assertForbidden();
    }
}

