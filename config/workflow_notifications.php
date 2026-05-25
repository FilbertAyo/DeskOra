<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Workflow email notification routing
    |--------------------------------------------------------------------------
    |
    | Weekly plan/report submissions notify users by permission (not job title).
    | Assign the document_approver role to the CEO and budget_operator to finance.
    |
    */

    'log_missing_recipients' => env('WORKFLOW_NOTIFICATIONS_LOG_MISSING', true),

    'weekly_document_submitted' => [
        'permission' => 'approve documents',
    ],

    'weekly_budget_submitted' => [
        'permission' => 'process weekly budgets',
    ],

    /*
    | Roles that should default to email when created/updated via user management.
    */
    'roles_default_email_channel' => [
        'document_approver',
        'budget_operator',
        'superadmin',
    ],

];
