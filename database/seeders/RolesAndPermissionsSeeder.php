<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
            'view settings',
            'users management settings',
            'update other settings',
            'approve final item purchase',
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
    }
}
