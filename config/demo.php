<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo view-only role
    |--------------------------------------------------------------------------
    |
    | Users with this role can browse every screen (the role is seeded with the
    | full permission catalog so navigation and buttons stay visible) but all
    | writes are blocked by EnsureDemoReadOnly middleware.
    |
    */

    'role' => 'demo_viewer',

    'message' => 'This is a demo account. You can explore DeskOra freely, but changes cannot be saved.',

    'user' => [
        'enabled' => env('DEMO_VIEWER_ENABLED', true),
        'name' => env('DEMO_VIEWER_NAME', 'DeskOra Demo'),
        'title' => env('DEMO_VIEWER_TITLE', 'Guest Viewer'),
        'email' => env('DEMO_VIEWER_EMAIL', 'demo@deskora.app'),
        'password' => env('DEMO_VIEWER_PASSWORD', 'DemoViewOnly!'),
        'phone' => env('DEMO_VIEWER_PHONE', '0000000000'),
    ],

];
