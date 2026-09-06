# Environment: Development, Staging, Production

Flustra WA Gateway berjalan di **tiga tahap terpisah**, mengikuti pola yang sudah dipakai seluruh ekosistem Flustra (`PANDUAN_DEVTOSPROD_DAN_COOLIFY_FLUSTRA.md`).

Setiap tahap adalah lingkungan yang berdiri sendiri: domain sendiri, database sendiri, volume sendiri, dan **nomor WhatsApp sendiri**.

## Peta tahap

| | Development | Staging | Production |
|---|---|---|---|
| Domain | `wa-dev.flustra.tech` | `wa-staging.flustra.tech` | `wa.flustra.id` |
| Branch git | `dev` | `staging` | `main` |
| `APP_ENV` | `development` | `staging` | `production` |
| `APP_DEBUG` | `true` | `false` | `false` |
| `LOG_LEVEL` | `debug` | `info` | `error` |
| Database | `db_flustra-wa_dev` | `db_flustra-wa_staging` | `db_flustra-wa` |
| Nama resource | `flustra-wa-dev` | `flustra-wa-staging` | `flustra-wa` |
| `ENGINE_URL` | `http://127.0.0.1:3100` | idem | idem |
| Volume sesi | `wa-sessions-dev` | `wa-sessions-staging` | `wa-sessions` |
| Volume cadangan | `wa-storage-dev` | `wa-storage-staging` | `wa-storage` |
| `WA_MAX_SESSIONS` | 1 | 1 | 3 |
| Nomor WhatsApp | nomor uji coba | nomor uji coba (boleh sama dengan dev, sesi terpisah) | nomor resmi Flustra |

> **Satu resource per tahap sejak 17 Agustus 2026.** Engine dan worker tidak lagi punya resource sendiri; keduanya proses di dalam container yang sama (lihat [DEPLOYMENT.md](DEPLOYMENT.md)). Akibatnya untuk berkas ini: **variabel Laravel dan engine berada di satu daftar env yang sama**, dan tiga nama harus diberi awalan supaya tidak bertabrakan — `ENGINE_PORT`, `ENGINE_HOST`, `ENGINE_LOG_LEVEL`. `PORT`, `HOST`, dan `LOG_LEVEL` polos sekarang milik Laravel dan Coolify.
>
> Nama lama masih diterima sebagai cadangan supaya engine tetap bisa dijalankan sendiri saat pengembangan lokal. Yang tidak boleh terjadi diam-diam: `ENGINE_LOG_LEVEL` yang lupa diisi membuat engine mengikuti `LOG_LEVEL=error` milik Laravel, dan baris `Backup sesi terkirim` — satu-satunya bukti cadangan sesi tersimpan — berhenti muncul. `engine/tests/config.test.js` menjaga keempat aturan ini.

> **Nomor tidak boleh dipakai bersama antar tahap.** Satu nomor WhatsApp hanya bisa tertaut ke satu sesi aktif. Kalau nomor produksi dipakai men-scan di staging, sesi produksinya akan terputus — dan notifikasi pelanggan ikut berhenti. Sediakan nomor terpisah untuk dev/staging.

## Sumber kebenaran nilai rahasia

Nilai lengkap per tahap ada di `ENV/flustra-wa.md` pada repo utama (di luar repo ini, tidak pernah ikut ter-commit). Bagian di bawah hanya menjelaskan **yang berbeda antar tahap**; selebihnya sama.

---

## Development — `wa-dev.flustra.tech`

Tempat mencoba hal baru. Boleh dirusak.

```env
APP_ENV=development
APP_DEBUG=true
APP_URL=https://wa-dev.flustra.tech
LOG_LEVEL=debug

DB_DATABASE=db_flustra-wa_dev

ENGINE_URL=http://127.0.0.1:3100

# Jeda dipendekkan agar pengujian tidak berlarut-larut.
# Aman karena nomornya nomor uji coba, bukan nomor yang perlu dijaga.
WA_MIN_DELAY_MS=1000
WA_MAX_DELAY_MS=2000

# Batas longgar supaya pengujian tidak mentok kuota.
WORKSPACE_DEFAULT_MONTHLY_QUOTA=100000
WORKSPACE_DEFAULT_MAX_SESSIONS=3

# Retensi pendek — data uji tidak perlu disimpan lama.
RETENTION_MESSAGES_DAYS=7
RETENTION_WEBHOOK_DAYS=7
```

Blok engine, di daftar env yang sama:

```env
ENGINE_PORT=3100
ENGINE_HOST=127.0.0.1
ENGINE_LOG_LEVEL=debug
LARAVEL_URL=http://127.0.0.1:80
WA_DATA_PATH=/data/.wwebjs_auth
WA_MAX_SESSIONS=1
```

## Staging — `wa-staging.flustra.tech`

Latihan sebelum produksi. Nilainya **harus semirip mungkin dengan produksi** — kalau staging dibuat lebih longgar, ia berhenti berfungsi sebagai latihan.

```env
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://wa-staging.flustra.tech
LOG_LEVEL=info

DB_DATABASE=db_flustra-wa_staging

ENGINE_URL=http://127.0.0.1:3100

# Sama dengan produksi. Jeda inilah yang menentukan berapa lama broadcast
# berjalan; kalau staging lebih cepat, hasil ujinya menyesatkan.
WA_MIN_DELAY_MS=3000
WA_MAX_DELAY_MS=8000

WORKSPACE_DEFAULT_MONTHLY_QUOTA=1000
WORKSPACE_DEFAULT_MAX_SESSIONS=1

RETENTION_MESSAGES_DAYS=30
RETENTION_WEBHOOK_DAYS=14
```

Blok engine, di daftar env yang sama:

```env
ENGINE_PORT=3100
ENGINE_HOST=127.0.0.1
ENGINE_LOG_LEVEL=info
LARAVEL_URL=http://127.0.0.1:80
WA_DATA_PATH=/data/.wwebjs_auth
WA_MAX_SESSIONS=1
```

## Production — `wa.flustra.id`

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://wa.flustra.id
LOG_LEVEL=error

DB_DATABASE=db_flustra-wa

ENGINE_URL=http://127.0.0.1:3100

WA_MIN_DELAY_MS=3000
WA_MAX_DELAY_MS=8000

WORKSPACE_DEFAULT_MONTHLY_QUOTA=1000
WORKSPACE_DEFAULT_MAX_SESSIONS=1

RETENTION_MESSAGES_DAYS=90
RETENTION_WEBHOOK_DAYS=30
```

Blok engine, di daftar env yang sama:

```env
ENGINE_PORT=3100
ENGINE_HOST=127.0.0.1
ENGINE_LOG_LEVEL=info
LARAVEL_URL=http://127.0.0.1:80
WA_DATA_PATH=/data/.wwebjs_auth

# Tiap sesi = satu Chromium (±300-500 MB). Turun dari 10 pada 17 Agu 2026:
# RAM-nya sekarang dibagi dengan PHP, worker, penjadwal, MySQL, dan tujuh
# aplikasi Flustra lain di VPS yang sama. Cara menghitungnya di DEPLOYMENT.md §2.
WA_MAX_SESSIONS=3
```

> `APP_DEBUG=true` di produksi akan menampilkan isi environment — termasuk password database dan secret HMAC — di halaman error. Pastikan tetap `false`.

---

## Penagihan

Variabel berikut sama bentuknya di ketiga tahap; yang berbeda hanya isinya.
**Dev dan staging tidak boleh memakai QRIS merchant sungguhan** — pengujian di
sana akan menghasilkan kode QR yang benar-benar bisa dibayar orang.

```env
> **Aturan kutip berlaku untuk SEMUA nilai bernilai lebih dari satu kata, bukan
> hanya QRIS.** `APP_NAME=Flustra WA Gateway` tanpa kutip menolak seluruh berkas
> dengan `Failed to parse dotenv file ... unexpected whitespace` dan aplikasi
> tidak boot sama sekali — bukan sekadar kehilangan nama aplikasinya. Ini sudah
> terjadi di `.env.production` dan tidak ketahuan sampai berkasnya benar-benar
> dicoba dimuat, karena Coolify menyetel env-nya lewat jalur lain.

# QRIS statis milik merchant, string panjang berawalan 00020101021126...
# Kosong = halaman pembayaran hanya menampilkan instruksi transfer bank,
# bukan kode QR rusak. Pakai QRIS MERCHANT, bukan QRIS akun pribadi: akun
# pribadi punya batas nominal bulanan dan bisa dibekukan bank begitu polanya
# terbaca komersial.
#
# WAJIB DIKUTIP. Nama kota merchant di dalamnya (tag 60) hampir selalu memuat
# spasi; tanpa kutip dotenv menolak seluruh berkas dan aplikasi gagal boot,
# bukan sekadar kehilangan QRIS-nya. Salin apa adanya — spasi di ujung nama
# kota ikut dihitung panjang tag, dan satu saja terpangkas membuat CRC-nya
# tidak cocok lagi.
QRIS_PAYLOAD=
QRIS_MERCHANT_NAME=Flustra

# Ditampilkan berdampingan dengan kode QR, bukan sebagai jalur darurat: batas
# QRIS per transaksi mengikuti kebijakan tiap dompet digital dan bisa berhenti
# di bawah nilai paket tahunan Elite.
BILLING_BANK_NAME=
BILLING_BANK_ACCOUNT=
BILLING_BANK_HOLDER=

# Irama siklus. Nilai bawaan sudah masuk akal; ubah hanya kalau ada alasan.
BILLING_INVOICE_DUE_DAYS=7      # batas bayar sejak tagihan terbit
BILLING_ISSUE_DAYS_BEFORE=3     # tagihan perpanjangan terbit sekian hari sebelum habis
BILLING_GRACE_DAYS=30           # jarak antara pengiriman berhenti dan sesi dilepas
BILLING_UNIQUE_CODE=true        # kode unik 3 digit di nominal, untuk mencocokkan mutasi
BILLING_TAX_PERCENT=0           # 0 = harga yang dipajang sudah final, tanpa baris pajak

# Workspace milik Flustra sendiri yang nomornya dipakai mengirim SELURUH
# pemberitahuan WhatsApp. Jangan diisi workspace pelanggan: kuotanya yang
# terpotong dan laporan spam-nya yang jatuh ke nomor mereka.
#
# Kosong = seluruh pemberitahuan diam, tanpa satu pun yang gagal secara
# terlihat. Panel admin menampilkan peringatan merah di halaman Ringkasan
# selama keadaan ini berlangsung — itu satu-satunya gejalanya.
#
# SEJAK 6 SEP 2026 INI CUMA CADANGAN. Pilihannya ada di panel admin, menu
# Pengecualian, dan disimpan di tabel `app_settings` — itu yang dibaca lebih
# dulu. Alasannya urutan pemasangan lewat env mustahil: id workspace baru ada
# setelah workspace-nya dibuat lewat dashboard, jadi mengisinya di sini berarti
# deploy, buat workspace, salin id, deploy lagi.
BILLING_NOTIFY_WORKSPACE_ID=

# Nomor WhatsApp tim, untuk pemberitahuan yang butuh tindakan manusia —
# terutama "ada bukti pembayaran baru masuk". Selama pencocokan masih manual,
# tagihan hanya menjadi lunas kalau ada orang yang membukanya di panel, dan
# tanpa nomor ini tidak ada yang memberi tahu bahwa ada yang perlu dibuka.
BILLING_ADMIN_PHONE=

# Alamat yang dipakai pelanggan saat butuh manusia — termasuk saat lupa kata
# sandi, karena pemulihan mandiri sengaja belum dibuat.
BILLING_SUPPORT_EMAIL=flustrafinances@gmail.com

# Lantai masa percobaan. Percobaan selalu berakhir di akhir bulan; kalau sisa
# bulan berjalan lebih pendek dari ini, masanya melompat ke akhir bulan
# berikutnya supaya pendaftar tanggal 30 tidak cuma dapat satu hari.
BILLING_TRIAL_MIN_DAYS=10
```

### Pemberitahuan WhatsApp — apa saja yang dikirim

Semuanya lewat gateway ini sendiri, dari nomor workspace Flustra.

| Peristiwa | Kepada | Kenapa ada |
|---|---|---|
| Bukti pembayaran diunggah | Pelanggan | Menutup keraguan yang dulu berujung tagihan dibatalkan sendiri |
| Bukti pembayaran diunggah | **Tim** | Tagihan hanya lunas kalau ada yang membukanya di panel |
| Pembayaran dikonfirmasi | Pelanggan | Menutup lingkaran; tanpa ini mereka menunggu tanpa kabar |
| Bukti ditolak + alasan | Pelanggan | Penolakan tanpa alasan cuma membuat tagihan terbuka lagi tanpa ada yang tahu kenapa |
| Masa berlaku H-7/3/1/0 | Pelanggan | Spanduk dashboard hanya terlihat oleh yang kebetulan membukanya |
| Pengiriman dihentikan | Pelanggan | Layanan berhenti harus terasa sebagai kebijakan, bukan kerusakan |
| Nomor dilepas setelah tenggang | Pelanggan | Menjelaskan bahwa datanya tetap aman |
| **Sesi WhatsApp terputus** | Pelanggan | Gateway yang mati diam baru ketahuan saat pelanggan *mereka* yang mengeluh |
| Kuota 80% dan 100% | Pelanggan | Kuota habis tanpa peringatan terasa seperti kerusakan |

Semuanya **pelengkap**: kegagalan mengirim dicatat di log dan tidak pernah
menjatuhkan tindakan yang sedang berjalan — tagihan tetap lunas meski pesannya
gagal terkirim. Tiap peristiwa dijaga penanda di cache supaya job yang berjalan
dua kali tidak mengirim pesan yang sama dua kali; pesan kembar soal uang membuat
orang mengira ia ditagih dua kali.

### Email — belum dipakai

`MAIL_MAILER=log` dan memang belum ada satu pun alur yang mengandalkan email.
Pemulihan kata sandi sengaja belum dibuat; pelanggan yang lupa kata sandinya
menghubungi admin, dan admin mengatur ulang dari `/admin/pengguna`.

Isi `MAIL_*` hanya kalau alur yang butuh email benar-benar dibuat — jangan
mengisinya lebih dulu, karena kotak email yang terpasang tapi tidak terpakai
memberi kesan pemberitahuan sudah berjalan padahal tidak.

### Akun super admin

Panel `/admin` hanya bisa dibuka super admin, dan super admin hanya bisa
diangkat super admin lain — jadi akun pertama lahir dari seeder, bukan dari
antarmuka. Seeder aman dijalankan berulang: akun yang sudah ada tidak ditimpa
kata sandinya.

```env
ADMIN_EMAIL=flustrafinances@gmail.com
ADMIN_NAME="Flustra Finance"
ADMIN_PASSWORD=            # WAJIB diisi di staging dan produksi
```

`ADMIN_PASSWORD` yang dikosongkan jatuh ke nilai bawaan `12345678`. Nilai itu
ada di dalam repo, artinya **sudah bocor** — pakai hanya di lokal. Isi env-nya
sebelum menjalankan `php artisan db:seed` di staging maupun produksi, atau
ganti kata sandinya segera setelah akunnya terbuat.

---

## Rahasia yang wajib berbeda tiap tahap

Jangan pernah menyalin nilai ini antar tahap. Kalau secret dev bocor dan nilainya sama dengan produksi, penyerang bisa mengirim WhatsApp atas nama seluruh pelanggan.

| Variabel | Cara membuat |
|---|---|
| `APP_KEY` | `php artisan key:generate --show` |
| `ENGINE_TOKEN` | `openssl rand -hex 32` |
| `ENGINE_HMAC_SECRET` | `openssl rand -hex 32` — harus berbeda dari `ENGINE_TOKEN` |
| `DB_PASSWORD` | dari Coolify |
| API key workspace | Dashboard → API Keys → Buat |

`ENGINE_TOKEN` dan `ENGINE_HMAC_SECRET` harus **identik antara Laravel dan engine dalam satu tahap**, dan **berbeda antar tahap**.

## Daftar periksa promosi dev → staging → production

1. Migrasi berjalan bersih di tahap sebelumnya (`php artisan migrate --force`).
2. `php artisan test` hijau.
2b. `php artisan db:seed --force` dijalankan sekali di tahap baru, dengan `ADMIN_PASSWORD` sudah terisi.
3. Deploy ulang resource di tahap tersebut, lalu pastikan sesi kembali tersambung **tanpa scan QR** — ini uji regresi utamanya. Sejak penyatuan, redeploy ikut me-restart web, jadi uji ini sekaligus membuktikan penghentian rapi engine dan penantian boot-nya.
4. Kirim satu pesan uji dan pastikan statusnya sampai `delivered`.
5. Webhook uji coba menerima kiriman dan tanda tangannya lolos verifikasi.
6. Baru merge ke branch berikutnya.

## Menjalankan lokal

Lokal adalah tahap keempat yang tidak ada di Coolify, dan satu-satunya tempat engine masih dijalankan sebagai proses yang benar-benar terpisah (`npm --prefix engine run dev` membaca `engine/.env` sendiri). Buat `.env` di root dan di `engine/` — daftar variabelnya ada di tabel atas — lalu:

```bash
npm run all
```

Laravel di `http://localhost:8070`, engine di `http://127.0.0.1:3100`, plus queue worker dan Vite.
