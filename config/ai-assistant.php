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
        'How do I submit a weekly plan?',
        'What is the difference between a weekly plan and a weekly report?',
        'How does petty cash approval work?',
        'Where can I track my action log tasks?',
        'How do I view documents shared with me?',
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
        'weekly_submissions' => [
            'weekly plan',
            'weekly report',
            'monthly report',
            'submission',
            'submitted',
            'deadline',
            'work period',
            'draft plan',
            'draft report',
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
    ],

    'navigation_entry_domains' => [
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
        'all_documents' => 'documents',
    ],

    'context_snapshot_max_tasks' => 5,

    'context_snapshot_max_requests' => 5,

];
