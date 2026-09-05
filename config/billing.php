<?php

/*
|--------------------------------------------------------------------------
| Penagihan
|--------------------------------------------------------------------------
|
| Saluran pembayaran yang aktif sekarang hanya satu: QRIS dinamis dengan
| konfirmasi manual. Pembayaran tidak memberi tahu kami secara otomatis, jadi
| pencocokannya bersandar pada dua hal — nominal yang dibuat unik lewat kode
| tiga digit, dan bukti transfer yang diunggah pelanggan.
|
| Saat akun iPaymu terverifikasi, saluran kedua masuk sebagai satu kelas baru
| di `app/Services/Billing/` plus satu route webhook. Tidak ada satu pun bagian
| langganan, invoice, atau penegakan batas yang perlu berubah — itulah sebabnya
| `invoices.channel` mencatat saluran, bukan menawarkannya sebagai pilihan.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | QRIS
    |--------------------------------------------------------------------------
    |
    | `payload` adalah isi QRIS STATIS merchant, yaitu string panjang berawalan
    | `00020101021126...` yang tercetak di balik kode QR dari penyedia QRIS.
    | Aplikasi menyisipkan nominal ke dalamnya untuk membuat QRIS dinamis, jadi
    | pelanggan tidak perlu mengetik jumlah dan tidak bisa salah ketik.
    |
    | Pakai QRIS MERCHANT, bukan QRIS akun pribadi: akun pribadi punya batas
    | nominal bulanan dan bisa dibekukan bank begitu polanya terbaca komersial.
    |
    | Selama `payload` kosong, halaman pembayaran menampilkan instruksi transfer
    | bank dan menyembunyikan kode QR — lebih baik daripada memunculkan kode
    | rusak yang gagal discan di depan pelanggan yang sedang mau membayar.
    |
    | Di env, nilainya HARUS dikutip. Payload QRIS memuat nama kota merchant
    | (tag 60) yang hampir selalu mengandung spasi, dan dotenv menolak seluruh
    | berkas begitu menemukan spasi tanpa kutip — aplikasi mati total, bukan
    | cuma kehilangan QRIS-nya. Spasi di dalamnya, termasuk yang di ujung nama
    | kota, ikut dihitung panjang tag: satu saja terpangkas, CRC tidak cocok
    | lagi dan `Qris::valid()` menolak payload yang sebenarnya benar.
    |
    */

    'qris' => [
        'payload' => env('QRIS_PAYLOAD'),
        'merchant' => env('QRIS_MERCHANT_NAME', 'Flustra'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rekening bank (cadangan, dan untuk nominal besar)
    |--------------------------------------------------------------------------
    |
    | Batas QRIS per transaksi mengikuti kebijakan tiap penerbit dompet digital
    | dan bisa berhenti di Rp 5 juta — sementara paket tahunan Elite mendekati
    | angka itu. Transfer bank karena itu selalu ditampilkan berdampingan,
    | bukan disembunyikan sebagai jalur darurat.
    |
    */

    'bank' => [
        'name' => env('BILLING_BANK_NAME'),
        'account_number' => env('BILLING_BANK_ACCOUNT'),
        'account_holder' => env('BILLING_BANK_HOLDER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kode unik
    |--------------------------------------------------------------------------
    |
    | Ditambahkan ke nominal setiap tagihan supaya dua pelanggan yang membayar
    | paket sama di hari sama tidak menghasilkan mutasi yang identik. Tanpa ini
    | tidak ada cara mencocokkan pembayaran dengan tagihan selain menebak.
    |
    | Kode dijamin unik di antara tagihan yang masih menunggu bayar, bukan unik
    | selamanya — dua tagihan yang sudah lunas boleh memakai kode yang sama.
    |
    */

    'unique_code' => [
        'enabled' => (bool) env('BILLING_UNIQUE_CODE', true),
        'min' => 1,
        'max' => 999,
    ],

    /*
    |--------------------------------------------------------------------------
    | Irama siklus
    |--------------------------------------------------------------------------
    |
    | `issue_days_before` sengaja lebih dari nol: VA dan gerai ritel butuh waktu,
    | dan tagihan yang baru terbit di hari jatuh tempo berarti setiap pelanggan
    | mengalami layanan berhenti minimal sekali.
    |
    | `grace_days` adalah jarak antara "pengiriman berhenti" dan "sesi diputus".
    | Memutus sesi berarti pelanggan harus scan QR ulang saat kembali membayar,
    | dan itu alasan nomor satu orang tidak kembali. Selama masa ini nomornya
    | tetap tertaut, hanya tidak bisa dipakai mengirim.
    |
    */

    'invoice_due_days' => (int) env('BILLING_INVOICE_DUE_DAYS', 7),
    'issue_days_before' => (int) env('BILLING_ISSUE_DAYS_BEFORE', 3),
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 30),
    'reminder_days' => [7, 3, 1, 0],

    /*
    |--------------------------------------------------------------------------
    | Pengingat lewat WhatsApp
    |--------------------------------------------------------------------------
    |
    | Diisi id workspace milik Flustra sendiri yang nomornya sudah tertaut.
    | Kosong = pengingat cukup lewat spanduk di dashboard. Kegagalan mengirim
    | pengingat tidak pernah boleh menjatuhkan siklus penagihan; ia pelengkap.
    |
    */

    'notify_workspace_id' => env('BILLING_NOTIFY_WORKSPACE_ID'),

    /*
    | Nomor WhatsApp tim kami sendiri. Ke sinilah pemberitahuan yang butuh
    | tindakan manusia dikirim — terutama "ada bukti pembayaran baru masuk",
    | karena selama pembayaran dicocokkan manual, tagihan hanya menjadi lunas
    | kalau ada orang yang membukanya di panel.
    */
    'admin_phone' => env('BILLING_ADMIN_PHONE'),

    /*
    | Alamat yang dipakai pelanggan saat butuh manusia — termasuk saat lupa
    | kata sandi, karena pemulihan mandiri sengaja belum dibuat.
    */
    'support_email' => env('BILLING_SUPPORT_EMAIL', 'flustrafinances@gmail.com'),

    /*
    |--------------------------------------------------------------------------
    | Pajak
    |--------------------------------------------------------------------------
    |
    | 0 berarti harga yang dipajang sudah final dan tidak ada baris pajak di
    | tagihan. Begitu diisi (mis. 11), harga paket diperlakukan sebagai harga
    | SEBELUM pajak dan pajaknya muncul sebagai baris tersendiri — jadi jangan
    | mengisinya tanpa memutuskan lebih dulu apakah harga yang dipajang di
    | halaman depan sudah termasuk pajak atau belum.
    |
    */

    'tax_percent' => (float) env('BILLING_TAX_PERCENT', 0),

];
