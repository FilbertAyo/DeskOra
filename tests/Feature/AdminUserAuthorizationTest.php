<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_user_without_permission_cannot_list_users(): void
    {
        $actor = User::factory()->create(['status' => 'active']);

        $this->actingAs($actor)
            ->get(route('admin.index'))
            ->assertForbidden();
    }

    public function test_user_without_permission_cannot_create_or_edit_users(): void
    {
        $actor = User::factory()->create(['status' => 'active']);
        $target = User::factory()->create(['status' => 'active']);

        $this->actingAs($actor)
            ->get(route('admin.create'))
            ->assertForbidden();

        $this->actingAs($actor)
            ->get(route('admin.edit', $target))
            ->assertForbidden();

        $this->actingAs($actor)
            ->post(route('admin.activate', $target))
            ->assertForbidden();
    }

    public function test_user_with_permission_can_list_users(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->givePermissionTo('users management settings');

        $this->actingAs($admin)
            ->get(route('admin.index'))
            ->assertOk();
    }
}
