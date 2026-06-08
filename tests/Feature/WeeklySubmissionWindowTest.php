<?php

namespace Tests\Feature;

use App\Database\DocumentsUnionView;
use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\WeeklySubmissionWindowService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklySubmissionWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        DocumentsUnionView::recreate(withUnicodeCollation: false);
    }

    public function test_plan_slot_targets_current_week_during_plan_grace_when_next_week_plan_missing(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 3, 10, 0, 0));

        $week22 = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 22,
            'week_start_date' => '2026-05-25',
            'week_end_date' => '2026-05-31',
            'plan_deadline' => '2026-05-23',
            'report_deadline' => '2026-05-30',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $week23 = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 23,
            'week_start_date' => '2026-06-01',
            'week_end_date' => '2026-06-07',
            'plan_deadline' => '2026-06-03',
            'report_deadline' => '2026-06-06',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        WorkPeriod::create([
            'year' => 2026,
            'week_number' => 24,
            'week_start_date' => '2026-06-08',
            'week_end_date' => '2026-06-14',
            'plan_deadline' => '2026-06-06',
            'report_deadline' => '2026-06-13',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $this->assertTrue($week23->fresh()->isPlanGracePeriodActive());

        $user = User::factory()->create();
        $user->givePermissionTo(['create weekly plan', 'create weekly report']);

        $slot = app(WeeklySubmissionWindowService::class)->resolvePlanSlot($user);

        $this->assertTrue($slot->showAction());
        $this->assertSame($week23->id, $slot->period->id);
        $this->assertTrue($slot->graceActive);
    }

    public function test_report_slot_targets_previous_week_during_report_grace(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 3, 10, 0, 0));

        WorkPeriod::create([
            'year' => 2026,
            'week_number' => 22,
            'week_start_date' => '2026-05-25',
            'week_end_date' => '2026-05-31',
            'plan_deadline' => '2026-05-23',
            'report_deadline' => '2026-06-03',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        WorkPeriod::create([
            'year' => 2026,
            'week_number' => 23,
            'week_start_date' => '2026-06-01',
            'week_end_date' => '2026-06-07',
            'plan_deadline' => '2026-05-30',
            'report_deadline' => '2026-06-06',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo(['create weekly plan', 'create weekly report']);

        $slot = app(WeeklySubmissionWindowService::class)->resolveReportSlot($user);

        $this->assertTrue($slot->showAction());
        $this->assertSame(22, $slot->period->week_number);
        $this->assertTrue($slot->graceActive);
    }

    public function test_plan_slot_offers_continue_draft_instead_of_blocking_create(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 3, 10, 0, 0));

        $week23 = WorkPeriod::create([
            'year' => 2026,
            'week_number' => 23,
            'week_start_date' => '2026-06-01',
            'week_end_date' => '2026-06-07',
            'plan_deadline' => '2026-06-03',
            'report_deadline' => '2026-06-06',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        WorkPeriod::create([
            'year' => 2026,
            'week_number' => 24,
            'week_start_date' => '2026-06-08',
            'week_end_date' => '2026-06-14',
            'plan_deadline' => '2026-06-06',
            'report_deadline' => '2026-06-13',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('create weekly plan');

        $draft = Document::create([
            'user_id' => $user->id,
            'period_id' => $week23->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Draft plan',
            'state' => Document::STATE_DRAFT,
            'main_target' => 'Target',
        ]);

        $slot = app(WeeklySubmissionWindowService::class)->resolvePlanSlot($user);

        $this->assertTrue($slot->showAction());
        $this->assertFalse($slot->canCreateNew());
        $this->assertTrue($slot->canContinueDraft());
        $this->assertSame($week23->id, $slot->period->id);
        $this->assertTrue($slot->document->isDraft());
    }

    public function test_plan_candidate_periods_do_not_error_when_next_period_is_missing(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 10, 10, 0, 0));

        WorkPeriod::create([
            'year' => 2026,
            'week_number' => 24,
            'week_start_date' => '2026-06-08',
            'week_end_date' => '2026-06-14',
            'plan_deadline' => '2026-06-10',
            'report_deadline' => '2026-06-13',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo('create weekly plan');

        $slot = app(WeeklySubmissionWindowService::class)->resolvePlanSlot($user);

        $this->assertInstanceOf(\App\Data\WeeklySubmissionSlot::class, $slot);
        $this->assertTrue($slot->showAction());
        $this->assertSame(24, $slot->period?->week_number);
    }

    public function test_grace_entries_include_all_extended_plan_and_report_windows_user_still_owes(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 10, 10, 0, 0));

        WorkPeriod::create([
            'year' => 2026,
            'week_number' => 22,
            'week_start_date' => '2026-05-25',
            'week_end_date' => '2026-05-31',
            'plan_deadline' => '2026-06-10',
            'report_deadline' => '2026-06-10',
            'status' => WorkPeriod::STATUS_CLOSED,
        ]);

        WorkPeriod::create([
            'year' => 2026,
            'week_number' => 23,
            'week_start_date' => '2026-06-01',
            'week_end_date' => '2026-06-07',
            'plan_deadline' => '2026-06-10',
            'report_deadline' => '2026-06-10',
            'status' => WorkPeriod::STATUS_CLOSED,
        ]);

        WorkPeriod::create([
            'year' => 2026,
            'week_number' => 24,
            'week_start_date' => '2026-06-08',
            'week_end_date' => '2026-06-14',
            'plan_deadline' => '2026-06-10',
            'report_deadline' => '2026-06-06',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo(['create weekly plan', 'create weekly report']);

        $windows = app(WeeklySubmissionWindowService::class);

        $planWeeks = $windows->gracePlanEntries($user)->map(fn ($slot) => $slot->period->week_number)->all();
        $reportWeeks = $windows->graceReportEntries($user)->map(fn ($slot) => $slot->period->week_number)->all();

        $this->assertSame([24, 23, 22], $planWeeks);
        $this->assertSame([23, 22], $reportWeeks);
    }
}
