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
        $modelMorphKey = $columnNames['model_morph_key'] ?? 'model_id';

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

        Department::create([
            'name' => 'Finance and Administration',
            'status' => 'active',
            'parent_id' => null,
        ]);

        Department::create([
            'name' => 'Communications and Advocacy',
            'status' => 'active',
            'parent_id' => null,
        ]);

        $user = User::create([
            'name' => 'Administrator',
            'email' => 'admin@adilisha.or.tz',
            'password' => Hash::make('password'),
            'phone' => '0000000000',
            'department_id' => $programsDept->id,
            'status' => 'active',
        ]);

        $superadminRole = Role::firstOrCreate(['name' => 'superadmin']);
        $user->syncRoles([$superadminRole->name]);

        $this->command->info('Users seeded: admin@adilisha.or.tz (password: password).');
        $this->command->info('Departments: Programs, Finance and Administration, Communications and Advocacy.');
    }
}
