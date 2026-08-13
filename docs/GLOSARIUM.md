# Glosarium

Istilah yang dipakai di seluruh kode dan dokumentasi.

---

### ack

Tanda terima dari WhatsApp. Angkanya bertingkat: `1` sampai ke server, `2` sampai ke HP tujuan, `3`/`4` dibaca. Dipetakan menjadi status `sent`, `delivered`, `read`.

Ack bisa datang tidak berurutan, karena itu status pesan dijaga agar hanya boleh maju.

### antrean (queue)

Ada dua, dan keduanya berbeda tujuan.

**Antrean Laravel** (tabel `jobs`) memisahkan pengiriman dari siklus permintaan dan memberi percobaan ulang.

**Antrean engine** (dalam memori, per sesi) memberi jeda antar pesan supaya nomor tidak diblokir. Ada di engine, bukan Laravel, supaya isolasinya per sesi — broadcast satu tenant tidak menahan pesan tenant lain.

### API key

Kredensial untuk memanggil REST API, dikirim lewat header `X-Api-Key`. Format `fwa_<prefix>.<rahasia>`. Yang tersimpan di server hanya hash-nya, jadi nilai penuhnya hanya bisa dilihat sekali saat dibuat.

### backoff

Jeda yang membesar antar percobaan ulang. `SendMessageJob` memakai 10 detik, 1 menit, 5 menit — sesi yang baru putus butuh waktu untuk pulih, jadi mengulang secepat mungkin hanya membakar jatah percobaan.

### batch_id

ULID yang menandai semua pesan dari satu broadcast, sehingga bisa ditelusuri sebagai satu kesatuan.

### bootstrap (engine)

Proses saat engine menyala: menanyakan daftar sesi ke Laravel, memulihkan kredensialnya, lalu menyambungkan kembali. Inilah yang membuat deploy ulang tidak memaksa scan QR.

### chat id

Alamat percakapan di WhatsApp. Perorangan: `6281234567890@c.us`. Grup: `<id>@g.us`.

### driver

Cara pesan dikirim. `wwebjs` (aktif), `cloud_api` (slot untuk Meta/Twilio resmi), `fonnte`. Dipilih per sesi lewat kolom `wa_sessions.driver`.

### engine

Program Node.js terpisah yang benar-benar berbicara dengan WhatsApp. Menjalankan Chromium lewat Puppeteer. Tidak punya database — semua yang perlu diingat ada di Laravel.

### HMAC

Tanda tangan berbasis secret bersama. Dipakai dua tempat: callback engine → Laravel, dan kiriman webhook → tenant. Membuktikan pengirimnya benar dan isinya tidak diubah di jalan.

### idempoten

Operasi yang aman diulang tanpa mengubah hasil. `gateway:setup-tenant` dibuat idempoten supaya penyiapan server bisa dijalankan berulang.

### kind (sesi)

`platform` atau `tenant`.

**Platform** — nomor resmi Flustra, untuk pesan atas nama Flustra: OTP, undangan anggota, notifikasi billing.

**Tenant** — nomor milik pelanggan, untuk pesan atas nama pelanggan: invoice ke customer, PO ke vendor.

Dipisah karena customer pelanggan tidak mengenal Flustra, dan trafik pihak ketiga dari satu nomor platform akan cepat membuatnya diblokir.

### kuota

Batas pesan keluar per bulan per tenant. Dihitung **saat pesan diantre**, bukan saat terkirim — kalau dihitung belakangan, satu tenant bisa mengantrekan puluhan ribu pesan sebelum ketahuan melewati batas.

### LocalAuth / RemoteAuth

Dua cara whatsapp-web.js menyimpan kredensial sesi.

**LocalAuth** hanya ke filesystem. Ini yang dipakai versi lama di flustra-erp, dan penyebab sesi hilang setiap deploy.

**RemoteAuth** menyimpan ke filesystem **dan** memampatkannya berkala ke penyimpanan luar lewat store yang bisa ditulis sendiri. Gateway ini memakainya dengan store yang mengirim ke Laravel.

### multi-tenant

Satu sistem melayani banyak pelanggan dengan data yang terpisah. Semua kueri berangkat dari tenant yang sedang aktif, bukan dari model global.

### normalisasi nomor

Mengubah `0812…`, `+62 812-…`, `62812…` menjadi satu bentuk `62812…`. Aturannya sengaja sama persis dengan `WhatsAppLink` di flustra-web supaya nomor yang sudah tersimpan di aplikasi lain tidak berubah arti.

### OTP

Kode sekali pakai untuk membuktikan kepemilikan nomor. Gateway yang membuat dan mengirimnya; flustra-auth yang menyimpan hasil verifikasinya sebagai bagian dari identitas.

### persistent volume

Penyimpanan yang hidupnya terpisah dari container. Container boleh dihapus dan dibuat ulang; isinya tetap. Di sinilah kredensial sesi disimpan.

### provider

Lihat **driver**. `WhatsAppProvider` adalah interface-nya di kode.

### Puppeteer

Library Node untuk mengendalikan Chromium secara terprogram. Dipakai whatsapp-web.js untuk membuka dan mengoperasikan WhatsApp Web.

### rate limit

Batas jumlah permintaan API per menit. Dihitung **per API key**, bukan per IP — beberapa aplikasi Flustra berjalan di VPS yang sama.

### retensi

Berapa lama data disimpan sebelum dipangkas otomatis. Pesan 90 hari, log webhook 30 hari (produksi). Angka pemakaian tetap aman karena `usage_counters` menyimpan agregat terpisah.

### scope

Pembatas kemampuan API key. `*` untuk semuanya, `otp` untuk endpoint OTP. Scope `otp` hanya diberikan ke flustra-auth.

### sesi

Satu nomor WhatsApp yang tertaut, beserta kredensialnya. Satu sesi = satu Chromium di engine.

### soft delete

Menandai baris sebagai terhapus (`deleted_at`) tanpa benar-benar membuangnya. Dipakai `tenants` dan `wa_sessions`, memberi jeda sebelum penghapusan permanen.

### tenant (workspace)

Wadah yang memisahkan satu pelanggan dari yang lain. Nomor, API key, pesan, template, dan webhook semuanya milik satu tenant.

### ULID

Pengenal unik yang bisa diurutkan waktu, mirip UUID tapi lebih ringkas dan aman dipakai sebagai nama file. Dipakai `wa_sessions.id` dan `messages.id`.

### webhook

Kiriman HTTP dari gateway ke aplikasi tenant saat ada kejadian — pesan masuk, status berubah. Setiap kiriman ditandatangani HMAC.

### whatsapp-web.js

Library Node yang mengotomasi WhatsApp Web. Bukan API resmi: ia menjalankan browser sungguhan dan menekan tombolnya secara terprogram.
