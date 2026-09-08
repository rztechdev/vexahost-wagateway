# Engine: Seluk-beluk Sisi Node.js (Baileys)

Engine adalah bagian yang bertanggung jawab berkomunikasi langsung dengan jaringan WhatsApp. Dokumen ini menjelaskan cara kerjanya secara rinci — untuk siapa pun yang akan mengubah, men-debug, atau mengoperasikannya.

Sisi Laravel dibahas di [REFERENSI_KODE.md](REFERENSI_KODE.md).

---

## 1. Apa yang sebenarnya terjadi

Engine menggunakan **Baileys** (`@whiskeysockets/baileys`), yaitu pustaka murni JavaScript yang mengimplementasikan protokol WebSocket WhatsApp (Noise protocol handshake, enkripsi end-to-end Signal, dan serialisasi Protobuf).

Engine **TIDAK** lagi menjalankan browser Chromium atau Puppeteer.

Konsekuensi arsitektur ini:

- **Satu sesi = satu koneksi WebSocket murni.** Tanpa overhead peramban (Chromium/Puppeteer). Biaya RAM per sesi Baileys di server produksi: **BELUM DIUKUR SAMA SEKALI**.
- **Kapasitas platform.** Batas aman sesi Baileys belum diketahui sebelum pengukuran produksi selesai dilakukan. Batas konfigurasi `WA_MAX_SESSIONS` dipertahankan pada nilai operasional saat ini (`3`).
- **Koneksi instan.** Opsi `syncFullHistory: false` disetel secara eksplisit sehingga engine tidak menarik riwayat chat besar saat baru terhubung, menghindari lonjakan memori dan waktu tunggu yang lama.
- **Kredensial berbasis multi-file auth.** Kunci enkripsi sesi disimpan sebagai berkas-berkas JSON (`creds.json`, `app-state-sync-key-*.json`, `pre-key-*.json`, dll.) di folder sesi.

---

## 2. Prinsip perancangan

### Engine tidak menyimpan state bisnis
Engine tidak memiliki database relasional dan tidak menyimpan daftar sesi secara permanen. Saat booting, engine menanyakan daftar sesi aktif ke Laravel (`/internal/engine/bootstrap`). State kredensial disimpan lokal di volume `/data/sessions` dengan cadangan otomatis di Laravel (`session.json`).

### Kegagalan satu sesi terisolasi
Setiap sesi memiliki socket Baileys dan antrean pesan keluar masing-masing. Diskoneksi atau kegagalan pada satu nomor tidak memengaruhi nomor lain.

### Isolasi kesalahan antara Laravel dan Engine
- Jika Laravel sedang restart, pengiriman event webhook dari engine ditelan secara aman (`laravel.event()` menelan error jaringan tanpa crash).
- Jika engine mati atau sedang menghubungkan ulang, Laravel menangani balasan 503 sebagai galat sementara (*temporary*), sehingga antrean pengiriman pesan diulang kembali (*retry*) oleh worker queue.

### Kontrak eksternal tetap terkunci
Baileys secara internal menggunakan format JID `@s.whatsapp.net` untuk direct chat dan enum status `WAMessageStatus`. Engine dan Laravel **mengunci kontrak API eksternal**:
- `chat_id` selalu dikembalikan dalam format `<nomor>@c.us` untuk chat personal dan `<id>@g.us` untuk grup.
- `wa_message_id` adalah ID stanza WhatsApp yang unik dan stabil (string buram).
- `ack` selalu dipetakan ke integer `1` (sent), `2` (delivered), atau `3` (read).

---

## 3. Struktur Berkas

```
engine/src/
├─ server.js            Express, penjaga token, endpoint HTTP & metrik kesehatan
├─ session-manager.js   Inti: Map sesi, siklus socket Baileys, normalisasi event & kontrak
├─ queue.js             Antrean per sesi + jeda anti-ban (3–8 detik)
├─ laravel.js           Klien HTTP bertanda tangan HMAC ke Laravel (/internal/*)
├─ config.js            Pembacaan env + validasi saat boot
├─ logger.js            Pino logger dengan penyamaran nomor telepon
└─ stores/
   └─ laravel-store.js  Sinkronisasi backup kredensial multi-file auth ke Laravel
```

### `config.js`
Membaca environment dan memvalidasi variabel wajib saat booting:
- `ENGINE_TOKEN`: Token statis untuk menjaga endpoint HTTP engine.
- `ENGINE_HMAC_SECRET`: Kunci rahasia untuk menandatangani panggilan callback ke Laravel.
- `LARAVEL_URL`: URL internal aplikasi Laravel.
- `dataPath`: Direktori lokal penyimpanan kredensial (bawaan: `/data/sessions`).
- `maxSessions`: Batas maksimal sesi aktif (`WA_MAX_SESSIONS`, bawaan 20).
- `initTimeoutMs`: Batas waktu inisialisasi koneksi (bawaan 180.000 ms).

### `logger.js`
Menggunakan Pino dengan konfigurasi `redact` untuk menyamarkan nomor telepon (`to`, `from`, `*.phone_number`) guna mematuhi privasi data pelanggan.

### `laravel.js`
Klien HTTP ke endpoint internal Laravel (`/internal/*`). Setiap permintaan ditandatangani dengan HMAC-SHA256:
```
signature = HMAC-SHA256(timestamp + "." + body, ENGINE_HMAC_SECRET)
```
Untuk upload backup kredensial, body dikirim sebagai JSON murni dengan header checksum SHA-256 (`X-Engine-Body-Sha256`).

### `server.js`
Express HTTP server (port 3100) dengan rute:

| Method | Path | Keterangan |
|---|---|---|
| GET | `/health` | Status engine, metrik sesi websocket, memori RSS/heap, dan kompatibilitas pemantau |
| POST | `/sessions/:id/start` | Menyalakan sesi Baileys |
| POST | `/sessions/:id/stop` | Menghentikan socket sesi |
| POST | `/sessions/:id/logout` | Memutus tautan di WhatsApp dan menghapus backup |
| GET | `/sessions/:id/status` | Status sesi (`connecting`, `qr`, `connected`, `disconnected`) |
| POST | `/sessions/:id/messages` | Mengirim pesan (teks / media) |

Balasan kegagalan pengiriman pesan:
- `200`: Berhasil dikirim ke antrean WhatsApp.
- `422`: Gagal permanen (nomor tujuan tidak terdaftar di WhatsApp, payload tidak valid).
- `503`: Gagal sementara (sesi belum siap/reconnecting, server sibuk).

---

## 4. `session-manager.js`

Komponen inti yang mengelola seluruh instans socket Baileys.

### Siklus Koneksi & Pemetaan Event

1. **`start(sessionId)`**:
   - Memastikan tidak melebihi `WA_MAX_SESSIONS`.
   - Menyiapkan folder sesi di `/data/sessions/{sessionId}`. Jika belum ada kredensial lokal, memeriksa dan memulihkan cadangan dari Laravel via `LaravelStore.extract()`.
   - Menginisialisasi auth state melalui `useMultiFileAuthState(sessionDir)`.
   - Membuka socket dengan `makeWASocket()`.
   - Memasang pengukur batas waktu inisialisasi (`pengukurInit`).
2. **`connection.update`**:
   - Jika menerima `qr`: Mengonversi string QR menjadi format Data URL PNG via `qrcode.toDataURL()`, melepas pengukur inisialisasi, dan mengirim event `qr` ke Laravel.
   - Jika `connection === 'open'`: Mengambil data nomor telepon dan push name, status berubah menjadi `connected`, memasang sinkronisasi backup berkala, dan mengirim event `ready` ke Laravel.
   - Jika `connection === 'close'`:
     - Kode `401` (`DisconnectReason.loggedOut`): Sesi dikeluarkan oleh WhatsApp. Status menjadi `disconnected`, memicu event `disconnected` dengan alasan `logged_out`, dan menghentikan sesi.
     - Kode `515` (`DisconnectReason.restartRequired`): WhatsApp meminta restart koneksi. Engine langsung melakukan penyambungan ulang segera (delay 0 ms).
     - Error koneksi sementara (timeout, connection lost): Mencoba menyambung kembali dengan *exponential backoff* (2s, 3s, 4.5s... maks 15s) hingga 5 kali percobaan. Jika batas habis, status menjadi `disconnected` dan sesi dihentikan.
3. **`creds.update`**:
   - Menyimpan pembaruan kunci enkripsi ke disk lokal via `saveCreds()`.
4. **`messages.upsert`**:
   - Mengabaikan pesan keluar sendiri (`key.fromMe`) dan broadcast status WhatsApp.
   - Membuka pembungkus pesan ephemeral atau view-once.
   - Menormalkan JID tujuan dan pengirim ke format kontrak eksternal (`toContractChatId`), mengubah `@s.whatsapp.net` menjadi `@c.us`.
   - Mengirim event `message` ke Laravel.
5. **`messages.update`**:
   - Memetakan pembaruan status pengiriman (`WAMessageStatus`):
     - `SERVER_ACK` (2) → ack `1` (sent)
     - `DELIVERY_ACK` (3) → ack `2` (delivered)
     - `READ` (4) atau `PLAYED` (5) → ack `3` (read)
   - Mengirim event `message_ack` ke Laravel.

### Pengiriman Pesan (`send`)

- Memverifikasi keberadaan nomor tujuan di WhatsApp via `sock.onWhatsApp(digits)`. Jika nomor tidak terdaftar, melempar error dengan tanda `permanent = true` (menghasilkan status HTTP 422).
- Mendukung pesan teks dan media (`image`, `video`, `audio`, `document`) dari buffer base64.
- Mengembalikan ID WhatsApp buram dan `chat_id` yang dinormalkan ke `@c.us` atau `@g.us`:
  ```json
  {
    "wa_message_id": "3EB0ABCDEF1234567890",
    "chat_id": "6281234567890@c.us"
  }
  ```
- Mencegah duplikasi pengiriman berulang dengan pencatatan promise berdasarkan `messageId`.

---

## 5. `queue.js`

Mengantre pesan keluar per nomor WhatsApp menggunakan antrean Promise terisolasi, diselingi jeda acak 3–8 detik antar pesan. Isolasi per sesi memastikan broadcast satu workspace tidak memperlambat pengiriman pesan workspace lain.

---

## 6. `stores/laravel-store.js`

Menyimpan cadangan kredensial multi-file auth Baileys ke storage Laravel (`session-backups` disk).

- `save({ session: sessionId })`: Membaca seluruh berkas JSON kredensial pada folder sesi, membundelnya ke dalam satu struktur dokumen JSON `{ version: 1, sessionId, files: { [nama]: konten } }`, menghitung digest SHA-256, dan mengunggahnya ke `/internal/sessions/{sessionId}/backup`.
- `extract({ session: sessionId })`: Mengunduh dokumen JSON cadangan dari Laravel dan merekonstruksi seluruh berkas auth ke folder `/data/sessions/{sessionId}`.
- `delete({ session: sessionId })`: Menghapus cadangan di Laravel dan membersihkan folder lokal.
- `sessionExists({ session: sessionId })`: Memeriksa keberadaan berkas kredensial di lokal atau memverifikasi ke Laravel.

---

## 7. Menjalankan dan Debugging

```bash
# Menjalankan engine lokal untuk pengembangan
npm --prefix engine run dev

# Menjalankan pengujian unit engine
npm --prefix engine test

# Memeriksa kesehatan engine lokal
curl http://127.0.0.1:3100/health
```

### Log Penting
- `Engine WhatsApp berjalan (Baileys)`: Engine siap menerima koneksi.
- `Memulihkan sesi tersimpan`: Proses bootstrap pemulihan sesi dimulai.
- `Sesi siap`: Sesi berhasil tersambung ke WhatsApp.
- `Backup sesi terkirim`: Kredensial berhasil dicadangkan ke Laravel.
- `Mencoba menyambung kembali websocket`: Percobaan reconnect otomatis akibat gangguan jaringan.
