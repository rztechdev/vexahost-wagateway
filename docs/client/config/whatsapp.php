<?php

/*
|--------------------------------------------------------------------------
| Klien Flustra WA Gateway
|--------------------------------------------------------------------------
|
| File ini disalin apa adanya ke setiap aplikasi Flustra yang perlu mengirim
| WhatsApp (flustra-erp, flustra-web, flustra-pricing, flustra-helpdesk).
| Pasangannya: app/Services/WhatsAppGateway.php.
|
| Sumber aslinya ada di repo flustra-wa (docs/client/). Kalau ada perubahan,
| ubah di sana dulu lalu salin ulang ke tiap aplikasi.
|
*/

return [

    'enabled' => env('WA_GATEWAY_ENABLED', true),

    'url' => env('WA_GATEWAY_URL', 'https://wa.flustra.id'),

    // API key milik tenant aplikasi ini di dashboard gateway.
    'key' => env('WA_GATEWAY_KEY'),

    // Kosongkan untuk memakai sesi tenant pertama yang sedang terhubung.
    // Isi kalau aplikasi ini harus selalu mengirim dari nomor tertentu.
    'session' => env('WA_GATEWAY_SESSION'),

    /*
    | Nomor resmi Flustra, untuk pesan yang memang atas nama Flustra: OTP,
    | undangan anggota, notifikasi billing, balasan tiket.
    |
    | Sengaja dipisah dari nomor milik tenant. Dokumen yang ditujukan ke pihak
    | ketiga — invoice ke customer, PO ke vendor — harus keluar dari nomor
    | perusahaan yang bersangkutan, bukan dari nomor Flustra: penerimanya tidak
    | mengenal Flustra, dan volume kiriman semacam itu dari satu nomor platform
    | memancing pemblokiran.
    */
    'platform_session' => env('WA_GATEWAY_PLATFORM_SESSION'),

    // Sengaja pendek. Notifikasi WhatsApp adalah pelengkap email, bukan
    // penggantinya — gateway yang lambat tidak boleh menahan request pengguna.
    'timeout' => (int) env('WA_GATEWAY_TIMEOUT', 5),

    'connect_timeout' => (int) env('WA_GATEWAY_CONNECT_TIMEOUT', 2),

    /*
    | Hanya kirim ke nomor yang kepemilikannya sudah dibuktikan lewat OTP.
    |
    | Nomor di tabel users cuma pernah divalidasi formatnya. Kalau ada salah
    | ketik satu digit, pesan mendarat di HP orang asing — dan laporan spam dari
    | mereka bisa membuat nomor platform Flustra diblokir WhatsApp, yang
    | mematikan notifikasi untuk seluruh aplikasi sekaligus.
    |
    | Status verifikasi berasal dari flustra-auth dan dicerminkan ke kolom
    | users.phone_verified_at aplikasi ini lewat SSO. Jangan matikan opsi ini
    | di produksi.
    */
    'require_verified_phone' => env('WA_GATEWAY_REQUIRE_VERIFIED_PHONE', true),

];
