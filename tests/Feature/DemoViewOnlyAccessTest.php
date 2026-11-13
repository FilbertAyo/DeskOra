<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DemoViewOnlyAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_demo_viewer_can_open_admin_and_settings_pages(): void
    {
        $demo = $this->demoUser();

        $this->actingAs($demo)
            ->get(route('admin.index'))
            ->assertOk();

        $this->actingAs($demo)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Roles/Index')
                ->where('auth.user.isDemo', true));

        $this->actingAs($demo)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->where('auth.user.isDemo', true));
    }

    public function test_demo_viewer_cannot_submit_mutating_requests(): void
    {
        $demo = $this->demoUser();

        $this->actingAs($demo)
            ->from(route('admin.index'))
            ->post(route('admin.store'), [
                'name' => 'Should Not Create',
                'email' => 'blocked@example.com',
                'phone' => '0700000099',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect(route('admin.index'))
            ->assertSessionHas('warning', config('demo.message'));

        $this->assertDatabaseMissing('users', [
            'email' => 'blocked@example.com',
        ]);
    }

    public function test_demo_viewer_json_mutations_are_forbidden(): void
    {
        $demo = $this->demoUser();

        $this->actingAs($demo)
            ->postJson(route('analytics.events.store'), [
                'feature_key' => 'feature.dashboard.performance_details',
            ])
            ->assertForbidden()
            ->assertJson([
                'message' => config('demo.message'),
            ]);
    }

    public function test_demo_viewer_can_still_logout(): void
    {
        $demo = $this->demoUser();

        $this->actingAs($demo)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_staff_mutations_are_not_blocked_by_demo_middleware(): void
    {
        $staff = User::factory()->create(['status' => 'active']);
        $staff->assignRole('staff');

        $this->actingAs($staff)
            ->postJson(route('analytics.events.store'), [
                'feature_key' => 'feature.dashboard.performance_details',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_demo_viewer_skips_login_otp(): void
    {
        Notification::fake();

        $demo = $this->demoUser(['last_login_at' => null]);

        $this->post('/login', [
            'email' => $demo->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        Notification::assertNothingSent();
    }

    public function test_demo_viewer_role_cannot_be_updated_or_deleted(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->givePermissionTo('users management settings');

        $role = Role::findByName('demo_viewer');

        $this->actingAs($admin)
            ->from(route('roles.index'))
            ->put(route('roles.update', $role), [
                'name' => 'renamed_demo',
                'permissions' => [],
            ])
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['name' => 'demo_viewer']);

        $this->actingAs($admin)
            ->delete(route('roles.destroy', $role))
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['name' => 'demo_viewer']);
    }

    public function test_users_without_demo_role_are_not_treated_as_demo_viewers(): void
    {
        $staff = User::factory()->create(['status' => 'active']);
        $staff->givePermissionTo('create weekly plan');

        $this->assertFalse($staff->fresh()->isDemoViewer());
        $this->assertSame([], $staff->fresh()->getRoleNames()->all());
        $this->assertTrue($staff->fresh()->canReceiveGracePeriodWorkEmail());
    }

    public function test_demo_user_seeder_creates_shareable_account(): void
    {
        $this->seed(DemoUserSeeder::class);

        $user = User::query()->where('email', config('demo.user.email'))->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->isDemoViewer());
        $this->assertTrue($user->can('view all documents'));
        $this->assertTrue($user->can('users management settings'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function demoUser(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'status' => 'active',
            'last_login_at' => now()->subHour(),
        ], $overrides));

        $user->assignRole('demo_viewer');

        return $user->fresh();
    }
}
