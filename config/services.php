<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Layanan Pihak Ketiga
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    'mayar' => [
        'api_key' => env('MAYAR_API_KEY'),
        'api_url' => env('MAYAR_API_URL', 'https://api.mayar.id/hl/v2'),
        'webhook_token' => env('MAYAR_WEBHOOK_TOKEN'),
        'mode' => env('MAYAR_MODE', 'production'),
    ],

];
