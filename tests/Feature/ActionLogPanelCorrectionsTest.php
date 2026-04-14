<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionLogPanelCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_manager_can_add_tasks_to_submitted_action_log_and_keep_single_weekly_log(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->givePermissionTo('create action logs');
        $period = $this->createOpenPeriod(2026, 14, '2026-03-30', '2026-04-05');

        $document = Document::create([
            'user_id' => $manager->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_ACTION_LOG,
            'title' => 'Old custom title',
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'tasks' => [
                    $this->taskRow('task_a', 'Initial submitted task'),
                ],
            ],
        ]);

        $response = $this->actingAs($manager)->post(route('action-logs.store'), [
            'period_id' => $period->id,
            'action' => 'draft',
            'content' => [
                'tasks' => [
                    $this->taskRow('task_b', 'Follow-up task on Friday'),
                ],
            ],
        ]);

        $response->assertRedirect(route('action-logs.show', $document));

        $this->assertEquals(
            1,
            Document::query()
                ->where('type', Document::TYPE_ACTION_LOG)
                ->where('period_id', $period->id)
                ->count()
        );

        $document->refresh();
        $tasks = $document->content['tasks'] ?? [];

        $this->assertSame(Document::STATE_SUBMITTED, $document->state);
        $this->assertSame('Action Log - Week 14 of 2026', $document->title);
        $this->assertCount(2, $tasks);
        $this->assertSame('Initial submitted task', $tasks[0]['task'] ?? null);
        $this->assertSame('Follow-up task on Friday', $tasks[1]['task'] ?? null);
    }

    public function test_carry_forward_shows_only_not_done_or_cancelled_tasks(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 2, 10, 0, 0));

        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->givePermissionTo('create action logs');

        $previous = $this->createOpenPeriod(2026, 13, '2026-03-23', '2026-03-29');
        $this->createOpenPeriod(2026, 14, '2026-03-30', '2026-04-05');

        Document::create([
            'user_id' => $manager->id,
            'period_id' => $previous->id,
            'type' => Document::TYPE_ACTION_LOG,
            'title' => 'Action Log - Week 13 of 2026',
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'tasks' => [
                    $this->taskRow('task_done', 'Done task', [], 'Done'),
                    $this->taskRow('task_cancelled', 'Cancelled task', [], 'Cancelled', null, null, '', 'No longer needed'),
                    $this->taskRow('task_open', 'Carry this open task', [], 'In Progress'),
                ],
            ],
        ]);

        $response = $this->actingAs($manager)->get(route('action-logs.create'));

        $response->assertOk();
        $response->assertViewHas('carryForwardTasks', function (array $carryForwardTasks): bool {
            $texts = collect($carryForwardTasks)->map(fn ($task) => $task['task'] ?? null)->filter()->values()->all();

            return in_array('Carry this open task', $texts, true)
                && ! in_array('Done task', $texts, true)
                && ! in_array('Cancelled task', $texts, true);
        });

        Carbon::setTestNow();
    }

    public function test_manager_cannot_cancel_task_without_reason(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->givePermissionTo('create action logs');
        $period = $this->createOpenPeriod(2026, 14, '2026-03-30', '2026-04-05');

        $document = Document::create([
            'user_id' => $manager->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_ACTION_LOG,
            'title' => 'Action Log - Week 14 of 2026',
            'state' => Document::STATE_DRAFT,
            'content' => [
                'tasks' => [
                    $this->taskRow('task_a', 'Cancelable task'),
                ],
            ],
        ]);

        $response = $this->actingAs($manager)->put(route('action-logs.update', $document), [
            'period_id' => $period->id,
            'action' => 'draft',
            'content' => [
                'tasks' => [
                    $this->taskRow('task_a', 'Cancelable task', [], 'Cancelled'),
                ],
            ],
        ]);

        $response->assertSessionHas('error', 'Please provide a cancellation reason for every cancelled action log task.');
    }

    public function test_assigned_staff_cannot_set_cancelled_status(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->givePermissionTo('create action logs');
        /** @var User $staff */
        $staff = User::factory()->create();
        $period = $this->createOpenPeriod(2026, 14, '2026-03-30', '2026-04-05');

        $document = Document::create([
            'user_id' => $manager->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_ACTION_LOG,
            'title' => 'Action Log - Week 14 of 2026',
            'state' => Document::STATE_DRAFT,
            'content' => [
                'tasks' => [
                    $this->taskRow('task_a', 'Assigned task', [$staff->id], 'Not Started'),
                ],
            ],
        ]);

        $response = $this->actingAs($staff)->put(route('action-logs.update', $document), [
            'period_id' => $period->id,
            'action' => 'draft',
            'content' => [
                'tasks' => [
                    $this->taskRow('task_a', 'Assigned task', [$staff->id], 'Cancelled', null, null, 'Trying to cancel', 'No reason'),
                ],
            ],
        ]);

        $response->assertSessionHas('success');
        $document->refresh();
        $task = $document->content['tasks'][0] ?? [];

        $this->assertSame('Not Started', $task['progress'] ?? null);
    }

    public function test_staff_progress_updates_append_history_timeline_entries(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->givePermissionTo('create action logs');
        /** @var User $staff */
        $staff = User::factory()->create();
        $period = $this->createOpenPeriod(2026, 14, '2026-03-30', '2026-04-05');

        $document = Document::create([
            'user_id' => $manager->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_ACTION_LOG,
            'title' => 'Action Log - Week 14 of 2026',
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'tasks' => [
                    $this->taskRow('task_a', 'Assigned weekly task', [$staff->id], 'Not Started'),
                ],
            ],
        ]);

        $response = $this->actingAs($staff)->put(route('action-logs.update', $document), [
            'period_id' => $period->id,
            'action' => 'draft',
            'content' => [
                'tasks' => [
                    $this->taskRow('task_a', 'Assigned weekly task', [$staff->id], 'In Progress', null, null, 'Week 14 progress update'),
                ],
            ],
        ]);

        $response->assertSessionHas('success');

        $document->refresh();
        $task = $document->content['tasks'][0] ?? [];
        $history = is_array($task['history'] ?? null) ? $task['history'] : [];
        $latest = end($history);

        $this->assertSame('In Progress', $task['progress'] ?? null);
        $this->assertNotEmpty($history);
        $this->assertSame('In Progress', $latest['to_progress'] ?? null);
        $this->assertSame('Week 14 progress update', $latest['notes_progress'] ?? null);
    }

    private function taskRow(
        string $uid,
        string $task,
        array $responsibleUserIds = [],
        string $progress = 'Not Started',
        ?string $dueDate = null,
        ?string $compilationDate = null,
        string $notesProgress = '',
        ?string $statusReason = null
    ): array {
        return [
            'task_uid' => $uid,
            'task_no' => 1,
            'task' => $task,
            'responsible_user_ids' => $responsibleUserIds,
            'progress' => $progress,
            'due_date' => $dueDate,
            'compilation_date' => $compilationDate,
            'notes_progress' => $notesProgress,
            'status_reason' => $statusReason,
        ];
    }

    private function createOpenPeriod(int $year, int $weekNumber, string $start, string $end): WorkPeriod
    {
        return WorkPeriod::create([
            'year' => $year,
            'week_number' => $weekNumber,
            'week_start_date' => $start,
            'week_end_date' => $end,
            'plan_deadline' => $end,
            'report_deadline' => $end,
            'status' => WorkPeriod::STATUS_OPEN,
        ]);
    }
}
