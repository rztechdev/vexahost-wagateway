# API Key & Keamanan

Cara aplikasi Anda membuktikan diri saat memanggil gateway, dan cara menjaganya tetap aman.

---

## Membuat API key

Menu **API Keys** → beri nama → **Buat**.

Kuncinya berbentuk seperti ini:

```
fwa_a1b2c3d4.7HkQmZpXvR2wLnT9sYbF4jGcE6dAuN8i
```

**Ditampilkan satu kali saja.** Kami hanya menyimpan sidik jarinya, bukan kuncinya — jadi bahkan kami tidak bisa menampilkannya lagi. Salin saat itu juga dan simpan di tempat aman.

Kalau hilang: buat kunci baru, perbarui aplikasi Anda, lalu cabut yang lama.

## Memakainya

Sertakan di setiap permintaan:

```http
X-Api-Key: fwa_a1b2c3d4.7HkQmZpXvR2wLnT9sYbF4jGcE6dAuN8i
```

Atau sebagai bearer token, kalau pustaka HTTP Anda lebih nyaman dengan pola itu:

```http
Authorization: Bearer fwa_a1b2c3d4.7HkQmZpXvR2wLnT9sYbF4jGcE6dAuN8i
```

Keduanya sama saja.

## Satu aplikasi, satu kunci

Buat kunci terpisah untuk setiap aplikasi dan setiap lingkungan:

| Nama kunci | Dipakai |
|---|---|
| `Aplikasi Kasir — produksi` | Sistem kasir yang melayani pelanggan |
| `Aplikasi Kasir — uji coba` | Lingkungan pengujian |
| `CRM` | Aplikasi lain |

Alasannya sederhana: kalau satu kunci bocor, Anda mencabut kunci itu saja. Aplikasi lain tetap berjalan.

Kalau semua aplikasi berbagi satu kunci, mencabutnya berarti mematikan semuanya sekaligus — pada saat Anda sedang panik menangani kebocoran.

## Menyimpan kunci

**Jangan taruh di dalam kode.** Kode masuk ke sistem versi, dibaca banyak orang, dan bisa tidak sengaja terbagikan.

Taruh di environment variable:

```env
WA_GATEWAY_URL=https://wa.flustra.id
WA_GATEWAY_KEY=fwa_a1b2c3d4.7HkQmZpXvR2wLnT9sYbF4jGcE6dAuN8i
```

**Jangan pakai di sisi browser.** Kunci yang dipakai di JavaScript halaman web bisa dibaca siapa pun yang membuka Inspect Element. Panggilan ke gateway harus dari server aplikasi Anda.

Kalau halaman web Anda perlu memicu pengiriman, buat endpoint di server Anda sendiri yang memanggil gateway — dengan pemeriksaan hak akses milik Anda.

## Mencabut kunci

**API Keys** → **Cabut** pada kunci yang bersangkutan. Berlaku seketika: permintaan berikutnya dengan kunci itu ditolak.

Kunci yang dicabut tidak dihapus dari daftar. Catatannya tetap ada agar Anda bisa melihat kapan terakhir dipakai — informasi yang dibutuhkan saat menyelidiki kejadian yang tidak diinginkan.

Cabut kunci ketika:

- Anda menduga kunci bocor
- Anggota tim yang memegangnya sudah tidak bekerja dengan Anda
- Aplikasi yang memakainya sudah tidak dipakai lagi

## Memantau pemakaian

Kolom **Terakhir dipakai** menunjukkan kapan kunci itu terakhir dipanggil.

Kunci yang tidak pernah dipakai berbulan-bulan sebaiknya dicabut. Kunci yang tidak dipakai tetap berlaku, dan kunci berlaku yang terlupakan adalah risiko yang tidak memberi manfaat apa pun.

## Batas permintaan

Setiap kunci punya batas jumlah permintaan per menit. Melewatinya menghasilkan balasan `429` beserta header `Retry-After` yang memberi tahu berapa detik harus menunggu.

Batasnya dihitung **per kunci**, bukan per alamat IP. Jadi beberapa aplikasi Anda yang berjalan di server yang sama tidak saling menghabiskan jatah.

Lihat [Batas & Kuota](BATAS_DAN_KUOTA.md).

## Keamanan workspace

**Peran anggota.** Undang rekan tim dengan peran secukupnya:

| Peran | Boleh |
|---|---|
| Owner | Semuanya |
| Admin | Kelola sesi, API key, webhook, anggota |
| Member | Melihat dan mengirim pesan |

Member tidak bisa membuat atau mencabut API key.

**Keluarkan anggota yang sudah tidak terlibat**, dan cabut kunci yang mereka buat.

**Riwayat pesan berisi data pelanggan Anda.** Nomor telepon dan isi percakapan tersimpan sesuai masa retensi paket Anda. Pertimbangkan itu saat memutuskan siapa yang boleh mengakses workspace.
