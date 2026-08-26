<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Additive demo account seeder. Safe on production: updateOrCreate only.
 *
 * Creates (or refreshes) a view-only user assigned solely to demo_viewer.
 * Disable with DEMO_VIEWER_ENABLED=false.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('demo.user.enabled')) {
            $this->command?->warn('DemoUserSeeder skipped (DEMO_VIEWER_ENABLED is false).');

            return;
        }

        $roleName = (string) config('demo.role', 'demo_viewer');
        $role = Role::firstOrCreate(['name' => $roleName]);

        $department = Department::query()->first();

        $email = (string) config('demo.user.email');
        $password = (string) config('demo.user.password');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) config('demo.user.name'),
                'title' => (string) config('demo.user.title'),
                'password' => Hash::make($password),
                'phone' => (string) config('demo.user.phone'),
                'department_id' => $department?->id,
                'status' => 'active',
                'notification_channel' => 'sms',
                'email_verified_at' => now(),
                'last_login_at' => now(),
            ]
        );

        $user->syncRoles([$role->name]);

        $this->command?->info("Demo viewer ready: {$email} / {$password} (view-only — mutations are blocked).");
    }
}
