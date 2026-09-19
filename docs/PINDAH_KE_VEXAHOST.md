# Pindah merek: Flustra → VexaHost

Sejak 19 Sep 2026 produk ini bernama **VexaHost WA Gateway**, milik
**PT DESTINARA CHAKRAWALA ARTHA**. Dokumen ini mencatat apa yang berubah, apa
yang sengaja tidak berubah, dan urutan aman memindahkan repo, server,
database, dan domain. Langkah server dikerjakan manual oleh Ryan.

**Keputusan Ryan (19 Sep 2026): mulai dari database kosong.** Belum ada
pelanggan, jadi data lama tidak dipindahkan — hanya diarsipkan satu kali
sebagai jaga-jaga.

## Yang berubah

| | Dulu | Sekarang |
|---|---|---|
| Nama | Flustra WA Gateway | VexaHost WA Gateway |
| Badan hukum | PT FLUSTRA FINANCES ARTHA | PT DESTINARA CHAKRAWALA ARTHA |
| Domain produksi | `wa.flustra.id` | `wa.vexahostcloud.my.id` |
| Domain dev / staging | `wa-dev.flustra.tech` / `wa-staging.flustra.tech` | `wa-dev.vexahostcloud.my.id` / `wa-staging.vexahostcloud.my.id` |
| Repo | `flustratech-dev/flustra-wa` | `rztechdev/vexahost-wagateway` |
| Resource Coolify | project Flustra | resource aplikasi **terpisah** di dalam project vexahost |
| Database | `db_flustra-wa`, `_dev`, `_staging` di MySQL Flustra | `db_vexahost-wa-production`, `-dev`, `-staging` di MySQL vexahost — **mulai kosong** |
| Email pengirim & kontak | `flustrafinances@gmail.com` | `vexahostcloudtech@gmail.com` (SMTP Brevo milik vexahost) |
| Admin | Flustra Finance | Ryan Rizki — persis akun admin vexahost |
| Nomor admin, notifikasi, kontak publik | nomor lama | `085808749131` (nomor bisnis VexaHost) |
| Login Google | OAuth client Flustra | OAuth client vexahost |
| Logo & favicon | logo Flustra WA | logo VexaHost, favicon sama persis dengan vexahost |
| Header webhook | `X-Flustra-Signature` / `-Event` | `X-VexaHost-Signature` / `-Event` |
| Awalan API key baru | `fwa_` | `vwa_` |
| Perintah artisan | `flustra:*` | `vexahost:*` |
| Akun | berdiri sendiri | **tertaut dengan vexahost** — [AKUN_TERTAUT.md](AKUN_TERTAUT.md) |
| Port lokal | 8070 | 8051 (vexahost lokal di 8050) |

## Yang sengaja TIDAK berubah

- **Alamat API** (`/api/v1/...`) dan bentuk jawabannya.
- **Branch**: `dev` → `staging` → `main`, sama seperti sebelumnya.
- **Pembayaran**: akun Mayar dan rekening bank tetap. QRIS memakai payload akun
  DANA yang sama (NMID dan nomor merchant identik) — yang berubah hanya nama
  toko yang tampil: DESTINARA. Semuanya dibaca dari env, jadi tetap jalan di
  database yang masih kosong.
- **`APP_KEY`** dibiarkan yang lama. Dengan database kosong ia tidak lagi wajib
  sama, tapi tidak ada alasan menggantinya.
- **API key lama `fwa_…` tetap dikenali kode** — tapi karena database mulai
  kosong, kunci lama itu sendiri ikut hilang. Aplikasi yang memakai gateway
  perlu kunci baru.
- **Aplikasi Flustra lain** (erp, web, pricing, helpdesk, clientportal) dan
  sebutan mereka di dokumentasi. Mereka dipindahkan Ryan terpisah: workspace
  dan API key baru di gateway ini, lalu `WA_GATEWAY_URL` ke domain baru.

## Repo

Dipindah 19 Sep 2026: seluruh riwayat dan ketiga branch didorong ke
`rztechdev/vexahost-wagateway`. Di salinan lokal, remote lama disimpan dengan
nama `flustra-lama` **dengan alamat push dimatikan** — ia hanya bisa dibaca,
dan push nyasar ke repo lama ditolak di mesin sendiri. Repo lama tidak ikut
berubah oleh push apa pun ke repo baru; arsipkan (jangan hapus) setelah
resource Coolify-nya tidak lagi menunjuk ke sana.

## Urutan pindah

Urutannya penting di dua tempat: resource lama tidak dimatikan sampai yang baru
terbukti jalan, dan domain lama baru dilepas setelah semua yang memanggilnya
sudah pindah.

1. **Arsip database lama — sekali, tidak di-import.** Sebelum apa pun dihapus:

   ```bash
   mysqldump --single-transaction --routines db_flustra-wa > flustra-wa-arsip.sql
   ```

   Simpan berkasnya di luar server. Database lama baru dihapus setelah semua
   di bawah selesai dan terbukti jalan.
2. **Database baru.** Di MySQL vexahost buat `db_vexahost-wa-production`,
   `-staging`, `-dev` (utf8mb4). Kosong — migrasi yang mengisinya.
3. **Resource Coolify di project vexahost** — aplikasi terpisah, bukan digabung
   ke container vexahost: engine WhatsApp tidak boleh ikut restart setiap kali
   vexahost di-deploy. Repo `rztechdev/vexahost-wagateway`, Nixpacks, Start
   Command `bash ./start.sh`, volume `/data` dan `/app/storage/app/private`,
   domain baru, env dari `.env.<tahap>` di root repo ini. Beri GitHub App
   Coolify akses ke repo baru.
4. **DNS.** A record `wa`, `wa-staging`, `wa-dev` di `vexahostcloud.my.id`.
5. **Deploy.** Migrasi berjalan sendiri saat boot (`start.sh`).
6. **Buat admin — wajib, sekali:** di terminal Coolify
   `php artisan db:seed --force`. `start.sh` hanya menjalankan migrasi, bukan
   seeder; tanpa langkah ini database baru tidak punya satu pun admin.
7. **Deploy vexahost** dengan `LINKED_ACCOUNTS_*` terisi, lalu **tautkan akun
   sekali** di kedua aplikasi: `php artisan akun-tertaut:tautkan`. Urutannya
   setelah langkah 6 — admin sudah ada, jadi akun admin vexahost tertaut ke
   admin gateway, bukan lahir sebagai pengguna biasa.
8. **Panel admin gateway**:
   - menu **Pengecualian**: buat workspace pengirim pemberitahuan, hubungkan
     sesi, **scan QR sekali dengan HP 085808749131**, daftarkan sebagai nomor
     istimewa, lalu tekan **Kirim tes**;
   - kirim email tes dari halaman yang sama;
   - Pengaturan Pembayaran: periksa Mayar, QRIS, dan rekening terbaca dari env.
9. **Google Cloud Console** (OAuth client vexahost) → tambahkan redirect URI
   `https://wa.vexahostcloud.my.id/auth/google/callback` dan versi `wa-staging.`
   / `wa-dev.`-nya. Tanpa ini tombol Google gagal `redirect_uri_mismatch`.
10. **Mayar** → ganti URL webhook ke
    `https://wa.vexahostcloud.my.id/api/webhooks/mayar`, **saat produksi baru
    sudah hidup** — selama masih menunjuk domain lama, pembayaran tercatat di
    aplikasi lama. Tokennya tidak berubah.
11. **Periksa**:
    - masuk sebagai admin (email `vexahostcloudtech@gmail.com`, kata sandi admin
      vexahost);
    - daftar akun uji di WA, lalu masuk di vexahost dengan kata sandi yang sama;
    - `/admin/sistem` hijau.
12. **Aplikasi Flustra** → workspace dan API key baru, lalu `WA_GATEWAY_URL` ke
    `https://wa.vexahostcloud.my.id`.
13. **Baru lepas yang lama**: di aplikasi lama tekan **Putus tautan** untuk
    nomor-nomornya (supaya tidak tertinggal sebagai perangkat tertaut di HP),
    hentikan dan hapus resource lama, hapus DNS domain lama tanpa pengalihan,
    lalu arsipkan repo lama.
