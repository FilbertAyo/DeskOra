<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandPaletteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guest_cannot_fetch_commands(): void
    {
        $this->getJson(route('commands.index'))->assertUnauthorized();
    }

    public function test_active_user_receives_permission_gated_commands(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('review pettycash');

        $response = $this->actingAs($user)->getJson(route('commands.index'));

        $response->assertOk();
        $commands = $response->json('commands');
        $this->assertIsArray($commands);
        $this->assertNotEmpty($commands);

        $ids = collect($commands)->pluck('id')->all();
        $this->assertContains('dashboard', $ids);
        $this->assertContains('my_approval_queue', $ids);
        $this->assertContains('review_petty_cash', $ids);
        $this->assertNotContains('approve_petty_cash', $ids);
        $this->assertNotContains('users_settings', $ids);

        foreach ($commands as $command) {
            $this->assertArrayHasKey('label', $command);
            $this->assertArrayHasKey('url', $command);
            $this->assertNotEmpty($command['url']);
        }
    }

    public function test_inactive_user_is_forbidden(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);

        $this->actingAs($user)
            ->getJson(route('commands.index'))
            ->assertForbidden();
    }
}
