<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Session reminder
    |--------------------------------------------------------------------------
    |
    | Responsible staff see a popup each session while requisitions or weekly
    | budgets are waiting. "Remind me later" hides it until the next login.
    |
    */
    'session_snooze_key' => 'approval_gate.snoozed',

    /*
    | Reminder modal only counts weekly budgets in the current and next work
    | periods (the two latest weeks staff are working in). Requisitions are unchanged.
    */
    'budget_reminder_period_count' => 2,

    'work_routes' => [
        'petty.list',
        'petty.details',
        'petty.requisition.approvals',
        'requisition.show',
        'f_approve.approve',
        'l_approve.approve',
        'requests.authorize',
        'documents.weekly-budget-review',
        'documents.process-weekly-budget-review',
        'documents.weekly-budget-review.add-midweek-budget',
        'documents.weekly-budget-review.export.pdf',
        'documents.weekly-budget-review.export.excel',
        'approval-gate.snapshot',
        'approval-gate.remind-later',
    ],

];
