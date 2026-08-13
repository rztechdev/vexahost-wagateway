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

    /*
    | Provider WhatsApp berbayar. Hanya dipakai bila sebuah sesi diset
    | driver=fonnte. Driver bawaan (wwebjs) tidak memerlukan ini.
    */
    'fonnte' => [
        'token' => env('FONNTE_TOKEN'),
    ],

];
