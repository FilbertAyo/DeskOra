<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Additive roles/permissions seeder for local/dev and fresh installs.
 *
 * Creates missing permissions and the canonical role set, merges baseline
 * permissions onto known roles, and never deletes users or documents.
 * Extra permissions already on a role are kept.
 *
 * Deploy does not run this seeder — production role consolidation is handled
 * by a one-time migration so deleted custom roles are not recreated.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Legacy petty-cash stage permissions → dedicated names (requisition-style).
     *
     * @var array<string, string|null>
     */
    private const PETTY_PERMISSION_MIGRATION = [
        'first pettycash approval' => 'review pettycash',
        'last pettycash approval' => 'approve pettycash',
        'authorize requests' => 'authorize pettycash',
        'review requests' => null,
        'approve requests' => null,
    ];

    /**
     * Obsolete per-module roles → consolidated money / management roles.
     *
     * @var array<string, string>
     */
    public const OBSOLETE_ROLE_MIGRATION = [
        'document_approver' => 'management',
        'requisition_reviewer' => 'budget_reviewer',
        'requisition_approver' => 'budget_approver',
        'requisition_authorizer' => 'budget_authorizer',
        'pettycash_reviewer' => 'budget_reviewer',
        'pettycash_approver' => 'budget_approver',
        'pettycash_authorizer' => 'budget_authorizer',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ============================================
        // PETTY CASH, REQUISITIONS, REPORTS, SETTINGS
        // ============================================
        $existingPermissions = [
            'request pettycash',
            'view requested pettycash',
            'review pettycash',
            'approve pettycash',
            'authorize pettycash',
            'approve pettycash payments',
            'view cashflow movements',
            'view reports',
            'upload organization reports',
            'view notification settings',
            'manage notifications',
            'users management settings',
            'manage roles and permissions',
            'manage departments',
            'manage year plans',
            'manage work periods',
            'manage anonymous feedback',
            'manage system settings',
            'view appreciation nominations',
            'view activity log',
            'view feature usage',
            'review staff requisition',
            'approve staff requisition',
            'authorize staff requisition',
            'download staff requisition',
        ];

        // ============================================
        // DOCUMENT MANAGEMENT PERMISSIONS
        // ============================================
        $documentPermissions = [
            // Document Creation Permissions
            'create weekly plan',
            'create weekly report',
            'create monthly report',

            // Document Viewing Permissions
            'view all documents',

            // Approval Permissions (removed verify, only approve)
            'approve documents',
            'view weekly budgets',
            'process weekly budgets',
            'review weekly budgets',
            'approve weekly budgets',
            'authorize weekly budgets',

            // Action logs permissions
            'view action logs',
            'create action logs',
            'manage action logs',

            // Meeting minutes permissions
            'manage minutes',

            // Official letters permissions
            'manage official letters',
            'authorize official letter downloads',

        ];

        $inventoryPermissions = [
            'view inventory',
            'manage inventory settings',
            'manage inventory assets',
            'manage inventory stock',
            'manage inventory receipts',
            'manage inventory transfers',
            'manage inventory borrowing',
            'manage inventory dispatches',
            'manage inventory maintenance',
            'approve inventory requests',
            'view own inventory items',
        ];

        // Combine all permissions
        $allPermissions = array_merge($existingPermissions, $documentPermissions, $inventoryPermissions);

        // Create all permissions
        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $this->migrateLegacyPettyPermissions();

        $superadminRole = Role::firstOrCreate(['name' => 'superadmin']);
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $managementRole = Role::firstOrCreate(['name' => 'management']);
        $budgetReviewerRole = Role::firstOrCreate(['name' => 'budget_reviewer']);
        $budgetApproverRole = Role::firstOrCreate(['name' => 'budget_approver']);
        $budgetAuthorizerRole = Role::firstOrCreate(['name' => 'budget_authorizer']);
        $budgetOperatorRole = Role::firstOrCreate(['name' => 'budget_operator']);
        $inventoryViewerRole = Role::firstOrCreate(['name' => 'inventory_viewer']);
        $inventoryOfficerRole = Role::firstOrCreate(['name' => 'inventory_officer']);
        $inventoryApproverRole = Role::firstOrCreate(['name' => 'inventory_approver']);
        $inventoryAdminRole = Role::firstOrCreate(['name' => 'inventory_admin']);
        $inventoryStoreClerkRole = Role::firstOrCreate(['name' => 'inventory_store_clerk']);
        $inventoryAssetOfficerRole = Role::firstOrCreate(['name' => 'inventory_asset_officer']);
        $demoViewerRole = Role::firstOrCreate(['name' => config('demo.role', 'demo_viewer')]);

        // Money-module stages: one role covers weekly budget, petty cash, and requisition.
        $budgetReviewerPermissions = [
            'view weekly budgets',
            'review weekly budgets',
            'review pettycash',
            'review staff requisition',
        ];
        $budgetApproverPermissions = [
            'view weekly budgets',
            'approve weekly budgets',
            'approve pettycash',
            'approve staff requisition',
        ];
        $budgetAuthorizerPermissions = [
            'view weekly budgets',
            'authorize weekly budgets',
            'authorize pettycash',
            'authorize staff requisition',
            'download staff requisition',
        ];
        $budgetOperatorPermissions = [
            'view weekly budgets',
            'process weekly budgets',
        ];
        $managementPermissions = [
            'approve documents',
            'view all documents',
        ];
        $staffPermissions = [
            'create weekly plan',
            'create weekly report',
        ];
        $inventoryViewerPermissions = [
            'view inventory',
        ];
        $inventoryOfficerPermissions = [
            'view inventory',
            'manage inventory assets',
            'manage inventory stock',
            'manage inventory receipts',
            'manage inventory transfers',
            'manage inventory borrowing',
            'manage inventory dispatches',
            'manage inventory maintenance',
        ];
        $inventoryApproverPermissions = [
            'view inventory',
            'approve inventory requests',
        ];
        $inventoryStoreClerkPermissions = [
            'view inventory',
            'manage inventory stock',
            'manage inventory receipts',
            'manage inventory borrowing',
            'manage inventory dispatches',
        ];
        $inventoryAssetOfficerPermissions = [
            'view inventory',
            'manage inventory assets',
            'manage inventory transfers',
            'manage inventory maintenance',
            'manage inventory dispatches',
        ];

        // Superadmin and demo viewer always mirror the full permission catalog.
        // Demo viewers get every permission so the UI stays fully visible; writes
        // are blocked separately by EnsureDemoReadOnly middleware.
        $superadminRole->syncPermissions($allPermissions);
        $demoViewerRole->syncPermissions($allPermissions);

        // Other roles keep admin-assigned extras (e.g. manage official letters on staff)
        // so local re-seeds do not wipe customizations.
        $this->ensureRolePermissions($staffRole, $staffPermissions);
        $this->ensureRolePermissions($managementRole, $managementPermissions);
        $this->ensureRolePermissions($budgetReviewerRole, $budgetReviewerPermissions);
        $this->ensureRolePermissions($budgetApproverRole, $budgetApproverPermissions);
        $this->ensureRolePermissions($budgetAuthorizerRole, $budgetAuthorizerPermissions);
        $this->ensureRolePermissions($budgetOperatorRole, $budgetOperatorPermissions);
        $this->ensureRolePermissions($inventoryViewerRole, $inventoryViewerPermissions);
        $this->ensureRolePermissions($inventoryOfficerRole, $inventoryOfficerPermissions);
        $this->ensureRolePermissions($inventoryApproverRole, $inventoryApproverPermissions);
        $this->ensureRolePermissions($inventoryAdminRole, $inventoryPermissions);
        $this->ensureRolePermissions($inventoryStoreClerkRole, $inventoryStoreClerkPermissions);
        $this->ensureRolePermissions($inventoryAssetOfficerRole, $inventoryAssetOfficerPermissions);

        $this->migrateObsoleteRoles();

        Role::query()->each(function (Role $role) {
            $this->ensureRolePermissions($role, ['view own inventory items']);
        });

        // Keep existing direct permission assignments untouched for compatibility,
        // but role assignment should be preferred moving forward.
        User::query()
            ->whereHas('permissions')
            ->each(function (User $user) use ($superadminRole) {
                if ($user->roles()->doesntExist()) {
                    $user->assignRole($superadminRole);
                }
            });

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Move users off obsolete roles, then delete those roles.
     */
    public function migrateObsoleteRoles(): void
    {
        foreach (self::OBSOLETE_ROLE_MIGRATION as $obsoleteName => $targetName) {
            $obsolete = Role::query()->where('name', $obsoleteName)->first();
            if (! $obsolete) {
                continue;
            }

            $target = Role::firstOrCreate(['name' => $targetName]);

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

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Remap legacy petty-cash stage permissions onto roles/users, then delete them.
     */
    private function migrateLegacyPettyPermissions(): void
    {
        $legacyNames = array_keys(self::PETTY_PERMISSION_MIGRATION);
        $legacyPermissions = Permission::query()
            ->whereIn('name', $legacyNames)
            ->get()
            ->keyBy('name');

        if ($legacyPermissions->isEmpty()) {
            return;
        }

        $newPermissions = Permission::query()
            ->whereIn('name', array_filter(array_values(self::PETTY_PERMISSION_MIGRATION)))
            ->get()
            ->keyBy('name');

        foreach (self::PETTY_PERMISSION_MIGRATION as $oldName => $newName) {
            $oldPermission = $legacyPermissions->get($oldName);
            if (! $oldPermission) {
                continue;
            }

            $newPermission = $newName ? $newPermissions->get($newName) : null;

            if ($newPermission) {
                $this->reassignPermission($oldPermission->id, $newPermission->id, 'role_has_permissions', 'role_id');
                $this->reassignPermission($oldPermission->id, $newPermission->id, 'model_has_permissions', 'model_id', 'model_type');
            }
        }

        $legacyIds = $legacyPermissions->pluck('id')->all();

        DB::table('role_has_permissions')->whereIn('permission_id', $legacyIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $legacyIds)->delete();
        Permission::query()->whereIn('id', $legacyIds)->delete();
    }

    /**
     * Copy assignments from an old permission to a new one without duplicating rows.
     */
    private function reassignPermission(
        int $oldPermissionId,
        int $newPermissionId,
        string $table,
        string $ownerColumn,
        ?string $typeColumn = null
    ): void {
        $rows = DB::table($table)->where('permission_id', $oldPermissionId)->get();

        foreach ($rows as $row) {
            $query = DB::table($table)
                ->where('permission_id', $newPermissionId)
                ->where($ownerColumn, $row->{$ownerColumn});

            if ($typeColumn) {
                $query->where($typeColumn, $row->{$typeColumn});
            }

            if ($query->exists()) {
                continue;
            }

            $insert = [
                'permission_id' => $newPermissionId,
                $ownerColumn => $row->{$ownerColumn},
            ];

            if ($typeColumn) {
                $insert[$typeColumn] = $row->{$typeColumn};
            }

            DB::table($table)->insert($insert);
        }
    }

    /**
     * Ensure baseline permissions exist on a role without removing manually added ones.
     *
     * @param  array<int, string>  $baselinePermissions
     */
    private function ensureRolePermissions(Role $role, array $baselinePermissions): void
    {
        $merged = collect($baselinePermissions)
            ->merge($role->permissions()->pluck('name'))
            ->unique()
            ->values()
            ->all();

        $role->syncPermissions($merged);
    }
}
