<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        DB::table($tableNames['model_has_roles'])
            ->where('model_type', User::class)
            ->delete();
        DB::table($tableNames['model_has_permissions'])
            ->where('model_type', User::class)
            ->delete();

        User::query()->delete();

        while (Department::whereDoesntHave('children')->delete() > 0) {
            //
        }

        $programsDept = Department::create([
            'name' => 'Programs',
            'status' => 'active',
            'parent_id' => null,
        ]);

        $financeDept = Department::create([
            'name' => 'Finance and Administration',
            'status' => 'active',
            'parent_id' => null,
        ]);

        Department::create([
            'name' => 'Communications and Advocacy',
            'status' => 'active',
            'parent_id' => null,
        ]);

        $superadminRole = Role::firstOrCreate(['name' => 'superadmin']);
        $documentApproverRole = Role::firstOrCreate(['name' => 'document_approver']);
        $budgetOperatorRole = Role::firstOrCreate(['name' => 'budget_operator']);
        $staffRole = Role::firstOrCreate(['name' => 'staff']);

        $admin = User::create([
            'name' => 'Administrator',
            'title' => 'System Administrator',
            'email' => 'admin@adilisha.or.tz',
            'password' => Hash::make('password'),
            'phone' => '0000000000',
            'department_id' => $programsDept->id,
            'status' => 'active',
            'notification_channel' => 'email',
        ]);
        $admin->syncRoles([$superadminRole->name]);

        $ceo = User::create([
            'name' => 'Chief Executive Officer',
            'title' => 'CEO',
            'email' => 'ceo@adilisha.or.tz',
            'password' => Hash::make('password'),
            'phone' => '0700000001',
            'department_id' => $programsDept->id,
            'status' => 'active',
            'notification_channel' => 'email',
        ]);
        $ceo->syncRoles([$documentApproverRole->name]);

        $financeManager = User::create([
            'name' => 'Finance Manager',
            'title' => 'Finance Manager',
            'email' => 'finance@adilisha.or.tz',
            'password' => Hash::make('password'),
            'phone' => '0700000002',
            'department_id' => $financeDept->id,
            'status' => 'active',
            'notification_channel' => 'email',
        ]);
        $financeManager->syncRoles([$budgetOperatorRole->name]);

        $staff = User::create([
            'name' => 'Programs Officer',
            'title' => 'Programs Officer',
            'email' => 'staff@adilisha.or.tz',
            'password' => Hash::make('password'),
            'phone' => '0700000003',
            'department_id' => $programsDept->id,
            'status' => 'active',
            'notification_channel' => 'email',
        ]);
        $staff->syncRoles([$staffRole->name]);

        $this->command->info('Users seeded (password for all: password):');
        $this->command->info('  admin@adilisha.or.tz — superadmin');
        $this->command->info('  ceo@adilisha.or.tz — document approver (weekly plan/report review emails)');
        $this->command->info('  finance@adilisha.or.tz — budget operator (weekly plan budget emails)');
        $this->command->info('  staff@adilisha.or.tz — staff (create weekly plan/report)');
        $this->command->info('Departments: Programs, Finance and Administration, Communications and Advocacy.');
    }
}
