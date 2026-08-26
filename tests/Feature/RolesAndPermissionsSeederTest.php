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
        $this->assertTrue(\Spatie\Permission\Models\Permission::query()->where('name', 'authorize official letter downloads')->exists());
        $this->assertFalse($staffRole->hasPermissionTo('authorize official letter downloads'));
    }

    public function test_inventory_roles_are_created_with_expected_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $viewer = Role::findByName('inventory_viewer');
        $officer = Role::findByName('inventory_officer');
        $approver = Role::findByName('inventory_approver');
        $admin = Role::findByName('inventory_admin');
        $clerk = Role::findByName('inventory_store_clerk');
        $assetOfficer = Role::findByName('inventory_asset_officer');
        $staff = Role::findByName('staff');
        $superadmin = Role::findByName('superadmin');

        $this->assertTrue($viewer->hasPermissionTo('view inventory'));
        $this->assertFalse($viewer->hasPermissionTo('manage inventory assets'));

        $this->assertTrue($officer->hasPermissionTo('manage inventory receipts'));
        $this->assertTrue($officer->hasPermissionTo('manage inventory borrowing'));
        $this->assertFalse($officer->hasPermissionTo('approve inventory requests'));
        $this->assertFalse($officer->hasPermissionTo('manage inventory settings'));

        $this->assertTrue($approver->hasPermissionTo('approve inventory requests'));
        $this->assertFalse($approver->hasPermissionTo('manage inventory assets'));

        $this->assertTrue($admin->hasPermissionTo('manage inventory settings'));
        $this->assertTrue($admin->hasPermissionTo('approve inventory requests'));

        $this->assertTrue($clerk->hasPermissionTo('manage inventory stock'));
        $this->assertFalse($clerk->hasPermissionTo('manage inventory assets'));

        $this->assertTrue($assetOfficer->hasPermissionTo('manage inventory assets'));
        $this->assertFalse($assetOfficer->hasPermissionTo('manage inventory stock'));

        $this->assertFalse($staff->hasPermissionTo('view inventory'));
        $this->assertTrue($superadmin->hasPermissionTo('view inventory'));
        $this->assertTrue($superadmin->hasPermissionTo('approve inventory requests'));

        $demoViewer = Role::findByName('demo_viewer');
        $this->assertTrue($demoViewer->hasPermissionTo('view all documents'));
        $this->assertTrue($demoViewer->hasPermissionTo('users management settings'));
        $this->assertTrue($demoViewer->hasPermissionTo('manage inventory assets'));
        $this->assertSame(
            $superadmin->permissions()->count(),
            $demoViewer->permissions()->count()
        );
    }

    public function test_reseed_does_not_assign_inventory_roles_to_existing_users(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = \App\Models\User::factory()->create();
        $user->assignRole('staff');

        $this->seed(RolesAndPermissionsSeeder::class);

        $user->refresh();
        $this->assertTrue($user->hasRole('staff'));
        $this->assertFalse($user->hasRole('inventory_officer'));
        $this->assertFalse($user->hasRole('inventory_admin'));
        $this->assertFalse($user->can('view inventory'));
    }

    public function test_reseed_preserves_extra_permissions_on_inventory_officer(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $officer = Role::findByName('inventory_officer');
        $officer->givePermissionTo('manage inventory settings');

        $this->seed(RolesAndPermissionsSeeder::class);

        $officer = $officer->fresh();
        $this->assertTrue($officer->hasPermissionTo('manage inventory settings'));
        $this->assertTrue($officer->hasPermissionTo('manage inventory receipts'));
        $this->assertFalse($officer->hasPermissionTo('approve inventory requests'));
    }
}
