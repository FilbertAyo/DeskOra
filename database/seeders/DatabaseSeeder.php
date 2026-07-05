<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            UsersSeeder::class,
            WorkPeriodSeeder::class,
            LocalAdminSeeder::class,
        ]);
    }
}
