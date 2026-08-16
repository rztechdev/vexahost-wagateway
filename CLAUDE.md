# Flustra WA Gateway — catatan untuk sesi berikutnya

Gateway WhatsApp terpusat multi-tenant untuk ekosistem Flustra, dirancang agar bisa dijual sebagai SaaS. Menggantikan proses `whatsapp-web.js` yang dulu menempel di dalam `flustra-erp`.

**Baca dulu:** [docs/PENGANTAR.md](docs/PENGANTAR.md) lalu [docs/ARSITEKTUR.md](docs/ARSITEKTUR.md). Peta seluruh dokumentasi internal ada di [docs/README.md](docs/README.md).

---

## Bentuknya

Empat proses, satu repo, **satu container** ([`start.sh`](start.sh)):

| Proses | Bahasa | Tugas |
|---|---|---|
| web | Laravel 12 | Dashboard, REST API, workspace, antrean, webhook |
| `engine/` | Node 20 | `whatsapp-web.js` + Chromium — satu sesi = satu Chromium |
| worker | Laravel | `queue:work` — pengiriman & webhook di latar belakang |
| penjadwal | Laravel | `schedule:work` — sinkronisasi status sesi tiap menit |

Laravel memegang **keadaan**, engine memegang **koneksi**. Engine tidak punya database: saat boot ia menanyakan daftar sesi ke Laravel lewat `/internal/engine/bootstrap`.

Ketahanan sesi (alasan utama project ini ada): kredensial disimpan di persistent volume `/data` **dan** dicadangkan berkala ke Laravel. Redeploy tidak memutus nomor. Tapi nomor tetap **bisa diganti** kapan saja lewat Putus tautan → Hubungkan → scan nomor lain.

## Perintah

```bash
npm run all          # Laravel :8070 + engine :3100 + queue + vite sekaligus
php artisan test     # seluruh tes
./vendor/bin/pint    # format PHP, jalankan sebelum commit
```

Tidak ada perintah CLI untuk menyiapkan workspace — semuanya lewat dashboard, termasuk untuk Flustra sendiri.

## Aturan yang berlaku di seluruh kode

**Semua teks Bahasa Indonesia** — komentar, pesan galat, nama tes, dokumentasi, UI. Nama kelas/method/kolom tetap Inggris.

**Selalu berangkat dari workspace.** `EnsureWorkspaceSelected::from($request)->sessions()->findOrFail($id)`, bukan `WaSession::find($id)`. Tidak ada global scope yang menangkap kelalaian ini.

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

**Hostname internal Coolify adalah UUID resource, bukan nama tampilannya.** `DB_HOST=flustra-mysql` menghasilkan `cURL error 6: Could not resolve host` — nama itu cuma label di antarmuka. Pakai UUID dari URL resource (`.../application/<uuid>`); Coolify sudah mengisinya begitu untuk `DB_HOST`. Sejak penyatuan container ini tidak lagi berlaku untuk `ENGINE_URL`/`LARAVEL_URL` — keduanya `127.0.0.1` sekarang, dan satu kelas kegagalan ikut hilang bersamanya.

**Env engine dan env Laravel berbagi satu ruang nama.** Tiga nama bertabrakan dan harus berawalan: `ENGINE_PORT`, `ENGINE_HOST`, `ENGINE_LOG_LEVEL`. `PORT` dan `HOST` polos dipakai Coolify untuk proses web; `LOG_LEVEL` polos dipakai Laravel dengan skala nilai berbeda — `error` di produksi, yang di engine berarti kehilangan baris `Backup sesi terkirim`, satu-satunya bukti cadangan sesi tersimpan dan patokan uji regresi utamanya. Nama lama tetap diterima sebagai cadangan supaya `npm --prefix engine run dev` masih jalan di lokal. `engine/tests/config.test.js` menjaganya.

**Chromium hilang dari image kalau `PUPPETEER_CACHE_DIR` tidak diset.** Puppeteer mengunduhnya saat `npm ci` ke `$HOME/.cache/puppeteer` = `/root/.cache/puppeteer`. Selama engine punya resource sendiri, folder itu ikut ke image akhir. Sejak base directory-nya `/`, yang dibawa Nixpacks ke image akhir hanya `/app` — unduhannya hilang. Kegagalannya terlambat dan di tempat yang salah: build hijau, container menyala normal, dan `Could not find Chrome (ver. ...)` baru muncul di **kartu sesi milik pelanggan**, dalam bahasa Inggris, saat sesi pertama dijalankan. Sekarang variabelnya dipasang dua kali — di Install Command (tempat mengunduh) dan di environment (tempat mencari) — dan `periksa_chromium()` di `start.sh` memindahkan kegagalannya ke saat boot, dengan perbaikannya disebutkan langsung.

**`PHP_CLI_SERVER_WORKERS` diabaikan tanpa `--no-reload`.** `ServeCommand::initialize()` mengembalikan `false` kalau flag itu tidak ada, dan peringatannya cuma muncul sekali di awal log — sesudah itu tidak ada gejala apa pun. Aplikasinya berfungsi normal, hanya melayani satu permintaan pada satu waktu, dan callback engine (`qr`, `ready`, pesan masuk, `message_ack`) antre di belakang halaman dashboard yang sedang dibuka orang. Cara memastikannya: `ps -eo pid,ppid,args | grep '[p]hp -S'` harus menghasilkan lebih dari satu baris. `start.sh` sudah memakai `--no-reload`; yang dilepas dengannya cuma restart otomatis saat `.env` berubah, dan di produksi berkas itu memang tidak pernah berubah saat container hidup.

**Nixpacks memakai Node 18 kalau `NIXPACKS_NODE_VERSION` tidak diset.** Engine menuntut Node 20 lewat `engines` di `engine/package.json`, dan Node 18 sudah EOL. Env Laravel sudah memuatnya sejak awal; env engine dulu tidak.

**Engine mati bukan kegagalan sesi.** `SyncSessionStatusJob` berjalan tiap menit dan memanggil `connect()` untuk sesi yang terbaca `disconnected`. Selama engine di-deploy ulang, panggilan itu gagal — dan dulu `connect()` menandai sesi `failed` untuk kegagalan apa pun. `BootstrapController` mengecualikan `failed` dari pemulihan otomatis, jadi nomor yang kredensialnya masih utuh di volume tampak putus dan seolah harus di-scan ulang **setiap deploy** — persis hal yang project ini dibuat untuk menghilangkan. Sekarang `ConnectionException` ditangani terpisah (tetap `disconnected`), dan bootstrap ikut memulihkan sesi `failed` yang `connected_at`-nya terisi. `SessionRecoveryTest` menjaga keduanya.

**`client.sendMessage()` kadang mengembalikan `undefined` walau pesannya sampai.** whatsapp-web.js tidak selalu berhasil menyusun objek `Message` balasannya. `sent.id?._serialized` melempar TypeError, engine menjawab 503, Laravel menganggapnya gagal dan mengulang — penerima menerima pesan yang sama tiga sampai empat kali sementara riwayat mencatatnya `failed`. Gejalanya: kolom `error` berbunyi `Cannot read properties of undefined (reading 'id')` pada pesan yang sebenarnya terkirim. **ID pesan tidak boleh menentukan berhasil-tidaknya pengiriman** — ia cuma pelengkap untuk melacak status.

**Habis waktu bukan berarti tidak terkirim.** Engine menahan tiap pesan 3–8 detik (jeda anti-ban) dan mengantre per sesi, jadi pesan keempat dalam satu giliran bisa menunggu lebih dari 30 detik. Dengan `ENGINE_TIMEOUT=15`, Laravel menyerah lebih awal lalu mengulang job-nya — penerima menerima pesan yang sama tiga sampai empat kali, persis sejumlah `$tries`. Dua penjagaan sekarang: engine menyaring kiriman ganda berdasarkan `message_id` (`SessionManager#kiriman`), dan pengiriman memakai `ENGINE_SEND_TIMEOUT` (60 detik) yang terpisah dari `ENGINE_TIMEOUT` (15 detik). **Jangan menyatukan keduanya lagi** — batas panjang untuk perintah sesi membuat tombol Hapus dan Putus tautan menggantung semenit di muka pengguna, batas pendek untuk pengiriman memunculkan kiriman ganda. Dan jangan menambah pemanggil engine yang tidak mengirim `message_id`.

**Flustra bukan pengguna istimewa.** Tidak ada tenant internal yang dibuat lewat CLI, tidak ada sesi bertipe `platform`. Aplikasi Flustra mendaftar, membuat workspace, dan menempel API key ke `.env` seperti pelanggan mana pun. Jalur istimewa yang dulu ada menghasilkan workspace tanpa anggota — mustahil dibuka lewat dashboard oleh siapa pun — dan sesi yang tidak pernah terpilih otomatis saat `session_id` dikosongkan. Keduanya tidak terlihat di antarmuka mana pun. Kalau ada kebutuhan baru yang "cuma bisa lewat CLI", itu tanda antarmukanya yang kurang, bukan alasan menambah command.

**Satu produk, satu cara mengirim.** Driver `fonnte` (layanan gateway berbayar milik pihak lain) dan slot `cloud_api` yang tidak pernah diimplementasi sudah dihapus sampai ke kelasnya. Keduanya dulu muncul sebagai pilihan di form pembuatan sesi — halaman pertama yang dilihat pelanggan baru menawarkan produk orang lain dan sebuah janji yang belum ada. `wa_sessions.driver` sekarang mencatat, bukan menawarkan pilihan; API menolak nilai selain `wwebjs`. Jangan menambahkan penyalur pihak ketiga ke sini lagi; kalau sebuah kebutuhan menuntut jalur resmi Meta, arahkan keluar — alasannya di [docs/PERBANDINGAN_PROVIDER.md](docs/PERBANDINGAN_PROVIDER.md).

**OTP dikirim dari nomor workspace pemanggil.** Dulu seluruh OTP keluar dari satu sesi global (`OTP_SESSION_ID`) dengan teks yang menyebut "Flustra" — padahal endpoint-nya terbuka untuk API key mana pun ber-scope `otp`. Artinya pelanggan mengirim kode dari nomor kami, atas nama kami, memotong kuota kami, dan laporan spam atasnya jatuh ke nomor kami. Sekarang pengirimnya dipilih dengan aturan yang sama persis dengan pesan biasa, dan teksnya memakai nama workspace.

**API key bisa dibuka lagi, dan itu disengaja.** `key_hash` tetap satu-satunya jalan verifikasi permintaan; `key_ciphertext` (cast `encrypted`) adalah salinan terpisah untuk ditampilkan ulang di dashboard kepada owner dan admin, dan dibuang saat kunci dicabut. Pertimbangannya: "hanya tampil sekali" tidak membuat orang lebih hati-hati, ia membuat mereka menyalin kunci ke catatan pribadi dan grup chat — tempat yang jauh lebih mudah bocor daripada tabel ini. Jangan menyatukan kedua kolom itu.

**Istilah produk hanya satu: workspace.** Sampai ke nama tabel dan kolom (`workspaces`, `workspace_id`). `tenant` hanya boleh muncul sebagai istilah arsitektur (multi-tenant) di dokumentasi internal.

**Hapus lunak bertabrakan dengan indeks unik.** `wa_sessions` unik pada `(workspace_id, name)` tanpa memandang `deleted_at`, jadi membuat ulang sesi dengan nama yang sama gagal dengan galat 1062. `SessionService::create()` membuang permanen baris tertrash bernama sama lebih dulu — permanen, bukan dipulihkan, supaya baris baru dapat ULID baru dan tidak mewarisi folder kredensial lama di engine.

**Persistent volume TIDAK menyelamatkan sesi — store Laravel yang menyelamatkan.** `RemoteAuth.extractRemoteSession()` selalu menghapus isi `userDataDir` di volume setiap kali sesi dijalankan, lalu memulihkannya dari store. Kalau store bilang tidak ada backup, yang tersisa folder kosong dan WhatsApp meminta scan QR lagi. Volume cuma menampung file kerja Chromium di antara dua restart. Konsekuensinya: `LaravelStore.sessionExists()` **tidak boleh** menjawab `false` saat Laravel sekadar tidak terjangkau — jawaban itu memicu penghapusan kredensial yang masih sempurna. Ia harus melempar galat, supaya `start()` batal sebelum penghapusan terjadi.

**Zip RemoteAuth ada di `dataPath`, bukan di working directory.** `compressSession()` menulis ke `path.join(this.dataPath, '<session>.zip')`. `LaravelStore.save()` dulu membacanya sebagai path relatif, jadi setiap upload berakhir ENOENT dan **tidak pernah ada satu pun backup tersimpan** — setiap redeploy menghapus kredensial lalu meminta scan QR ulang, persis masalah yang seluruh mekanisme ini dibuat untuk menghilangkan. Gejalanya diam: log cuma memuat satu baris warning, dan halaman sesi tampak normal sampai redeploy berikutnya. `engine/tests/laravel-store.test.js` menjaganya (`npm test` di dalam `engine/`).

**`client.destroy()` tidak menyimpan apa pun pada RemoteAuth** — ia hanya menghentikan timer backup. Penyimpanan saat SIGTERM harus dipanggil eksplisit lewat `manager.persist()` sebelum `stop()`.

**Dashboard tidak boleh mengandalkan callback saja untuk status sesi.** Satu event `ready` yang hilang membuat modal QR menampilkan "Menyiapkan sesi" tanpa akhir. Endpoint `sessions.status` menanyakan engine langsung selama sesi belum `connected`/`failed`, jadi keadaan sebenarnya muncul dalam hitungan detik tanpa menunggu `SyncSessionStatusJob` yang berjalan tiap menit — dan tanpa bergantung pada Scheduled Task Coolify yang bisa saja belum dipasang.

**`QR_TTL_SECONDS` jangan di bawah 60.** whatsapp-web.js menerbitkan QR baru dengan jeda tidak tetap sampai ~60 detik; masa berlaku lebih pendek membuat modal QR berkedip kosong.

**Laravel Pail tidak disertakan di `npm run all`** — butuh `pcntl` yang tidak ada di PHP Windows, dan `--kill-others` membuat matinya Pail menjatuhkan proses lain.

**Tidak ada template env di repo.** Berkas `.env.*.example` dihapus atas permintaan Ryan (13 Agu 2026) supaya isian env tinggal disalin dari berkas `.env.<tahap>` di mesinnya ke Coolify tanpa komentar yang mengganggu; `EnvTemplateTest` ikut dihapus. Konsekuensinya: menambah variabel env **tidak** akan ketahuan terlewat oleh tes apa pun. Daftarkan variabel baru di `docs/ENVIRONMENT.md` dan isikan ke resource Coolify pada perubahan yang sama — sejak 17 Agu 2026 cuma satu resource per tahap, jadi satu tempat saja.

## Alur git

`dev` → `staging` → `main`. Coolify men-deploy otomatis setiap push. Jangan push langsung ke `main`.

**Jangan pernah commit** `.env`, `engine/.env`, isi `.wwebjs_auth/`, atau zip sesi — kredensial WhatsApp yang bocor sama dengan memberi akses penuh ke nomor tersebut.

## Keadaan sekarang

Sudah ter-deploy di Coolify, **belum diuji end-to-end di produksi**. Uji regresi wajib ada di [docs/SETUP_VPS_COOLIFY.md](docs/SETUP_VPS_COOLIFY.md) Bagian 10 — yang terpenting: redeploy lalu pastikan sesi tersambung sendiri tanpa scan QR.

**Penyatuan tiga resource jadi satu (17 Agu 2026).** Sampai tanggal itu tiap tahap punya tiga resource Coolify (`flustra-wa`, `-engine`, `-worker`) yang masing-masing membangun ulang aplikasinya sendiri tiap deploy — tiga kali `composer install` + `npm ci` di VPS 2 vCPU yang menampung tujuh aplikasi Flustra lain. Sekarang satu container menjalankan keempat prosesnya lewat `start.sh`, mengikuti pola `flustra-erp` dan `flustra-clientportal`. Perubahannya **belum dijalankan di server**; langkah pindahnya ada di [docs/DEPLOYMENT.md §5](docs/DEPLOYMENT.md).

Alasan lama untuk memisahkan engine masih tertulis di [docs/ARSITEKTUR.md §2](docs/ARSITEKTUR.md) beserta apa yang terjadi pada masing-masing — baca itu dulu sebelum mengusulkan memisahkannya lagi. Yang tidak terjawab dan harus dijaga dengan angka, bukan arsitektur: Chromium berebut RAM dengan PHP, jadi `WA_MAX_SESSIONS` diturunkan dari 10 ke 3.

Integrasi ke `flustra-erp`, `flustra-web`, `flustra-pricing`, `flustra-helpdesk` sudah ditulis (`app/Services/WhatsAppGateway.php` disalin identik ke keempatnya) tapi belum dijalankan dengan gateway produksi. Lihat [docs/INTEGRASI_APP.md](docs/INTEGRASI_APP.md).

Yang sengaja belum dibuat: billing/langganan, realtime dashboard, beberapa instance engine. Rinciannya di [docs/ARSITEKTUR.md](docs/ARSITEKTUR.md#11-yang-sengaja-belum-dibuat).
