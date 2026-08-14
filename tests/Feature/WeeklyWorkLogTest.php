<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WeeklyWorkLog;
use App\Models\WorkPeriod;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyWorkLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_user_can_save_today_work_log(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $period = WorkPeriod::factory()->create([
            'status' => WorkPeriod::STATUS_OPEN,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'week_end_date' => now()->endOfWeek()->toDateString(),
        ]);

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('weekly-work-logs.store'), [
                'period_id' => $period->id,
                'log_date' => now()->toDateString(),
                'done' => 'Completed field visit',
                'blocker' => 'Vehicle delay',
                'next' => 'Write report',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('weekly_work_logs', [
            'user_id' => $user->id,
            'period_id' => $period->id,
            'done' => 'Completed field visit',
            'blocker' => 'Vehicle delay',
        ]);
    }

    public function test_index_redirects_to_dashboard(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        WorkPeriod::factory()->create([
            'status' => WorkPeriod::STATUS_OPEN,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'week_end_date' => now()->endOfWeek()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('weekly-work-logs.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_user_cannot_update_another_users_log(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);
        $period = WorkPeriod::factory()->create([
            'status' => WorkPeriod::STATUS_OPEN,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'week_end_date' => now()->endOfWeek()->toDateString(),
        ]);

        $log = WeeklyWorkLog::create([
            'user_id' => $owner->id,
            'period_id' => $period->id,
            'log_date' => now()->toDateString(),
            'done' => 'Owner work',
        ]);

        $this->actingAs($other)
            ->put(route('weekly-work-logs.update', $log), [
                'done' => 'Hijacked',
            ])
            ->assertForbidden();
    }
}
