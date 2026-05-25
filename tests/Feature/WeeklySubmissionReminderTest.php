<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Models\WorkPeriod;
use App\Services\WeeklySubmissionReminderService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklySubmissionReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_reminder_includes_plan_cta_before_plan_deadline(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 4, 2, 10, 0, 0));
        [$current, $next] = $this->createCurrentAndNextPeriods();
        $user = $this->makeUserWithWeeklyPermissions();
        $svc = app(WeeklySubmissionReminderService::class);

        $r = $svc->forUser($user);

        $this->assertTrue($r->showToast);
        $this->assertNotNull($r->plan);
        $this->assertTrue($r->plan->showCta);
        $this->assertFalse($r->plan->isMissed);
        $this->assertSame($next->id, $r->plan->period->id);
        $this->assertNotNull($r->report);
        $this->assertTrue($r->report->showCta);
        $this->assertSame($current->id, $r->report->period->id);
    }

    public function test_plan_still_has_cta_after_deadline_when_period_is_open(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 4, 6, 10, 0, 0));
        [, $next] = $this->createCurrentAndNextPeriods();
        $user = $this->makeUserWithWeeklyPermissions();
        $svc = app(WeeklySubmissionReminderService::class);

        $r = $svc->forUser($user);

        $this->assertNotNull($r->plan);
        $this->assertFalse($r->plan->isMissed);
        $this->assertTrue($r->plan->showCta);
        $this->assertEmpty($r->missedMessages);
        $this->assertTrue($r->showToast);
    }

    public function test_plan_is_missed_when_work_period_is_closed(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 4, 6, 10, 0, 0));
        [, $next] = $this->createCurrentAndNextPeriods();
        $next->update(['status' => WorkPeriod::STATUS_CLOSED]);
        $user = $this->makeUserWithWeeklyPermissions();
        $svc = app(WeeklySubmissionReminderService::class);

        $r = $svc->forUser($user);

        $this->assertNotNull($r->plan);
        $this->assertTrue($r->plan->isMissed);
        $this->assertFalse($r->plan->showCta);
        $this->assertNotEmpty($r->missedMessages);
    }

    public function test_plan_is_still_open_on_saturday_before_2359_cutoff(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 4, 5, 12, 0, 0));
        [, $next] = $this->createCurrentAndNextPeriods();
        $user = $this->makeUserWithWeeklyPermissions();
        $svc = app(WeeklySubmissionReminderService::class);

        $r = $svc->forUser($user);

        $this->assertNotNull($r->plan);
        $this->assertTrue($r->plan->showCta);
        $this->assertFalse($r->plan->isMissed);
        $this->assertSame($next->id, $r->plan->period->id);
    }

    public function test_submitted_plan_removes_plan_from_toast(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 4, 2, 10, 0, 0));
        [, $next] = $this->createCurrentAndNextPeriods();
        $user = $this->makeUserWithWeeklyPermissions();

        Document::create([
            'user_id' => $user->id,
            'period_id' => $next->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Target',
        ]);

        $r = app(WeeklySubmissionReminderService::class)->forUser($user);

        $this->assertNull($r->plan);
        $this->assertNotNull($r->report);
        $this->assertTrue($r->showToast);
    }

    public function test_no_toast_when_both_weeklies_submitted(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 4, 2, 10, 0, 0));
        [$current, $next] = $this->createCurrentAndNextPeriods();
        $user = $this->makeUserWithWeeklyPermissions();

        Document::create([
            'user_id' => $user->id,
            'period_id' => $next->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Plan',
            'state' => Document::STATE_SUBMITTED,
            'main_target' => 'Target',
        ]);
        Document::create([
            'user_id' => $user->id,
            'period_id' => $current->id,
            'type' => Document::TYPE_WEEKLY_REPORT,
            'title' => 'Report',
            'state' => Document::STATE_SUBMITTED,
        ]);

        $r = app(WeeklySubmissionReminderService::class)->forUser($user);

        $this->assertFalse($r->showToast);
        $this->assertNull($r->plan);
        $this->assertNull($r->report);
    }

    public function test_returned_plan_after_deadline_still_has_cta(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 4, 6, 10, 0, 0));
        [, $next] = $this->createCurrentAndNextPeriods();
        $user = $this->makeUserWithWeeklyPermissions();

        Document::create([
            'user_id' => $user->id,
            'period_id' => $next->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Plan',
            'state' => Document::STATE_DRAFT,
            'main_target' => 'Target',
            'review_rejection_reason' => 'Please fix objectives.',
        ]);

        $r = app(WeeklySubmissionReminderService::class)->forUser($user);

        $this->assertNotNull($r->plan);
        $this->assertTrue($r->plan->showCta);
        $this->assertFalse($r->plan->isMissed);
        $this->assertTrue($r->showToast);
    }

    public function test_allows_submit_weekly_plan_after_deadline_when_period_is_open(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 4, 6, 10, 0, 0));
        [, $next] = $this->createCurrentAndNextPeriods();
        $user = $this->makeUserWithWeeklyPermissions();

        $draft = Document::create([
            'user_id' => $user->id,
            'period_id' => $next->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
            'title' => 'Old draft',
            'state' => Document::STATE_DRAFT,
            'main_target' => 'Target',
        ]);

        $svc = app(WeeklySubmissionReminderService::class);

        $this->assertFalse($svc->blocksNewWeeklyDocument(Document::TYPE_WEEKLY_PLAN, $next->fresh()));
        $this->assertTrue($svc->allowsSubmitWeeklyDocument($draft->fresh()));
    }

    public function test_store_blocks_new_weekly_plan_when_work_period_is_closed(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 4, 6, 10, 0, 0));
        [, $next] = $this->createCurrentAndNextPeriods();
        $next->update(['status' => WorkPeriod::STATUS_CLOSED]);
        $user = User::factory()->create();
        $user->givePermissionTo('create weekly plan');

        $this->assertTrue(
            app(WeeklySubmissionReminderService::class)->blocksNewWeeklyDocument(Document::TYPE_WEEKLY_PLAN, $next->fresh()),
        );

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'type' => Document::TYPE_WEEKLY_PLAN,
            'period_id' => (string) $next->id,
            'main_target' => 'Closed period',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'This work period is closed. Please contact your administrator.');
        $this->assertDatabaseMissing('documents', [
            'user_id' => $user->id,
            'type' => Document::TYPE_WEEKLY_PLAN,
        ]);
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
            'plan_deadline' => '2025-04-05',
            'report_deadline' => '2025-04-12',
            'status' => WorkPeriod::STATUS_OPEN,
        ]);

        return [$current, $next];
    }

    private function makeUserWithWeeklyPermissions(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['create weekly plan', 'create weekly report']);

        return $user;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
