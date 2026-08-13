# Deployment di Coolify

Mengikuti konvensi yang sudah dipakai ekosistem Flustra (`PANDUAN_SETUP_COOLIFY_LENGKAP_FLUSTRA.md` di repo utama): Nixpacks, perintah start di-override lewat UI, healthcheck dimatikan untuk aplikasi Laravel.

Baca [ENVIRONMENT.md](ENVIRONMENT.md) dulu untuk memahami pembagian tiga tahapnya.

---

## Ringkasan

Setiap tahap terdiri dari **3 resource** dari repo yang sama:

| Resource | Base directory | Domain | Keterangan |
|---|---|---|---|
| `flustra-wa` | `/` | `wa.flustra.id` | Dashboard + REST API |
| `flustra-wa-engine` | `/engine` | *(tanpa domain)* | Butuh persistent volume |
| `flustra-wa-worker` | `/` | *(tanpa domain)* | Worker antrean |

Domain per tahap:

| Tahap | Branch | Domain |
|---|---|---|
| Development | `dev` | `wa-dev.flustra.tech` |
| Staging | `staging` | `wa-staging.flustra.tech` |
| Production | `main` | `wa.flustra.id` |

Total 9 resource untuk tiga tahap. Nama resource dev/staging diberi akhiran (`flustra-wa-dev`, `flustra-wa-engine-dev`, dan seterusnya).

---

## Persiapan sekali saja

### DNS

Production di `flustra.id` butuh A record baru:

| Tipe | Nama | Nilai |
|---|---|---|
| A | `wa` | `187.124.137.101` |

Dev dan staging tidak perlu apa-apa — `flustra.tech` sudah punya wildcard A record.

### Database

Di container MySQL bersama, buat tiga database:

```sql
CREATE DATABASE `db_flustra-wa`         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE `db_flustra-wa_staging` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE `db_flustra-wa_dev`     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Akses repo

Repo `flustratech-dev/flustra-wa` harus terlihat oleh GitHub App Coolify yang sudah ada (`flustra-github-app`). Kalau App-nya diset ke repo tertentu, tambahkan repo ini ke daftarnya.

---

## 1. Resource aplikasi Laravel

- **Build pack**: Nixpacks
- **Base directory**: `/`
- **Branch**: sesuai tahap
- **Domain**: sesuai tahap
- **Healthcheck**: **dimatikan** (sama seperti aplikasi Flustra lain)

**Install command**

```
composer install --no-dev --optimize-autoloader && npm ci
```

**Build command**

```
npm run build
```

**Start command**

```
php artisan serve --host=0.0.0.0 --port=80
```

**Post-deployment command**

```
php artisan migrate --force && php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Persistent storage**

| Nama volume | Mount path |
|---|---|
| `wa-storage` | `/app/storage/app/private` |

> Volume ini wajib untuk menyimpan cadangan sesi (`session-backups`) dan media. Jika terlewat, cadangan sesi terhapus setiap deploy.

**Jaringan Internal (Penting)**

Pastikan opsi **Connect To Predefined Network** diaktifkan di tab Configuration → Advanced agar Laravel bisa menghubungi Engine lewat nama service.

Environment variables: salin dari `ENV/flustra-wa.md` bagian tahap yang sesuai.

## 2. Resource engine

- **Build pack**: Nixpacks — `engine/nixpacks.toml` menyediakan library sistem untuk Chromium
- **Base directory**: `/engine`
- **Domain**: **kosongkan.** Engine hanya diakses lewat jaringan internal
- **Install**: `npm ci`
- **Start**: `npm start`
- **Healthcheck**: boleh diaktifkan, `GET /health`
- **Restart policy**: `always`

**Persistent storage — bagian terpenting:**

| Nama volume | Mount path |
|---|---|
| `wa-sessions` (prod) | `/data` |
| `wa-sessions-staging` | `/data` |
| `wa-sessions-dev` | `/data` |

Tanpa volume ini, sesi hilang setiap deploy — persis masalah yang gateway ini dibuat untuk menyelesaikannya.

**Resource minimum:**

| Tahap | RAM | Sesi |
|---|---|---|
| Development | 1 GB | 2 |
| Staging | 2 GB | 3 |
| Production | 4 GB | 10 |

## 3. Resource worker

Repo yang sama, base directory `/`, tanpa domain.

**Start command**

```
php artisan queue:work --sleep=3 --tries=3 --timeout=240 --max-time=3600
```
*(Catatan: `--max-time=3600` sengaja dipakai agar worker keluar tiap jam untuk melepas memori, dan Coolify akan menghidupkannya lagi karena Restart Policy always).*

**Post-deployment command**
*(Kosongkan, jangan jalankan migrate di sini agar tidak bentrok dengan resource web).*

**Persistent storage**

| Nama volume | Mount path |
|---|---|
| `wa-storage` | `/app/storage/app/private` |

> Wajib memakai nama volume dan mount path yang sama persis dengan aplikasi Laravel agar worker bisa mengakses file media/lampiran.

Environment variables identik dengan resource Laravel di tahap yang sama.

Tambahkan juga **Scheduled Task** pada resource Laravel: `php artisan schedule:run`, setiap menit. Inilah yang menjalankan sinkronisasi status sesi dan pembersihan data lama.

---

## 4. Penyiapan setelah deploy pertama

Tenant internal dan sesi platform tidak bisa dibuat lewat dashboard — keduanya bukan sesuatu yang boleh diatur pelanggan. Jalankan dari terminal resource Laravel:

```bash
php artisan gateway:setup-tenant "Flustra Internal" \
  --internal \
  --session="Platform" --platform \
  --key="flustra-auth otp" --scopes=otp \
  --max-sessions=5
```

Command ini idempoten — aman dijalankan ulang.

Keluarannya memuat ULID sesi platform dan API key penuh (satu-satunya kesempatan membacanya):

1. Salin ULID sesi ke `PLATFORM_SESSION_ID` di env resource Laravel, lalu redeploy.
2. Salin API key ke `WA_GATEWAY_KEY` di env **flustra-auth** — ini kunci ber-scope `otp`.
3. Buka dashboard → **Sesi WhatsApp** → **Hubungkan**, lalu scan QR dengan nomor resmi Flustra.

Lalu buat kunci terpisah untuk tiap aplikasi konsumen, **tanpa** scope `otp`:

```bash
php artisan gateway:setup-tenant "Flustra Internal" --key="flustra-erp produksi"
php artisan gateway:setup-tenant "Flustra Internal" --key="flustra-web produksi"
php artisan gateway:setup-tenant "Flustra Internal" --key="flustra-pricing produksi"
php artisan gateway:setup-tenant "Flustra Internal" --key="flustra-helpdesk produksi"
```

Masukkan tiap kunci ke `WA_GATEWAY_KEY` pada aplikasi yang bersangkutan. Lihat [INTEGRASI_APP.md](INTEGRASI_APP.md).

---

## 5. Uji regresi wajib

Ini pemeriksaan terpenting. Lakukan di development lebih dulu, lalu staging, sebelum menyentuh produksi.

**Uji 1 — sesi selamat dari deploy ulang**

1. Pastikan satu sesi berstatus **Terhubung**.
2. Tunggu ±5 menit sampai cadangan pertama terkirim. Cek log engine: `Backup sesi terkirim`.
3. Redeploy resource engine dari Coolify.
4. Setelah engine hidup lagi, sesi harus kembali **Terhubung sendiri, tanpa scan QR**.

**Uji 2 — sesi selamat meski volume hilang**

Ulangi uji 1, tapi kosongkan isi `/data` sebelum langkah 3. Sesi tetap harus pulih, kali ini dari cadangan di database.

**Uji 3 — nomor tetap bisa diganti**

Klik **Putus tautan** pada sesi, lalu **Hubungkan** dan scan dengan nomor berbeda. Sesi harus tersambung dengan nomor baru, sementara riwayat pesan dan API key tetap utuh.

Uji 1 dan 2 membuktikan nomor tidak terputus sendiri. Uji 3 membuktikan nomor tidak terkunci.

**Uji 4 — pengiriman**

Kirim satu pesan dari halaman Kirim Pesan, pastikan statusnya naik sampai `delivered`.

**Uji 5 — webhook**

Daftarkan webhook ke webhook.site, kirim pesan dari HP ke nomor tersebut, pastikan payload-nya sampai dan tanda tangannya lolos verifikasi.

---

## 6. Pemecahan masalah

| Gejala | Kemungkinan penyebab |
|---|---|
| Sesi mentok `connecting` | Chromium gagal jalan — cek log engine; biasanya RAM kurang atau library sistem tidak lengkap |
| Semua callback ditolak 401 | `ENGINE_HMAC_SECRET` berbeda antara Laravel dan engine |
| `Token engine tidak valid` | `ENGINE_TOKEN` berbeda antara keduanya |
| Sesi minta QR ulang tiap deploy | Volume belum ter-mount ke `/data`, atau `WA_DATA_PATH` tidak menunjuk ke sana |
| Pesan mentok `queued` | Resource worker tidak berjalan |
| Engine restart berulang | Kehabisan memori — turunkan `WA_MAX_SESSIONS` atau tambah RAM |
| Laravel tidak bisa menghubungi engine | `ENGINE_URL` memakai `localhost`, bukan nama service internal |
| Halaman error menampilkan isi environment | `APP_DEBUG` masih `true` — segera matikan |

Panduan operasional lengkap: [OPERASIONAL.md](OPERASIONAL.md).
