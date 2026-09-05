<?php

/*
|--------------------------------------------------------------------------
| Paket Langganan
|--------------------------------------------------------------------------
|
| Paket sengaja tinggal di berkas config, bukan di tabel database.
|
| Harga dan batas berubah beberapa kali setahun, bukan setiap hari. Menaruhnya
| di tabel berarti menambah migrasi, seeder, CRUD di admin, dan satu sumber
| kebenaran baru yang bisa berbeda antara dev, staging, dan produksi tanpa ada
| yang menyadarinya. Di config, setiap perubahan harga terlihat di diff git,
| ikut di-review, dan sampai ke semua tahap lewat jalur yang sama dengan kode.
|
| Konsekuensinya yang harus diingat: mengubah angka di sini mengubah batas
| SELURUH pelanggan pada paket itu seketika, termasuk yang sudah membayar.
| Kalau suatu saat perlu mengunci harga lama untuk pelanggan lama, tempatnya
| di kolom `subscriptions.plan_slug` — beri slug baru (mis. `prime-2027`),
| jangan mengubah angka paket yang sedang dipakai orang.
|
| Nilai 0 berarti "tanpa batas", mengikuti perjanjian yang sudah dipakai
| `Workspace::hasQuotaRemaining()` untuk `monthly_message_quota`.
|
| Langganan melekat pada WORKSPACE, bukan pada akun. Satu orang yang memegang
| tiga cabang berlangganan tiga kali — dan itu memang yang seharusnya, karena
| biaya nyata kami (satu Chromium per nomor) juga tumbuh per workspace.
|
*/

return [

    /*
    | Paket yang diberikan ke workspace yang baru dibuat, dan ke workspace lama
    | yang belum pernah berlangganan saat penagihan dinyalakan.
    */
    'default' => 'essentials',

    /*
    | Harga tahunan = harga bulanan x 10. Dua bulan gratis, dan angkanya tidak
    | perlu ditulis dua kali sehingga tidak mungkin berbeda diam-diam.
    */
    'yearly_multiplier' => 10,

    'catalog' => [

        'essentials' => [
            'name' => 'Essentials',
            'tagline' => 'Satu usaha dengan satu nomor.',
            'price_monthly' => 149_000,
            'highlight' => false,

            'max_sessions' => 1,
            'monthly_message_quota' => 3_000,
            'max_api_keys' => 3,
            'max_members' => 2,
            'message_retention_days' => 30,
            'api_rate_limit_per_minute' => 60,

            'features' => [
                '1 nomor WhatsApp aktif',
                '3.000 pesan keluar per bulan',
                '3 API key',
                'Webhook pesan masuk & status',
                'Template pesan',
                'Riwayat pesan 30 hari',
                '2 anggota tim',
                'Dukungan lewat email',
            ],
        ],

        'prime' => [
            'name' => 'Prime',
            'tagline' => 'Volume besar dari satu nomor.',
            'price_monthly' => 249_000,
            'highlight' => true,

            'max_sessions' => 1,
            'monthly_message_quota' => 25_000,
            'max_api_keys' => 10,
            'max_members' => 10,
            'message_retention_days' => 90,
            'api_rate_limit_per_minute' => 120,

            'features' => [
                '1 nomor WhatsApp aktif',
                '25.000 pesan keluar per bulan',
                '10 API key',
                'Batas API 120 permintaan/menit',
                'Riwayat pesan 90 hari',
                '10 anggota tim dengan peran',
                'Dukungan lewat WhatsApp',
            ],
        ],

        'elite' => [
            'name' => 'Elite',
            'tagline' => 'Dua nomor dan volume tanpa khawatir.',
            'price_monthly' => 449_000,
            'highlight' => false,

            'max_sessions' => 2,
            'monthly_message_quota' => 100_000,
            'max_api_keys' => 0,
            'max_members' => 0,
            'message_retention_days' => 365,
            'api_rate_limit_per_minute' => 300,

            'features' => [
                '2 nomor WhatsApp aktif',
                '100.000 pesan keluar per bulan',
                'API key tanpa batas',
                'Batas API 300 permintaan/menit',
                'Riwayat pesan 12 bulan',
                'Anggota tim tanpa batas',
                'Dukungan WhatsApp prioritas',
            ],
        ],

    ],

];
