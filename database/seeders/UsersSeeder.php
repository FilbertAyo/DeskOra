<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample departments if they don't exist
        $departments = [
            ['name' => 'Administration', 'status' => 'active'],
            ['name' => 'Operations', 'status' => 'active'],
            ['name' => 'Finance', 'status' => 'active'],
            ['name' => 'Human Resources', 'status' => 'active'],
            ['name' => 'IT', 'status' => 'active'],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(
                ['name' => $dept['name']],
                ['status' => $dept['status']]
            );
        }

        // Get departments
        $adminDept = Department::where('name', 'Administration')->first();
        $opsDept = Department::where('name', 'Operations')->first();
        $financeDept = Department::where('name', 'Finance')->first();
        $hrDept = Department::where('name', 'Human Resources')->first();
        $itDept = Department::where('name', 'IT')->first();

        // Define permission sets
        $adminPermissions = Permission::all()->pluck('name')->toArray();
        
        $staffPermissions = [
            'create weekly plan',
            'create weekly report',
            'create monthly report',
            'view own documents',
            'edit own documents',
            'delete own documents',
            'submit documents',
            'view dashboard',
            'request pettycash',
        ];

        $actionLogPermissions = [
            'view own documents',
            'edit own documents',
            'delete own documents',
            'submit documents',
            'view action logs',
            'create action logs',
            'manage action logs',
            'view dashboard',
        ];

        $approverPermissions = Permission::all()->pluck('name')->toArray();

        // ============================================
        // ADMIN USERS (All permissions)
        // ============================================
        $adminUsers = [
            [
                'name' => 'System Administrator',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'phone' => '0712345678',
                'department_id' => $adminDept->id,
                'status' => 'active',
                'permissions' => $adminPermissions,
            ],
        ];

        foreach ($adminUsers as $userData) {
            $permissions = $userData['permissions'];
            unset($userData['permissions']);

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            $user->syncPermissions($permissions);
        }

        // ============================================
        // STAFF USERS (Create documents - initial permissions)
        // ============================================
        $staffUsers = [
            [
                'name' => 'John Doe',
                'email' => 'staff1@example.com',
                'password' => Hash::make('password'),
                'phone' => '0711111111',
                'department_id' => $opsDept->id,
                'status' => 'active',
                'permissions' => $staffPermissions,
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'staff2@example.com',
                'password' => Hash::make('password'),
                'phone' => '0711111112',
                'department_id' => $opsDept->id,
                'status' => 'active',
                'permissions' => $staffPermissions,
            ],
            [
                'name' => 'Michael Johnson',
                'email' => 'staff3@example.com',
                'password' => Hash::make('password'),
                'phone' => '0711111113',
                'department_id' => $financeDept->id,
                'status' => 'active',
                'permissions' => $staffPermissions,
            ],
            [
                'name' => 'Sarah Williams',
                'email' => 'staff4@example.com',
                'password' => Hash::make('password'),
                'phone' => '0711111114',
                'department_id' => $hrDept->id,
                'status' => 'active',
                'permissions' => $staffPermissions,
            ],
        ];

        foreach ($staffUsers as $userData) {
            $permissions = $userData['permissions'];
            unset($userData['permissions']);

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            $user->syncPermissions($permissions);
        }

        // ============================================
        // ACTION LOG USERS (Create/manage action logs)
        // ============================================
        $actionLogUsers = [
            [
                'name' => 'David Brown',
                'email' => 'minutes1@example.com',
                'password' => Hash::make('password'),
                'phone' => '0722222221',
                'department_id' => $adminDept->id,
                'status' => 'active',
                'permissions' => $actionLogPermissions,
            ],
            [
                'name' => 'Emily Davis',
                'email' => 'minutes2@example.com',
                'password' => Hash::make('password'),
                'phone' => '0722222222',
                'department_id' => $opsDept->id,
                'status' => 'active',
                'permissions' => $actionLogPermissions,
            ],
        ];

        foreach ($actionLogUsers as $userData) {
            $permissions = $userData['permissions'];
            unset($userData['permissions']);

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            $user->syncPermissions($permissions);
        }

        // ============================================
        // APPROVER USERS (Can approve documents - all permissions)
        // ============================================
        $approverUsers = [
            [
                'name' => 'Chief Executive Officer',
                'email' => 'approver1@example.com',
                'password' => Hash::make('password'),
                'phone' => '0744444441',
                'department_id' => $adminDept->id,
                'status' => 'active',
                'permissions' => $approverPermissions,
            ],
            [
                'name' => 'Operations Manager',
                'email' => 'approver2@example.com',
                'password' => Hash::make('password'),
                'phone' => '0744444442',
                'department_id' => $opsDept->id,
                'status' => 'active',
                'permissions' => $approverPermissions,
            ],
            [
                'name' => 'Finance Director',
                'email' => 'approver3@example.com',
                'password' => Hash::make('password'),
                'phone' => '0744444443',
                'department_id' => $financeDept->id,
                'status' => 'active',
                'permissions' => $approverPermissions,
            ],
        ];

        foreach ($approverUsers as $userData) {
            $permissions = $userData['permissions'];
            unset($userData['permissions']);

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            $user->syncPermissions($permissions);
        }

        $this->command->info('Users seeded successfully!');
        $this->command->info('Default password for all users: password');
    }
}
