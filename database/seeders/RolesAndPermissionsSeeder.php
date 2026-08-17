<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

        // Combine all permissions
        $allPermissions = array_merge($existingPermissions, $documentPermissions);

        // Create all permissions
        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $this->migrateLegacyPettyPermissions();

        $superadminRole = Role::firstOrCreate(['name' => 'superadmin']);
        $staffRole = Role::firstOrCreate(['name' => 'staff']);
        $documentApproverRole = Role::firstOrCreate(['name' => 'document_approver']);
        $budgetReviewerRole = Role::firstOrCreate(['name' => 'budget_reviewer']);
        $budgetApproverRole = Role::firstOrCreate(['name' => 'budget_approver']);
        $budgetAuthorizerRole = Role::firstOrCreate(['name' => 'budget_authorizer']);
        $budgetOperatorRole = Role::firstOrCreate(['name' => 'budget_operator']);
        $requisitionReviewerRole = Role::firstOrCreate(['name' => 'requisition_reviewer']);
        $requisitionApproverRole = Role::firstOrCreate(['name' => 'requisition_approver']);
        $requisitionAuthorizerRole = Role::firstOrCreate(['name' => 'requisition_authorizer']);
        $pettycashReviewerRole = Role::firstOrCreate(['name' => 'pettycash_reviewer']);
        $pettycashApproverRole = Role::firstOrCreate(['name' => 'pettycash_approver']);
        $pettycashAuthorizerRole = Role::firstOrCreate(['name' => 'pettycash_authorizer']);

        $budgetReviewerPermissions = [
            'view weekly budgets',
            'review weekly budgets',
        ];
        $budgetApproverPermissions = [
            'view weekly budgets',
            'approve weekly budgets',
        ];
        $budgetAuthorizerPermissions = [
            'view weekly budgets',
            'authorize weekly budgets',
        ];
        $budgetOperatorPermissions = [
            'view weekly budgets',
            'process weekly budgets',
        ];
        $documentApproverPermissions = [
            'approve documents',
            'view all documents',
            'view weekly budgets',
        ];
        $staffPermissions = [
            'create weekly plan',
            'create weekly report',
        ];
        $requisitionReviewerPermissions = [
            'review staff requisition',
        ];
        $requisitionApproverPermissions = [
            'approve staff requisition',
        ];
        $requisitionAuthorizerPermissions = [
            'authorize staff requisition',
            'download staff requisition',
        ];
        $pettycashReviewerPermissions = [
            'review pettycash',
        ];
        $pettycashApproverPermissions = [
            'approve pettycash',
        ];
        $pettycashAuthorizerPermissions = [
            'authorize pettycash',
        ];

        // Superadmin always mirrors the full permission catalog.
        $superadminRole->syncPermissions($allPermissions);

        // Other roles keep admin-assigned extras (e.g. manage official letters on staff)
        // so deploy.sh reseeds do not wipe production customizations.
        $this->ensureRolePermissions($staffRole, $staffPermissions);
        $this->ensureRolePermissions($documentApproverRole, $documentApproverPermissions);
        $this->ensureRolePermissions($budgetReviewerRole, $budgetReviewerPermissions);
        $this->ensureRolePermissions($budgetApproverRole, $budgetApproverPermissions);
        $this->ensureRolePermissions($budgetAuthorizerRole, $budgetAuthorizerPermissions);
        $this->ensureRolePermissions($budgetOperatorRole, $budgetOperatorPermissions);
        $this->ensureRolePermissions($requisitionReviewerRole, $requisitionReviewerPermissions);
        $this->ensureRolePermissions($requisitionApproverRole, $requisitionApproverPermissions);
        $this->ensureRolePermissions($requisitionAuthorizerRole, $requisitionAuthorizerPermissions);
        $this->ensureRolePermissions($pettycashReviewerRole, $pettycashReviewerPermissions);
        $this->ensureRolePermissions($pettycashApproverRole, $pettycashApproverPermissions);
        $this->ensureRolePermissions($pettycashAuthorizerRole, $pettycashAuthorizerPermissions);

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
