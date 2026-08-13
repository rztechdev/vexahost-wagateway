# REST API v1

| Tahap | Base URL |
|---|---|
| Production | `https://wa.flustra.id` |
| Staging | `https://wa-staging.flustra.tech` |
| Development | `https://wa-dev.flustra.tech` |
| Lokal | `http://localhost:8070` |

API key berbeda per tahap dan tidak bisa dipakai lintas tahap.

## Autentikasi

Setiap request menyertakan API key tenant:

```
X-Api-Key: fwa_a1b2c3d4.PANJANGSEKALIRAHASIA...
```

Bisa juga sebagai bearer token: `Authorization: Bearer fwa_a1b2c3d4....`

Kunci dibuat di dashboard (menu **API Keys**) dan **hanya ditampilkan sekali** — yang tersimpan di server cuma hash-nya.

Rate limit dihitung per kunci, bukan per IP, supaya beberapa aplikasi Flustra yang berjalan di VPS yang sama tidak saling menghabiskan jatah. Default 60 request/menit.

## Bentuk respons

```json
{ "success": true, "data": { } }
{ "success": false, "error": { "message": "Kuota pesan bulan ini sudah habis (1000 pesan)." } }
```

| Kode | Arti |
|---|---|
| 202 | Pesan diterima dan diantre |
| 401 | API key tidak ada, salah, atau dicabut |
| 403 | Tenant nonaktif, atau kunci tidak punya scope yang diminta |
| 422 | Data tidak valid, sesi tidak siap, atau kuota habis |
| 429 | Melewati rate limit |

## Endpoint

### `GET /api/v1/health`

Status tenant, jumlah sesi terhubung, dan pemakaian bulan berjalan. Tidak kena rate limit — aman dipakai untuk monitoring.

### Sesi

| Method | Path | Keterangan |
|---|---|---|
| `GET` | `/api/v1/sessions` | Daftar sesi |
| `POST` | `/api/v1/sessions` | Buat sesi (`name`, `driver`) |
| `GET` | `/api/v1/sessions/{id}` | Detail sesi |
| `POST` | `/api/v1/sessions/{id}/connect` | Jalankan sesi & munculkan QR |
| `GET` | `/api/v1/sessions/{id}/qr` | QR sebagai data URI PNG |
| `POST` | `/api/v1/sessions/{id}/disconnect` | Hentikan tanpa memutus tautan |
| `POST` | `/api/v1/sessions/{id}/logout` | Putus tautan nomor. Setelah ini `connect` lagi untuk scan QR — boleh dengan nomor yang berbeda |
| `DELETE` | `/api/v1/sessions/{id}` | Hapus sesi & backup-nya |

### Kirim pesan teks

```bash
curl -X POST https://wa.flustra.id/api/v1/messages/text \
  -H "X-Api-Key: $WA_GATEWAY_KEY" \
  -H "Content-Type: application/json" \
  -d '{"to":"081234567890","message":"Halo dari Flustra"}'
```

```json
{ "success": true, "data": { "id": "01K2C...", "status": "queued", "to": "6281234567890" } }
```

`session_id` opsional. Kalau tidak diisi, dipakai sesi tenant pertama yang sedang terhubung — sesi platform tidak pernah dipilih otomatis.

Nomor diterima dalam format apa pun (`0812…`, `62812…`, `+62 812-3456-7890`) dan dinormalisasi ke `62812…`. Untuk grup, kirim chat id lengkap berakhiran `@g.us`.

### Kirim media

`POST /api/v1/messages/media` — `multipart/form-data` dengan field `file` (maks 16 MB), `to`, `caption` opsional, `type` (`image`|`document`|`video`|`audio`).

### Kirim massal

```bash
curl -X POST https://wa.flustra.id/api/v1/messages/bulk \
  -H "X-Api-Key: $WA_GATEWAY_KEY" \
  -H "Content-Type: application/json" \
  -d '{"to":["081234567890","081298765432"],"message":"Pengumuman"}'
```

Nomor yang formatnya rusak dikembalikan di `rejected` tanpa membatalkan sisanya. Semua pesan berbagi satu `batch_id`.

Setiap pesan diberi jeda acak 3–8 detik. Broadcast 500 nomor wajar memakan beberapa jam — jeda ini yang menjaga nomor tidak diblokir.

### Kirim dari template

`POST /api/v1/messages/template` — `to`, `template` (slug), `variables` (objek). Placeholder `{{ nama }}` di template diganti nilainya.

### Riwayat

| Method | Path |
|---|---|
| `GET` | `/api/v1/messages?status=&direction=&session_id=&batch_id=&per_page=` |
| `GET` | `/api/v1/messages/{id}` |

Status pesan bergerak `queued → sending → sent → delivered → read`, atau `failed`. Status tidak pernah mundur, meski ack dari WhatsApp datang tidak berurutan.

### Webhook

| Method | Path |
|---|---|
| `GET` | `/api/v1/webhooks` |
| `POST` | `/api/v1/webhooks` — `url`, `events` (opsional; kosong = semua) |
| `DELETE` | `/api/v1/webhooks/{id}` |

### OTP — perlu scope `otp`

`POST /api/v1/otp/send` (`phone`, `purpose`) dan `POST /api/v1/otp/verify` (`phone`, `purpose`, `code`).

Dipakai flustra-auth untuk verifikasi kepemilikan nomor. Jangan berikan scope ini ke kunci integrasi biasa: kunci yang bocor dengan scope `otp` bisa dipakai membombardir nomor orang lain dan membuat nomor platform diblokir.

## Menerima webhook

Gateway mengirim `POST` JSON:

```json
{
  "event": "message.received",
  "timestamp": "2026-08-13T09:12:44+07:00",
  "delivery_id": "01K2C...",
  "data": {
    "message_id": "01K2C...",
    "session_id": "01K2B...",
    "from": "6289999999999",
    "type": "text",
    "body": "Halo min",
    "is_group": false
  }
}
```

Event yang tersedia: `message.received`, `message.status`, `session.status`, `session.qr`.

**Selalu verifikasi tanda tangannya** sebelum memproses:

```php
$expected = hash_hmac('sha256', $request->getContent(), $webhookSecret);

if (! hash_equals($expected, $request->header('X-Flustra-Signature'))) {
    abort(401);
}
```

Endpoint Anda harus membalas 2xx. Kegagalan diulang 3 kali dengan jeda menaik; endpoint yang gagal 50 kali berturut-turut dinonaktifkan otomatis agar antrean tidak terus terisi kiriman yang pasti gagal.
