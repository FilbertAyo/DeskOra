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
        'notify_department_head' => env('WORKFLOW_NOTIFY_DEPT_HEAD_ON_SUBMIT', true),
    ],

    'weekly_budget_submitted' => [
        'permission' => 'review weekly budgets',
    ],

    'weekly_budget' => [
        'submitted' => [
            'permission' => 'review weekly budgets',
            'subject' => 'Weekly budget awaiting review',
            'headline' => 'New weekly budget submitted',
            'intro' => 'A weekly plan with budget items has been submitted and is awaiting your review.',
            'action' => ['label' => 'Review Weekly Budget'],
            'view' => 'mail.budgets.stage-advanced',
            'sms' => 'Weekly budget from :submitter for :period needs your review.',
        ],
        'reviewed' => [
            'permission' => 'approve weekly budgets',
            'subject' => 'Weekly budget awaiting approval',
            'headline' => 'Budget reviewed — approval needed',
            'intro' => 'A weekly budget item has been reviewed and is awaiting your approval.',
            'action' => ['label' => 'Approve Weekly Budget'],
            'view' => 'mail.budgets.stage-advanced',
            'sms' => 'Weekly budget for :period needs your approval.',
        ],
        'approved' => [
            'permission' => 'authorize weekly budgets',
            'subject' => 'Weekly budget awaiting authorization',
            'headline' => 'Budget approved — authorization needed',
            'intro' => 'A weekly budget item has been approved and is awaiting your authorization.',
            'action' => ['label' => 'Authorize Weekly Budget'],
            'view' => 'mail.budgets.stage-advanced',
            'sms' => 'Weekly budget for :period needs your authorization.',
        ],
        'authorized' => [
            'subject' => 'Weekly budget fully authorized',
            'headline' => 'Your weekly budget was authorized',
            'intro' => 'Your weekly plan budget has completed all approval stages.',
            'action' => ['label' => 'View Weekly Budget Review'],
            'view' => 'mail.budgets.owner-update',
            'sms' => 'Your weekly budget for :period was fully authorized.',
        ],
        'rejected' => [
            'subject' => 'Weekly budget rejected',
            'headline' => 'Your weekly budget was rejected',
            'intro' => 'A weekly budget item on your plan was rejected during review.',
            'action' => ['label' => 'View Weekly Budget Review'],
            'view' => 'mail.budgets.owner-update',
            'sms' => 'A weekly budget item for :period was rejected.',
        ],
    ],

    /*
    | Roles that should default to email when created/updated via user management.
    */
    'roles_default_email_channel' => [
        'document_approver',
        'budget_operator',
        'budget_reviewer',
        'budget_approver',
        'budget_authorizer',
        'superadmin',
        'requisition_reviewer',
        'requisition_approver',
        'requisition_authorizer',
    ],

];
