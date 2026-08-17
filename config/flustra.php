<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Produk Flustra lain
    |--------------------------------------------------------------------------
    |
    | Ditampilkan di footer halaman depan dan halaman dokumentasi. Daftarnya
    | ditaruh di sini, bukan disalin ke dua Blade, supaya menambah produk baru
    | tidak berakhir dengan dua footer yang isinya berbeda.
    |
    | Yang sengaja tidak masuk daftar: office.flustra.id (ERP) dan admin panel
    | karena dipakai internal, auth.flustra.id karena itu halaman masuk bukan
    | produk, dan linktree karena cuma kumpulan tautan.
    |
    */

    'produk' => [
        'Flustra ID' => 'https://flustra.id',
        'Portal Klien' => 'https://portal.flustra.id',
        'Helpdesk' => 'https://helpdesk.flustra.id',
        'Artikel' => 'https://artikel.flustra.id',
        'Harga Layanan' => 'https://pricing.flustra.id',
    ],

];
