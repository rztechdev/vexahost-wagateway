# Cutover — deploy perbaikan kebocoran memori

Ditulis 8 September 2026, untuk deploy yang membawa seluruh perbaikan setelah
dua insiden: 7 September (server jatuh, memori habis, swap 100%, sshd tidak
sanggup mengirim banner) dan 8 September (tabrakan `userDataDir` — empat
Chromium berebut satu profil, pelanggan gagal scan QR dengan `Execution context
was destroyed`).

Deploy ini **besar dan sekali jalan** setelah berminggu-minggu tanpa deploy,
membawa migrasi database, perubahan autentikasi, dan perubahan perilaku engine.
Tidak ada satu pun perubahan di bawah yang sudah pernah berjalan di produksi.
Daftar di halaman ini bukan pelengkap — ia bagian dari pekerjaannya.

---

## 0. Migrasi: urutan dan reversibilitas

`start.sh` menjalankan `php artisan migrate --force` sebelum menyalakan apa pun,
dan **berhenti total kalau gagal**. Kelimanya sudah diuji `migrate:rollback` satu
per satu pada database bersih — kolom "Reversibel" di bawah hasil percobaan,
bukan perkiraan.

| # | Migrasi | Yang dilakukan | Reversibel |
|---|---|---|---|
| 1 | `010000_add_connect_failures_to_wa_sessions` | 1 kolom `tinyint default 0` | **Ya, bersih** |
| 2 | `010050_add_key_hash_fast_to_api_keys` | 1 kolom `varchar(64)` nullable | **Ya, bersih** |
| 3 | `010100_rehash_api_keys_to_sha256` | Mengisi kolom #2 dari `key_ciphertext` | **Ya, bersih** — `down()` mengosongkannya |
| 4 | `020000_add_retention_indexes` | 4 indeks | **Ya, bersih** |
| 5 | `030000_create_waitlist_entries` | 1 tabel baru | **Ya, bersih** |

**Tidak satu pun menghapus atau menimpa data yang sudah ada.** Itu bukan
kebetulan; migrasi #3 sengaja dirancang begitu.

### Kenapa #3 tidak menyentuh `key_hash`

Rancangan yang lebih jelas — menimpa `key_hash` dengan SHA-256 dan memindahkan
bcrypt ke kolom cadangan — **tidak bisa di-rollback dengan mengembalikan kode
saja.** Kode lama membaca `key_hash` lewat `Hash::check()`, dan
`BcryptHasher::check()` **MELEMPAR** `RuntimeException` ("This password does not
use the Bcrypt algorithm") begitu menemui hash yang bukan bcrypt — bukan sekadar
menolaknya. Rollback sesudah itu berarti seluruh API key pelanggan mati serentak.

Karena itu `key_hash` dibiarkan berisi bcrypt dan SHA-256 masuk kolom baru
`key_hash_fast`. Kode baru memakai yang cepat; kode lama membaca kolom yang sama
seperti biasa dan tidak pernah tahu kolom baru itu ada.

Biayanya satu bcrypt saat kunci **dibuat** (bukan saat dipakai) — beberapa kali
seumur hidup workspace, melawan ribuan verifikasi per hari.

Kemampuan rollback itu dilepas nanti oleh `flustra:hash-api-bersihkan`. **Jangan
menjalankannya di hari cutover.** Syarat amannya di §6.

---

## 1. Sebelum menekan Deploy

### 1a. Yang TIDAK ikut turun lewat repo

Berkas `.env.*` **tidak ada di git** (`.gitignore:27`). Tiga nilai di bawah harus
diisi tangan di **Environment Variables** resource Coolify, dan tidak ada satu
pun uji yang bisa menangkap kalau terlewat.

| Variabel | Isi | Kalau terlewat |
|---|---|---|
| `LOG_STACK` | `daily` | `storage/logs/laravel.log` tumbuh tanpa batas, selamanya |
| `LOG_DAILY_DAYS` | `14` | Rotasi jalan tapi tidak pernah membuang yang lama |
| `RETENTION_WEBHOOK_DAYS` | `7` | Tetap 30 hari. Tidak merusak, cuma tabel lebih besar |

`DB_QUEUE_RETRY_AFTER` **tidak perlu diisi** — ia tidak ada di environment
Coolify, jadi nilai barunya (1200) datang dari `config/queue.php`.

### 1b. Resource Limits Coolify — yang paling penting di halaman ini

Batas memori yang sekarang aktif dipasang lewat `docker update`, **bukan lewat
Coolify**. Ia **lenyap saat container dibuat ulang** — yaitu tepat pada deploy
ini, momen paling berisiko dari seluruh rangkaian.

| Kolom | Nilai |
|---|---|
| Memory Limit | `3584m` |
| Memory Swap | `4096m` |

**Kenapa 3584/4096 dan bukan lebih besar.** `memory.events` pada 8 September
berbunyi `max=1024 oom_kill=0`: container menyentuh plafonnya seribu kali dan
**tidak sekali pun dibunuh**. Itu bukan kabar baik. Kernel berhasil mereklaim
setiap kali — dengan swap cgroup juga penuh (505 dari 512 MB) — dan reklamasi
terus-menerus itulah yang membuat semuanya lambat sampai sshd tidak sanggup
mengirim banner.

Pelajarannya berlawanan dengan dugaan: **container yang dibunuh lebih murah
daripada container yang megap-megap.** Yang dibunuh menyala lagi dalam hitungan
detik dan sesinya pulih dari cadangan; yang megap-megap menyeret seluruh host.
Karena itu swap sengaja **tidak** dinaikkan — 512 MB cukup meredam lonjakan
sesaat, dan terlalu kecil untuk menyembunyikan kebocoran berjam-jam.

Perkiraan setelah perbaikan tabrakan profil (satu Chromium per profil):

| | MB |
|---|---|
| 2 sesi menganggur (±300 tiap satu) | 600 |
| 1 sesi menarik riwayat awal — **puncak**, bukan tunak | 800 |
| Node + PHP serve ×4 + worker + penjadwal | ±750 |
| **Puncak wajar** | **±2.150** |

Menyisakan ±1,4 GB ruang kepala. Kalau `memory.events max` naik lagi setelah
cutover, yang benar **bukan** menaikkan plafon melainkan menurunkan
`WA_MAX_SESSIONS` ke `2` — plafon lebih tinggi cuma memindahkan titik jatuh ke
MySQL milik flustra-erp.

### 1c. Tiga pemeriksaan cepat di server

```bash
free -h
docker ps --format '{{.Names}}' | wc -l
tail -5 /var/log/flustra-capacity.log
```

Available < 1,5 GB → **tunda**. Build Nixpacks menaikkan pemakaian memori, dan
itu persis pemicu terakhir 7 September.

### 1d. Yang berubah di build

`nixpacks.toml` menambah `tini`. Build pertama lebih lama dan harus lolos tahap
setup. Kalau `tini` gagal terpasang, aplikasi **tetap jalan** — `start.sh`
melewati blok re-exec — dan tidak ada yang rusak.

Jangan berharap `tini` mengurangi zombie yang terlihat sekarang. Reaper PID 1
hanya menuai anak yang **induknya sudah mati**, dan zombie di produksi induknya
masih hidup (§5, butir 5). `tini` ada untuk yatim sungguhan — subproses Chromium
yang di-reparent ke PID 1 saat Node mati mendadak — dan itu keadaan yang
seharusnya justru makin jarang setelah perbaikan ini.

---

## 2. Uji asap pasca-deploy

Satu blok, tempel apa adanya. Berhenti pada yang pertama gagal, lalu ke §3.

```bash
CID=$(docker ps -qf name=flustra-wa)

echo "== 1. container ==" && docker ps --filter name=flustra-wa --format '{{.Status}}'

echo "== 2. boot ==" && docker logs --tail 120 "$CID" 2>&1 | grep -E 'start.sh|migrat|Chromium|tini'

echo "== 3. health ==" && docker exec "$CID" sh -c \
  'curl -s -H "X-Engine-Token: $ENGINE_TOKEN" http://127.0.0.1:${ENGINE_PORT:-3100}/health'

echo && echo "== 4. sesi ==" && docker exec "$CID" php artisan tinker --execute="
DB::table('wa_sessions')->select('name','status','connect_failures')->get()
  ->each(fn(\$s) => print(str_pad(\$s->name,20).str_pad(\$s->status,14).\$s->connect_failures.PHP_EOL));"

echo "== 5. antrean ==" && docker exec "$CID" php artisan tinker --execute="
echo 'jobs='.DB::table('jobs')->count().' failed='.DB::table('failed_jobs')->count().PHP_EOL;"

echo "== 6. pangkas KERING ==" && docker exec "$CID" php artisan flustra:pangkas --dry-run

echo "== 7. API key lama ==" && docker exec "$CID" php artisan tinker --execute="
\$k = App\Models\ApiKey::whereNotNull('key_ciphertext')->first();
if (! \$k) { echo 'tidak ada kunci ber-ciphertext untuk diuji'.PHP_EOL; }
else {
  \$ok = \$k->verifySecret(explode('.', \$k->plainKey(), 2)[1]);
  echo 'verifikasi='.(\$ok ? 'DITERIMA' : 'DITOLAK')
     .'  bcrypt_masih_ada='.(str_starts_with((string) \$k->key_hash, '\$2') ? 'ya' : 'TIDAK').PHP_EOL;
}"
```

### Yang harus Anda lihat

| # | Harapan | Kalau tidak |
|---|---|---|
| 1 | `Up X seconds`, **tanpa** `Restarting` | §3a |
| 2 | Berurutan: `Menjalankan ulang di bawah tini` → `Menjalankan migrasi database.` → `Chromium ditemukan:` → `Menyalakan web, engine...` | baris tini absen = `tini` tidak terpasang; **bukan** alasan rollback |
| 3 | `chromium_duplicates: 0` **dan** `chromium_orphans: 0` | §3d |
| 4 | Tiga baris `connected`, `connect_failures` = 0 | §3e |
| 5 | `jobs` kecil dan turun | worker tidak jalan |
| 6 | Tabel angka, tanpa galat. **Tidak menghapus apa pun** | jangan lanjut ke pangkas sungguhan |
| 7 | `verifikasi=DITERIMA` dan `bcrypt_masih_ada=ya` | §3c |

**`chromium_duplicates` angka yang paling menentukan.** Ia menghitung proses
BERLEBIH pada profil yang sama — bentuk kerusakan 8 September, ketika tiga profil
dipegang enam proses. Jumlah profilnya sendiri wajar waktu itu. `null` berarti
`/proc` tidak terbaca; itu temuan, bukan hasil yang lolos.

### Dua uji yang menyentuh nomor sungguhan — lakukan sadar

Keduanya mengirim WhatsApp betulan. Ganti nomornya dengan nomor Anda sendiri.

```bash
docker exec "$CID" php artisan tinker --execute="
\$s = App\Models\WaSession::where('status','connected')->first();
if (! \$s) { echo 'tidak ada sesi connected'.PHP_EOL; return; }
\$m = app(App\Services\MessageDispatcher::class)->queue(\$s->workspace, [
  'session_id' => \$s->id, 'to' => '6281234567890', 'type' => 'text',
  'body' => 'Uji asap cutover '.now()->toTimeString(),
]);
echo 'antre id='.\$m->id.PHP_EOL;"
```

Satu menit kemudian — statusnya harus `sent`, dan webhook-nya terkirim:

```bash
docker exec "$CID" php artisan tinker --execute="
\$m = App\Models\Message::latest()->first();
echo 'status='.\$m->status.'  wa_id='.(\$m->wa_message_id ?? '-').PHP_EOL;
echo 'webhook: '.(App\Models\WebhookDelivery::where('created_at','>',now()->subMinutes(3))
  ->get()->map(fn(\$d) => \$d->event.'='.(\$d->response_code ?? 'belum'))->implode(', ') ?: '(tidak ada webhook aktif)').PHP_EOL;"
```

Kalau workspace itu memang tidak punya webhook aktif, baris webhook kosong dan
itu benar — bukan kegagalan.

---

## 3. Kalau gagal

### 3a. Gagal saat build, atau migrasi menolak jalan

Container berhenti dan **tidak melayani apa pun** — disengaja. Aplikasi yang
melayani permintaan dengan skema setengah jadi merusak data.

Coolify → **Deployments** → deployment sebelumnya → **Rollback**.

Kelima migrasi aman ditinggal pada kode lama (§0): dua kolom yang tidak dibaca,
satu tabel yang tidak dibaca, dan empat indeks yang tidak dipakai. **Tidak perlu
`migrate:rollback`.**

### 3b. Migrasi SUDAH jalan tapi kode gagal start

Ini keadaan yang paling sering ditakuti, dan pada deploy ini ia **tidak
berbahaya**. Urutannya:

1. **Jangan** menjalankan `migrate:rollback`. Skema barunya kompatibel mundur;
   memutarnya balik menambah satu operasi berisiko tanpa membeli apa pun.
2. Rollback kode lewat Coolify (§3a).
3. Verifikasi kode lama menyala: uji asap **#1, #4, dan #7**. Yang ke-7 paling
   penting — ia membuktikan API key masih diterima.
4. Baca `docker logs` deployment yang gagal, perbaiki, deploy lagi. Migrasi yang
   sudah tercatat tidak dijalankan dua kali.

Satu-satunya keadaan yang mengubah ini: `flustra:hash-api-bersihkan` sudah
dijalankan. Kalau ya, langkah 2 mematikan seluruh API key — lihat §3c.

### 3c. Rollback SETELAH `flustra:hash-api-bersihkan`

**Satu-satunya arah yang tidak gratis**, dan itu sebabnya ia perintah dan bukan
migrasi: keputusan "kita tidak akan rollback lagi" tidak boleh diambil oleh
proses boot.

Sebelum deploy versi lama, kembalikan bcrypt:

```bash
docker exec $(docker ps -qf name=flustra-wa) php artisan tinker --execute="
\$gagal = 0;
App\Models\ApiKey::whereNotNull('key_ciphertext')->get()->each(function (\$k) use (&\$gagal) {
    if (str_starts_with((string) \$k->key_hash, '\$2')) return;
    try { [, \$r] = explode('.', \$k->key_ciphertext, 2); } catch (Throwable) { \$gagal++; return; }
    \$k->forceFill(['key_hash' => Illuminate\Support\Facades\Hash::make(\$r)])->saveQuietly();
    echo \$k->prefix.' dikembalikan ke bcrypt'.PHP_EOL;
});
echo 'gagal didekripsi: '.\$gagal.PHP_EOL;
echo 'tanpa ciphertext (TIDAK bisa dikembalikan): '
   .App\Models\ApiKey::whereNull('key_ciphertext')->count().PHP_EOL;"
```

Kunci tanpa `key_ciphertext` **tidak bisa** dikembalikan — rahasianya memang
tidak tersimpan di mana pun. Kunci seperti itu harus dicabut, dibuat ulang, dan
pemiliknya diberi tahu.

### 3d. `chromium_duplicates` > 0 setelah deploy

Berarti ada jalur yang menyalakan Chromium tanpa melewati pembebasan profil.
Jangan rollback; kumpulkan bukti dulu:

```bash
docker exec $(docker ps -qf name=flustra-wa) sh -c '
for p in /proc/[0-9]*; do
  tr "\0" " " < $p/cmdline 2>/dev/null | grep -o -- "--user-data-dir=[^ ]*wwebjs_auth[^ ]*" \
    | sed "s|^|${p#/proc/} |"
done | sort -k2'
```

Penahan sementara: `WA_MAX_SESSIONS=2` di environment Coolify. Membebaskan
300–500 MB seketika, dengan biaya satu slot pelanggan.

### 3e. Sesi tidak pulih

1. `docker logs` — cari `Bootstrap gagal` atau `Chromium tidak ditemukan`.
2. Chromium tidak ditemukan: `PUPPETEER_CACHE_DIR` hilang dari Install Command
   atau Environment. Perbaiki, deploy ulang. Kredensial aman.
3. Bootstrap gagal: engine mencoba ulang 5× tiap 5 detik lalu diam.
   `docker restart` sudah cukup.
4. `disconnected` dan tidak pernah naik: periksa `connect_failures`. Kalau sudah
   8, penjadwal menyerah **dengan sengaja** — tekan **Hubungkan** sekali di
   dashboard, itu mengembalikan jatahnya penuh.
5. Ada yang `qr` — **berhenti dan jangan tekan apa pun.** Itu berarti cadangan
   kredensial tidak terbaca, dan penyebab paling mungkin volume `wa-storage`
   yang tidak ter-mount. Periksa Persistent Storage dulu; scan QR ulang
   menghapus jalur pemulihannya.

---

## 4. Pemantauan setelahnya

```bash
./scripts/pantau.sh
./scripts/ringkas-kapasitas.sh /var/log/flustra-capacity.log
```

| Angka | Harus |
|---|---|
| `chromium_duplicates` | **tepat 0**, tiap kali |
| `chromium_orphans` | **tepat 0** |
| lantai `memory.current` | mendatar, bukan sekadar rendah |
| `memory.events max` | tidak naik |
| `webhook_deliveries` + `audit_logs` | naik lalu **berhenti** di dataran retensi |
| daftar tunggu (panel Sistem) | 0; kalau naik, kapasitas perlu ditambah |

---

## 5. Apa yang TIDAK diuji oleh 610 uji itu

568 uji PHP dan 42 uji engine berjalan di SQLite, dengan HTTP dipalsukan dan
tanpa satu pun Chromium sungguhan. Yang berikut hanya akan ketahuan di produksi
— **lihat ke sini di jam pertama.**

**1. Chromium sungguhan.** Tidak satu pun uji menjalankan Puppeteer. Seluruh
perilaku `Client`, `RemoteAuth`, dan Chromium diwakili tiruan. Yang diuji
sungguhan hanya bahwa sebuah **proses sistem operasi** mati saat disuruh mati.
Artinya `tutupChromium` terbukti membunuh proses, tapi **tidak** terbukti bahwa
`browser.close()` milik Puppeteer berperilaku seperti dugaan kami pada Chromium
di dalam container itu.

**2. Pembebasan profil terhadap `/proc` asli.** Pemilihannya diuji pada pohon
`/proc` tiruan. Kalau Chromium di sana menulis `--user-data-dir` dengan bentuk
berbeda (jalur relatif, simlink `/data` yang ter-resolve lain), pencocokannya
meleset — dan gagalnya **diam**: penjagaan tidak menemukan apa pun dan `start()`
melanjutkan seperti sebelumnya. Uji asap #3 yang menangkapnya.

**3. `tini` dan re-exec PID 1.** Tidak diuji sama sekali. Yang bisa salah: tini
tidak terpasang (aman, dilewati), atau ia tidak meneruskan SIGTERM sehingga
`matikan()` tidak berjalan — dan itu berarti **kredensial sesi tidak tersimpan
saat redeploy berikutnya**. Cara memastikan: redeploy kedua kalinya, dan sesi
harus tetap pulih tanpa scan QR.

**4. Loop `schedule:run` yang menggantikan `schedule:work`.** Perhitungan
tidurnya diuji di shell, tapi bukan bahwa job terjadwal benar-benar berjalan tiap
menit di container. Kalau salah, gejalanya diam: `SyncSessionStatusJob` berhenti
dan sesi yang putus tidak pernah tersambung lagi. Periksa dalam 10 menit pertama
— angkanya harus bergerak tiap menit:

```bash
docker exec $(docker ps -qf name=flustra-wa) php artisan tinker --execute="
echo App\Models\WaSession::max('last_seen_at').PHP_EOL;"
```

**5. Zombie — sumbernya sudah diketahui, dan sengaja TIDAK diperbaiki.**

Hipotesis awal kami keliru dua kali. Pertama: "zombie = Chromium yang
di-reparent ke PID 1". Kedua: "sumbernya `schedule:work` yang beranak tiap
menit". Keduanya dibantah data server:

```
25 zombie <- pid 2082    php artisan serve --host=0.0.0.0 --port=80
13 zombie <- pid 510122  php artisan serve --host=0.0.0.0 --port=80
```

Induknya `php artisan serve` — server bawaan PHP yang, dengan
`PHP_CLI_SERVER_WORKERS=4`, mem-fork worker dan tidak selalu menuainya. Bukan
Chromium, bukan penjadwal.

**Ini utang teknis yang dicatat, bukan masalah yang dibiarkan tanpa nama.**
Alasannya: `kernel.pid_max` di server itu 4.194.304, jadi 38 zombie tidak
mendekati batas apa pun, dan deploy ini sudah membawa +1.281 baris perubahan.
Menambah satu perubahan lagi ke jalur yang melayani SETIAP permintaan HTTP demi
sesuatu yang tidak berbahaya adalah pertukaran yang salah.

Perbaikan sebenarnya nanti bukan menambal reaper melainkan berhenti memakai
`php artisan serve` di produksi — ia memang server pengembangan. Itu perubahan
tersendiri, dengan pengujiannya sendiri.

Yang perlu dipantau: **lajunya**, bukan jumlahnya. Laju yang melonjak berarti
sesi sering mati-hidup, dan churn Chromium jauh lebih mahal daripada zombie-nya.
`scripts/ringkas-kapasitas.sh` menghitung laju itu.

Perubahan `schedule:work` → loop `schedule:run` **tetap dipertahankan**, tapi
nilainya bukan yang tadinya diklaim: ia mengurangi satu lapis proses per menit
dan membuat induknya menuai anaknya sendiri — bukan menyelesaikan zombie.

**6. MySQL.** Seluruh uji berjalan di SQLite. Yang bisa berbeda: perilaku indeks
baru pada `webhook_deliveries` dan `audit_logs`, dan `DELETE ... whereIn` dalam
`Pemangkas`. Tabel-tabel itu di bawah 1 MB sekarang, jadi risikonya kecil hari
ini dan tidak kecil enam bulan lagi.

**7. Beban sungguhan.** Tidak ada uji yang menjalankan tiga sesi bersamaan,
broadcast seribu pesan, atau engine yang menjawab lambat. Batas 10 detik pada
`tutupChromium` dan 180 detik pada inisialisasi dipilih dari penalaran, bukan
dari pengukuran di bawah beban.

**8. Email daftar tunggu.** `EmailNotifier` menelan kegagalan tanpa melempar.
Uji membuktikan notifikasi lonceng terkirim; **tidak** membuktikan email sampai.
Kalau SMTP salah konfigurasi, antrean tetap tercatat dan tetap ditandai
`notified_at`, tapi orangnya hanya tahu kalau membuka dashboard.

**9. Kunci API lama dengan bentuk hash tak terduga.** Diuji untuk bcrypt cost 12.
Produksi mungkin punya kunci dengan cost berbeda. Uji asap #7 memeriksanya pada
kunci nyata.

---

## 6. Setelah stabil: melepas jaring pengaman hash

**Jangan di hari cutover.** Syarat sebelum menjalankannya:

1. Sudah **7 hari** berjalan tanpa insiden.
2. Sudah pernah **satu redeploy biasa** yang lolos sesudahnya.
3. `chromium_duplicates` konsisten 0 selama itu.
4. Tidak ada rencana rollback yang tersisa.

```bash
docker exec $(docker ps -qf name=flustra-wa) php artisan flustra:hash-api-bersihkan --dry-run
# baca angkanya, lalu:
docker exec $(docker ps -qf name=flustra-wa) php artisan flustra:hash-api-bersihkan
```

Setelah ini rollback ke kode lama **mematikan seluruh API key**, dan §3c jadi
langkah wajib bukan pilihan. Yang dibeli: satu kolom hilang, dan pembuatan kunci
berhenti membayar bcrypt. Keduanya kecil. Jangan terburu-buru.
