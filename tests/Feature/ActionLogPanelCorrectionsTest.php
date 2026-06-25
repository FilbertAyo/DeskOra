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

            $this->assertSame(Document::STATE_DRAFT, $document->state);
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

    public function test_manager_can_cancel_task_without_separate_reason(): void
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

        $response->assertSessionHas('success');
        $document->refresh();
        $task = $document->content['tasks'][0] ?? [];

        $this->assertSame('Cancelled', $task['progress'] ?? null);
    }

    public function test_assigned_staff_can_set_cancelled_status(): void
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
                    $this->taskRow('task_a', 'Assigned task', [$staff->id], 'Not Started'),
                ],
            ],
        ]);

        $response = $this->actingAs($staff)->put(route('action-logs.quick-update', $document), [
            'task_uid' => 'task_a',
            'progress' => 'Cancelled',
            'notes_progress' => 'No longer needed',
        ]);

        $response->assertSessionHas('success');
        $document->refresh();
        $task = $document->content['tasks'][0] ?? [];

        $this->assertSame('Cancelled', $task['progress'] ?? null);
        $this->assertSame('No longer needed', $task['notes_progress'] ?? null);
    }

    public function test_quick_update_requires_progress_notes(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 2, 10, 0, 0));

        $manager = User::factory()->create();
        $manager->givePermissionTo('create action logs');
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
                    $this->taskRow('task_a', 'Assigned task', [$staff->id], 'Not Started'),
                ],
            ],
        ]);

        $response = $this->actingAs($staff)->put(route('action-logs.quick-update', $document), [
            'task_uid' => 'task_a',
            'progress' => 'In Progress',
            'notes_progress' => '',
        ]);

        $response->assertSessionHasErrors('notes_progress');
    }

    public function test_assigned_staff_cannot_access_edit_page(): void
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
                    $this->taskRow('task_a', 'Assigned task', [$staff->id], 'Not Started'),
                ],
            ],
        ]);

        $response = $this->actingAs($staff)->get(route('action-logs.edit', $document));

        $response->assertRedirect(route('action-logs.show', $document));
        $response->assertSessionHas('error');
    }

    public function test_assigned_staff_cannot_full_update(): void
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
                    $this->taskRow('task_a', 'Assigned task', [$staff->id], 'Not Started'),
                ],
            ],
        ]);

        $response = $this->actingAs($staff)->put(route('action-logs.update', $document), [
            'period_id' => $period->id,
            'action' => 'draft',
            'content' => [
                'tasks' => [
                    $this->taskRow('task_a', 'Assigned task', [$staff->id], 'In Progress'),
                ],
            ],
        ]);

        $response->assertRedirect(route('action-logs.show', $document));
        $response->assertSessionHas('error');
        $document->refresh();
        $this->assertSame('Not Started', $document->content['tasks'][0]['progress'] ?? null);
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

        $response = $this->actingAs($staff)->put(route('action-logs.quick-update', $document), [
            'task_uid' => 'task_a',
            'progress' => 'In Progress',
            'notes_progress' => 'Week 14 progress update',
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

    public function test_action_log_timeline_aggregates_real_updates_across_separate_weekly_documents(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();

        $period16 = $this->createOpenPeriod(2026, 16, '2026-04-13', '2026-04-19');
        $period17 = $this->createOpenPeriod(2026, 17, '2026-04-20', '2026-04-26');
        $period18 = $this->createOpenPeriod(2026, 18, '2026-04-27', '2026-05-03');

        $week16Doc = Document::create([
            'user_id' => $manager->id,
            'period_id' => $period16->id,
            'type' => Document::TYPE_ACTION_LOG,
            'title' => 'Action Log - Week 16 of 2026',
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
                        'notes_progress' => 'Updated on week 16 only',
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
                                'source' => 'update',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $week17Doc = Document::create([
            'user_id' => $manager->id,
            'period_id' => $period17->id,
            'type' => Document::TYPE_ACTION_LOG,
            'title' => 'Action Log - Week 17 of 2026',
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
                        'notes_progress' => '',
                        'status_reason' => null,
                        'history' => [
                            [
                                'period_id' => (string) $period17->id,
                                'period_label' => 'Week 17 of 2026',
                                'updated_by' => $manager->id,
                                'from_progress' => null,
                                'to_progress' => 'In Progress',
                                'notes_progress' => null,
                                'status_reason' => null,
                                'changed_at' => '2026-04-20 08:00:00',
                                'source' => 'auto_init',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $week18Doc = Document::create([
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
                        'notes_progress' => '',
                        'status_reason' => null,
                        'history' => [
                            [
                                'period_id' => (string) $period18->id,
                                'period_label' => 'Week 18 of 2026',
                                'updated_by' => $manager->id,
                                'from_progress' => null,
                                'to_progress' => 'In Progress',
                                'notes_progress' => null,
                                'status_reason' => null,
                                'changed_at' => '2026-04-27 08:00:00',
                                'source' => 'auto_init',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // Viewing Week 18: row empty, timeline shows Week 16 (real) + Week 17/18 empty.
        $this->actingAs($manager)
            ->get(route('action-logs.show', $week18Doc))
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
                    && ($week16['is_empty'] ?? null) === false
                    && ($week16['notes_progress'] ?? null) === 'Updated on week 16 only'
                    && (string) ($week16['period_id'] ?? '') === (string) WorkPeriod::where('week_number', 16)->value('id')
                    && is_array($week17)
                    && ($week17['is_empty'] ?? null) === true
                    && is_array($week18)
                    && ($week18['is_empty'] ?? null) === true;
            });

        // Viewing Week 17: row empty, timeline shows Week 16 (real) + Week 17 empty (no Week-17-labeled clone of Week 16 content).
        $this->actingAs($manager)
            ->get(route('action-logs.show', $week17Doc))
            ->assertOk()
            ->assertViewHas('tasks', function (array $tasks): bool {
                if (count($tasks) !== 1) {
                    return false;
                }
                $row = $tasks[0];
                $timeline = is_array($row['display_history'] ?? null) ? $row['display_history'] : [];
                if (count($timeline) !== 2) {
                    return false;
                }

                $byLabel = collect($timeline)->keyBy(fn ($entry) => (string) ($entry['period_label'] ?? ''));
                $week16 = $byLabel->get('Week 16 of 2026');
                $week17 = $byLabel->get('Week 17 of 2026');

                return ($row['display_notes_progress'] ?? null) === ''
                    && array_key_exists('display_progress', $row)
                    && $row['display_progress'] === null
                    && is_array($week16)
                    && ($week16['is_empty'] ?? null) === false
                    && ($week16['notes_progress'] ?? null) === 'Updated on week 16 only'
                    && is_array($week17)
                    && ($week17['is_empty'] ?? null) === true;
            });

        // Viewing Week 16: row filled from its own real entry, timeline shows only Week 16.
        $this->actingAs($manager)
            ->get(route('action-logs.show', $week16Doc))
            ->assertOk()
            ->assertViewHas('tasks', function (array $tasks): bool {
                if (count($tasks) !== 1) {
                    return false;
                }
                $row = $tasks[0];
                $timeline = is_array($row['display_history'] ?? null) ? $row['display_history'] : [];

                return ($row['display_notes_progress'] ?? null) === 'Updated on week 16 only'
                    && ($row['display_progress'] ?? null) === 'In Progress'
                    && count($timeline) === 1
                    && ($timeline[0]['period_label'] ?? null) === 'Week 16 of 2026'
                    && ($timeline[0]['is_empty'] ?? null) === false;
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

    public function test_carry_forward_collects_incomplete_tasks_across_multi_week_gap(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 10, 0, 0));

        try {
            /** @var User $manager */
            $manager = User::factory()->create();
            $manager->givePermissionTo('create action logs');

            $week21 = $this->createOpenPeriod(2026, 21, '2026-05-18', '2026-05-24');
            $this->createOpenPeriod(2026, 22, '2026-05-25', '2026-05-31');
            $week23 = $this->createOpenPeriod(2026, 23, '2026-06-01', '2026-06-07');
            $this->createOpenPeriod(2026, 24, '2026-06-08', '2026-06-14');
            $this->createOpenPeriod(2026, 25, '2026-06-15', '2026-06-21');

            Document::create([
                'user_id' => $manager->id,
                'period_id' => $week21->id,
                'type' => Document::TYPE_ACTION_LOG,
                'title' => 'Action Log - Week 21 of 2026',
                'state' => Document::STATE_SUBMITTED,
                'content' => [
                    'tasks' => [
                        $this->taskRow('task_old', 'Stale week 21 task', [], 'In Progress'),
                    ],
                ],
            ]);

            Document::create([
                'user_id' => $manager->id,
                'period_id' => $week23->id,
                'type' => Document::TYPE_ACTION_LOG,
                'title' => 'Action Log - Week 23 of 2026',
                'state' => Document::STATE_SUBMITTED,
                'content' => [
                    'tasks' => [
                        $this->taskRow('task_recent', 'Week 23 only task', [], 'Not Started'),
                    ],
                ],
            ]);

            $response = $this->actingAs($manager)->get(route('action-logs.create'));

            $response->assertOk();
            $response->assertViewHas('carryForwardTasks', function (array $carryForwardTasks): bool {
                $texts = collect($carryForwardTasks)->pluck('task')->filter()->values()->all();

                return in_array('Stale week 21 task', $texts, true)
                    && in_array('Week 23 only task', $texts, true);
            });
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_edit_syncs_missing_carried_tasks_into_current_week_log(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 19, 10, 0, 0));

        try {
            /** @var User $manager */
            $manager = User::factory()->create();
            $manager->givePermissionTo('create action logs');

            $week24 = $this->createOpenPeriod(2026, 24, '2026-06-08', '2026-06-14');
            $week25 = $this->createOpenPeriod(2026, 25, '2026-06-15', '2026-06-21');

            Document::create([
                'user_id' => $manager->id,
                'period_id' => $week24->id,
                'type' => Document::TYPE_ACTION_LOG,
                'title' => 'Action Log - Week 24 of 2026',
                'state' => Document::STATE_SUBMITTED,
                'content' => [
                    'tasks' => [
                        $this->taskRow('task_carry', 'Task to carry', [], 'In Progress'),
                    ],
                ],
            ]);

            $week25Doc = Document::create([
                'user_id' => $manager->id,
                'period_id' => $week25->id,
                'type' => Document::TYPE_ACTION_LOG,
                'title' => 'Action Log - Week 25 of 2026',
                'state' => Document::STATE_SUBMITTED,
                'content' => [
                    'tasks' => [
                        $this->taskRow('task_local', 'Week 25 local task'),
                    ],
                ],
            ]);

            $response = $this->actingAs($manager)->get(route('action-logs.edit', $week25Doc));

            $response->assertOk();

            $week25Doc->refresh();
            $taskTexts = collect($week25Doc->content['tasks'] ?? [])->pluck('task')->all();

            $this->assertContains('Task to carry', $taskTexts);
            $this->assertContains('Week 25 local task', $taskTexts);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_manager_update_preserves_responsible_when_form_omits_assignees(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->givePermissionTo('create action logs');
        /** @var User $assignee */
        $assignee = User::factory()->create();
        $period = $this->createOpenPeriod(2026, 25, '2026-06-15', '2026-06-21');

        $document = Document::create([
            'user_id' => $manager->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_ACTION_LOG,
            'title' => 'Action Log - Week 25 of 2026',
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'tasks' => [
                    $this->taskRow('task_a', 'Assigned carry task', [$assignee->id], 'In Progress'),
                ],
            ],
        ]);

        $response = $this->actingAs($manager)->put(route('action-logs.update', $document), [
            'period_id' => $period->id,
            'action' => 'draft',
            'content' => [
                'tasks' => [
                    [
                        'task_uid' => 'task_a',
                        'task' => 'Assigned carry task',
                        'progress' => 'In Progress',
                        'notes_progress' => 'Still in progress',
                    ],
                ],
            ],
        ]);

        $response->assertSessionHas('success');
        $document->refresh();
        $task = $document->content['tasks'][0] ?? [];

        $this->assertSame([(string) $assignee->id], $task['responsible_user_ids'] ?? null);
    }

    public function test_manager_can_delete_task_via_autosave_update(): void
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
                    $this->taskRow('task_a', 'Keep this task'),
                    $this->taskRow('task_b', 'Remove this task'),
                ],
            ],
        ]);

        $response = $this->actingAs($manager)->putJson(route('action-logs.update', $document), [
            'period_id' => $period->id,
            'action' => 'draft',
            'content' => [
                'tasks' => [
                    $this->taskRow('task_a', 'Keep this task'),
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(1, 'tasks');
        $response->assertJsonPath('tasks.0.task_uid', 'task_a');

        $document->refresh();
        $uids = collect($document->content['tasks'] ?? [])->pluck('task_uid')->all();
        $this->assertSame(['task_a'], $uids);
    }

    public function test_show_page_displays_responsible_from_prior_week_when_current_row_empty(): void
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        /** @var User $assignee */
        $assignee = User::factory()->create();

        $week24 = $this->createOpenPeriod(2026, 24, '2026-06-08', '2026-06-14');
        $week25 = $this->createOpenPeriod(2026, 25, '2026-06-15', '2026-06-21');

        Document::create([
            'user_id' => $manager->id,
            'period_id' => $week24->id,
            'type' => Document::TYPE_ACTION_LOG,
            'title' => 'Action Log - Week 24 of 2026',
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'tasks' => [
                    $this->taskRow('task_a', 'Shared task', [$assignee->id], 'In Progress'),
                ],
            ],
        ]);

        $week25Doc = Document::create([
            'user_id' => $manager->id,
            'period_id' => $week25->id,
            'type' => Document::TYPE_ACTION_LOG,
            'title' => 'Action Log - Week 25 of 2026',
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'tasks' => [
                    $this->taskRow('task_a', 'Shared task', [], 'In Progress'),
                ],
            ],
        ]);

        $tasks = $week25Doc->getActionLogTasksForPeriodDisplay();
        $row = $tasks[0] ?? [];

        $this->assertSame([(string) $assignee->id], $row['responsible_user_ids'] ?? null);
    }

    public function test_staff_can_quick_update_carried_task_after_edit_sync(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 19, 10, 0, 0));

        try {
            /** @var User $manager */
            $manager = User::factory()->create();
            $manager->givePermissionTo('create action logs');
            /** @var User $staff */
            $staff = User::factory()->create();

            $week24 = $this->createOpenPeriod(2026, 24, '2026-06-08', '2026-06-14');
            $week25 = $this->createOpenPeriod(2026, 25, '2026-06-15', '2026-06-21');

            Document::create([
                'user_id' => $manager->id,
                'period_id' => $week24->id,
                'type' => Document::TYPE_ACTION_LOG,
                'title' => 'Action Log - Week 24 of 2026',
                'state' => Document::STATE_SUBMITTED,
                'content' => [
                    'tasks' => [
                        $this->taskRow('task_carry', 'Carried dashboard task', [$staff->id], 'In Progress'),
                    ],
                ],
            ]);

            $week25Doc = Document::create([
                'user_id' => $manager->id,
                'period_id' => $week25->id,
                'type' => Document::TYPE_ACTION_LOG,
                'title' => 'Action Log - Week 25 of 2026',
                'state' => Document::STATE_SUBMITTED,
                'content' => [
                    'tasks' => [
                        $this->taskRow('task_local', 'Local week 25 task', [$staff->id]),
                    ],
                ],
            ]);

            $this->actingAs($manager)->get(route('action-logs.edit', $week25Doc))->assertOk();

            $week25Doc->refresh();
            $this->assertTrue(
                collect($week25Doc->content['tasks'] ?? [])->contains(fn ($task) => ($task['task'] ?? null) === 'Carried dashboard task')
            );

            $response = $this->actingAs($staff)->put(route('action-logs.quick-update', $week25Doc), [
                'task_uid' => 'task_carry',
                'progress' => 'In Progress',
                'notes_progress' => 'Updated in week 25',
            ]);

            $response->assertRedirect();
            $response->assertSessionHas('success');

            $week25Doc->refresh();
            $carried = collect($week25Doc->content['tasks'] ?? [])->firstWhere('task_uid', 'task_carry');
            $this->assertSame('Updated in week 25', $carried['notes_progress'] ?? null);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_sync_carried_tasks_command_reports_and_applies_changes(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 19, 10, 0, 0));

        try {
            /** @var User $manager */
            $manager = User::factory()->create();
            $week24 = $this->createOpenPeriod(2026, 24, '2026-06-08', '2026-06-14');
            $week25 = $this->createOpenPeriod(2026, 25, '2026-06-15', '2026-06-21');

            Document::create([
                'user_id' => $manager->id,
                'period_id' => $week24->id,
                'type' => Document::TYPE_ACTION_LOG,
                'title' => 'Action Log - Week 24 of 2026',
                'state' => Document::STATE_SUBMITTED,
                'content' => [
                    'tasks' => [
                        $this->taskRow('task_cmd', 'Command carry task', [], 'Not Started'),
                    ],
                ],
            ]);

            $week25Doc = Document::create([
                'user_id' => $manager->id,
                'period_id' => $week25->id,
                'type' => Document::TYPE_ACTION_LOG,
                'title' => 'Action Log - Week 25 of 2026',
                'state' => Document::STATE_SUBMITTED,
                'content' => [
                    'tasks' => [],
                ],
            ]);

            $this->artisan('action-logs:sync-carried-tasks', ['--period' => $week25->id])
                ->assertSuccessful();

            $week25Doc->refresh();
            $taskTexts = collect($week25Doc->content['tasks'] ?? [])->pluck('task')->all();
            $this->assertContains('Command carry task', $taskTexts);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_draft_autosave_on_submitted_log_reverts_to_draft(): void
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
            'state' => Document::STATE_SUBMITTED,
            'content' => [
                'tasks' => [
                    $this->taskRow('task_a', 'Submitted task'),
                ],
            ],
        ]);

        $response = $this->actingAs($manager)->putJson(route('action-logs.update', $document), [
            'period_id' => $period->id,
            'action' => 'draft',
            'content' => [
                'tasks' => [
                    $this->taskRow('task_a', 'Updated submitted task'),
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('state', Document::STATE_DRAFT);

        $document->refresh();
        $this->assertSame(Document::STATE_DRAFT, $document->state);
    }

    public function test_autosave_returns_json_tasks(): void
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
                    $this->taskRow('task_a', 'Initial task'),
                ],
            ],
        ]);

        $response = $this->actingAs($manager)->putJson(route('action-logs.update', $document), [
            'period_id' => $period->id,
            'action' => 'draft',
            'content' => [
                'tasks' => [
                    $this->taskRow('task_a', 'Updated via JSON'),
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('tasks.0.task', 'Updated via JSON');
        $response->assertJsonPath('document_id', (string) $document->id);
    }

    public function test_submit_after_autosave_sets_submitted(): void
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
                    $this->taskRow('task_a', 'Draft task'),
                ],
            ],
        ]);

        $this->actingAs($manager)->putJson(route('action-logs.update', $document), [
            'period_id' => $period->id,
            'action' => 'draft',
            'content' => [
                'tasks' => [
                    $this->taskRow('task_a', 'Autosaved task'),
                ],
            ],
        ])->assertOk();

        $response = $this->actingAs($manager)->put(route('action-logs.update', $document), [
            'period_id' => $period->id,
            'action' => 'submit',
            'content' => [
                'tasks' => [
                    $this->taskRow('task_a', 'Autosaved task'),
                ],
            ],
        ]);

        $response->assertRedirect(route('action-logs.show', $document));
        $document->refresh();
        $this->assertSame(Document::STATE_SUBMITTED, $document->state);
    }

    public function test_store_json_creates_document_for_create_flow(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 2, 12, 0, 0));

        try {
            /** @var User $manager */
            $manager = User::factory()->create();
            $manager->givePermissionTo('create action logs');
            $period = $this->createOpenPeriod(2026, 14, '2026-03-30', '2026-04-05');

            $response = $this->actingAs($manager)->postJson(route('action-logs.store'), [
                'period_id' => $period->id,
                'action' => 'draft',
                'content' => [
                    'tasks' => [
                        $this->taskRow('task_new', 'First autosaved task'),
                    ],
                ],
            ]);

            $response->assertOk();
            $response->assertJsonPath('success', true);
            $response->assertJsonPath('state', Document::STATE_DRAFT);
            $response->assertJsonPath('tasks.0.task', 'First autosaved task');

            $documentId = $response->json('document_id');
            $this->assertNotEmpty($documentId);

            $document = Document::find($documentId);
            $this->assertNotNull($document);
            $this->assertSame(Document::TYPE_ACTION_LOG, $document->type);
            $this->assertSame(Document::STATE_DRAFT, $document->state);
        } finally {
            Carbon::setTestNow();
        }
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
