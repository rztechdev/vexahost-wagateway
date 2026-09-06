# PRD — Enam Penambahan Menuju Enterprise

Dokumen ini untuk **agent berikutnya**, bukan untuk dibaca sekali lalu ditutup.
Enam fitur di bawah dikerjakan **satu per satu**, dalam urutan yang ditentukan di
§0, dan tiap fitur punya "Definisi selesai" yang harus benar-benar terpenuhi
sebelum pindah ke berikutnya.

Baca [CLAUDE.md](CLAUDE.md) lebih dulu. Dokumen ini mengasumsikan Anda sudah
tahu aturan yang berlaku di seluruh kode — terutama: semua teks Bahasa
Indonesia, semua pengiriman lewat `MessageDispatcher::queue()`, status langganan
hanya berpindah lewat `SubscriptionService`, dan komentar menjelaskan **alasan**,
bukan mekanisme.

---

## 0. Urutan pengerjaan, dan kenapa begitu

| # | Fitur | Kenapa di urutan ini |
|---|---|---|
| 1 | **Keamanan** (§3) | Paling kecil, tidak bergantung apa pun, dan menutup lubang yang sudah ada sekarang. Dikerjakan lebih dulu supaya lima fitur berikutnya lahir di atas dasar yang benar. |
| 2 | **Email/Brevo** (§6) | Kecil, dan **helpdesk bergantung padanya**. Tanpa email yang benar-benar terkirim, notifikasi tiket cuma janji. |
| 3 | **Webhook per API key** (§4) | Berdiri sendiri, tidak menyentuh penagihan. Dikerjakan sebelum dua fitur penagihan supaya tidak ada dua perubahan besar yang bertabrakan di berkas yang sama. |
| 4 | **Reseller & referal** (§1) | Menyentuh checkout dan tagihan, tapi tidak mengubah cara kuota dihitung. |
| 5 | **Pay as you go** (§2) | Paling berisiko: ia menambah **cara ketiga** kuota ditegakkan (setelah kuota bulanan dan jatah coba gratis). Dikerjakan setelah referal supaya diskon referal sudah ada saat saldo pertama dijual. |
| 6 | **Helpdesk** (§5) | Paling besar, dan satu-satunya yang bisa ditunda tanpa merugikan pelanggan yang sudah membayar. |

**"Satu per satu" berarti berurutan, bukan ganti sesi tiap fitur.** Kerjakan
keenamnya dalam satu rangkaian kalau memungkinkan — yang dilarang adalah
**mencampur dua fitur dalam satu perubahan**. Tiap fitur menyentuh
`SubscriptionService`, `MessageDispatcher`, atau panel admin; dua perubahan besar
yang bertabrakan di berkas yang sama menghasilkan kegagalan yang tidak bisa
ditelusuri ke salah satunya.

Aturannya: **selesai → buktikan → commit → baru pindah.** "Selesai" berarti
seluruh kotak di "Definisi selesai" fitur itu terpenuhi dan **dibuktikan dengan
menjalankannya**, bukan diklaim. Satu commit per fitur, supaya kalau ada yang
harus dibatalkan, yang batal cuma satu fitur.

---

## Keputusan yang SUDAH diambil Ryan — jangan ditanya ulang

| Hal | Keputusan |
|---|---|
| Pay as you go | **Isi saldo di depan** (prepaid). Bukan tagih di belakang. |
| Harga PAYG | **Rp 200 per pesan** |
| Reseller | Pembeli dapat **diskon**, reseller dapat **komisi** — dua-duanya |
| Kode referal | **5 huruf kapital acak**, ditukar saat checkout |
| Helpdesk | **Bangun baru di dalam flustra-wa.** JANGAN disambungkan ke `flustra-helpdesk` |
| Email | Pengirim pakai **email admin** (`flustrafinances@gmail.com`). Ryan yang mengonfigurasi di Brevo |

---

# §3. Keamanan data — dikerjakan pertama

## Masalah

Ryan meminta "SSL/TLS 256-bit dan terhindar dari peretasan". Yang perlu
dijernihkan lebih dulu: **TLS itu urusan Traefik/Coolify, bukan aplikasi.**
Sertifikat Let's Encrypt di Coolify sudah memberi TLS 1.2/1.3 dengan cipher
AES-256 secara bawaan; tidak ada yang perlu dikerjakan di Laravel untuk itu, dan
menambahkan "kode SSL" di aplikasi adalah pekerjaan yang tidak ada isinya.

Yang **benar-benar kurang** dan sudah diverifikasi di `.env.production` maupun di
kode:

| Temuan | Akibatnya |
|---|---|
| `SESSION_SECURE_COOKIE` tidak diset | Cookie sesi ikut terkirim lewat HTTP. Satu permintaan `http://` sebelum redirect sudah cukup membocorkan sesi admin di jaringan yang sama. |
| Tidak ada header keamanan sama sekali | Tidak ada HSTS (browser tidak tahu harus selalu HTTPS), tidak ada `X-Frame-Options` (halaman bisa di-iframe untuk clickjacking), tidak ada `X-Content-Type-Options`. |
| `SESSION_ENCRYPT=false` | Isi sesi tersimpan apa adanya di tabel `sessions`. |
| `trustProxies(at: '*')` | Mempercayai header `X-Forwarded-*` dari **siapa pun**. Benar di belakang Traefik, salah kalau port aplikasi pernah terekspos langsung. |

## Ruang lingkup

1. **Middleware header keamanan** (`app/Http/Middleware/HeaderKeamanan.php`),
   dipasang global. Isinya: `Strict-Transport-Security` (hanya saat HTTPS),
   `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
   `Referrer-Policy: strict-origin-when-cross-origin`,
   `Permissions-Policy` yang mematikan kamera/mikrofon/geolokasi.
   **Jangan** memasang CSP ketat di langkah ini — dashboard memakai Alpine dan
   skrip inline; CSP yang salah membuat seluruh antarmuka mati tanpa galat yang
   jelas. Kalau CSP diinginkan, itu pekerjaan tersendiri dengan `nonce`.
2. **`SESSION_SECURE_COOKIE=true`** dan **`SESSION_ENCRYPT=true`** di
   `.env.production` dan `.env.staging`. **JANGAN** di `.env` lokal — dev
   berjalan di `http://127.0.0.1` dan cookie secure membuat login lokal gagal
   tanpa pesan apa pun.
3. **Halaman Sistem** (`/admin/sistem`) menambahkan pemeriksaan keamanan:
   HTTPS aktif, cookie secure, `APP_DEBUG=false`, header terpasang. Tiap
   pemeriksaan menyebutkan **akibatnya** kalau merah, mengikuti pola yang sudah
   ada di halaman itu.
4. **Rate limit pada login** — periksa dulu apakah sudah ada; kalau belum,
   `RateLimiter` 5 percobaan per menit per IP+email. Tanpa ini, kata sandi bisa
   ditebak sebanyak-banyaknya, dan tidak ada satu pun catatan yang menunjukkannya.

## Yang mudah salah

- Memasang HSTS di lingkungan lokal membuat browser **menolak** `http://localhost`
  untuk waktu lama, dan itu tidak bisa dibatalkan dengan menghapus kode. Header
  ini hanya boleh keluar saat `$request->secure()`.
- Menyetel `SESSION_SECURE_COOKIE=true` di `.env` lokal membuat login gagal
  dengan gejala "form-nya kembali ke halaman login tanpa pesan galat" — sangat
  sulit ditebak.

## Definisi selesai

- [x] Header muncul di respons produksi, dan tidak ada satu pun halaman yang rusak
- [x] Login lokal (`http://127.0.0.1:8070`) tetap berfungsi
- [x] Halaman Sistem menampilkan empat pemeriksaan baru dengan akibatnya
- [x] `KeamananTest`: header ada saat HTTPS, HSTS **tidak** ada saat HTTP
- [x] Seluruh suite lulus di SQLite **dan** MySQL

**Selesai.** `HeaderKeamanan` dipasang global di `bootstrap/app.php`;
`SESSION_SECURE_COOKIE`/`SESSION_ENCRYPT` menyala di `.env.production` dan
`.env.staging` saja. Rate limit login ternyata **sudah ada** (`throttle:login`,
5/menit per email+IP di `AppServiceProvider`) — yang ditambahkan cuma tesnya.
Pemeriksaan HTTPS & cookie di halaman Sistem sengaja hijau di `local`/`testing`:
keduanya memang dimatikan di sana, dan merah tiap hari melatih orang
mengabaikan merah. `trustProxies(at: '*')` **tidak** diubah — ia disebut di
tabel temuan tapi tidak masuk ruang lingkup.

---

# §6. Konfigurasi email (Brevo) — dikerjakan kedua

## Masalah

`.env.production` memuat `MAIL_MAILER=log` (diubah dari `smtp` pada 6 Sep 2026
karena `smtp` tanpa `MAIL_HOST` jatuh ke `127.0.0.1:2525` dan gagal dengan
timeout, bukan galat yang jelas). Artinya **aplikasi ini tidak pernah mengirim
satu pun email**. Itu juga alasan pemulihan kata sandi mandiri sengaja dilepas.

Dua project Flustra lain sudah memakai Brevo dengan kredensial yang sama:

```
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=b43439001@smtp-brevo.com
MAIL_ENCRYPTION=tls
```

(Ada di `flustra-clientportal/.env.production` dan `flustra-erp/.env.production`.
Keduanya mengirim dari `officeflustra@gmail.com`.)

**Keputusan Ryan: flustra-wa memakai email admin sebagai pengirim, bukan
`officeflustra@gmail.com`.** Ryan sendiri yang mendaftarkannya di Brevo.

## Ruang lingkup

1. Isi `MAIL_*` di `.env.production` dan `.env.staging`:
   `MAIL_MAILER=smtp`, host/port/username/encryption seperti di atas,
   `MAIL_FROM_ADDRESS=flustrafinances@gmail.com`, `MAIL_FROM_NAME="${APP_NAME}"`.
   **`MAIL_PASSWORD` diisi Ryan** — jangan menyalin kredensial project lain ke
   sini tanpa persetujuannya.
2. **Halaman Sistem**: pemeriksaan "Email keluar" — `MAIL_MAILER` bukan `log`,
   host terisi. Akibat kalau merah: "Tidak ada satu pun email yang benar-benar
   terkirim; pemulihan kata sandi dan pemberitahuan tiket cuma janji."
3. **Tombol kirim email tes** di `/admin/pengecualian` (halaman itu sudah
   memegang tombol tes WhatsApp — email menyusul di sebelahnya, dengan pola
   pesan gagal yang sama: menyebut langkah mana yang belum selesai).
4. **Pemberitahuan email untuk penagihan**, mengiringi yang sudah ada di
   WhatsApp: tagihan terbit, pembayaran diterima, masa berlaku akan habis.
   Teksnya di kelas tersendiri (`App\Mail\...`), **jangan** ditulis di controller
   — alasannya sama dengan `BillingMessages`.

## Yang mudah salah

- **Brevo menolak pengirim yang belum diverifikasi.** Kalau
  `flustrafinances@gmail.com` belum didaftarkan sebagai sender di Brevo,
  seluruh email gagal dengan `550` dan itu **tidak terlihat di antarmuka mana
  pun** — sama persis dengan masalah notifikasi WhatsApp yang diam. Karena itu
  tombol tes di poin 3 wajib ada, bukan opsional.
- Jangan menghidupkan kembali pemulihan kata sandi mandiri di langkah ini.
  Itu keputusan tersendiri (CLAUDE.md mencatat alasannya) dan butuh empat rute,
  dua view, dan satu controller. Kalau Ryan menginginkannya, kerjakan terpisah
  **setelah** email terbukti terkirim.

## Definisi selesai

- [~] Tombol kirim email tes berhasil ke alamat mana pun
- [x] Halaman Sistem menunjukkan keadaan email dengan jujur
- [x] Tiga email penagihan terkirim di jalur yang sama dengan notifikasi WA
- [x] Kegagalan email **tidak pernah** menjatuhkan penagihan (pola `WhatsAppNotifier`)

**Selesai kecuali pembuktian pengiriman sungguhan.** Tombolnya ada dan
jalurnya terbukti utuh — form → controller → `EmailNotifier::kirimTes()` →
`AuditLog` (`settings.email.tested`) — tapi di lokal `MAIL_MAILER=log`, jadi
yang terbukti barulah jalur gagalnya, dengan pesan yang menyebut langkah yang
kurang. **Kotak pertama baru bisa dicentang setelah Ryan mengisi
`MAIL_PASSWORD` di Coolify dan mendaftarkan `flustrafinances@gmail.com`
sebagai sender di Brevo**, lalu menekan tombolnya sekali di staging.

`MAIL_*` sudah terisi di `.env.production` dan `.env.staging` (keduanya
gitignored — harus disalin manual ke Coolify), `MAIL_PASSWORD` sengaja
dikosongkan. Tagihan terbit sengaja TIDAK punya pasangan WhatsApp: ia terbit
di hari yang sama dengan pengingat H-3, dan dua pesan WA beruntun tentang uang
yang sama terbaca seperti penagihan ganda.

Di luar ruang lingkup tapi ikut karena menghalangi: sebelas pemanggilan
`BillingCycleJob::handle()` di `BillingCycleTest` dulu mendaftar dependensinya
satu per satu, jadi dependensi baru apa pun menjatuhkan sebelasnya dengan
`ArgumentCountError`. Sekarang lewat `app()->call()`, persis seperti antrean
menjalankannya di produksi.

---

# §4. Webhook per API key — dikerjakan ketiga

## Masalah

Ryan: *"kalo untuk satu api satu web bagaimana cara mengakalinya … seperti
payment gateway Xendit yang satu aktivasi satu web."*

Sekarang webhook menempel di **workspace** (`webhooks.workspace_id`). Pelanggan
dengan tiga website menerima kejadian yang sama di ketiganya, dan tidak ada cara
tahu kejadian itu berasal dari integrasi yang mana. Untuk pelanggan enterprise
yang punya toko, aplikasi kasir, dan CRM di satu workspace, itu tidak terpakai.

**Keputusan: webhook menempel di API key.** Satu API key = satu website = satu
webhook URL, persis pola Xendit.

## Model data

```
webhooks
  + api_key_id  (nullable, FK ke api_keys, nullOnDelete)
```

`null` berarti **webhook tingkat workspace** — berlaku untuk kejadian yang tidak
punya API key pemicu (mis. `session.status`, `session.qr`). Baris lama otomatis
jadi webhook tingkat workspace, jadi **tidak ada pelanggan yang webhook-nya mati
saat migrasi berjalan**. Itu syarat mutlak: memindahkan webhook berarti memutus
integrasi yang sedang berjalan di sisi pelanggan, dan mereka tidak akan tahu
sampai ada pesan yang hilang.

## Aturan pengiriman

| Kejadian | Dikirim ke |
|---|---|
| `message.status` (pesan keluar) | Webhook milik API key **yang mengirim pesan itu**, kalau ada; kalau tidak, webhook workspace |
| `message.received` (pesan masuk) | **Seluruh** webhook workspace + seluruh webhook API key. Pesan masuk tidak punya "pengirim", jadi tidak ada dasar memilih salah satu |
| `session.*` | Seluruh webhook workspace saja |

Untuk `message.status` dibutuhkan kolom baru: `messages.api_key_id` (nullable) —
diisi `MessageDispatcher::queue()` dari API key yang sedang dipakai. Pesan yang
dikirim dari dashboard tidak punya API key, dan itu benar: statusnya jatuh ke
webhook workspace.

## Ruang lingkup

1. Migrasi: `webhooks.api_key_id`, `messages.api_key_id`
2. `MessageDispatcher::queue()` menerima API key aktif (dari
   `AuthenticateApiKey`, taruh di request attribute — **jangan** lewat singleton
   global, itu bocor antar-job di worker)
3. `WebhookDispatcher` memilih tujuan sesuai tabel di atas
4. Halaman Webhook di dashboard: tiap baris menyebutkan ia milik API key yang
   mana, atau "seluruh workspace"
5. Dokumentasi publik: satu bagian baru tentang memisahkan beberapa website

## Yang mudah salah

- Jangan mengubah bentuk payload webhook. Aplikasi pelanggan sudah mem-parsing-nya;
  menambah field aman, mengubah nama field memutus integrasi yang berjalan.
- `AuthenticateApiKey` berjalan di permintaan HTTP, sedangkan pengiriman terjadi
  di **job**. API key harus disimpan ke baris `messages` saat antre, bukan
  dibaca lagi saat kirim — di dalam job tidak ada request.

## Definisi selesai

- [ ] Webhook lama (tanpa `api_key_id`) tetap menerima semua kejadian seperti sebelumnya
- [ ] `message.status` dari API key A tidak sampai ke webhook API key B
- [ ] Pesan dari dashboard tetap sampai ke webhook workspace
- [ ] `WebhookRoutingTest` menguji ketiganya

---

# §1. Reseller & kode referal — dikerjakan keempat

## Keputusan

- Kode: **5 huruf kapital acak** (`A-Z`, tanpa angka). Hindari huruf yang mudah
  tertukar saat didikte lewat telepon — **buang I, O**, sisakan 24 huruf.
  24⁵ = 7,9 juta kombinasi; tabrakan diperiksa saat pembuatan, bukan diharapkan
  tidak terjadi.
- **Pembeli dapat diskon**, **reseller dapat komisi**. Dua-duanya.
- Ditukar **saat checkout**.

## Model data

```
referral_codes
  id, code (5 huruf, unik), owner_user_id (FK users),
  discount_percent (int, mis. 10), commission_percent (int, mis. 20),
  max_redemptions (nullable), redeemed_count,
  expires_at (nullable), is_active, created_by, timestamps

referral_redemptions
  id, referral_code_id, workspace_id, invoice_id,
  discount_amount, commission_amount, status (pending|approved|paid|void),
  created_at
```

Kolom baru: `invoices.referral_code_id` (nullable) dan `invoices.discount_amount`.

**`discount_amount` disimpan di tagihan, bukan dihitung ulang saat ditampilkan.**
Persen di `referral_codes` boleh berubah kapan saja; tagihan yang sudah terbit
tidak boleh ikut berubah nilainya. Ini aturan yang sama dengan `invoices.total`
yang sudah ada.

## Aturan

1. Diskon **hanya untuk tagihan pertama** sebuah workspace. Kalau tidak, satu
   kode bisa memotong seluruh pendapatan dari pelanggan itu selamanya.
2. Satu workspace hanya bisa menukar **satu** kode, sekali seumur hidup.
3. Reseller **tidak bisa** memakai kodenya sendiri — periksa `owner_user_id`
   terhadap pemilik workspace.
4. Komisi dihitung dari **total setelah diskon**, dan baru berstatus `approved`
   saat tagihannya **lunas**. Tagihan yang kedaluwarsa atau dibatalkan tidak
   pernah menghasilkan komisi.
5. Pembayaran komisi **manual** (transfer). Panel admin menampilkan berapa yang
   terutang ke siapa, dan tombol menandai sudah dibayar. Jangan membangun
   pembayaran otomatis — pembayaran masuk saja masih dicocokkan manusia.

## Antarmuka

- **Halaman bayar**: kolom "Punya kode referal?" — validasi lewat AJAX,
  menampilkan potongannya sebelum tagihan terbit
- **Panel admin, menu baru "Reseller"**: daftar kode, siapa pemiliknya, berapa
  kali dipakai, komisi terutang vs sudah dibayar
- **Dashboard reseller**: halaman untuk pemilik kode melihat kodenya, berapa
  yang mendaftar, dan komisinya. Boleh ditunda ke tahap kedua kalau resellernya
  masih sedikit dan Ryan yang melaporkan manual.

## Yang mudah salah

- Kode yang ditukar saat checkout tapi tagihannya tidak pernah dibayar **tidak
  boleh** menghabiskan jatah `max_redemptions`. Hitung `redeemed_count` saat
  **lunas**, bukan saat ditukar.
- Diskon harus masuk sebelum kode unik tiga digit dihitung
  (`SubscriptionService::allocateUniqueCode`), kalau tidak nominal yang
  ditransfer pelanggan tidak akan cocok dengan tagihannya.

## Definisi selesai

- [ ] Kode dibuat dari panel admin, 5 huruf, tidak pernah bertabrakan
- [ ] Diskon terlihat di halaman bayar sebelum tagihan terbit
- [ ] Kode sendiri ditolak; kode kedua di workspace yang sama ditolak
- [ ] Komisi baru `approved` setelah lunas; tagihan kedaluwarsa tidak menghasilkan apa pun
- [ ] Nominal unik tetap cocok setelah diskon
- [ ] `ReferralTest` menguji seluruh penolakan di atas, bukan cuma jalur bahagia

---

# §2. Pay as you go — dikerjakan kelima

## Keputusan

- **Isi saldo di depan** (prepaid). Bukan tagih di belakang.
- **Rp 200 per pesan.**

## Kenapa prepaid, dan kenapa Rp 200 itu angka yang benar

Pembayaran di sistem ini masih dicocokkan manusia lewat QRIS. Postpaid berarti
pelanggan mengirim ribuan pesan lalu menghilang, dan pesannya sudah telanjur
terkirim — uangnya tidak bisa ditarik kembali.

Harga per pesan paket yang ada sekarang:

| Paket | Harga | Kuota | Per pesan |
|---|---|---|---|
| Essentials | 149.000 | 2.000 | **Rp 74,50** |
| Prime | 249.000 | 10.000 | Rp 24,90 |
| Elite | 449.000 | 50.000 | Rp 8,98 |

Rp 200 jauh di atas paket termurah, jadi PAYG **tidak menggerus penjualan
paket** — dan itu memang tujuannya. Titik impasnya: **745 pesan per bulan.**
Di atas itu Essentials selalu lebih murah, dan itu cerita jualan yang bersih:
"PAYG untuk yang kirimnya sedikit dan tidak tentu; kalau rutin, ambil paket."

## Model data

```
workspaces
  + billing_mode (enum: 'subscription' | 'payg', default 'subscription')
  + balance (bigint, dalam rupiah, default 0)

balance_transactions
  id, workspace_id, type (topup|charge|refund|adjustment),
  amount (positif menambah, negatif mengurangi),
  balance_after, message_id (nullable), invoice_id (nullable),
  note, created_by (nullable), created_at
```

**`balance_transactions` adalah buku besar, bukan log.** Saldo di
`workspaces.balance` harus selalu sama dengan jumlah seluruh mutasinya, dan
halaman admin wajib punya cara memeriksa kecocokan itu. Saldo yang tidak bisa
direkonsiliasi adalah uang pelanggan yang tidak bisa dipertanggungjawabkan.

## Penegakan

`MessageDispatcher::guardWorkspace()` sekarang punya dua cabang (jatah coba
gratis seumur hidup, dan kuota bulanan paket). PAYG menambah **cabang ketiga**:

```
if ($workspace->billing_mode === 'payg') {
    if ($workspace->balance < config('billing.payg.price_per_message')) {
        throw new RuntimeException('Saldo tidak cukup. Isi saldo untuk melanjutkan.');
    }
    return;   // pemotongan terjadi SETELAH terkirim, bukan di sini
}
```

**Pemotongan saldo terjadi saat pesan benar-benar terkirim** (`SendMessageJob`
sukses), bukan saat diantrekan. Bedanya penting: pesan yang gagal karena nomor
tidak terdaftar tidak boleh memotong saldo pelanggan. Konsekuensinya harus
diterima sadar — broadcast besar bisa membuat saldo minus sedikit karena
beberapa pesan sedang berjalan bersamaan. Batasi dengan memeriksa saldo saat
antre **dan** saat kirim.

## Ruang lingkup

1. Paket `payg` di `config/plans.php` dengan `sellable => true` tapi bentuk
   harga berbeda (per pesan, bukan per bulan). Halaman harga menampilkannya
   sebagai kartu keempat dengan bentuk yang berbeda — jangan memaksanya masuk
   cetakan kartu paket bulanan.
2. Alur isi saldo memakai **jalur tagihan yang sudah ada**: `issueInvoice` dengan
   jenis `topup`, QRIS, unggah bukti, admin menandai lunas → saldo bertambah.
   Jangan membuat alur pembayaran kedua.
3. `SubscriptionService::markPaid()` bercabang: tagihan langganan memperpanjang
   periode, tagihan topup menambah saldo. **Ini titik paling berisiko di seluruh
   PRD** — satu kesalahan di sini berarti pelanggan membayar dan tidak menerima
   apa pun.
4. Minimum isi saldo **Rp 50.000** (250 pesan). Di bawah itu ongkos verifikasi
   manualnya lebih besar dari nilainya.
5. Peringatan saldo menipis lewat WhatsApp di 20% dan 0, memakai
   `BillingMessages` dan penanda cache yang sudah ada.
6. Halaman Saldo di dashboard: sisa, riwayat mutasi, tombol isi saldo.

## Yang mudah salah

- Workspace PAYG **tidak punya** `current_period_end`, jadi seluruh kode yang
  membaca tanggal itu harus tahan `null` — termasuk `isExpiringSoon()`,
  `BillingCycleJob`, dan spanduk di layout. Jatah coba gratis sudah punya
  masalah yang sama dan sudah diperbaiki; ikuti polanya.
- `workspaces.service_until` untuk PAYG: **`null`**. Yang membatasi saldo, bukan
  tanggal.
- Jangan memakai `float` untuk uang. Rupiah, bilangan bulat, satuan rupiah penuh.

## Definisi selesai

- [ ] Isi saldo lewat QRIS menambah saldo hanya setelah admin menandai lunas
- [ ] Pesan memotong saldo saat terkirim, bukan saat antre
- [ ] Pesan gagal tidak memotong saldo
- [ ] Saldo habis menghentikan pengiriman dengan pesan yang bisa ditindaklanjuti
- [ ] `workspaces.balance` selalu sama dengan jumlah `balance_transactions`
- [ ] Halaman harga menampilkan PAYG tanpa merusak tiga kartu yang sudah ada
- [ ] `PaygTest` menguji seluruhnya, termasuk rekonsiliasi saldo

---

# §5. Helpdesk — dikerjakan terakhir

## Keputusan Ryan

**Bangun baru di dalam flustra-wa. JANGAN disambungkan ke `flustra-helpdesk`.**

Catatan untuk agent berikutnya, sekali saja lalu jangan diperdebatkan lagi:
`flustra-helpdesk` memang sudah ada dan sudah memakai gateway ini. Ryan sudah
tahu, dan memilih helpdesk yang berdiri sendiri di dalam produk ini. Alasannya
masuk akal untuk SaaS: pelanggan flustra-wa tidak seharusnya dilempar ke produk
lain untuk mengeluh. **Kerjakan sesuai keputusannya, jangan mengusulkan
integrasi lagi.**

## Model data

```
tickets
  id, workspace_id, user_id, subject, category, priority (low|normal|high),
  status (open|answered|closed), last_reply_at, closed_at, timestamps

ticket_messages
  id, ticket_id, user_id (nullable — null berarti balasan admin),
  body, is_from_admin, attachment_path (nullable), created_at
```

## Ruang lingkup

1. **Sisi pelanggan**: menu "Bantuan" — daftar tiket, buat tiket, balas.
   Tersedia untuk **semua** workspace termasuk yang belum berlangganan dan yang
   langganannya mati. Pelanggan yang layanannya berhenti justru yang paling
   butuh menghubungi kami; menutup helpdesk untuk mereka adalah kesalahan yang
   paling mahal.
2. **Sisi admin**: menu "Tiket" — daftar dengan saringan status, halaman
   detail, balas, tutup. Saringan bawaan **"perlu dijawab"**, mengikuti pola
   yang sudah terbukti di halaman Tagihan.
3. **Notifikasi WhatsApp** (Ryan menegaskan ini wajib):
   - Tiket baru → **admin** (`BillingMessages` → ganti nama jadi kelas
     pemberitahuan yang lebih umum, atau buat `HelpdeskMessages` sejenis)
   - Admin membalas → **pelanggan**
   - Pelanggan membalas → **admin**
4. **Notifikasi email** untuk hal yang sama, memakai §6 yang sudah selesai.

## Yang mudah salah

- Notifikasi tiket **wajib** lewat `WhatsAppNotifier` — tidak pernah melempar
  galat, selalu dari sesi Flustra, satu peristiwa satu pesan. Menulis pengiriman
  sendiri di controller melanggar aturan yang sudah ditulis di CLAUDE.md.
- Lampiran: batasi jenis dan ukuran, simpan di disk `media` seperti bukti bayar.
  Jangan pernah menyajikannya lewat URL yang bisa ditebak — ikuti pola
  `billing.proof` yang sudah memeriksa kepemilikan.
- Helpdesk **tidak boleh** tunduk pada `EnsureSubscriptionActive` untuk POST.
  Rutenya di luar grup itu, atau dikecualikan secara eksplisit.

## Definisi selesai

- [ ] Pelanggan dengan langganan mati tetap bisa membuat dan membalas tiket
- [ ] Admin dikabari lewat WhatsApp saat ada tiket baru dan balasan
- [ ] Pelanggan dikabari saat dibalas
- [ ] Lampiran tidak bisa dibuka workspace lain
- [ ] `HelpdeskTest` menguji keempatnya

---

## Aturan yang berlaku untuk SEMUA enam fitur

1. **Tes lulus di SQLite dan MySQL.** Lokal SQLite, produksi MySQL. Cara
   menjalankan di MySQL ada di §"Cara menguji" di bawah. Ini bukan formalitas —
   satu migrasi ber-SQL mentah sudah pernah lolos SQLite dan jatuh di MySQL.
2. **Migrasi tidak boleh memutus yang sedang berjalan.** Kolom baru `nullable`
   atau berisi nilai bawaan yang benar untuk baris lama.
3. **Tiap fitur menambah pemeriksaannya sendiri di `/admin/sistem`** kalau ia
   punya cara gagal yang tidak terlihat. Itu satu-satunya tempat yang menjawab
   "apa yang sedang rusak tanpa ada yang tahu".
4. **Pengecualian yang sudah ada wajib dihormati**: `Workspace::isExempt()`
   melewati penagihan, `SpecialNumber` melewati batas sesi dan status workspace.
   Fitur baru yang memeriksa langganan harus memakai helper itu, bukan membaca
   `is_internal` langsung.
5. **Jangan sentuh `flustra-pricing`.** Beda ranah, sudah ditegaskan Ryan.
6. **Satu commit, satu push.** Push beruntun membuat deploy Coolify antre.

## Cara menguji di MySQL

```bash
php -r "(new PDO('mysql:host=127.0.0.1','root',''))->exec('CREATE DATABASE uji_flustra CHARACTER SET utf8mb4');"
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=uji_flustra DB_USERNAME=root DB_PASSWORD= php artisan test
```

## Yang sengaja TIDAK ada di PRD ini

- **Pembayaran otomatis (iPaymu).** Masih menunggu verifikasi akun. Selama
  pencocokan manual, jangan membuat antarmuka penyalur pembayaran — antarmuka
  dengan satu implementasi adalah slot kosong yang menjanjikan sesuatu yang
  belum ada.
- **Pemulihan kata sandi mandiri.** Baru masuk akal setelah §6 terbukti
  mengirim.
- **CSP ketat.** Butuh `nonce` di seluruh skrip inline; pekerjaan tersendiri.
- **Dashboard realtime.** Sudah tercatat di `docs/ARSITEKTUR.md` sebagai yang
  sengaja belum dibuat.
