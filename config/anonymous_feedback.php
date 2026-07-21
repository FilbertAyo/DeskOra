<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public URL token
    |--------------------------------------------------------------------------
    |
    | Anonymous feedback forms are served at /feedback/{token}. The token must
    | match this value so the URL is not guessable. Generate e.g. with:
    | php -r "echo bin2hex(random_bytes(16));"
    |
    */

    'public_token' => env('ANONYMOUS_FEEDBACK_PUBLIC_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Submission notifications
    |--------------------------------------------------------------------------
    |
    | Active users with this permission and an email on file receive a message
    | when anonymous feedback is submitted. SMS/email channel preference is
    | ignored so managers always get inbox alerts.
    |
    | Optional comma-separated extra addresses (e.g. for backups):
    | ANONYMOUS_FEEDBACK_NOTIFY_EMAILS=you@example.com,other@example.com
    |
    */

    'notify_permission' => 'manage anonymous feedback',

    'notify_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ANONYMOUS_FEEDBACK_NOTIFY_EMAILS', ''))
    ))),

    'log_missing_recipients' => true,

];
