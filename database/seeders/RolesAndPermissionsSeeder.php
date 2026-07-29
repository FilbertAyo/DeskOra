<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
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
            'first pettycash approval',
            'last pettycash approval',
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
            'review staff requisition',
            'approve staff requisition',
            'authorize staff requisition',
            'download staff requisition',
            'review requests',
            'approve requests',
            'authorize requests',
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

        ];

        // Combine all permissions
        $allPermissions = array_merge($existingPermissions, $documentPermissions);

        // Create all permissions
        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

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

        // Keep existing direct permission assignments untouched for compatibility,
        // but role assignment should be preferred moving forward.
        User::query()
            ->whereHas('permissions')
            ->each(function (User $user) use ($superadminRole) {
                if ($user->roles()->doesntExist()) {
                    $user->assignRole($superadminRole);
                }
            });
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
