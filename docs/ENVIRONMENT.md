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
| Nama service engine | `flustra-wa-engine-dev` | `flustra-wa-engine-staging` | `flustra-wa-engine` |
| Volume engine | `wa-sessions-dev` | `wa-sessions-staging` | `wa-sessions` |
| Batas sesi engine | 2 | 3 | 10 |
| Nomor WhatsApp | nomor uji coba | nomor uji coba (boleh sama dengan dev, sesi terpisah) | nomor resmi Flustra |

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

ENGINE_URL=http://flustra-wa-engine-dev:3100

# Jeda dipendekkan agar pengujian tidak berlarut-larut.
# Aman karena nomornya nomor uji coba, bukan nomor yang perlu dijaga.
WA_MIN_DELAY_MS=1000
WA_MAX_DELAY_MS=2000

# Batas longgar supaya pengujian tidak mentok kuota.
TENANT_DEFAULT_MONTHLY_QUOTA=100000
TENANT_DEFAULT_MAX_SESSIONS=3

# Retensi pendek — data uji tidak perlu disimpan lama.
RETENTION_MESSAGES_DAYS=7
RETENTION_WEBHOOK_DAYS=7
```

Engine:

```env
HOST=0.0.0.0
LARAVEL_URL=http://flustra-wa-dev:80
WA_DATA_PATH=/data/.wwebjs_auth
WA_MAX_SESSIONS=2
LOG_LEVEL=debug
```

## Staging — `wa-staging.flustra.tech`

Latihan sebelum produksi. Nilainya **harus semirip mungkin dengan produksi** — kalau staging dibuat lebih longgar, ia berhenti berfungsi sebagai latihan.

```env
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://wa-staging.flustra.tech
LOG_LEVEL=info

DB_DATABASE=db_flustra-wa_staging

ENGINE_URL=http://flustra-wa-engine-staging:3100

# Sama dengan produksi. Jeda inilah yang menentukan berapa lama broadcast
# berjalan; kalau staging lebih cepat, hasil ujinya menyesatkan.
WA_MIN_DELAY_MS=3000
WA_MAX_DELAY_MS=8000

TENANT_DEFAULT_MONTHLY_QUOTA=1000
TENANT_DEFAULT_MAX_SESSIONS=1

RETENTION_MESSAGES_DAYS=30
RETENTION_WEBHOOK_DAYS=14
```

Engine:

```env
HOST=0.0.0.0
LARAVEL_URL=http://flustra-wa-staging:80
WA_DATA_PATH=/data/.wwebjs_auth
WA_MAX_SESSIONS=3
LOG_LEVEL=info
```

## Production — `wa.flustra.id`

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://wa.flustra.id
LOG_LEVEL=error

DB_DATABASE=db_flustra-wa

ENGINE_URL=http://flustra-wa-engine:3100

WA_MIN_DELAY_MS=3000
WA_MAX_DELAY_MS=8000

TENANT_DEFAULT_MONTHLY_QUOTA=1000
TENANT_DEFAULT_MAX_SESSIONS=1

RETENTION_MESSAGES_DAYS=90
RETENTION_WEBHOOK_DAYS=30
```

Engine:

```env
HOST=0.0.0.0
LARAVEL_URL=http://flustra-wa:80
WA_DATA_PATH=/data/.wwebjs_auth
WA_MAX_SESSIONS=10
LOG_LEVEL=info
```

> `APP_DEBUG=true` di produksi akan menampilkan isi environment — termasuk password database dan secret HMAC — di halaman error. Pastikan tetap `false`.

---

## Rahasia yang wajib berbeda tiap tahap

Jangan pernah menyalin nilai ini antar tahap. Kalau secret dev bocor dan nilainya sama dengan produksi, penyerang bisa mengirim WhatsApp atas nama seluruh pelanggan.

| Variabel | Cara membuat |
|---|---|
| `APP_KEY` | `php artisan key:generate --show` |
| `ENGINE_TOKEN` | `openssl rand -hex 32` |
| `ENGINE_HMAC_SECRET` | `openssl rand -hex 32` — harus berbeda dari `ENGINE_TOKEN` |
| `DB_PASSWORD` | dari Coolify |
| API key tenant | `php artisan gateway:setup-tenant … --key="…"` |

`ENGINE_TOKEN` dan `ENGINE_HMAC_SECRET` harus **identik antara Laravel dan engine dalam satu tahap**, dan **berbeda antar tahap**.

## Daftar periksa promosi dev → staging → production

1. Migrasi berjalan bersih di tahap sebelumnya (`php artisan migrate --force`).
2. `php artisan test` hijau.
3. Deploy ulang engine di tahap tersebut, lalu pastikan sesi kembali tersambung **tanpa scan QR** — ini uji regresi utamanya.
4. Kirim satu pesan uji dan pastikan statusnya sampai `delivered`.
5. Webhook uji coba menerima kiriman dan tanda tangannya lolos verifikasi.
6. Baru merge ke branch berikutnya.

## Menjalankan lokal

Lokal adalah tahap keempat yang tidak ada di Coolify. Salin `.env.example` → `.env` (root dan `engine/`), lalu:

```bash
npm run all
```

Laravel di `http://localhost:8070`, engine di `http://127.0.0.1:3100`, plus queue worker dan Vite.
