<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Engine Node.js (whatsapp-web.js)
    |--------------------------------------------------------------------------
    |
    | Engine berjalan sebagai resource Coolify terpisah tanpa domain publik.
    | `url` memakai UUID resource Coolify, bukan localhost maupun nama tampilan
    | resource, karena Laravel dan engine ada di container berbeda.
    |
    | `timeout` harus melampaui antrean anti-ban di engine, bukan sekadar lama
    | pengiriman satu pesan. Engine menahan tiap pesan 3-8 detik dan mengantre
    | per sesi, jadi pesan keempat dalam satu giliran bisa menunggu lebih dari
    | 30 detik. Batas 15 detik dulu membuat Laravel menyerah lebih awal lalu
    | mengulang job-nya — dan penerima menerima pesan yang sama tiga sampai
    | empat kali.
    |
    */

    'engine' => [
        'url' => env('ENGINE_URL', 'http://127.0.0.1:3100'),
        'token' => env('ENGINE_TOKEN'),
        'hmac_secret' => env('ENGINE_HMAC_SECRET'),
        'timeout' => (int) env('ENGINE_TIMEOUT', 60),
        'connect_timeout' => (int) env('ENGINE_CONNECT_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sesi Pengirim OTP
    |--------------------------------------------------------------------------
    |
    | Endpoint /api/v1/otp mengirim kode verifikasi atas nama Flustra sendiri,
    | jadi ia butuh satu sesi yang ditunjuk secara eksplisit — tidak bisa
    | menebak dari workspace pemanggil seperti endpoint pesan biasa.
    |
    | Isinya ID sesi biasa, sama seperti yang dibuat pelanggan lewat dashboard.
    | Tidak ada tipe sesi khusus: sesi bertipe `platform` dulu pernah ada dan
    | justru menjadi sumber kebingungan, karena hanya bisa dibuat lewat CLI dan
    | tidak pernah terpilih otomatis saat session_id dikosongkan.
    |
    */

    'otp_session_id' => env('OTP_SESSION_ID'),

    /*
    |--------------------------------------------------------------------------
    | Masa Berlaku QR
    |--------------------------------------------------------------------------
    |
    | Harus lebih panjang dari jeda terlama antar penerbitan QR oleh
    | whatsapp-web.js (sekitar 60 detik), supaya tidak pernah ada celah di mana
    | dashboard tidak punya QR untuk ditampilkan.
    |
    */

    'qr_ttl_seconds' => (int) env('QR_TTL_SECONDS', 90),

    /*
    |--------------------------------------------------------------------------
    | Batas Bawaan Workspace
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'max_sessions' => (int) env('WORKSPACE_DEFAULT_MAX_SESSIONS', 1),
        'monthly_message_quota' => (int) env('WORKSPACE_DEFAULT_MONTHLY_QUOTA', 1000),
        'api_rate_limit_per_minute' => (int) env('WORKSPACE_DEFAULT_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Jeda Antar Pesan (anti-ban)
    |--------------------------------------------------------------------------
    |
    | Engine menyisipkan jeda acak antara min dan max detik pada setiap pesan
    | keluar dalam satu sesi. Mengirim ratusan pesan tanpa jeda adalah pola
    | paling cepat membuat nomor diblokir.
    |
    */

    'throttle' => [
        'min_delay_ms' => (int) env('WA_MIN_DELAY_MS', 3000),
        'max_delay_ms' => (int) env('WA_MAX_DELAY_MS', 8000),
    ],

    /*
    |--------------------------------------------------------------------------
    | OTP
    |--------------------------------------------------------------------------
    */

    'otp' => [
        'length' => 6,
        'ttl_seconds' => (int) env('OTP_TTL_SECONDS', 300),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN', 60),
        'max_per_phone_per_day' => (int) env('OTP_MAX_PER_PHONE_PER_DAY', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retensi Data
    |--------------------------------------------------------------------------
    */

    'retention' => [
        'messages_days' => (int) env('RETENTION_MESSAGES_DAYS', 90),
        'webhook_deliveries_days' => (int) env('RETENTION_WEBHOOK_DAYS', 30),
    ],

];
