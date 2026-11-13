<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyReportActionTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_action_log_progress_service_tracks_pending_tasks_for_assigned_staff(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 2, 10, 0, 0));

        [$staff] = $this->seedStaffWithPendingActionTask();

        $progress = app(\App\Services\ActionLogProgressService::class)->buildForUser($staff);

        $this->assertNotEmpty($progress['activeTasks']);
        $this->assertTrue(app(\App\Services\ActionLogProgressService::class)->hasPendingUpdates($staff));
        $this->assertTrue(
            collect($progress['activeTasks'])->contains(fn ($task) => ($task['task'] ?? '') === 'Assigned weekly task' && ! empty($task['needs_update']))
        );
    }

    public function test_step1_create_and_edit_do_not_contain_action_tasks_section(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 2, 10, 0, 0));

        [$staff, $period] = $this->seedStaffWithPendingActionTask();
        $staff->givePermissionTo('create weekly report');

        $createResponse = $this->actingAs($staff)->get(route('documents.create', ['type' => 'weekly_report']));
        $createResponse->assertOk();
        $createResponse->assertDontSee('id="weeklyReportActionTasksSection"', false);
        $createResponse->assertDontSee('Action Tasks (assigned to you)', false);

        $report = Document::create([
            'user_id' => $staff->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Weekly Report',
            'state' => Document::STATE_DRAFT,
            'content' => [],
        ]);

        $editResponse = $this->actingAs($staff)->get(route('documents.edit', $report));
        $editResponse->assertOk();
        $editResponse->assertDontSee('id="weeklyReportActionTasksSection"', false);
        $editResponse->assertDontSee('Action Tasks (assigned to you)', false);
    }

    public function test_draft_save_redirects_to_edit_not_show(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 2, 10, 0, 0));

        [$staff, $period] = $this->seedStaffWithPendingActionTask();
        $staff->givePermissionTo('create weekly report');

        $response = $this->actingAs($staff)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $period->id,
            'action' => 'draft',
        ]);

        $report = Document::query()
            ->where('user_id', $staff->id)
            ->where('type', Document::TYPE_WEEKLY_REPORT)
            ->firstOrFail();

        $response->assertRedirect(route('documents.edit', $report));
        $this->assertDatabaseHas('documents', [
            'id' => $report->id,
            'state' => Document::STATE_DRAFT,
        ]);
    }

    public function test_continue_with_pending_tasks_redirects_to_action_tasks_page(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 2, 10, 0, 0));

        [$staff, $period] = $this->seedStaffWithPendingActionTask();
        $staff->givePermissionTo('create weekly report');

        $response = $this->actingAs($staff)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $period->id,
            'action' => 'continue',
        ]);

        $report = Document::query()
            ->where('user_id', $staff->id)
            ->where('type', Document::TYPE_WEEKLY_REPORT)
            ->firstOrFail();

        $response->assertRedirect(route('documents.weekly-report.action-tasks', $report));
    }

    public function test_continue_with_no_assigned_tasks_skips_to_review_page(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 2, 10, 0, 0));

        $staff = User::factory()->create();
        $staff->givePermissionTo('create weekly report');
        $period = $this->createOpenPeriod(2026, 14, '2026-03-30', '2026-04-05');

        $response = $this->actingAs($staff)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $period->id,
            'action' => 'continue',
        ]);

        $report = Document::query()
            ->where('user_id', $staff->id)
            ->where('type', Document::TYPE_WEEKLY_REPORT)
            ->firstOrFail();

        $response->assertRedirect(route('documents.weekly-report.review', $report));
    }

    public function test_direct_submit_from_step_one_is_blocked(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 2, 10, 0, 0));

        $staff = User::factory()->create();
        $staff->givePermissionTo('create weekly report');
        $period = $this->createOpenPeriod(2026, 14, '2026-03-30', '2026-04-05');

        $response = $this->actingAs($staff)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_REPORT,
            'period_id' => $period->id,
            'action' => 'submit',
            'target_accomplished_status' => 'completed',
        ]);

        $response->assertSessionHasErrors('action');
        $this->assertDatabaseMissing('documents', [
            'user_id' => $staff->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'state' => Document::STATE_SUBMITTED,
        ]);
    }

    public function test_submit_blocked_on_review_when_action_tasks_pending(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 2, 10, 0, 0));

        [$staff, $period] = $this->seedStaffWithPendingActionTask();
        $staff->givePermissionTo('create weekly report');

        $report = Document::create([
            'user_id' => $staff->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Weekly Report',
            'state' => Document::STATE_DRAFT,
            'content' => [],
        ]);

        $this->actingAs($staff)->get(route('documents.weekly-report.review', $report))
            ->assertRedirect(route('documents.weekly-report.action-tasks', $report));

        $response = $this->actingAs($staff)->put(route('documents.update', $report), [
            'action' => 'submit',
            'wizard_review' => '1',
            'target_accomplished_status' => 'completed',
        ]);

        $response->assertSessionHasErrors('action_tasks');
        $report->refresh();
        $this->assertSame(Document::STATE_DRAFT, $report->state);
    }

    public function test_submit_succeeds_from_review_after_action_task_quick_update(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 2, 10, 0, 0));

        [$staff, $period, $actionLog] = $this->seedStaffWithPendingActionTask();
        $staff->givePermissionTo('create weekly report');

        $report = Document::create([
            'user_id' => $staff->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Weekly Report',
            'state' => Document::STATE_DRAFT,
            'content' => [],
        ]);

        $this->actingAs($staff)->put(route('action-logs.quick-update', $actionLog), [
            'task_uid' => 'task_a',
            'progress' => 'In Progress',
            'notes_progress' => 'Updated from weekly report flow',
        ])->assertSessionHas('success');

        $this->assertFalse(app(\App\Services\ActionLogProgressService::class)->hasPendingUpdates($staff));

        $reviewPage = $this->actingAs($staff)->get(route('documents.weekly-report.review', $report));
        $reviewPage->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Documents/WeeklyReportReview'));

        $response = $this->actingAs($staff)->put(route('documents.update', $report), [
            'action' => 'submit',
            'wizard_review' => '1',
            'target_accomplished_status' => 'completed',
        ]);

        $response->assertRedirect();
        $report->refresh();
        $this->assertSame(Document::STATE_SUBMITTED, $report->state);
    }

    public function test_submit_allowed_from_review_when_no_assigned_action_tasks(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 2, 10, 0, 0));

        $staff = User::factory()->create();
        $staff->givePermissionTo('create weekly report');
        $period = $this->createOpenPeriod(2026, 14, '2026-03-30', '2026-04-05');

        $report = Document::create([
            'user_id' => $staff->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Weekly Report',
            'state' => Document::STATE_DRAFT,
            'content' => [],
        ]);

        $this->actingAs($staff)->get(route('documents.weekly-report.review', $report))
            ->assertOk();

        $response = $this->actingAs($staff)->put(route('documents.update', $report), [
            'action' => 'submit',
            'wizard_review' => '1',
            'target_accomplished_status' => 'completed',
        ]);

        $response->assertRedirect();
        $report->refresh();
        $this->assertSame(Document::STATE_SUBMITTED, $report->state);
    }

    public function test_standalone_submit_redirects_weekly_report_to_review(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 2, 10, 0, 0));

        [$staff, $period] = $this->seedStaffWithPendingActionTask();
        $staff->givePermissionTo('create weekly report');

        $report = Document::create([
            'user_id' => $staff->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Weekly Report',
            'state' => Document::STATE_DRAFT,
            'content' => [
                'target_accomplished_status' => 'completed',
            ],
        ]);

        $response = $this->actingAs($staff)->post(route('documents.submit', $report));

        $response->assertRedirect(route('documents.weekly-report.review', $report));
        $report->refresh();
        $this->assertSame(Document::STATE_DRAFT, $report->state);
    }

    public function test_wizard_routes_blocked_for_submitted_weekly_reports(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 2, 10, 0, 0));

        $staff = User::factory()->create();
        $staff->givePermissionTo('create weekly report');
        $period = $this->createOpenPeriod(2026, 14, '2026-03-30', '2026-04-05');

        $report = Document::create([
            'user_id' => $staff->id,
            'period_id' => $period->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Weekly Report',
            'state' => Document::STATE_SUBMITTED,
            'content' => [],
        ]);

        $this->actingAs($staff)->get(route('documents.weekly-report.action-tasks', $report))
            ->assertNotFound();

        $this->actingAs($staff)->get(route('documents.weekly-report.review', $report))
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: WorkPeriod, 2: Document}
     */
    private function seedStaffWithPendingActionTask(): array
    {
        /** @var User $manager */
        $manager = User::factory()->create();
        $manager->givePermissionTo('create action logs');
        /** @var User $staff */
        $staff = User::factory()->create();
        $period = $this->createOpenPeriod(2026, 14, '2026-03-30', '2026-04-05');

        $actionLog = Document::create([
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

        return [$staff, $period, $actionLog];
    }

    private function taskRow(
        string $uid,
        string $task,
        array $responsibleUserIds = [],
        string $progress = 'Not Started',
    ): array {
        return [
            'task_uid' => $uid,
            'task_no' => 1,
            'task' => $task,
            'responsible_user_ids' => array_map(static fn ($id) => (string) $id, $responsibleUserIds),
            'progress' => $progress,
            'due_date' => null,
            'compilation_date' => null,
            'notes_progress' => '',
            'status_reason' => null,
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
