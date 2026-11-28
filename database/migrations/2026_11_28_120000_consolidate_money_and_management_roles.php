<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * One-time production consolidation:
 * - Expand budget_reviewer / budget_approver / budget_authorizer across weekly
 *   budget, petty cash, and staff requisition.
 * - Move document approval onto management; retire document_approver.
 * - Migrate users off per-module petty/requisition roles, then delete those roles.
 *
 * Does not create inventory or other optional roles, so admin-deleted roles
 * stay gone after deploy stops running RolesAndPermissionsSeeder.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private const ROLE_BASELINES = [
        'management' => [
            'approve documents',
            'view all documents',
        ],
        'budget_reviewer' => [
            'view weekly budgets',
            'review weekly budgets',
            'review pettycash',
            'review staff requisition',
        ],
        'budget_approver' => [
            'view weekly budgets',
            'approve weekly budgets',
            'approve pettycash',
            'approve staff requisition',
        ],
        'budget_authorizer' => [
            'view weekly budgets',
            'authorize weekly budgets',
            'authorize pettycash',
            'authorize staff requisition',
            'download staff requisition',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const OBSOLETE_ROLE_MIGRATION = [
        'document_approver' => 'management',
        'requisition_reviewer' => 'budget_reviewer',
        'requisition_approver' => 'budget_approver',
        'requisition_authorizer' => 'budget_authorizer',
        'pettycash_reviewer' => 'budget_reviewer',
        'pettycash_approver' => 'budget_approver',
        'pettycash_authorizer' => 'budget_authorizer',
    ];

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('roles') || ! DB::getSchemaBuilder()->hasTable('permissions')) {
            return;
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissionNames = collect(self::ROLE_BASELINES)->flatten()->unique()->values()->all();
        foreach ($permissionNames as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::ROLE_BASELINES as $roleName => $baseline) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $merged = collect($baseline)
                ->merge($role->permissions()->pluck('name'))
                ->unique()
                ->values()
                ->all();
            $role->syncPermissions($merged);
        }

        foreach (self::OBSOLETE_ROLE_MIGRATION as $obsoleteName => $targetName) {
            $obsolete = Role::query()->where('name', $obsoleteName)->where('guard_name', 'web')->first();
            if (! $obsolete) {
                continue;
            }

            $target = Role::firstOrCreate(['name' => $targetName, 'guard_name' => 'web']);

            $userIds = DB::table('model_has_roles')
                ->where('role_id', $obsolete->id)
                ->where('model_type', User::class)
                ->pluck('model_id');

            foreach ($userIds as $userId) {
                $alreadyHasTarget = DB::table('model_has_roles')
                    ->where('role_id', $target->id)
                    ->where('model_id', $userId)
                    ->where('model_type', User::class)
                    ->exists();

                if (! $alreadyHasTarget) {
                    DB::table('model_has_roles')->insert([
                        'role_id' => $target->id,
                        'model_id' => $userId,
                        'model_type' => User::class,
                    ]);
                }
            }

            DB::table('model_has_roles')->where('role_id', $obsolete->id)->delete();
            DB::table('role_has_permissions')->where('role_id', $obsolete->id)->delete();
            $obsolete->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Irreversible data consolidation — roles and user assignments are not restored.
    }
};
