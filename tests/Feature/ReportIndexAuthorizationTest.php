<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportIndexAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_user_without_permission_cannot_view_reports(): void
    {
        $actor = User::factory()->create(['status' => 'active']);

        $this->actingAs($actor)
            ->get(route('reports'))
            ->assertForbidden();
    }

    public function test_user_with_permission_can_view_reports_index(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('view reports');

        $this->actingAs($user)
            ->get(route('reports'))
            ->assertOk();
    }
}
