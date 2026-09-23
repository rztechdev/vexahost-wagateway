<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Produk VexaHost lain
    |--------------------------------------------------------------------------
    |
    | Ditampilkan di footer halaman depan dan halaman dokumentasi. Daftarnya
    | ditaruh di sini, bukan disalin ke dua Blade, supaya menambah produk baru
    | tidak berakhir dengan dua footer yang isinya berbeda.
    |
    */

    'produk' => [
        'VexaHost Cloud' => 'https://vexahostcloud.my.id',
        'Jasa Web' => 'https://build.vexahostcloud.my.id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Akun super admin pertama
    |--------------------------------------------------------------------------
    |
    | Disamakan persis dengan akun admin aplikasi vexahost — nama, email,
    | kata sandi, nomor, dan perusahaan dibaca dari variabel env yang sama
    | (`ADMIN_*`), jadi satu orang memegang satu identitas admin di kedua
    | aplikasi. Dibaca lewat config, bukan env() langsung, karena migrasi
    | pemindahan admin lama ikut memakainya dan env() mengembalikan null begitu
    | config di-cache.
    |
    | Kata sandi bawaan hanya untuk lokal. Seeder memperingatkan kalau nilai ini
    | yang terpakai, dan migrasi pemindahan admin tidak pernah menulisnya.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Ryan Rizki'),
        'email' => env('ADMIN_EMAIL', 'vexahostcloudtech@gmail.com'),
        'password' => env('ADMIN_PASSWORD'),
        'phone' => env('ADMIN_PHONE', '6285808749131'),
        'company' => env('ADMIN_COMPANY', 'VexaHost Cloud Indonesia'),
    ],

];
