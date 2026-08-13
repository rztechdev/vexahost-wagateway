# Setup VPS & Coolify

Dari VPS kosong sampai Flustra WA Gateway melayani permintaan. Ikuti berurutan — setiap bagian mengandalkan yang sebelumnya.

Dokumen internal. Untuk pembagian tahap dev/staging/production lihat [ENVIRONMENT.md](ENVIRONMENT.md); untuk detail tiap resource lihat [DEPLOYMENT.md](DEPLOYMENT.md).

---

## Bagian 0 — Yang perlu disiapkan

| | Keterangan |
|---|---|
| VPS | Ubuntu 22.04/24.04 LTS. **Minimal 4 GB RAM** kalau engine ikut di sini |
| Domain | `flustra.id` (produksi) dan `flustra.tech` (dev & staging) |
| Akses | SSH root, dan akses ke organisasi GitHub `flustratech-dev` |
| Nomor WhatsApp | Nomor uji coba, **terpisah** dari nomor produksi |

### Kenapa RAM jadi penentu

Setiap nomor WhatsApp menjalankan satu Chromium: **300–500 MB**. Ini batas skala yang sebenarnya, bukan CPU.

| RAM VPS | Sesi wajar | Catatan |
|---|---|---|
| 2 GB | 1–2 | Hanya cukup untuk mencoba |
| 4 GB | 8–10 | Minimum untuk produksi serius |
| 8 GB | 15–20 | Nyaman |

Kalau ekosistem Flustra lain (web, erp, pricing, helpdesk) berbagi VPS yang sama, hitung jatah mereka lebih dulu sebelum menetapkan `WA_MAX_SESSIONS`.

---

## Bagian 1 — Menyiapkan VPS

Masuk sebagai root:

```bash
ssh root@IP_VPS
```

### 1.1 Perbarui sistem

```bash
apt update && apt upgrade -y
apt install -y curl git ufw
```

### 1.2 Zona waktu

```bash
timedatectl set-timezone Asia/Jakarta
```

Penting bukan sekadar kerapian: callback engine ditandatangani dengan timestamp dan ditolak Laravel bila selisihnya lebih dari 5 menit. Jam server yang melenceng jauh membuat **seluruh callback gagal**.

Pastikan sinkronisasi waktu menyala:

```bash
timedatectl set-ntp true
timedatectl        # NTP service: active
```

### 1.3 Swap

Chromium bisa melonjak sesaat. Tanpa swap, lonjakan itu membuat kernel membunuh prosesnya (OOM kill) dan sesi terputus.

```bash
fallocate -l 4G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab

# Pakai swap hanya saat benar-benar mendesak, bukan sebagai pengganti RAM.
sysctl vm.swappiness=10
echo 'vm.swappiness=10' >> /etc/sysctl.conf
```

Verifikasi:

```bash
free -h
```

### 1.4 Firewall

```bash
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 8000/tcp     # panel Coolify
ufw enable
ufw status
```

> **Port 3100 (engine) tidak dibuka.** Engine hanya dihubungi Laravel lewat jaringan internal Docker. Membukanya ke internet berarti siapa pun yang menebak tokennya bisa mengirim WhatsApp atas nama semua pelanggan.

Setelah semuanya berjalan, tutup 8000 dan akses panel lewat SSH tunnel:

```bash
ufw delete allow 8000/tcp
# dari komputer Anda:
ssh -L 8000:localhost:8000 root@IP_VPS
```

---

## Bagian 2 — Memasang Coolify

```bash
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash
```

Pemasangan memakan 3–5 menit dan sekalian memasang Docker.

Buka `http://IP_VPS:8000`, buat akun admin **pertama kali itu juga** — pendaftaran terbuka sampai akun pertama dibuat.

Setelah masuk:

1. **Projects** → **+ New** → beri nama `Flustra Ecosystem` (kalau belum ada)
2. **Sources** → hubungkan GitHub App, beri akses ke repo `flustratech-dev/flustra-wa`

---

## Bagian 3 — DNS

Di pengelola DNS `flustra.id`:

| Tipe | Nama | Nilai |
|---|---|---|
| A | `wa` | IP VPS |

Di `flustra.tech`, wildcard biasanya sudah ada. Kalau belum:

| Tipe | Nama | Nilai |
|---|---|---|
| A | `*` | IP VPS |

Tunggu propagasi, lalu pastikan sudah mengarah:

```bash
dig +short wa.flustra.id
dig +short wa-dev.flustra.tech
```

Keduanya harus mengembalikan IP VPS. Jangan lanjut sebelum ini benar — Let's Encrypt akan gagal menerbitkan sertifikat dan Coolify menandai deploy sebagai gagal.

---

## Bagian 4 — Database

Kalau MySQL bersama belum ada, buat lewat Coolify: **+ New** → **Database** → **MySQL 8**. Catat nama service internal dan password-nya.

Masuk ke containernya, lalu buat tiga database:

```sql
CREATE DATABASE `db_flustra-wa`         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE `db_flustra-wa_staging` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE `db_flustra-wa_dev`     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

`DB_HOST` diisi **nama service internal** MySQL di Coolify, bukan `localhost` — keduanya container terpisah.

---

## Bagian 5 — Membuat rahasia

Di VPS, buat dua nilai acak untuk **tiap tahap**:

```bash
openssl rand -hex 32    # ENGINE_TOKEN
openssl rand -hex 32    # ENGINE_HMAC_SECRET
```

Aturan yang tidak boleh dilanggar:

- **Sama persis** antara Laravel dan engine dalam satu tahap
- **Berbeda** antar tahap
- **Berbeda** satu sama lain (token ≠ hmac secret)

Kalau token bocor lewat log, ia tidak boleh sekaligus memberi kemampuan memalsukan callback.

---

## Bagian 6 — Resource 1: aplikasi Laravel

**+ New** → **Public/Private Repository** → pilih `flustratech-dev/flustra-wa`.

| Pengaturan | Nilai |
|---|---|
| Name | `flustra-wa` |
| Branch | `main` |
| Build Pack | Nixpacks |
| Base Directory | `/` |
| Domain | `https://wa.flustra.id` |
| Health Check | **Dimatikan** |

**Install Command**

```
composer install --no-dev --optimize-autoloader && npm ci
```

**Build Command**

```
npm run build
```

**Start Command**

```
php artisan serve --host=0.0.0.0 --port=80
```

**Post-deployment Command**

```
php artisan migrate --force && php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Persistent Storage**

| Name | Mount Path |
|---|---|
| `wa-storage` | `/app/storage/app/private` |

*(Volume ini menyimpan cadangan sesi di `session-backups` agar tidak terhapus saat redeploy, serta untuk menyimpan media lampiran).*

**Catatan Jaringan:**
Pastikan opsi **Connect To Predefined Network** diaktifkan (di tab Configuration → Advanced) agar container ini bisa menghubungi Engine.

**Environment Variables** — daftar lengkapnya di [ENVIRONMENT.md](ENVIRONMENT.md); yang wajib diperiksa:

| Variabel | Nilai |
|---|---|
| `APP_KEY` | dari `php artisan key:generate --show` |
| `DB_HOST`, `DB_PASSWORD` | dari Coolify |
| `ENGINE_URL` | `http://<uuid-resource-engine>:3100` — UUID, bukan nama tampilan (lihat Bagian 7) |
| `ENGINE_TOKEN`, `ENGINE_HMAC_SECRET` | dari Bagian 5 |
| `PLATFORM_SESSION_ID` | dikosongkan dulu, diisi di Bagian 9 |

> **Pastikan `APP_DEBUG=false`.** Kalau `true`, halaman error menampilkan seluruh isi environment — termasuk password database dan secret HMAC.

Deploy. Buka `https://wa.flustra.id` — halaman utama harus muncul dengan HTTPS.

---

## Bagian 7 — Resource 2: engine

**+ New** → repo yang sama.

| Pengaturan | Nilai |
|---|---|
| Name | `flustra-wa-engine` |
| Branch | `main` |
| Build Pack | Nixpacks |
| Base Directory | `/engine` |
| Domain | **kosongkan** |
| Health Check Path | `/health` |
| Restart Policy | `always` |

**Install Command**

```
npm ci
```

**Start Command**

```
npm start
```

**Persistent Storage** — inilah bagian terpenting seluruh panduan ini:

| | Nilai |
|---|---|
| Name | `wa-sessions` |
| Mount Path | `/data` |

Tanpa volume ini, kredensial nomor tersimpan di dalam container yang dibuat ulang setiap deploy — dan **setiap deploy memaksa scan QR ulang**, persis masalah yang gateway ini dibuat untuk menyelesaikannya.

**Environment Variables** engine:

```env
PORT=3100
HOST=0.0.0.0
ENGINE_TOKEN=<sama dengan Laravel>
ENGINE_HMAC_SECRET=<sama dengan Laravel>
LARAVEL_URL=http://<uuid-resource-flustra-wa>:80
WA_DATA_PATH=/data/.wwebjs_auth
WA_BACKUP_INTERVAL_MS=300000
WA_MIN_DELAY_MS=3000
WA_MAX_DELAY_MS=8000
WA_MAX_SESSIONS=10
LOG_LEVEL=info
```

`HOST` harus `0.0.0.0`, bukan `127.0.0.1` — kalau tidak, container Laravel tidak bisa menjangkaunya.

**Catatan Jaringan Internal:**
Agar saling terhubung, pastikan opsi **Connect To Predefined Network** diaktifkan di tab Configuration → Advanced pada **kedua resource** (Laravel dan Engine).

Nama host yang bisa di-resolve adalah **UUID resource**, bukan nama tampilannya. `flustra-wa-engine` hanya label di antarmuka Coolify; DNS internal tidak mengenalnya. UUID ada di URL browser saat membuka resource (`.../application/<uuid>`) dan di domain generated-nya. Pola yang sama berlaku untuk `DB_HOST` — Coolify sudah mengisinya dengan UUID.

Uji koneksi dari terminal Laravel: `curl http://<uuid-resource-engine>:3100/health`. Jawaban yang diharapkan `{"status":"ok",...}`. `Could not resolve host` berarti UUID salah atau predefined network belum aktif; `Connection refused` berarti nama sudah benar tapi engine belum jalan atau `HOST` bukan `0.0.0.0`.

Deploy, lalu periksa log. Yang diharapkan:

```
{"level":30,"msg":"Engine WhatsApp berjalan","port":3100}
{"level":30,"msg":"Memulihkan sesi tersimpan","count":0}
```

Baris kedua membuktikan engine berhasil menghubungi Laravel **dan** tanda tangan HMAC-nya diterima. Kalau yang muncul `Bootstrap gagal`, berarti `ENGINE_HMAC_SECRET` berbeda antara kedua resource.

---

## Bagian 8 — Resource 3: worker

**+ New** → repo yang sama.

| Pengaturan | Nilai |
|---|---|
| Name | `flustra-wa-worker` |
| Base Directory | `/` |
| Domain | kosongkan |
| Start Command | `php artisan queue:work --sleep=3 --tries=3 --timeout=240 --max-time=3600` |
| Post-deployment Command | kosongkan |
| Restart Policy | `always` |

**Persistent Storage**

| Name | Mount Path |
|---|---|
| `wa-storage` | `/app/storage/app/private` |

> Volume harus **sama persis** (nama dan mount path) dengan resource aplikasi Laravel. Jika tidak, worker tidak akan bisa menemukan file media yang diunggah dari API.

Environment variables **identik** dengan resource Laravel.

Tanpa worker, pesan berhenti di status `queued` selamanya.

### Scheduled Task

Pada resource `flustra-wa`, tambahkan **Scheduled Task**:

| | Nilai |
|---|---|
| Command | `php artisan schedule:run` |
| Frequency | `* * * * *` |

Ini yang menjalankan sinkronisasi status sesi setiap menit (menyambungkan ulang sesi yang putus) dan pembersihan data lama.

---

## Bagian 9 — Penyiapan pertama

Buka terminal resource `flustra-wa` di Coolify.

### 9.1 Tenant internal & sesi platform

Keduanya tidak bisa dibuat lewat dashboard — memang bukan sesuatu yang boleh diatur pelanggan.

```bash
php artisan gateway:setup-tenant "Flustra Internal" \
  --internal \
  --session="Platform" --platform \
  --key="flustra-auth otp" --scopes=otp \
  --max-sessions=5
```

Keluarannya memuat ULID sesi dan API key penuh — **satu-satunya kesempatan membacanya**.

1. Salin ULID ke `PLATFORM_SESSION_ID` pada env `flustra-wa`, lalu **redeploy**
2. Salin API key ke `WA_GATEWAY_KEY` pada env **flustra-auth**

### 9.2 Tautkan nomor platform

Buka `https://wa.flustra.id`, daftar akun admin, lalu **Sesi WhatsApp** → **Hubungkan** → scan QR dengan nomor resmi Flustra.

### 9.3 Kunci untuk tiap aplikasi

```bash
php artisan gateway:setup-tenant "Flustra Internal" --key="flustra-erp produksi"
php artisan gateway:setup-tenant "Flustra Internal" --key="flustra-web produksi"
php artisan gateway:setup-tenant "Flustra Internal" --key="flustra-pricing produksi"
php artisan gateway:setup-tenant "Flustra Internal" --key="flustra-helpdesk produksi"
```

Masukkan tiap kunci ke `WA_GATEWAY_KEY` aplikasi yang bersangkutan, beserta:

```env
WA_GATEWAY_ENABLED=true
WA_GATEWAY_URL=https://wa.flustra.id
WA_GATEWAY_KEY=<kunci aplikasi ini>
WA_GATEWAY_PLATFORM_SESSION=<ULID sesi platform>
WA_GATEWAY_REQUIRE_VERIFIED_PHONE=true
```

**Jangan** berikan scope `otp` ke aplikasi selain flustra-auth. Lihat [INTEGRASI_APP.md](INTEGRASI_APP.md).

---

## Bagian 10 — Verifikasi

Jangan anggap selesai sebelum kelimanya lulus.

### Uji 1 — sesi selamat dari deploy ulang

Ini uji terpenting.

1. Pastikan satu sesi berstatus **Terhubung**
2. Tunggu ±5 menit sampai log engine memuat `Backup sesi terkirim`
3. Redeploy resource `flustra-wa-engine`
4. Sesi harus kembali **Terhubung sendiri, tanpa scan QR**

### Uji 2 — selamat meski volume hilang

Ulangi Uji 1, tapi kosongkan `/data` lebih dulu:

```bash
rm -rf /data/.wwebjs_auth/*
```

Sesi tetap harus pulih — kali ini dari cadangan di database.

### Uji 3 — nomor tetap bisa diganti

**Putus tautan / ganti nomor** → **Hubungkan** → scan dengan nomor berbeda. Sesi tersambung dengan nomor baru, sementara riwayat pesan dan API key tetap utuh.

Uji 1 dan 2 membuktikan nomor tidak terputus sendiri. Uji 3 membuktikan nomor tidak terkunci.

### Uji 4 — pengiriman

```bash
curl -X POST https://wa.flustra.id/api/v1/messages/text \
  -H "X-Api-Key: $KEY" -H "Content-Type: application/json" \
  -d '{"to":"08xxxxxxxxxx","message":"Uji dari produksi"}'
```

Status harus naik sampai `delivered` di Riwayat Pesan.

### Uji 5 — webhook

Daftarkan alamat dari webhook.site, kirim pesan dari HP ke nomor gateway, pastikan payload sampai dan tanda tangannya lolos verifikasi.

---

## Bagian 11 — Dev & staging

Ulangi Bagian 6–9 dengan perbedaan berikut:

| | Development | Staging |
|---|---|---|
| Branch | `dev` | `staging` |
| Domain | `wa-dev.flustra.tech` | `wa-staging.flustra.tech` |
| Nama resource | `flustra-wa-dev`, `flustra-wa-engine-dev`, `flustra-wa-worker-dev` | `…-staging` |
| Database | `db_flustra-wa_dev` | `db_flustra-wa_staging` |
| Volume | `wa-sessions-dev` → `/data` | `wa-sessions-staging` → `/data` |
| `WA_MAX_SESSIONS` | 2 | 3 |
| Acuan env | Coolify resource `…-dev` | Coolify resource `…-staging` |

> **Jangan pakai nomor produksi untuk uji coba di dev/staging.** Satu nomor hanya bisa tertaut ke satu sesi aktif — men-scan di sini akan memutus sesi produksinya dan menghentikan notifikasi pelanggan.

---

## Bagian 12 — Perawatan

### Pemantauan harian

| Yang dicek | Cara |
|---|---|
| Sesi masih terhubung | Dashboard → Sesi WhatsApp |
| Pesan gagal tidak melonjak | Dashboard → Ringkasan |
| Antrean tidak menumpuk | `php artisan queue:failed` |
| RAM engine | Coolify → resource engine |

### Cadangan

Coolify bisa menjadwalkan backup database. **Aktifkan** — di sanalah cadangan kredensial sesi tersimpan. Kehilangan database berarti semua nomor harus di-scan ulang.

### Pembaruan berkala

Per kuartal, perbarui `whatsapp-web.js`. Versi lama bisa berhenti bekerja saat WhatsApp Web berubah:

```bash
npm --prefix engine update whatsapp-web.js
```

Uji di development lebih dulu, lalu staging, baru produksi.

---

## Pemecahan masalah

| Gejala | Penyebab |
|---|---|
| Deploy gagal saat menerbitkan SSL | DNS belum mengarah ke VPS |
| Sesi mentok `connecting`, tidak ada QR | Chromium gagal jalan — cek log engine; biasanya RAM kurang |
| Log engine: `Bootstrap gagal` | `ENGINE_HMAC_SECRET` beda antara Laravel dan engine |
| `Token engine tidak valid` | `ENGINE_TOKEN` beda antara keduanya |
| Semua callback ditolak 401 padahal secret sama | Jam server melenceng — periksa `timedatectl` |
| Sesi minta QR ulang tiap deploy | Volume belum ter-mount ke `/data` |
| Pesan mentok `queued` | Resource worker tidak berjalan |
| Engine restart berulang | Kehabisan memori — turunkan `WA_MAX_SESSIONS` atau tambah RAM/swap |
| `Could not resolve host` saat menghubungi engine | `ENGINE_URL` memakai nama tampilan resource, bukan UUID-nya |
| Halaman error menampilkan isi environment | `APP_DEBUG` masih `true` |

Panduan operasional harian: [OPERASIONAL.md](OPERASIONAL.md).
