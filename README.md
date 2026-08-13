# Flustra WA Gateway

Gateway WhatsApp terpusat untuk seluruh ekosistem Flustra — multi-nomor, multi-tenant, REST API, webhook, dan riwayat pesan. Dirancang agar bisa dijual sebagai SaaS.

**Satu kalimat:** aplikasi Anda memanggil satu endpoint HTTP, dan pesan WhatsApp terkirim dari nomor yang sudah Anda tautkan.

```bash
curl -X POST https://wa.flustra.id/api/v1/messages/text \
  -H "X-Api-Key: fwa_a1b2c3d4.…" \
  -H "Content-Type: application/json" \
  -d '{"to":"081234567890","message":"Invoice INV-001 sudah lunas."}'
```

---

## Dokumentasi

Mulai dari sini kalau Anda baru:

| Dokumen | Isi |
|---|---|
| **[docs/PENGANTAR.md](docs/PENGANTAR.md)** | **Mulai di sini.** Apa itu flustra-wa, untuk apa, masalah apa yang diselesaikan, dan istilah-istilahnya |
| [docs/ARSITEKTUR.md](docs/ARSITEKTUR.md) | Bagaimana bagian-bagiannya bekerja sama, alur data, dan alasan di balik keputusannya |
| [docs/PANDUAN_DEVELOPER.md](docs/PANDUAN_DEVELOPER.md) | Menyiapkan mesin, struktur kode, cara menambah fitur, menulis tes |
| [docs/API.md](docs/API.md) | Referensi REST API v1 dan webhook |
| [docs/INTEGRASI_APP.md](docs/INTEGRASI_APP.md) | Menyambungkan aplikasi Flustra lain ke gateway |
| [docs/ENVIRONMENT.md](docs/ENVIRONMENT.md) | Tiga tahap: development, staging, production |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Deploy di Coolify, langkah demi langkah |
| [docs/OPERASIONAL.md](docs/OPERASIONAL.md) | Menjalankan di produksi: pemantauan, pemulihan, pemecahan masalah |
| [docs/PERBANDINGAN_PROVIDER.md](docs/PERBANDINGAN_PROVIDER.md) | whatsapp-web.js vs WhatsApp Business API resmi (Twilio/Meta) |
| [docs/VERIFIKASI_NOMOR.md](docs/VERIFIKASI_NOMOR.md) | Alur OTP dan kenapa nomor wajib diverifikasi |
| [docs/FAQ.md](docs/FAQ.md) | Pertanyaan yang sering muncul |

## Ringkasan teknis

- **Laravel 12** (PHP 8.2+) — dashboard, REST API, tenant, antrean, webhook
- **Node.js 20+** — engine `whatsapp-web.js` yang menjalankan Chromium
- **MySQL** di produksi, SQLite untuk lokal
- **Blade + Tailwind v4 + Alpine.js** — tanpa SPA

Satu repo, tiga deployable: root = Laravel, `engine/` = Node, plus satu worker antrean.

## Menjalankan lokal

```bash
composer install
npm install
npm --prefix engine install
```

Salin `.env.example` → `.env` di root **dan** di `engine/`. Isi `ENGINE_TOKEN` dan `ENGINE_HMAC_SECRET` dengan nilai acak yang **sama persis** di keduanya:

```bash
php artisan key:generate
php artisan migrate
```

Jalankan semuanya sekaligus:

```bash
npm run all
```

- Laravel — http://localhost:8070
- Engine — http://127.0.0.1:3100
- Vite dan queue worker ikut berjalan

Daftar akun lewat http://localhost:8070/register, lalu buat sesi dan scan QR-nya.

Detail lengkap ada di [docs/PANDUAN_DEVELOPER.md](docs/PANDUAN_DEVELOPER.md).

## Tes

```bash
php artisan test
```

## Lisensi

Proprietary — milik Flustra Tech. Bukan produk resmi WhatsApp atau Meta.
