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
        Carbon::setTestNow(Carbon::create(2026, 4, 2, 12, 0, 0));
        try {
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
        } finally {
            Carbon::setTestNow();
        }
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
            $carried = collect($carryForwardTasks)->firstWhere('task', 'Carry this open task');

            return in_array('Carry this open task', $texts, true)
                && ! in_array('Done task', $texts, true)
                && ! in_array('Cancelled task', $texts, true)
                && is_array($carried)
                && ($carried['notes_progress'] ?? null) === ''
                && ($carried['history'] ?? null) === [];
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

    public function test_action_log_show_scopes_notes_and_progress_to_document_work_period(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();

        $period1 = $this->createOpenPeriod(2026, 13, '2026-03-23', '2026-03-29');
        $period2 = $this->createOpenPeriod(2026, 14, '2026-03-30', '2026-04-05');

        $document = Document::create([
            'user_id' => $manager->id,
            'period_id' => $period1->id,
            'type' => Document::TYPE_ACTION_LOG,
            'title' => 'Action Log - Week 13 of 2026',
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'tasks' => [
                    [
                        'task_uid' => 'task_mixed',
                        'task_no' => 1,
                        'task' => 'Shared task',
                        'responsible_user_ids' => [],
                        'progress' => 'Done',
                        'due_date' => null,
                        'compilation_date' => null,
                        'notes_progress' => 'Latest field text from another week',
                        'status_reason' => null,
                        'history' => [
                            [
                                'period_id' => (string) $period2->id,
                                'period_label' => 'Week 14 of 2026',
                                'updated_by' => $manager->id,
                                'from_progress' => 'Not Started',
                                'to_progress' => 'In Progress',
                                'notes_progress' => 'Staff only updated week 14',
                                'status_reason' => null,
                                'changed_at' => '2026-04-01 10:00:00',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->actingAs($manager)
            ->get(route('action-logs.show', $document))
            ->assertOk()
            ->assertViewHas('tasks', function (array $tasks): bool {
                if (count($tasks) !== 1) {
                    return false;
                }
                $row = $tasks[0];

                return ($row['task'] ?? null) === 'Shared task'
                    && ($row['display_notes_progress'] ?? null) === ''
                    && array_key_exists('display_progress', $row)
                    && $row['display_progress'] === null
                    && is_array($row['display_history'] ?? null)
                    && count($row['display_history']) === 1
                    && (($row['display_history'][0]['notes_progress'] ?? null) === 'Staff only updated week 14');
            });
    }

    public function test_action_log_timeline_includes_empty_weeks_between_last_update_and_current_week(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();

        $period16 = $this->createOpenPeriod(2026, 16, '2026-04-13', '2026-04-19');
        $period17 = $this->createOpenPeriod(2026, 17, '2026-04-20', '2026-04-26');
        $period18 = $this->createOpenPeriod(2026, 18, '2026-04-27', '2026-05-03');

        $document = Document::create([
            'user_id' => $manager->id,
            'period_id' => $period18->id,
            'type' => Document::TYPE_ACTION_LOG,
            'title' => 'Action Log - Week 18 of 2026',
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'tasks' => [
                    [
                        'task_uid' => 'task_gap',
                        'task_no' => 1,
                        'task' => 'Follow-up task',
                        'responsible_user_ids' => [],
                        'progress' => 'In Progress',
                        'due_date' => null,
                        'compilation_date' => null,
                        'notes_progress' => 'Latest row cache',
                        'status_reason' => null,
                        'history' => [
                            [
                                'period_id' => (string) $period16->id,
                                'period_label' => 'Week 16 of 2026',
                                'updated_by' => $manager->id,
                                'from_progress' => 'Not Started',
                                'to_progress' => 'In Progress',
                                'notes_progress' => 'Updated on week 16 only',
                                'status_reason' => null,
                                'changed_at' => '2026-04-15 09:00:00',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->actingAs($manager)
            ->get(route('action-logs.show', $document))
            ->assertOk()
            ->assertViewHas('tasks', function (array $tasks): bool {
                if (count($tasks) !== 1) {
                    return false;
                }

                $row = $tasks[0];
                $timeline = is_array($row['display_history'] ?? null) ? $row['display_history'] : [];
                if (count($timeline) !== 3) {
                    return false;
                }

                $byLabel = collect($timeline)->keyBy(fn ($entry) => (string) ($entry['period_label'] ?? ''));
                $week16 = $byLabel->get('Week 16 of 2026');
                $week17 = $byLabel->get('Week 17 of 2026');
                $week18 = $byLabel->get('Week 18 of 2026');

                return ($row['display_notes_progress'] ?? null) === ''
                    && array_key_exists('display_progress', $row)
                    && $row['display_progress'] === null
                    && is_array($week16)
                    && ($week16['is_empty'] ?? false) === false
                    && ($week16['notes_progress'] ?? null) === 'Updated on week 16 only'
                    && is_array($week17)
                    && ($week17['is_empty'] ?? false) === true
                    && is_array($week18)
                    && ($week18['is_empty'] ?? false) === true;
            });
    }

    public function test_manager_update_uses_existing_period_when_period_id_not_submitted(): void
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
                    $this->taskRow('task_a', 'Existing task'),
                ],
            ],
        ]);

        $response = $this->actingAs($manager)->put(route('action-logs.update', $document), [
            'action' => 'draft',
            'content' => [
                'tasks' => [
                    $this->taskRow('task_a', 'Updated task text'),
                ],
            ],
        ]);

        $response->assertRedirect(route('action-logs.show', $document));
        $response->assertSessionHas('success');

        $document->refresh();
        $this->assertSame($period->id, $document->period_id);
        $this->assertSame('Updated task text', $document->content['tasks'][0]['task'] ?? null);
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
