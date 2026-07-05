<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class LocalAdminSeeder extends Seeder
{
    /**
     * Seed a local-only superadmin account with every permission.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('LocalAdminSeeder skipped (not in local environment).');

            return;
        }

        $department = Department::query()->first()
            ?? Department::create([
                'name' => 'Programs',
                'status' => 'active',
                'parent_id' => null,
            ]);

        $superadminRole = Role::firstOrCreate(['name' => 'superadmin']);
        $superadminRole->syncPermissions(Permission::query()->pluck('name')->all());

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Local Admin',
                'title' => 'System Administrator',
                'password' => Hash::make('1234'),
                'phone' => '0000000000',
                'department_id' => $department->id,
                'status' => 'active',
                'notification_channel' => 'email',
                'email_verified_at' => now(),
            ]
        );

        $admin->syncRoles([$superadminRole->name]);

        $this->command?->info('Local admin ready: admin@example.com / 1234 (email verified, all permissions).');
    }
}
