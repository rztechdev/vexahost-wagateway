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

**Paket hidup di `config/plans.php`, bukan di tabel.** Essentials/Prime/Elite (149rb/249rb/449rb per bulan, tahunan = ×10) beserta seluruh batasnya dibaca dari satu berkas config oleh halaman depan, halaman langganan, penegakan batas, dan penyusunan tagihan sekaligus. Jangan pernah menulis ulang angka atau daftar fitur sebagai teks di Blade — halaman harga yang menjanjikan angka berbeda dari yang ditegakkan adalah janji yang kita langgar di hadapan orang yang baru saja membayar. Mengubah angka di config **mengubah batas seluruh pelanggan paket itu seketika, termasuk yang sudah membayar**; kalau perlu mengunci harga lama untuk pelanggan lama, beri slug baru (`prime-2027`), jangan ubah paket yang sedang dipakai orang.

**Batas ditegakkan dari kolom `workspaces`, bukan dibaca dari paket tiap kali.** `markPaid()` menyalin `max_sessions`, `monthly_message_quota`, dan `api_rate_limit_per_minute` dari paket ke kolom. Alasannya dua: dua penegakan terpanas (kuota pesan dan jumlah sesi) berjalan di jalur pengiriman dan tidak boleh memuat config per pesan, dan kolom itu sekaligus memberi admin ruang menaikkan satu workspace tanpa memindahkan seluruh pelanggan paket itu. Yang **tidak** disalin: batas API key dan anggota tim — keduanya dibaca langsung dari paket, karena kebutuhan kelonggaran manual di sana tidak pernah muncul.

**Status langganan hanya berpindah lewat `SubscriptionService`.** Tiap perpindahan menyentuh `subscriptions.status` *dan* `workspaces.status` sekaligus. Keduanya harus selalu sepakat: langganan lewat jatuh tempo dengan workspace masih `active` berarti pelanggan mengirim tanpa membayar; sebaliknya berarti layanan mati padahal tagihannya lunas. Tidak ada controller, job, atau perintah yang boleh menulis salah satunya sendiri.

**Lewat jatuh tempo memutus pengiriman, bukan nomor.** `past_due` menandai workspace `suspended` — dengan itu `MessageDispatcher::guardWorkspace()` dan `AuthenticateApiKey` yang sudah ada ikut berlaku tanpa satu pun pemeriksaan tambahan. Sesi baru dilepas setelah `BILLING_GRACE_DAYS` (30 hari), dan memakai `disconnect()` **bukan** `logout()`: logout membuang kredensial di kedua sisi, jadi pelanggan yang kembali membayar diminta scan QR ulang — alasan paling sering orang tidak jadi kembali.

**Masa gratis hanya untuk akun pra-SaaS.** Workspace baru lahir berstatus `unpaid` — belum pernah berlangganan, belum boleh mengirim apa pun — dan `ensureFor()` ikut menandai `workspaces.status = 'suspended'` supaya penegakan yang membaca kolom itu (`MessageDispatcher`, `AuthenticateApiKey`) ikut berlaku. Grant gratis sampai akhir bulan diberikan **sekali** oleh migrasi `give_existing_workspaces_a_trial`, bukan oleh keadaan bawaan. Ini pernah salah: bawaannya `trialing`, sehingga setiap pendaftar baru ikut memakai produk berbayar cuma-cuma sampai akhir bulan tanpa pernah diputuskan siapa pun. Kalau suatu saat masa coba untuk pendaftar baru diinginkan, tempatnya di `ensureFor()` dengan tanggal eksplisit — jangan mengembalikan `trialing` sebagai bawaan.

`unpaid` sengaja dibedakan dari `past_due`: yang satu belum pernah mulai, yang lain pernah lalu berhenti, dan keduanya butuh kalimat berbeda di depan pengguna. "Pengiriman pesan dihentikan" tidak masuk akal bagi orang yang belum pernah mengirim apa pun.

**Panel admin: daftar untuk mencari, halaman untuk bertindak.** Semua daftar berbentuk tabel; pengelolaan satu workspace pindah ke `/admin/workspaces/{id}`. Sebelumnya tiap baris bisa mengembang jadi panel berisi empat form — daftar yang berubah bentuk saat disentuh sulit dipindai, dan form yang bersembunyi di dalam baris membuat orang tidak yakin sedang mengubah workspace yang mana. Pengecualiannya halaman Tagihan: tindakannya berjajar di bawah tabel karena memeriksa bukti adalah pekerjaan berulang belasan kali berturut-turut.

Menu lain yang harus tetap ada: **Lalu Lintas Pesan** (satu-satunya tempat yang menjawab "masalahnya di satu pelanggan atau di gateway kami"), **Catatan Audit** (`audit_logs` sudah lama ditulis tapi dulu hanya bisa dibaca lewat tinker — halaman ini lahir dari kejadian nyata: tagihan berbukti dibatalkan 24 detik setelah buktinya diunggah), dan **Sistem** (satu tempat untuk semua yang bisa berhenti bekerja tanpa gejala: engine mati sementara sesi tampak hijau, notifikasi diam karena env kosong, QRIS salah salin). Label dan warna status hidup di `App\Support\StatusBadge`, bukan ditulis ulang tiap Blade.

**Dashboard memakai sidebar tetap (5 Sep 2026).** Sebelumnya sidebar-nya menyatu dengan latar halaman (`lg:bg-transparent lg:border-0`) dan tidak terbaca sebagai navigasi tetap — orang membacanya sebagai kolom teks pertama, lalu mencari menu di tempat lain. Sekarang: `<aside>` melekat di tepi kiri dengan `bg-sidebar` dan garisnya sendiri, konten diberi ruang lewat `lg:pl-64`, judul halaman pindah ke bilah atas yang lengket, dan pemilih workspace turun ke dalam sidebar (seluruh isi menu di bawahnya memang milik workspace itu). Menunya dikelompokkan; `cocok` pada tiap butir menjaga halaman anak — `messages.show`, `billing.*` — tetap menyalakan menu induknya.

Pembagian tempatnya disengaja dan jangan ditukar: **sidebar** memuat apa yang bisa dikerjakan di dalam workspace, **kanan atas** memuat akun. Tombol profil di bilah atas hanya avatar; nama dan email muncul di dalam dropdown-nya, karena di bilah atas keduanya cuma mengulang sesuatu yang sudah pasti diketahui orang yang sedang login. Kaki sidebar menyisakan satu tombol Keluar saja — menu akun di dua tempat berarti dua daftar yang harus dijaga tetap sama.

Menu **selalu** dirender, termasuk sebelum pengguna punya workspace; saat itu tautannya diarahkan ke onboarding karena middleware memantulkan semua halaman di dalamnya. Menyembunyikannya membuat pendaftar baru tidak pernah tahu apa yang sebenarnya mereka dapat.

Di dalam sidebar tidak ada garis pemisah horizontal — baik di bawah logo maupun di bawah pemilih workspace — supaya ia terbaca sebagai satu bidang utuh; yang tetap bergaris hanya bilah atas konten dan kaki sidebar. Lebarnya `w-72`, dan konten diberi ruang lewat `lg:pl-72`; kalau salah satunya diubah, yang lain harus ikut. Geseran buka-tutup di layar kecil diatur `.panel-sidebar` di `app.css`, bukan `-translate-x-full`. Warna sidebar di mode terang bersemu hijau muda (`--sidebar: #eaf2ea`), bukan krem preset aslinya; mode gelap tidak diubah.

**Halaman langganan dipecah jadi empat alamat.** `/billing` (ringkasan), `/billing/paket`, `/billing/riwayat`, `/billing/invoices/{id}`. Yang penting dari pemisahan ini bukan kerapian melainkan bahwa tiap langkah bisa ditautkan langsung — spanduk "langganan habis" menuju **paket**, bukan ringkasan, karena yang dibutuhkan orang yang menekannya adalah memilih dan membayar. Navigasi antar keempatnya ada di satu partial (`_nav.blade.php`) yang dipakai bersama; tab yang hilang di salah satu halaman berarti jalan buntu bagi yang membukanya dari sana.

**Halaman bayar berbentuk checkout dua kolom.** Kiri tiga langkah bernomor (data penagihan → bayar → kirim bukti), kanan ringkasan pesanan yang menempel saat digulir. Pemilihan metode ada di kolom **kanan**, berdampingan dengan totalnya: memilih cara membayar adalah keputusan tentang uang, dan angkanya harus terlihat saat keputusan itu diambil. Metode yang tidak dikonfigurasi **tidak pernah** muncul sebagai pilihan — menawarkan cara bayar yang belum ada di baliknya persis kesalahan driver `fonnte` dulu; `BillingTest` menjaganya dari dua arah (payload QRIS rusak tidak ditawarkan, rekening terisi ditawarkan).

Data penagihan (`workspaces.billing_name/billing_email/billing_phone`) menempel pada workspace, bukan pada tiap tagihan — yang membayar tidak berganti tiap bulan. Konsekuensinya: mengganti nama penagihan mengubah tampilan seluruh tagihan lama juga. Kalau suatu saat faktur harus membeku pada keadaan saat terbit, salin nilainya ke `invoices` pada saat itu.

**Tidak ada pemulihan kata sandi mandiri, dan itu keputusan sadar (6 Sep 2026).** Selama email belum benar-benar terkirim, tautan reset akan berakhir di berkas log sementara antarmuka tetap bilang "sudah kami kirim" — kegagalan yang baru ketahuan saat ada pelanggan terkunci. Jalur resminya: pelanggan menghubungi admin, admin mengatur ulang dari `/admin/pengguna`. Halaman login menyembunyikan tautan "Lupa kata sandi?" sendiri lewat `Route::has('password.request')`, jadi tidak ada tombol yang menuju jalan buntu. Kalau nanti SMTP sudah jalan, yang perlu dibuat kembali: `PasswordResetController` + dua view + empat rute bernama `password.*`.

**Pemberitahuan WhatsApp dikirim lewat gateway ini sendiri.** Semua teksnya di `BillingMessages`, pengirimannya lewat `WhatsAppNotifier` — jangan menulis pesan langsung di controller: pelanggan menerima semuanya dari nomor yang sama, dan nada yang berbeda-beda terbaca sebagai ketidakrapian atau penipuan. Tiga aturan yang berlaku di sana: tidak pernah melempar galat (notifikasi itu pelengkap, tagihan tetap harus lunas meski pesannya gagal), selalu dari sesi workspace Flustra (`BILLING_NOTIFY_WORKSPACE_ID`) bukan sesi pelanggan, dan satu peristiwa satu pesan lewat penanda cache.

Yang perlu diingat saat menambah pemanggil baru: `WhatsAppNotifier` memakai `MessageDispatcher`, jadi apa pun yang dipakai `MessageDispatcher` **tidak boleh** menyuntikkan notifier lewat constructor — ambil dari container di dalam method (lihat `warnIfQuotaLow`), kalau tidak lingkarannya menutup dan seluruh pengiriman pesan mati. Dan panggil di luar `DB::transaction`: di dalamnya, pesan yang gagal akan menggulung balik penandaan lunas.

Kalau `BILLING_NOTIFY_WORKSPACE_ID` kosong atau nomor Flustra terputus, **seluruh** pemberitahuan diam tanpa satu pun gejala. Halaman Ringkasan panel admin menampilkan peringatan merah selama keadaan itu berlangsung — itu satu-satunya cara menyadarinya.

**Bukti yang sudah masuk mengunci tagihannya.** Pelanggan tidak bisa membatalkan tagihan yang buktinya sudah dikirim, dan saringan bawaan `/admin/tagihan` adalah **perlu-diperiksa** — yang menentukan sebuah tagihan perlu dilihat manusia bukan statusnya, melainkan adanya bukti yang belum dijawab. Keduanya menutup kegagalan yang sudah benar-benar terjadi (6 Sep 2026): bukti terunggah, tidak ada tanda yang cukup jelas bahwa ia diterima, pelanggan mengira gagal lalu membatalkan tagihannya 24 detik kemudian — dan tagihan `canceled` waktu itu lenyap dari layar admin. Uang masuk, layanan mati, tidak ada satu pun tempat yang menunjukkannya. Tagihan yang telanjur ditutup **tetap** bisa ditandai lunas dari panel; menolaknya berarti memaksa pelanggan membayar dua kali.

**Setelah bukti terkirim, pelanggan mendarat di `billing.verifying`.** Halaman tersendiri, bukan kembali ke form dengan spanduk hijau — spanduk tipis terbukti tidak cukup untuk keputusan yang menyangkut uang.

**Dialog memakai SweetAlert2, bukan `confirm()` bawaan.** Pasang lewat atribut, bukan `onsubmit`: `data-konfirmasi="pesan"` pada `<form>` untuk konfirmasi, `data-validasi` untuk menahan pengiriman saat ada isian wajib yang kosong lalu menyebutkan yang mana. Pesan dari server dititipkan lewat flash `swal` dan dirender `partials/pesan-server` sebagai `<script type="application/json">` — bukan sebagai kode JS yang di-echo, supaya nama workspace milik orang lain tidak pernah bisa merusak atau menyisipkan sesuatu ke halaman. Layout baru harus ikut meng-include partial itu, kalau tidak pesannya hilang diam-diam.

**Pembayaran masih dicocokkan manusia.** Tidak ada notifikasi otomatis dari mana pun. Yang menghubungkan uang masuk dengan tagihan cuma nominal yang dibuat unik lewat kode tiga digit dan bukti transfer yang diunggah pelanggan; admin yang memutuskan di `/admin/tagihan`. Karena itu `QrisManual` sengaja **tidak** diberi antarmuka bersama meski iPaymu direncanakan — antarmuka dengan satu implementasi adalah slot kosong yang menjanjikan sesuatu yang belum ada, persis pola `cloud_api` yang akhirnya dihapus. Saat iPaymu jalan, saat itulah antarmukanya dibuat dengan dua implementasi nyata. Yang sudah disiapkan dari sekarang hanya bekasnya di data: `invoices.channel` dan `invoices.external_id`.

**`QRIS_PAYLOAD` di env wajib dikutip.** Payload QRIS memuat nama kota merchant (tag 60) yang hampir selalu mengandung spasi — punya Flustra berbunyi `6015Kota Tangerang ` lengkap dengan spasi di ujungnya. Tanpa kutip, dotenv menolak **seluruh** berkas dan aplikasi gagal boot dengan `Failed to parse dotenv file`, bukan sekadar kehilangan QRIS-nya. Dan spasi itu ikut dihitung panjang tag: dikutip tapi terpangkas berarti CRC tidak cocok lagi, `Qris::valid()` menolaknya, dan halaman bayar diam-diam turun ke transfer bank untuk payload yang sebenarnya benar.

**QRIS rusak tidak boleh tergambar.** Payload yang salah ketik saat disalin ke env menghasilkan kode QR yang tampak wajar tapi ditolak setiap aplikasi bank — dan kegagalannya baru terlihat saat pelanggan sudah berdiri di depan layar. `Qris::valid()` memeriksa CRC sebelum halaman merender apa pun; yang gagal jatuh ke instruksi transfer bank. Batasnya: CRC dihitung atas string mentah tanpa memedulikan struktur TLV, jadi panjang tag yang salah tulis tetap lolos. `tests/Unit/QrisTest.php` menjaga keduanya.

**Warna diambil dari satu tempat.** Palet tema "Nature" (hijau, mengikuti logo) hidup sebagai variabel CSS di `resources/css/app.css`; `resources/css/auth.css` punya salinannya sendiri karena halaman auth memakai latar WebGL dan efek kaca yang butuh warna solid. Menambah warna baru berarti menambah token, bukan menulis `bg-green-600` di Blade. Yang sengaja dikecualikan: status sesi (amber/biru/merah — lima keadaan tidak mungkin dibedakan dengan lima rona hijau) dan blok kode (selalu gelap di kedua mode).

**Font seluruh produk sans-serif: Plus Jakarta Sans (5 Sep 2026).** Anthropic Serif dilepas atas permintaan Ryan bersamaan dengan perombakan halaman depan; keempat `@font-face`-nya dibuang dari `app.css` dan berkas `.otf`-nya tidak lagi dimuat siapa pun. Plus Jakarta Sans dipilih karena halaman auth sudah memakainya sejak awal — font lain di sini akan membuat satu produk terasa seperti dua aplikasi saat pengguna berpindah dari layar masuk ke dashboard. Dimuat dari Google Fonts di **empat** layout (`app`, `admin`, `docs`, `welcome`); menambah layout baru berarti menambah tautannya juga, kalau tidak halaman itu diam-diam jatuh ke font sistem. Tumpukan cadangannya sengaja berujung sans-serif, bukan serif.

**Halaman depan memakai ritme, bukan kartu.** Sampai 5 Sep 2026 tiga bagian berturut-turut memakai kotak `border` berukuran sama — tiga belas kotak identik beruntun, dan tidak ada satu pun bagian yang terasa lebih penting dari yang lain. Sekarang kartu berbingkai hanya enam di seluruh halaman (tiga kartu harga, dua panel kode, satu panel ilustrasi), dan bagian dibedakan dengan berselang antara latar polos dan `bg-muted/30`. Kalau menambah bagian baru, ikuti selang-selingnya — jangan menambah kotak.

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

**Penagihan menyala (5 Sep 2026).** Paket, langganan, tagihan, QRIS dinamis, panel `/admin`, dan siklus perpanjangan harian (`BillingCycleJob`, 08:00) sudah ada dan berjalan di lokal. Seluruh workspace yang sudah ada dipindahkan ke Essentials berstatus `trialing` sampai akhir bulan berjalan lewat migrasi, **tanpa** menurunkan batas mereka — batas paket baru berlaku saat langganan pertama dibayar. Belum dijalankan di server, dan belum ada satu rupiah pun yang benar-benar masuk lewatnya.

Yang belum: gateway pembayaran otomatis (iPaymu, menunggu verifikasi akun), realtime dashboard, beberapa instance engine. Rinciannya di [docs/ARSITEKTUR.md](docs/ARSITEKTUR.md#11-yang-sengaja-belum-dibuat).

**Kapasitas adalah pembatas jualan, bukan kode.** `engine/.env.production` masih memuat `WA_MAX_SESSIONS=10` sementara [docs/DEPLOYMENT.md §2](docs/DEPLOYMENT.md) menyebut 3 untuk VPS 8 GB — sepuluh sesi berarti sampai 5 GB Chromium di kotak yang menampung tujuh aplikasi Flustra lain, dan yang dibunuh OOM killer belum tentu Chromium. Selama angka itu belum dibetulkan dan diukur ulang, platform ini hanya sanggup melayani tiga nomor aktif untuk **semua** pelanggan sekaligus. Panel `/admin` menampilkan angkanya paling atas supaya batas itu tidak lagi baru ketahuan dari keluhan.
