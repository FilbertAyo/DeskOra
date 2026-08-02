<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_user_without_permission_cannot_view_roles(): void
    {
        $actor = User::factory()->create(['status' => 'active']);

        $this->actingAs($actor)
            ->get(route('roles.index'))
            ->assertForbidden();
    }

    public function test_user_with_permission_can_view_roles(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->givePermissionTo('users management settings');

        $this->actingAs($admin)
            ->get(route('roles.index'))
            ->assertOk();
    }
}
