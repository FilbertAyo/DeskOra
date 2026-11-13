<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OTP format
    |--------------------------------------------------------------------------
    |
    | Can be one of alpha, alphanumeric, numeric
    |
    */
    'format' => env('OTP_FORMAT', 'numeric'),

    /*
    |--------------------------------------------------------------------------
    | OTP characters length
    |--------------------------------------------------------------------------
    |
    | Number of characters of OTP
    |
    */
    'length' => (int) env('OTP_LENGTH', 4),

    /*
    |--------------------------------------------------------------------------
    | OTP expiration
    |--------------------------------------------------------------------------
    |
    | Number of minutes before OTP expires
    |
    */
    'expires' => (int) env('OTP_EXPIRES', 10),

    /*
    |--------------------------------------------------------------------------
    | OTP notification
    |--------------------------------------------------------------------------
    |
    | Notification to use for OTP
    |
    */
    'notification' => \SadiqSalau\LaravelOtp\OtpNotification::class,
];
