# Flustra WA Gateway — catatan untuk sesi berikutnya

Gateway WhatsApp terpusat multi-tenant untuk ekosistem Flustra, dirancang agar bisa dijual sebagai SaaS. Menggantikan proses `whatsapp-web.js` yang dulu menempel di dalam `flustra-erp`.

**Baca dulu:** [docs/PENGANTAR.md](docs/PENGANTAR.md) lalu [docs/ARSITEKTUR.md](docs/ARSITEKTUR.md). Peta seluruh dokumentasi internal ada di [docs/README.md](docs/README.md).

---

## Bentuknya

Tiga proses, satu repo:

| Proses | Bahasa | Tugas |
|---|---|---|
| root | Laravel 12 | Dashboard, REST API, tenant, antrean, webhook |
| `engine/` | Node 20 | `whatsapp-web.js` + Chromium — satu sesi = satu Chromium |
| worker | Laravel | `queue:work` — pengiriman & webhook di latar belakang |

Laravel memegang **keadaan**, engine memegang **koneksi**. Engine tidak punya database: saat boot ia menanyakan daftar sesi ke Laravel lewat `/internal/engine/bootstrap`.

Ketahanan sesi (alasan utama project ini ada): kredensial disimpan di persistent volume `/data` **dan** dicadangkan berkala ke Laravel. Redeploy tidak memutus nomor. Tapi nomor tetap **bisa diganti** kapan saja lewat Putus tautan → Hubungkan → scan nomor lain.

## Perintah

```bash
npm run all          # Laravel :8070 + engine :3100 + queue + vite sekaligus
php artisan test     # seluruh tes
./vendor/bin/pint    # format PHP, jalankan sebelum commit
```

`php artisan gateway:setup-tenant "Nama" --session="Sesi" --key="kunci"` untuk menyiapkan tenant/sesi/API key dari CLI.

## Aturan yang berlaku di seluruh kode

**Semua teks Bahasa Indonesia** — komentar, pesan galat, nama tes, dokumentasi, UI. Nama kelas/method/kolom tetap Inggris.

**Selalu berangkat dari tenant.** `EnsureTenantSelected::from($request)->sessions()->findOrFail($id)`, bukan `WaSession::find($id)`. Tidak ada global scope yang menangkap kelalaian ini.

**Pesan keluar hanya lewat `MessageDispatcher::queue()`** — di situlah normalisasi nomor, pemeriksaan kuota, dan pencatatan pemakaian terjadi.

**Nomor selalu lewat `PhoneNumber`** — `normalize()` sebelum menyimpan, `mask()` sebelum menulis log.

**Kegagalan WhatsApp tidak boleh menjatuhkan proses lain.** Notifikasi itu pelengkap; invoice tetap harus tersimpan meski WhatsApp-nya gagal.

**Komentar menjelaskan alasan, bukan mekanisme.** Kode sudah menunjukkan *apa*; komentar berguna untuk *kenapa begitu* dan *apa akibatnya kalau tidak*.

## Dua kumpulan dokumentasi — jangan tertukar

| | Untuk siapa | Lokasi | Tampil di web |
|---|---|---|---|
| Internal | Tim | `docs/` | Tidak |
| Produk | Pelanggan SaaS | `resources/docs/` | Ya, di `/docs` |

Halaman publik **tidak boleh** memuat cara meng-clone source code, perintah instalasi, atau alamat repo GitHub. Ryan menjual ini sebagai SaaS. `DocsTest` menjaga batas itu — ia menolak frasa `git clone`, `github.com`, `composer install`, `npm install`, `php artisan` muncul di halaman publik.

Menambah halaman publik: buat berkas di `resources/docs/`, daftarkan di katalog `app/Support/DocsRepository.php` (katalog itu sekaligus daftar putih).

## Jebakan yang pernah menggigit

**Nama rute bertabrakan.** `Route::apiResource` di `routes/api.php` harus diberi awalan `api.` — tanpa itu ia menimpa nama rute dashboard, dan `route('sessions.index')` menghasilkan URL API.

**`where($kolom, null)` menjadi `whereNull`.** Pernah membuat satu ack tanpa id menandai pesan yang masih mengantre sebagai terbaca. Periksa nilai null secara eksplisit sebelum menyusun kueri.

**Baris baru dari `firstOrCreate` tidak membaca nilai default database.** Tulis nilai awal secara eksplisit, kalau tidak counter terbaca `null` bukan `0`.

**Hostname internal Coolify adalah UUID resource, bukan nama tampilannya.** `ENGINE_URL=http://flustra-wa-engine:3100` menghasilkan `cURL error 6: Could not resolve host` — nama itu cuma label di antarmuka. Pakai UUID dari URL resource (`.../application/<uuid>`), sama seperti yang sudah dipakai Coolify untuk `DB_HOST`. Berlaku dua arah: `ENGINE_URL` di Laravel + worker, `LARAVEL_URL` di engine.

**Nixpacks memakai Node 18 kalau `NIXPACKS_NODE_VERSION` tidak diset.** Engine menuntut Node 20 lewat `engines` di `engine/package.json`, dan Node 18 sudah EOL. Env Laravel sudah memuatnya sejak awal; env engine dulu tidak.

**Hapus lunak bertabrakan dengan indeks unik.** `wa_sessions` unik pada `(tenant_id, name)` tanpa memandang `deleted_at`, jadi membuat ulang sesi dengan nama yang sama gagal dengan galat 1062. `SessionService::create()` membuang permanen baris tertrash bernama sama lebih dulu — permanen, bukan dipulihkan, supaya baris baru dapat ULID baru dan tidak mewarisi folder kredensial lama di engine.

**`QR_TTL_SECONDS` jangan di bawah 60.** whatsapp-web.js menerbitkan QR baru dengan jeda tidak tetap sampai ~60 detik; masa berlaku lebih pendek membuat modal QR berkedip kosong.

**Laravel Pail tidak disertakan di `npm run all`** — butuh `pcntl` yang tidak ada di PHP Windows, dan `--kill-others` membuat matinya Pail menjatuhkan proses lain.

**Tidak ada template env di repo.** Berkas `.env.*.example` dihapus atas permintaan Ryan (13 Agu 2026) supaya isian env tinggal disalin dari berkas `.env.<tahap>` di mesinnya ke Coolify tanpa komentar yang mengganggu; `EnvTemplateTest` ikut dihapus. Konsekuensinya: menambah variabel env **tidak** akan ketahuan terlewat oleh tes apa pun. Daftarkan variabel baru di `docs/ENVIRONMENT.md` dan isikan ke ketiga resource Coolify pada perubahan yang sama.

## Alur git

`dev` → `staging` → `main`. Coolify men-deploy otomatis setiap push. Jangan push langsung ke `main`.

**Jangan pernah commit** `.env`, `engine/.env`, isi `.wwebjs_auth/`, atau zip sesi — kredensial WhatsApp yang bocor sama dengan memberi akses penuh ke nomor tersebut.

## Keadaan sekarang

Sudah ter-deploy penuh di Coolify (ketiga resource), **belum diuji end-to-end di produksi**. Uji regresi wajib ada di [docs/SETUP_VPS_COOLIFY.md](docs/SETUP_VPS_COOLIFY.md) Bagian 10 — yang terpenting: redeploy engine lalu pastikan sesi tersambung sendiri tanpa scan QR.

Integrasi ke `flustra-erp`, `flustra-web`, `flustra-pricing`, `flustra-helpdesk` sudah ditulis (`app/Services/WhatsAppGateway.php` disalin identik ke keempatnya) tapi belum dijalankan dengan gateway produksi. Lihat [docs/INTEGRASI_APP.md](docs/INTEGRASI_APP.md).

Yang sengaja belum dibuat: billing/langganan, driver `cloud_api` (Meta/Twilio resmi), realtime dashboard, beberapa instance engine. Rinciannya di [docs/ARSITEKTUR.md](docs/ARSITEKTUR.md#11-yang-sengaja-belum-dibuat).
