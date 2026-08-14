<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deduplication window (seconds)
    |--------------------------------------------------------------------------
    |
    | Skip recording a page_view when the same user + feature_key was already
    | recorded within this many seconds (refresh storms / back-button noise).
    |
    */
    'dedupe_seconds' => 30,

    /*
    |--------------------------------------------------------------------------
    | Retention (days)
    |--------------------------------------------------------------------------
    */
    'retention_days' => 365,

    /*
    |--------------------------------------------------------------------------
    | Route names excluded from page-view tracking
    |--------------------------------------------------------------------------
    */
    'excluded_routes' => [
        'session.ping',
        'ai-assist.status',
        'commands.index',
        'analytics.events.store',
        'approval-gate.snapshot',
        'approval-gate.remind-later',
        'feature-usage.index',
        'login',
        'logout',
        'password.request',
        'password.reset',
        'password.email',
        'password.update',
        'password.confirm',
        'register',
        'verification.notice',
        'verification.verify',
        'verification.send',
        'anonymous-feedback.form',
        'anonymous-feedback.store',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route name prefixes excluded from page-view tracking
    |--------------------------------------------------------------------------
    */
    'excluded_route_prefixes' => [
        'sanctum.',
        'ignition.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Path prefixes excluded from page-view tracking
    |--------------------------------------------------------------------------
    */
    'excluded_path_prefixes' => [
        '/up',
        '/sanctum',
        '/_debugbar',
        '/livewire',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route name → module mapping (first match wins; order matters)
    |--------------------------------------------------------------------------
    |
    | Patterns support trailing .* as a prefix wildcard (e.g. year-plan.*).
    | Exact names match without a wildcard.
    |
    */
    'route_modules' => [
        'dashboard' => ['module' => 'Dashboard', 'feature_key' => 'module.dashboard'],

        'executive-pulse.*' => ['module' => 'Management', 'feature_key' => 'module.management.executive_pulse'],
        'documents.reviewer-dashboard' => ['module' => 'Management', 'feature_key' => 'module.management.submissions'],
        'documents.reviewer-show' => ['module' => 'Management', 'feature_key' => 'module.management.submissions'],
        'anonymous-feedback.*' => ['module' => 'Management', 'feature_key' => 'module.management.anonymous_feedback'],
        'management.appreciation.*' => ['module' => 'Management', 'feature_key' => 'module.management.staff_appreciation'],
        'work-periods.*' => ['module' => 'Management', 'feature_key' => 'module.management.work_periods'],

        'year-plan.*' => ['module' => 'Year Plan', 'feature_key' => 'module.year_plan'],

        'weekly-matrix.*' => ['module' => 'Weekly Matrix', 'feature_key' => 'module.weekly_matrix'],

        'documents.weekly-plans' => ['module' => 'Activities', 'feature_key' => 'module.activities.weekly_plans'],
        'documents.weekly-reports' => ['module' => 'Activities', 'feature_key' => 'module.activities.weekly_reports'],
        'documents.monthly-report' => ['module' => 'Activities', 'feature_key' => 'module.activities.monthly_report'],
        'documents.weekly-budget-review*' => ['module' => 'Activities', 'feature_key' => 'module.activities.weekly_budget_review'],
        'weekly-work-logs.*' => ['module' => 'Activities', 'feature_key' => 'module.activities.work_logs'],
        'action-logs.*' => ['module' => 'Activities', 'feature_key' => 'module.activities.action_logs'],
        'meeting-minutes.*' => ['module' => 'Activities', 'feature_key' => 'module.activities.meeting_minutes'],

        'official-letters.*' => ['module' => 'Official Letters', 'feature_key' => 'module.official_letters'],

        'documents.*' => ['module' => 'Documents', 'feature_key' => 'module.documents'],

        'petty.list' => ['module' => 'Approvals', 'feature_key' => 'module.approvals'],
        'petty.cashier' => ['module' => 'Finance', 'feature_key' => 'module.finance.cashier'],
        'petty.*' => ['module' => 'Requests', 'feature_key' => 'module.requests.petty'],
        'requisition.records*' => ['module' => 'Finance', 'feature_key' => 'module.finance.requisition_records'],
        'requisition.*' => ['module' => 'Requests', 'feature_key' => 'module.requests.requisition'],
        'deposit.*' => ['module' => 'Finance', 'feature_key' => 'module.finance.deposits'],
        'cashflow.*' => ['module' => 'Finance', 'feature_key' => 'module.finance.cashflow'],

        'reports*' => ['module' => 'Org Reports', 'feature_key' => 'module.org_reports'],

        'admin.*' => ['module' => 'Settings', 'feature_key' => 'module.settings.users'],
        'roles.*' => ['module' => 'Settings', 'feature_key' => 'module.settings.roles'],
        'departments' => ['module' => 'Settings', 'feature_key' => 'module.settings.departments'],
        'department.*' => ['module' => 'Settings', 'feature_key' => 'module.settings.departments'],
        'settings.system.*' => ['module' => 'Settings', 'feature_key' => 'module.settings.system'],
        'activity-log.*' => ['module' => 'Settings', 'feature_key' => 'module.settings.activity_log'],
        'feature-usage.*' => ['module' => 'Settings', 'feature_key' => 'module.settings.feature_usage'],
        'settings.*' => ['module' => 'Settings', 'feature_key' => 'module.settings'],

        'profile.*' => ['module' => 'Profile', 'feature_key' => 'module.profile'],

        'ai-assistant.*' => ['module' => 'AI helpers', 'feature_key' => 'module.ai_helpers'],
        'ai-assist.*' => ['module' => 'AI helpers', 'feature_key' => 'module.ai_helpers'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Known in-page feature keys
    |--------------------------------------------------------------------------
    |
    | Only these keys are accepted by the client ingest endpoint.
    |
    */
    'features' => [
        'feature.dashboard.performance_details' => [
            'label' => 'Performance details',
            'module' => 'Dashboard',
        ],
        'feature.dashboard.matrix_modal' => [
            'label' => 'Weekly matrix modal',
            'module' => 'Dashboard',
        ],
        'feature.dashboard.work_log_open' => [
            'label' => 'Open work log',
            'module' => 'Dashboard',
        ],
        'feature.dashboard.action_needed' => [
            'label' => 'Action needed item',
            'module' => 'Dashboard',
        ],
        'feature.dashboard.scorecard' => [
            'label' => 'Weekly scorecard',
            'module' => 'Dashboard',
        ],
        'feature.dashboard.action_tasks' => [
            'label' => 'Action tasks',
            'module' => 'Dashboard',
        ],
        'feature.ai.assistant_open' => [
            'label' => 'Deskora AI assistant',
            'module' => 'AI helpers',
        ],
        'feature.ai.command_palette' => [
            'label' => 'DeskOra Go command palette',
            'module' => 'AI helpers',
        ],
        'feature.approvals.review_action' => [
            'label' => 'Approval review action',
            'module' => 'Approvals',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback for unmatched authenticated GET pages
    |--------------------------------------------------------------------------
    */
    'fallback' => [
        'module' => 'Other',
        'feature_key' => 'module.other',
    ],

];
