<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Production-safe: create `task assignment` and merge onto management (+ superadmin if present).
 */
return new class extends Migration
{
    private const PERMISSION = 'task assignment';

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permissions') || ! DB::getSchemaBuilder()->hasTable('roles')) {
            return;
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        // Additive only: never sync/replace the role's existing permissions.
        foreach (['management', 'superadmin'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }

            if (! $role->hasPermissionTo(self::PERMISSION)) {
                $role->givePermissionTo(self::PERMISSION);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permissions')) {
            return;
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::query()
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->first();

        if ($permission) {
            $permission->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
