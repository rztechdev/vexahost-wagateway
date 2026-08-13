# Engine: Seluk-beluk Sisi Node.js

Engine adalah bagian yang benar-benar berbicara dengan WhatsApp. Dokumen ini menjelaskan cara kerjanya secara rinci — untuk siapa pun yang akan mengubah, men-debug, atau sekadar memahaminya.

Sisi Laravel dibahas di [REFERENSI_KODE.md](REFERENSI_KODE.md).

---

## 1. Apa yang sebenarnya terjadi

`whatsapp-web.js` **bukan** klien protokol WhatsApp. Ia menjalankan Chromium sungguhan lewat Puppeteer, membuka `web.whatsapp.com`, dan menyuntikkan JavaScript untuk memanggil fungsi-fungsi internal halaman itu.

Konsekuensi yang harus selalu diingat:

- **Satu sesi = satu Chromium.** RAM 300–500 MB per nomor. Inilah batas skala yang sebenarnya.
- **Rapuh terhadap perubahan.** Kalau WhatsApp mengubah struktur internal halamannya, library harus menyesuaikan. Versi lama bisa berhenti bekerja tanpa peringatan.
- **Perilakunya asinkron dan tidak selalu berurutan.** Event bisa datang terlambat, ganda, atau terlewat.
- **Tidak ada jaminan.** Tidak ada SLA, tidak ada dukungan, tidak ada jalur banding kalau nomor diblokir.

Engine dirancang dengan asumsi semua itu bisa terjadi.

---

## 2. Prinsip perancangan

### Engine tidak menyimpan apa pun yang penting

Tidak ada database, tidak ada file konfigurasi berisi daftar sesi. Saat menyala, engine bertanya ke Laravel: sesi apa yang harus dijalankan?

Karena itu container engine bisa dibunuh, dihapus, dan dibangun ulang kapan saja tanpa kehilangan apa pun. Yang perlu diingat ada di Laravel (daftar sesi) dan di volume (kredensial).

### Kegagalan satu sesi tidak menular

Setiap sesi punya Client, antrean, dan siklus hidupnya sendiri. Sesi yang crash, ditolak autentikasinya, atau gagal dipulihkan tidak menghentikan sesi lain.

Ini terlihat jelas di `bootstrap()`: pemulihan dibungkus `try/catch` per sesi, bukan satu blok untuk semuanya.

### Engine tidak pernah menjatuhkan Laravel, dan sebaliknya

Kalau Laravel sedang restart, callback engine gagal — dan itu ditelan sebagai peringatan, bukan error. Sesi WhatsApp yang sehat tidak boleh terputus hanya karena aplikasi web sedang di-deploy.

Sebaliknya, engine yang mati membuat pengiriman gagal dengan kode "sementara", sehingga pesan dikembalikan ke antrean alih-alih dibuang.

---

## 3. Berkas

```
engine/src/
├─ server.js            Express, penjaga token, endpoint HTTP
├─ session-manager.js   Inti: Map sesi, siklus hidup Client, pemetaan event
├─ queue.js             Antrean per sesi + jeda anti-ban
├─ laravel.js           Klien HTTP bertanda tangan ke Laravel
├─ config.js            Pembacaan env + validasi saat boot
├─ logger.js            Pino + penyamaran nomor telepon
└─ stores/
   └─ laravel-store.js  Store RemoteAuth (cadangan kredensial)
```

### `config.js`

Membaca environment dan **gagal saat boot** bila `ENGINE_TOKEN`, `ENGINE_HMAC_SECRET`, atau `LARAVEL_URL` kosong.

Sengaja gagal di awal, bukan saat permintaan pertama. Engine yang berjalan tanpa token akan menolak semua permintaan Laravel dan mengirim callback tanpa tanda tangan — gejalanya membingungkan, sedangkan penyebabnya sepele.

`backupIntervalMs` dipaksa minimum 60000 karena itu batas yang diberlakukan library.

### `logger.js`

Pino dengan konfigurasi `redact` yang menyamarkan nomor telepon di semua jalur umum (`to`, `from`, `*.phone_number`).

Log dikirim ke agregator dan disimpan lama, sementara isinya data pribadi pelanggan tenant. Penyamaran ini bukan formalitas.

Format keluarannya JSON. Untuk membaca nyaman saat pengembangan:

```bash
npm --prefix engine run dev | npx pino-pretty
```

### `laravel.js`

Klien HTTP ke `/internal/*`. Setiap permintaan ditandatangani:

```js
signature = HMAC-SHA256(timestamp + "." + body, ENGINE_HMAC_SECRET)
```

Untuk unggahan besar, yang ditandatangani adalah hash isinya, bukan isinya:

```js
signature = HMAC-SHA256(timestamp + "." + path + "." + sha256(file), secret)
```

Method: `bootstrap()`, `event()`, `backupExists()`, `uploadBackup()`, `downloadBackup()`, `deleteBackup()`.

`event()` **menelan kegagalan** dan mengembalikan `null`. Ini disengaja — lihat prinsip di atas.

### `server.js`

Express dengan batas body 32 MB. Lampiran dikirim sebagai base64 di dalam JSON, jadi batasnya harus lebih besar dari batas lampiran WhatsApp (±16 MB) ditambah overhead base64.

Semua rute dijaga `X-Engine-Token`, kecuali `/health` supaya monitoring tidak perlu menyimpan token.

| Method | Path | Balasan |
|---|---|---|
| GET | `/health` | `{status, sessions, max_sessions, uptime_seconds}` |
| POST | `/sessions/:id/start` | `{status: 'starting'}` atau 409 |
| POST | `/sessions/:id/stop` | `{status: 'stopped'}` |
| POST | `/sessions/:id/logout` | `{status: 'logged_out'}` |
| GET | `/sessions/:id/status` | `{status, phone_number, push_name}` atau 404 |
| POST | `/sessions/:id/messages` | `{wa_message_id, chat_id}` |

**Arti kode balasan pengiriman**, dan ini penting karena Laravel bergantung padanya:

| Kode | Arti bagi Laravel |
|---|---|
| 200 | Terkirim |
| 422 | Gagal **permanen** — jangan diulang |
| 503 | Gagal **sementara** — kembalikan ke antrean |

Salah memberi kode berarti pesan yang seharusnya bisa terkirim dibuang, atau pesan yang mustahil terkirim diulang sampai kehabisan jatah.

`shutdown()` menangani SIGTERM dari Coolify: menutup server, lalu menghentikan semua Client dengan rapi. Ini memberi RemoteAuth kesempatan menuliskan kredensial terakhirnya — tanpa itu, sesi bisa tertinggal dalam keadaan setengah tertulis dan gagal dipulihkan setelah restart.

---

## 4. `session-manager.js`

Inti engine. Menyimpan `Map<sessionId, {client, status, phoneNumber, pushName}>`.

### `bootstrap()`

Dipanggil sekali saat server siap.

```js
await mkdir(config.dataPath, { recursive: true });
const sessions = await laravel.bootstrap();
for (const { session_id } of sessions) {
    try { await this.start(session_id); }
    catch (e) { logger.error(...); }   // satu gagal, sisanya lanjut
}
```

Kalau Laravel tidak bisa dihubungi, engine **tetap menyala** tanpa sesi. Ia akan bisa dipakai begitu Laravel hidup dan mengirim permintaan `start`.

### `start(sessionId)`

Tiga penjagaan sebelum membuat Client:

1. Sudah ada di Map → kembalikan yang ada
2. Sedang dalam proses start (`#starting`) → tolak. Dua permintaan bersamaan akan membuat dua Chromium untuk satu sesi, dan keduanya berebut folder kredensial
3. Sudah mencapai `WA_MAX_SESSIONS` → tolak dengan pesan jelas, alih-alih membiarkan container kehabisan memori dan restart berulang

Lalu:

```js
const client = new Client({
    authStrategy: new RemoteAuth({
        clientId: sessionId,
        dataPath: config.dataPath,
        store: this.#store,
        backupSyncIntervalMs: config.backupIntervalMs,
    }),
    puppeteer: { headless: true, args: config.puppeteerArgs },
});
```

`client.initialize()` **tidak di-await**. Memulihkan sesi bisa memakan puluhan detik; pemanggil hanya perlu tahu prosesnya dimulai. Keadaan sebenarnya dikabarkan lewat event.

### `stop()` vs `logout()`

- `stop()` — hapus dari Map, `client.destroy()`. Kredensial tetap ada di volume.
- `logout()` — `client.logout()` (memutus tautan di sisi WhatsApp), lalu `stop()`, lalu hapus cadangan di Laravel.

Setelah `logout()`, menyambung lagi berarti QR baru — dan **boleh dengan nomor yang berbeda**. Inilah jalur ganti nomor.

### `send()`

```js
async send(sessionId, { to, type, body, media }) {
    // tolak bila sesi tidak ada atau belum connected → error sementara
    return this.#queue.enqueue(sessionId, async () => {
        const chatId = await this.#resolveChatId(entry.client, to);
        // ... sendMessage
    });
}
```

`#resolveChatId()` memanggil `client.getNumberId()` untuk memastikan nomor benar-benar terdaftar di WhatsApp. Bila tidak, ia melempar error dengan `permanent = true`.

Tanpa pemeriksaan ini, pesan ke nomor tidak terdaftar gagal dengan error generik, dan Laravel akan mengulanginya empat kali tanpa guna.

### Pemetaan event

| Event whatsapp-web.js | Yang dilakukan |
|---|---|
| `qr` | Render PNG dengan `qrcode`, kirim `qr_image` sebagai data URI |
| `authenticated` | status → `connecting` |
| `auth_failure` | status → `failed`, teruskan pesannya |
| `ready` | Ambil `client.info.wid.user` dan `pushname`, kirim `ready` |
| `remote_session_saved` | Log debug — penanda cadangan berhasil |
| `disconnected` | Kirim `disconnected`, lalu `stop()` sesinya |
| `message` | Lewati status/story, kirim `message` |
| `message_ack` | Kirim `message_ack` dengan angka ack-nya |

**Kenapa QR dirender jadi PNG di engine.** Supaya Laravel dan dashboard tidak perlu library QR sama sekali — cukup menaruh data URI-nya di `<img src>`. Paket `qrcode` sudah ada di engine sebagai dependensi.

**Kenapa `disconnected` memanggil `stop()`.** Client yang sudah disconnected tidak bisa dipakai lagi. Membiarkannya di Map berarti `start()` berikutnya mengembalikan Client mati itu, bukan membuat yang baru.

**Kenapa status/story dilewati.** Jumlahnya bisa sangat banyak dan tidak ada nilainya sebagai pesan. Menyimpannya hanya memenuhi tabel `messages`.

Angka ack dipetakan di sisi Laravel: `1 → sent`, `2 → delivered`, `3` dan `4 → read`.

---

## 5. `queue.js`

Antrean keluar per sesi, sebagai rantai Promise.

```js
enqueue(sessionId, task) {
    const previous = this.#queues.get(sessionId) ?? Promise.resolve();
    const current = previous
        .catch(() => {})          // kegagalan sebelumnya tidak memblokir berikutnya
        .then(async () => {
            await this.#delay();  // jeda acak
            return task();
        });
    this.#queues.set(sessionId, current.catch(() => {}));
    return current;
}
```

Dua hal yang layak diperhatikan.

**`.catch(() => {})` pada rantai yang disimpan.** Tanpa itu, satu pesan gagal akan membuat seluruh antrean sesi itu berhenti — dan Node juga akan melaporkan unhandled rejection. Yang dikembalikan ke pemanggil tetap Promise asli, jadi kegagalannya tidak hilang.

**Jeda ada di sini, bukan di Laravel.** Isolasinya harus per sesi. Kalau jedanya diatur Laravel, broadcast 500 pesan milik satu tenant akan menahan satu pesan mendesak milik tenant lain. Dengan antrean per sesi, keduanya berjalan paralel.

Jeda acak 3–8 detik bukan hiasan: mengirim beruntun dengan jarak yang seragam adalah pola paling khas robot, dan cara paling cepat membuat nomor diblokir.

---

## 6. `stores/laravel-store.js`

Store `RemoteAuth`. Kontraknya empat method:

| Method | Argumen | Tugas |
|---|---|---|
| `sessionExists` | `{session}` | Apakah ada cadangan? |
| `save` | `{session}` | Unggah `<session>.zip` |
| `extract` | `{session, path}` | Unduh ke `path` |
| `delete` | `{session}` | Hapus cadangan |

`RemoteAuth` memanggilnya dengan nama berformat `RemoteAuth-<clientId>`; `toSessionId()` mengupas awalannya untuk mendapat id baris `wa_sessions`.

**`sessionExists` mengembalikan `false` saat Laravel tidak bisa dihubungi.** Menjawab "ada" akan membuat RemoteAuth mencoba mengunduh dan gagal, lalu berpotensi menimpa kredensial yang masih valid di volume dengan file kosong. Menjawab "tidak ada" hanya memunculkan QR baru — jauh lebih aman.

whatsapp-web.js yang menangani zip dan unzip-nya sendiri; store hanya memindahkan file.

---

## 7. Menjalankan dan men-debug

```bash
npm --prefix engine run dev              # dengan --watch
npm --prefix engine run dev | npx pino-pretty
curl http://127.0.0.1:3100/health        # tanpa token
```

### Log penting

| Pesan | Arti |
|---|---|
| `Engine WhatsApp berjalan` | Server siap |
| `Memulihkan sesi tersimpan` | Bootstrap dimulai, dengan jumlah sesi |
| `Sesi dipulihkan` | Satu sesi berhasil dijalankan |
| `Sesi siap` | Tersambung ke WhatsApp |
| `Backup sesi terkirim` | Cadangan tersimpan di Laravel |
| `Backup sesi dipulihkan` | Kredensial diunduh dari Laravel |
| `Sesi terputus` | Dengan alasannya |
| `Laravel menolak event` | Biasanya tanda tangan tidak cocok |

### Gejala dan penyebab

| Gejala | Penyebab |
|---|---|
| Mentok `connecting`, tidak ada QR | Chromium gagal jalan — cek log; RAM atau library sistem |
| `Laravel menolak event` terus-menerus | `ENGINE_HMAC_SECRET` berbeda antara kedua sisi |
| `Token engine tidak valid` | `ENGINE_TOKEN` berbeda |
| QR muncul padahal sesi pernah tersambung | Volume tidak ter-mount, atau cadangan hilang |
| Sering `disconnected` | HP pemilik nomor jarang online, atau perangkat dikeluarkan dari WhatsApp |
| Container restart berulang | Kehabisan memori — turunkan `WA_MAX_SESSIONS` |
| `Sesi sedang dalam proses dijalankan` | Dua permintaan start bersamaan; tunggu sebentar |

### Chromium tidak jalan di Coolify

`engine/nixpacks.toml` memuat daftar apt package yang dibutuhkan. Kalau Chromium tetap gagal, jalankan langsung di dalam container untuk melihat library mana yang hilang:

```bash
node -e "import('puppeteer').then(p => p.default.launch({headless:true, args:['--no-sandbox']}))"
```

---

## 8. Memelihara

### Memperbarui whatsapp-web.js

```bash
npm --prefix engine update whatsapp-web.js
```

Versi baru bisa mengubah nama atau bentuk event. Setelah memperbarui, periksa `#bindEvents` masih cocok, lalu uji di development sebelum staging.

Ini pekerjaan rutin yang tidak bisa dilewati: versi lama bisa berhenti bekerja kapan saja saat WhatsApp Web berubah.

### Menambah endpoint engine

1. Rute di `server.js` (otomatis terjaga token karena middleware berlaku global)
2. Method di `SessionManager` bila menyentuh sesi
3. Method pemanggil di `WwebjsProvider` sisi Laravel
4. Pilih kode balasan yang benar untuk kegagalan — 422 permanen, selain itu sementara

### Menambah beberapa instance engine

Belum didukung: semua sesi mengarah ke satu `ENGINE_URL`. Untuk menyebarkannya perlu:

1. Kolom `engine_id` di `wa_sessions`
2. Daftar engine di konfigurasi
3. `WwebjsProvider` memilih URL berdasarkan kolom itu
4. Endpoint bootstrap yang menyaring per engine

Dicatat sebagai batas di [ARSITEKTUR.md](ARSITEKTUR.md#11-yang-sengaja-belum-dibuat).
