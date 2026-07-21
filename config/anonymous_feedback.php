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
    | Active users with this permission and email notifications enabled receive
    | an email when anonymous feedback is submitted.
    |
    */

    'notify_permission' => 'manage anonymous feedback',

    'log_missing_recipients' => true,

];
