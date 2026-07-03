<?php

namespace Tests\Feature;

use App\Exceptions\StaffAppreciationException;
use App\Models\Nomination;
use App\Models\NominationParticipation;
use App\Models\NominationPeriod;
use App\Models\User;
use App\Services\StaffAppreciationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StaffAppreciationServiceTest extends TestCase
{
    use RefreshDatabase;

    private StaffAppreciationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Use the real application timezone so window math matches production.
        $this->service = app(StaffAppreciationService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_window_is_closed_before_the_26th(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 25, 23, 59, 0));

        $this->assertNull($this->service->currentOpenPeriod());
    }

    public function test_window_opens_on_the_26th_and_creates_period(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 26, 0, 0, 0));

        $period = $this->service->currentOpenPeriod();

        $this->assertNotNull($period);
        $this->assertSame(2026, $period->year);
        $this->assertSame(7, $period->month);
        $this->assertSame('2026-07-26 00:00:00', $period->opens_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-31 23:59:59', $period->closes_at->format('Y-m-d H:i:s'));
    }

    public function test_window_is_open_through_the_last_day(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 31, 23, 59, 30));

        $this->assertNotNull($this->service->currentOpenPeriod());
    }

    public function test_open_now_activates_window_before_the_26th(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 3, 12, 0, 0));

        // Automatic window is not open yet this early in the month.
        $this->assertNull($this->service->currentOpenPeriod());

        $period = $this->service->openNow();

        $this->assertSame(NominationPeriod::STATUS_OPEN, $period->status);
        $this->assertSame(7, $period->month);
        $this->assertSame('2026-07-31 23:59:59', $period->closes_at->format('Y-m-d H:i:s'));

        // Now the manually opened period is recognised as active.
        $active = $this->service->currentOpenPeriod();
        $this->assertNotNull($active);
        $this->assertSame($period->id, $active->id);

        $user = User::factory()->create(['status' => 'active']);
        $this->assertTrue($this->service->shouldPromptUser($user));
    }

    public function test_should_prompt_only_active_users_who_have_not_responded(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 27, 10, 0, 0));

        $active = User::factory()->create(['status' => 'active']);
        $inactive = User::factory()->create(['status' => 'inactive']);

        $this->assertTrue($this->service->shouldPromptUser($active));
        $this->assertFalse($this->service->shouldPromptUser($inactive));

        $this->service->skip($active);
        $this->assertFalse($this->service->shouldPromptUser($active->fresh()));
    }

    public function test_record_nomination_writes_decoupled_rows(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 27, 10, 0, 0));

        $actor = User::factory()->create();
        $nominee = User::factory()->create();

        $period = $this->service->recordNomination($actor, $nominee, '  Great teamwork  ');

        $this->assertDatabaseHas('nomination_participations', [
            'nomination_period_id' => $period->id,
            'user_id' => $actor->id,
            'status' => NominationParticipation::STATUS_NOMINATED,
        ]);

        $this->assertDatabaseHas('nominations', [
            'nomination_period_id' => $period->id,
            'nominee_id' => $nominee->id,
            'reason' => 'Great teamwork',
        ]);
    }

    public function test_nominations_table_has_no_nominator_column(): void
    {
        // The core anonymity guarantee: a nomination cannot reference its author.
        $this->assertFalse(Schema::hasColumn('nominations', 'user_id'));
        $this->assertFalse(Schema::hasColumn('nominations', 'nominator_id'));
        $this->assertFalse(Schema::hasColumn('nominations', 'created_by'));
    }

    public function test_user_can_only_respond_once_per_period(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 27, 10, 0, 0));

        $actor = User::factory()->create();
        $nominee = User::factory()->create();

        $this->service->recordNomination($actor, $nominee);

        $this->expectException(StaffAppreciationException::class);
        $this->service->recordNomination($actor, $nominee);
    }

    public function test_skip_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 27, 10, 0, 0));

        $actor = User::factory()->create();

        $this->service->skip($actor);
        $this->service->skip($actor);

        $this->assertSame(1, NominationParticipation::where('user_id', $actor->id)->count());
    }

    public function test_record_nomination_outside_window_throws(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 10, 10, 0, 0));

        $actor = User::factory()->create();
        $nominee = User::factory()->create();

        $this->expectException(StaffAppreciationException::class);
        $this->service->recordNomination($actor, $nominee);
    }

    public function test_ensure_and_close_closes_ended_windows(): void
    {
        $ended = NominationPeriod::create([
            'year' => 2026,
            'month' => 6,
            'opens_at' => Carbon::create(2026, 6, 26, 0, 0, 0),
            'closes_at' => Carbon::create(2026, 6, 30, 23, 59, 59),
            'status' => NominationPeriod::STATUS_OPEN,
        ]);

        Carbon::setTestNow(Carbon::create(2026, 7, 27, 0, 5, 0));

        $result = $this->service->ensureAndCloseFor();

        $this->assertSame(1, $result['closed']);
        $this->assertSame(NominationPeriod::STATUS_CLOSED, $ended->fresh()->status);
        $this->assertNotNull($result['ensured']);
        $this->assertSame(7, $result['ensured']->month);
    }

    public function test_stats_and_tallies(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 27, 10, 0, 0));

        $topNominee = User::factory()->create();
        $otherNominee = User::factory()->create();
        $voters = User::factory()->count(3)->create();

        $period = $this->service->recordNomination($voters[0], $topNominee, 'Helpful');
        $this->service->recordNomination($voters[1], $topNominee);
        $this->service->recordNomination($voters[2], $otherNominee);

        $stats = $this->service->participationStats($period);
        $this->assertSame(3, $stats['nominated']);

        $tallies = $this->service->nomineeTallies($period);
        $this->assertSame($topNominee->id, $tallies->first()['user']->id);
        $this->assertSame(2, $tallies->first()['votes']);

        $this->assertSame(1, $this->service->reasons($period)->count());
    }
}
