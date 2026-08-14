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
