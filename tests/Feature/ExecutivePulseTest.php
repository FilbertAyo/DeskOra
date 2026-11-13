<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkPeriod;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExecutivePulseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        WorkPeriod::factory()->create(['status' => WorkPeriod::STATUS_OPEN]);
    }

    public function test_guest_cannot_view_pulse(): void
    {
        $this->get(route('executive-pulse.index'))->assertRedirect();
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)
            ->get(route('executive-pulse.index'))
            ->assertForbidden();
    }

    public function test_manager_can_view_pulse_and_pdf_route(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('view reports');

        $this->actingAs($user)
            ->get(route('executive-pulse.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ExecutivePulse/Index')
                ->has('snapshot.highlights')
                ->has('downloadUrl'));

        $this->actingAs($user)
            ->get(route('executive-pulse.download'))
            ->assertOk();
    }
}

