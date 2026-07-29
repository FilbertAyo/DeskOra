<?php

namespace Tests\Feature;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseed_preserves_manually_assigned_staff_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $staffRole = Role::findByName('staff');
        $staffRole->givePermissionTo('manage official letters');

        $this->assertTrue($staffRole->fresh()->hasPermissionTo('manage official letters'));
        $this->assertTrue($staffRole->fresh()->hasPermissionTo('create weekly plan'));

        // Simulate deploy.sh re-running the seeder
        $this->seed(RolesAndPermissionsSeeder::class);

        $staffRole = $staffRole->fresh();
        $this->assertTrue($staffRole->hasPermissionTo('manage official letters'));
        $this->assertTrue($staffRole->hasPermissionTo('create weekly plan'));
        $this->assertTrue($staffRole->hasPermissionTo('create weekly report'));
    }

    public function test_reseed_still_ensures_staff_baseline_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $staffRole = Role::findByName('staff');
        $staffRole->syncPermissions(['manage official letters']);

        $this->seed(RolesAndPermissionsSeeder::class);

        $staffRole = $staffRole->fresh();
        $this->assertTrue($staffRole->hasPermissionTo('manage official letters'));
        $this->assertTrue($staffRole->hasPermissionTo('create weekly plan'));
        $this->assertTrue($staffRole->hasPermissionTo('create weekly report'));
    }
}
