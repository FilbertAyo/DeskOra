<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deskora AI Assistant
    |--------------------------------------------------------------------------
    |
    | Portal-wide AI assistant settings. Suggested questions appear as quick
    | prompts in the floating assistant panel.
    |
    */

    'document_summary_label' => 'Summarize this weekly submission',

    'suggested_questions' => [
        'What tasks are assigned to me this week?',
        'How do I submit a weekly plan?',
    'How does staff requisition approval work?',
        'What year plan activities apply to my sub-department?',
        'How do I record impact in the weekly matrix?',
       
    ],

    /*
    |--------------------------------------------------------------------------
    | Live data context domains
    |--------------------------------------------------------------------------
    |
    | The backend resolves small, pre-processed snapshots from these domains
    | when a question asks about the user's records — never for pure navigation.
    |
    */

    'context_domains' => [
        'assigned_tasks' => [
            'assigned task',
            'assigned tasks',
            'assigned to me',
            'my assigned',
            'tasks assigned',
            'assigned by',
            'from other staff',
            'team member',
            'this week tasks',
            'my tasks this week',
        ],
        'weekly_submissions' => [
            'weekly plan',
            'weekly report',
            'monthly report',
            'my report',
            'my reports',
            'staff report',
            'staff reports',
            'submission',
            'submitted',
            'deadline',
            'work period',
            'draft plan',
            'draft report',
            'what fields',
            'what inputs',
            'required field',
            'what do i fill',
            'what should i fill',
            'sections',
            'what did i put',
            'what have i filled',
            'what did i write',
            'my budget',
            'my activities',
            'my main target',
            'my objective',
            'progress options',
        ],
        'year_plans' => [
            'year plan',
            'yearly plan',
            'annual plan',
            'year plan activity',
            'year plan activities',
            'planned activity',
            'planned activities',
            'planned months',
        ],
        'weekly_matrix' => [
            'weekly matrix',
            'matrix',
            'department matrix',
            'weekly grid',
            'impact',
            'impacts',
            'outcome',
            'outcomes',
            'key activity',
            'year plan link',
            'year plan links',
        ],
        'finance_requests' => [
            'petty cash',
            'requisition',
            'reimbursement',
            'my request',
            'my requests',
            'payment status',
            'paid',
            'approved request',
        ],
        'approvals' => [
            'approve',
            'approval',
            'review queue',
            'authorize',
            'pending approval',
            'waiting for approval',
        ],
        'action_logs' => [
            'action log',
            'action task',
            'action tasks',
            'meeting minutes',
            'my tasks',
        ],
        'documents' => [
            'shared with me',
            'shared document',
            'all documents',
            'my documents',
        ],
        'system_stats' => [
            'active user',
            'active users',
            'total users',
            'how many users',
            'number of users',
            'user count',
            'staff count',
            'how many staff',
            'department count',
            'how many departments',
            'number of departments',
            'system stats',
            'system statistics',
        ],
    ],

    'navigation_entry_domains' => [
        'year_plan' => 'year_plans',
        'year_plan_overview' => 'year_plans',
        'weekly_matrix' => 'weekly_matrix',
        'weekly_matrix_overview' => 'weekly_matrix',
        'weekly_plans_list' => 'weekly_submissions',
        'create_weekly_plan' => 'weekly_submissions',
        'weekly_reports_list' => 'weekly_submissions',
        'create_weekly_report' => 'weekly_submissions',
        'monthly_reports' => 'weekly_submissions',
        'staff_requisitions_list' => 'finance_requests',
        'petty_cash_requests' => 'finance_requests',
        'review_petty_cash' => 'approvals',
        'review_requisitions' => 'approvals',
        'approve_petty_cash' => 'approvals',
        'approve_requisitions' => 'approvals',
        'action_log_list' => 'action_logs',
        'assigned_tasks_overview' => 'assigned_tasks',
        'all_documents' => 'documents',
        'users_settings' => 'system_stats',
        'departments_settings' => 'system_stats',
    ],

    /*
    |--------------------------------------------------------------------------
    | Context domain permissions
    |--------------------------------------------------------------------------
    |
    | Domains listed here require the given permission before the backend will
    | resolve their data. When the user lacks it, the resolver returns an
    | ACCESS NOTE so the AI can tell them they do not have access.
    |
    */

    'context_domain_permissions' => [
        'system_stats' => 'users management settings',
    ],

    'context_snapshot_max_tasks' => 5,

    'context_snapshot_max_requests' => 5,

];
