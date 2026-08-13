# Praktik Baik

Cara memakai gateway tanpa membuat nomor Anda diblokir WhatsApp.

---

## Yang perlu dipahami lebih dulu

Nomor Anda tertaut ke sistem kami sebagai **perangkat tertaut**, sama seperti WhatsApp Web di laptop. WhatsApp tidak secara resmi mengizinkan pengiriman otomatis lewat jalur ini.

Artinya: nomor Anda bisa diblokir, dan **tidak ada jalur banding**. Bukan kami yang memblokir, dan bukan kami yang bisa memulihkannya.

Kabar baiknya, pemblokiran hampir selalu punya sebab yang bisa dihindari. Halaman ini merangkumnya.

## Aturan paling penting

**Kirim hanya kepada orang yang mengharapkan pesan Anda.**

Hampir semua pemblokiran berawal dari satu hal: penerima menekan *Blokir* atau *Laporkan spam*. Ketika cukup banyak orang melakukannya dalam waktu singkat, WhatsApp bertindak.

Pelanggan yang baru saja memesan di toko Anda tidak akan melaporkan konfirmasi pesanan sebagai spam. Orang yang nomornya Anda beli dari daftar kontak pasti melaporkannya.

## Yang aman dikirim

| Jenis | Kenapa aman |
|---|---|
| Konfirmasi pesanan | Ditunggu penerima |
| Status pengiriman & nomor resi | Ditunggu |
| Pengingat jatuh tempo | Ada hubungan bisnis yang jelas |
| Konfirmasi pembayaran | Ditunggu |
| Balasan atas pertanyaan pelanggan | Mereka yang memulai |
| Pengingat janji temu | Disetujui sebelumnya |

## Yang berisiko tinggi

| Jenis | Kenapa berisiko |
|---|---|
| Promosi ke nomor yang tidak pernah berhubungan | Sumber laporan spam nomor satu |
| Nomor dari daftar yang dibeli | Penerima tidak mengenal Anda sama sekali |
| Pesan berantai atau ajakan berbagi | Ditandai sistem WhatsApp |
| Pesan identik ke ratusan nomor sekaligus | Pola yang mudah dikenali sebagai robot |
| Nomor baru langsung dipakai broadcast besar | Paling cepat kena |

## Nomor baru: mulai pelan

Nomor yang baru tertaut belum punya rekam jejak. Mengirim 500 pesan di hari pertama adalah cara tercepat kehilangan nomor tersebut.

Panduan kasar untuk nomor baru:

| Hari | Batas wajar |
|---|---|
| 1–3 | Di bawah 50 pesan/hari |
| 4–7 | Sampai 150 pesan/hari |
| Minggu 2 | Sampai 300 pesan/hari |
| Setelah sebulan | Naikkan bertahap sambil memantau |

Naikkan pelan-pelan, dan **berhenti menaikkan** kalau mulai muncul kegagalan pengiriman yang tidak wajar.

## Jeda kirim itu perlindungan, bukan hambatan

Kami menyisipkan jeda beberapa detik antar pesan. Broadcast 500 nomor memakan 30–60 menit.

Ini sengaja dan tidak bisa dimatikan. Mengirim beruntun tanpa jeda adalah pola paling khas robot. Kalau kami mengizinkannya, nomor pelanggan kami akan berjatuhan — termasuk nomor Anda.

Kalau perlu mengirim ke ribuan nomor, sebar ke beberapa hari. Bukan karena batas teknis, tapi karena volume mendadak adalah yang paling dicurigai.

## Menyusun pesan

**Sebutkan siapa Anda di awal.** Penerima belum tentu menyimpan nomor Anda.

```
*Toko Makmur*

Halo Budi, pesanan #1234 sudah kami kirim…
```

**Sebutkan kenapa Anda menghubungi.** "Terkait pesanan yang Anda buat kemarin" menghilangkan kecurigaan sebelum muncul.

**Variasikan isinya.** Ratusan pesan yang identik kata per kata lebih mencolok daripada pesan yang berisi nama dan nomor pesanan masing-masing penerima. Gunakan [template dengan placeholder](TEMPLATE_PESAN.md).

**Sediakan cara berhenti** untuk pesan promosi:

```
Balas STOP untuk berhenti menerima info promo.
```

Lalu benar-benar hormati permintaannya. Orang yang bisa berhenti dengan mudah tidak perlu menekan tombol laporkan.

**Jangan kirim kata sandi, kode OTP milik layanan lain, atau data kartu.** Riwayat WhatsApp tersinkron ke semua perangkat tertaut, tercadangkan ke cloud, dan sering terlihat di notifikasi layar kunci.

## Jaga nomornya tetap sehat

**Pakai nomor khusus untuk keperluan bisnis**, terpisah dari nomor pribadi.

**Buka WhatsApp di HP sesekali.** Perangkat tertaut akan kedaluwarsa kalau HP tidak pernah online dalam waktu lama.

**Balas pesan yang masuk.** Nomor yang hanya mengirim tanpa pernah menerima terlihat seperti mesin. Percakapan dua arah membangun rekam jejak yang baik. Manfaatkan [webhook](WEBHOOK.md) agar balasan pelanggan sampai ke tim Anda.

**Pantau angka kegagalan.** Lonjakan mendadak di kartu "Gagal bulan ini" biasanya tanda pertama ada yang tidak beres — jauh sebelum nomor benar-benar diblokir.

## Kalau nomor terlanjur diblokir

Tidak ada cara memulihkannya dari sisi kami.

Yang bisa dilakukan:

1. **Tautkan nomor pengganti ke sesi yang sama.** Klik Putus tautan, lalu Hubungkan dan scan nomor baru. Riwayat pesan dan API key tetap utuh — aplikasi Anda tidak perlu diubah sama sekali.
2. **Cari tahu sebabnya sebelum mengulang pola yang sama.** Nomor pengganti akan bernasib sama kalau perlakuannya tidak berubah.

## Kalau volume Anda sudah besar

Untuk pengiriman puluhan ribu pesan per bulan, atau untuk komunikasi yang tidak boleh gagal sama sekali, jalur non-resmi ini bukan pilihan yang tepat lagi.

WhatsApp Business API resmi tidak punya risiko pemblokiran ini — dengan konsekuensi biaya per pesan, kewajiban verifikasi bisnis, dan template yang harus disetujui lebih dulu.

Hubungi kami untuk membahas pilihan itu.
