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
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'Full DatabaseSeeder must not run in production. It includes UsersSeeder, which deletes users. Use RolesAndPermissionsSeeder only (as deploy.sh does).'
            );
        }

        $this->call([
            RolesAndPermissionsSeeder::class,
            UsersSeeder::class,
            WorkPeriodSeeder::class,
            LocalAdminSeeder::class,
        ]);
    }
}
