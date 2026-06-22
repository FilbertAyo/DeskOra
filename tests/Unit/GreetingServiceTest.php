<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\GreetingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GreetingServiceTest extends TestCase
{
    use RefreshDatabase;

    private GreetingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(GreetingService::class);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_morning_salutation_at_start_of_morning_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-21 05:00:00', config('app.timezone')));

        $user = User::factory()->create(['name' => 'Ayo Mwangi']);

        $greeting = $this->service->forUser($user);

        $this->assertSame('Good Morning', $greeting['salutation']);
        $this->assertSame('morning', $greeting['slot']);
        $this->assertSame('Ayo', $greeting['firstName']);
        $this->assertStringStartsWith('Good Morning, Ayo!', $greeting['full']);
    }

    public function test_afternoon_salutation_at_noon(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-21 12:00:00', config('app.timezone')));

        $user = User::factory()->create(['name' => 'Ayo Mwangi']);

        $greeting = $this->service->forUser($user);

        $this->assertSame('Good Afternoon', $greeting['salutation']);
        $this->assertSame('afternoon', $greeting['slot']);
    }

    public function test_evening_salutation_at_seventeen_hundred(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-21 17:00:00', config('app.timezone')));

        $user = User::factory()->create(['name' => 'Ayo Mwangi']);

        $greeting = $this->service->forUser($user);

        $this->assertSame('Good Evening', $greeting['salutation']);
        $this->assertSame('evening', $greeting['slot']);
    }

    public function test_night_salutation_uses_hello(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-21 23:30:00', config('app.timezone')));

        $user = User::factory()->create(['name' => 'Ayo Mwangi']);

        $greeting = $this->service->forUser($user);

        $this->assertSame('Hello', $greeting['salutation']);
        $this->assertSame('night', $greeting['slot']);
    }

    public function test_first_name_extraction_uses_first_word(): void
    {
        $this->assertSame('Ayo', $this->service->firstNameFrom('Ayo Mwangi'));
        $this->assertSame('there', $this->service->firstNameFrom(''));
        $this->assertSame('Single', $this->service->firstNameFrom('  Single  '));
    }

    public function test_same_user_and_slot_returns_cached_message(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-21 09:15:00', config('app.timezone')));

        $user = User::factory()->create(['name' => 'Ayo Mwangi']);

        $first = $this->service->forUser($user);
        $second = $this->service->forUser($user);

        $this->assertSame($first['message'], $second['message']);
        $this->assertSame($first['full'], $second['full']);
    }

    public function test_slot_change_produces_new_salutation(): void
    {
        $user = User::factory()->create(['name' => 'Ayo Mwangi']);

        Carbon::setTestNow(Carbon::parse('2026-06-21 11:30:00', config('app.timezone')));
        $morning = $this->service->forUser($user);

        Carbon::setTestNow(Carbon::parse('2026-06-21 13:30:00', config('app.timezone')));
        $afternoon = $this->service->forUser($user);

        $this->assertSame('Good Morning', $morning['salutation']);
        $this->assertSame('Good Afternoon', $afternoon['salutation']);
        $this->assertNotSame($morning['slot'], $afternoon['slot']);
    }

    public function test_resolve_slot_boundaries(): void
    {
        $tz = config('app.timezone');

        $this->assertSame(GreetingService::SLOT_MORNING, $this->service->resolveSlot(Carbon::parse('2026-06-21 05:00:00', $tz)));
        $this->assertSame(GreetingService::SLOT_MORNING, $this->service->resolveSlot(Carbon::parse('2026-06-21 11:59:00', $tz)));
        $this->assertSame(GreetingService::SLOT_AFTERNOON, $this->service->resolveSlot(Carbon::parse('2026-06-21 12:00:00', $tz)));
        $this->assertSame(GreetingService::SLOT_AFTERNOON, $this->service->resolveSlot(Carbon::parse('2026-06-21 16:59:00', $tz)));
        $this->assertSame(GreetingService::SLOT_EVENING, $this->service->resolveSlot(Carbon::parse('2026-06-21 17:00:00', $tz)));
        $this->assertSame(GreetingService::SLOT_EVENING, $this->service->resolveSlot(Carbon::parse('2026-06-21 21:59:00', $tz)));
        $this->assertSame(GreetingService::SLOT_NIGHT, $this->service->resolveSlot(Carbon::parse('2026-06-21 22:00:00', $tz)));
        $this->assertSame(GreetingService::SLOT_NIGHT, $this->service->resolveSlot(Carbon::parse('2026-06-21 04:59:00', $tz)));
    }
}
