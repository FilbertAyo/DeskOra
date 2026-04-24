<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

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
        // EXISTING PERMISSIONS (Petty Cash & System)
        // ============================================
        $existingPermissions = [
            'request pettycash',
            'view requested pettycash',
            'first pettycash approval',
            'last pettycash approval',
            'approve petycash payments',
            'view cashflow movements',
            'request item purchase',
            'approve item purchase',
            'view reports',
            'upload organization reports',
            'view settings',
            'manage notifications',
            'users management settings',
            'update other settings',
            'approve final item purchase',
            'manage year plans',
            'manage anonymous feedback',
            'view activity log',
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
            'view own documents',
            'view all documents',

            // Document Management Permissions
            'edit own documents',
            'delete own documents',
            'submit documents',

            // Approval Permissions (removed verify, only approve)
            'approve documents',
            'view weekly budgets',
            'process weekly budgets',

            // Action logs permissions
            'view action logs',
            'create action logs',
            'manage action logs',

            // Dashboard Permissions
            'view dashboard',
            'view reviewer dashboard',
        ];

        // Combine all permissions
        $allPermissions = array_merge($existingPermissions, $documentPermissions);

        // Create all permissions
        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $yearPlanPerm = Permission::findByName('manage year plans');
        User::query()
            ->whereHas('permissions', fn ($q) => $q->where('name', 'update other settings'))
            ->each(fn (User $user) => $user->givePermissionTo($yearPlanPerm));

        $anonymousFeedbackPerm = Permission::findByName('manage anonymous feedback');
        User::query()
            ->whereHas('permissions', fn ($q) => $q->where('name', 'update other settings'))
            ->each(fn (User $user) => $user->givePermissionTo($anonymousFeedbackPerm));

        $activityLogPerm = Permission::findByName('view activity log');
        User::query()
            ->whereHas('permissions', fn ($q) => $q->where('name', 'update other settings'))
            ->each(fn (User $user) => $user->givePermissionTo($activityLogPerm));

        $manageNotificationsPerm = Permission::findByName('manage notifications');
        User::query()
            ->whereHas('permissions', fn ($q) => $q->where('name', 'update other settings'))
            ->each(fn (User $user) => $user->givePermissionTo($manageNotificationsPerm));
    }
}
