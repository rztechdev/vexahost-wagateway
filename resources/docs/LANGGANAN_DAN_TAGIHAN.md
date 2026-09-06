# Langganan & Tagihan

Paket, cara membayar, dan apa yang terjadi kalau langganan habis.

---

## Paket

Semua paket memakai gateway, API, dan dashboard yang sama. Yang membedakan hanya seberapa besar Anda memakainya.

| | Essentials | Prime | Elite |
|---|---|---|---|
| Per bulan | Rp 149.000 | Rp 249.000 | Rp 449.000 |
| Per tahun | Rp 1.490.000 | Rp 2.490.000 | Rp 4.490.000 |
| Nomor WhatsApp aktif | 1 | 1 | 2 |
| Pesan keluar per bulan | 2.000 | 10.000 | 50.000 |
| API key | 3 | 10 | tanpa batas |
| Batas API per menit | 60 | 120 | 300 |
| Riwayat pesan | 30 hari | 90 hari | 12 bulan |
| Anggota tim | 2 | 10 | tanpa batas |

Membayar tahunan berarti membayar sepuluh bulan untuk dua belas — dua bulan gratis.

**Langganan melekat pada workspace.** Kalau Anda memisahkan dua cabang menjadi dua workspace, masing-masing berlangganan sendiri. Biaya kami tumbuh per nomor WhatsApp, dan nomor melekat pada workspace.

---

## Cara berlangganan

1. Buka menu **Langganan** di dashboard.
2. Pilih paket dan periode (bulanan atau tahunan), lalu klik tombolnya.
3. Tagihan terbit dan Anda diarahkan ke halaman pembayaran.
4. Scan kode QRIS dengan aplikasi bank atau dompet digital apa pun — nominalnya sudah tercantum di dalam kode, tidak perlu diketik.
5. Unggah bukti transfer. Kami memeriksanya dan mengaktifkan langganan Anda, biasanya dalam beberapa jam pada jam kerja.

Hanya **owner** dan **admin** workspace yang bisa mengurus langganan. Anggota biasa tetap bisa melihat statusnya.

### Kenapa nominalnya tidak bulat

Tiga digit terakhir setiap tagihan adalah **kode unik** — misalnya Rp 149.0**37**. Itulah yang kami pakai untuk mengenali pembayaran Anda di antara pembayaran orang lain pada hari yang sama.

Transfer tepat sampai angka terakhirnya. Nominal yang dibulatkan akan sulit dicocokkan dan memperlambat aktivasi.

### Transfer bank

Batas QRIS per transaksi berbeda-beda tiap aplikasi dan bisa berhenti di bawah nilai paket tahunan. Untuk nominal besar, rekening bank ditampilkan berdampingan dengan kode QR di halaman pembayaran.

---

## Perpanjangan

Tagihan perpanjangan terbit **tiga hari sebelum** periode Anda berakhir, dan pengingat dikirim ke nomor WhatsApp yang Anda isi di **Pengaturan → Nomor WhatsApp untuk tagihan**.

Tidak ada tarikan otomatis dari rekening atau kartu Anda. Setiap periode dibayar sendiri.

Membayar lebih awal tidak menghanguskan sisa hari Anda: periode baru dihitung mulai dari tanggal berakhirnya periode berjalan.

---

## Kalau langganan habis

Yang terjadi bertahap, dan tidak ada satu pun tahap yang menghapus data Anda.

**Sejak tanggal berakhir:**

- Pengiriman pesan berhenti — lewat dashboard maupun lewat API.
- Nomor WhatsApp Anda **tetap tertaut**. Tidak perlu discan ulang.
- Dashboard tetap terbuka. Riwayat pesan, template, webhook, dan API key bisa dilihat seperti biasa.

**Setelah 30 hari:**

- Sesi dilepas untuk membebaskan sumber daya. Menghubungkan kembali setelah membayar dilakukan dari halaman **Sesi** — dan kalau cadangan sesinya masih ada, nomornya menyala tanpa scan ulang.

**Data Anda tidak dihapus karena tidak membayar.** Riwayat pesan mengikuti masa retensi paket Anda seperti biasa.

Begitu tagihan lunas, layanan menyala kembali seketika.

---

## Naik atau turun paket

Pilih paket lain di menu **Langganan**, lalu bayar tagihannya. Paket berpindah **saat tagihan lunas**, bukan saat Anda memilihnya.

Turun paket berlaku dengan cara yang sama. Kalau paket baru punya batas lebih kecil dari yang sedang Anda pakai — misalnya jumlah API key — kelebihan yang sudah ada tidak dihapus, tapi Anda tidak bisa menambah yang baru sampai jumlahnya di bawah batas.

---

## Pertanyaan yang sering muncul

**Apakah harga sudah termasuk pajak?**
Harga yang tertera adalah harga yang Anda bayar. Kalau nanti ada komponen pajak, ia muncul sebagai baris tersendiri di tagihan sebelum Anda membayar.

**Bisakah saya minta faktur?**
Setiap tagihan punya nomor urut dan rinciannya bisa dibuka kapan saja dari menu **Langganan**.

**Apa yang terjadi kalau kuota pesan habis di tengah bulan?**
Pengiriman berhenti di batas paket dan tercatat di dashboard, bukan gagal diam-diam. Naikkan paket untuk melanjutkan; kuota kembali penuh di awal periode berikutnya.

**Saya sudah bayar tapi statusnya belum berubah.**
Pembayaran diperiksa manusia, bukan otomatis. Pastikan bukti transfer sudah terunggah di halaman tagihan, lalu hubungi kami kalau lewat satu hari kerja belum aktif juga.
