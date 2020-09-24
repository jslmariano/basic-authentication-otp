<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Module disable/enable
    |--------------------------------------------------------------------------
    |
    | This config would simply disable/enable the whole module feature
    |
     */
    'enabled' => env('AUTHENTICATION_OTP_ENABLED', true),


    /*
    |--------------------------------------------------------------------------
    | Default OTP Code Length
    |--------------------------------------------------------------------------
    |
    | This option controls the default OTP code length,
    | number sets here will be the length of the
    | OTP digit that would be sent to users
    |
     */
    'code_length'     => 4,


    /*
    |--------------------------------------------------------------------------
    | Default Force User Positions to use OTP
    |--------------------------------------------------------------------------
    |
    | This option controls the default user positions,
    | that will be forced to have otp enabled on their end
    |
    | Supported: 1, 2, 3, 4, 5, 6, 7
    | Table in database : roles
    |
     */
    'force_positions' => [1],


    /*
    |--------------------------------------------------------------------------
    | Default OTP Expires in seconds
    |--------------------------------------------------------------------------
    |
    | This option controls the default otp lifetime in seconds,
    | otp that are older than this configuration is considered as expired.
    |
    | Supported: Numbers
    |
     */
    'expire_seconds'  => 300, // 5 minutes


    /*
    |--------------------------------------------------------------------------
    | Default Force OTP to Expires in days
    |--------------------------------------------------------------------------
    |
    | This option controls the default otp lifetime in days,
    | otp that are older than this configuration is considered as expired,
    | even if it's verified.
    |
    | Supported: Numbers
    |
     */
    'force_expire_days'  => 30, // 1 month


    /*
    |--------------------------------------------------------------------------
    | Default NExmo credential
    |--------------------------------------------------------------------------
    |
    | This option controls the default nexmo credentials,
    | these credentials are found in nexmo dashboard and
    | used to communicate with nexmo apis
    |
    | REF : https://dashboard.nexmo.com/
    |
     */
    'nexmo'           => [
        'number'     => env('NEXMO_NUMBER'),
        'api_key'    => env('NEXMO_API_KEY'),
        'api_secret' => env('NEXMO_API_SECRET'),
    ],
];
