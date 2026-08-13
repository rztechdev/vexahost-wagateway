# Dokumentasi Flustra WA Gateway

Peta seluruh dokumentasi. Semua Bahasa Indonesia.

---

## Mulai dari mana

**Baru pertama kali mendengar project ini?**
→ [PENGANTAR.md](PENGANTAR.md) — apa itu, untuk apa, masalah apa yang diselesaikan, dan istilah-istilahnya. Tidak ada asumsi pengetahuan sebelumnya.

**Developer baru yang akan menulis kode?**
→ [PENGANTAR.md](PENGANTAR.md) → [ARSITEKTUR.md](ARSITEKTUR.md) → [PANDUAN_DEVELOPER.md](PANDUAN_DEVELOPER.md)

**Cuma mau memanggil API-nya dari aplikasi lain?**
→ [API.md](API.md), atau [INTEGRASI_APP.md](INTEGRASI_APP.md) kalau aplikasinya bagian dari ekosistem Flustra

**Mau men-deploy?**
→ [ENVIRONMENT.md](ENVIRONMENT.md) → [DEPLOYMENT.md](DEPLOYMENT.md)

**Ada yang bermasalah di produksi?**
→ [OPERASIONAL.md](OPERASIONAL.md)

**Punya pertanyaan singkat?**
→ [FAQ.md](FAQ.md)

---

## Daftar lengkap

### Memahami

| Dokumen | Isi |
|---|---|
| [PENGANTAR.md](PENGANTAR.md) | Apa & untuk apa, masalah yang diselesaikan, istilah, cara sesi bertahan dari deploy ulang |
| [ARSITEKTUR.md](ARSITEKTUR.md) | Bagaimana bagian-bagiannya bekerja sama, alur data, alasan tiap keputusan, batas yang disengaja |
| [GLOSARIUM.md](GLOSARIUM.md) | Istilah dari A sampai Z |
| [PERBANDINGAN_PROVIDER.md](PERBANDINGAN_PROVIDER.md) | whatsapp-web.js vs WhatsApp Business API resmi (Twilio/Meta), lengkap dengan biayanya |

### Membangun

| Dokumen | Isi |
|---|---|
| [PANDUAN_DEVELOPER.md](PANDUAN_DEVELOPER.md) | Menyiapkan mesin, menjalankan, struktur kode, menambah fitur, menulis tes |
| [REFERENSI_KODE.md](REFERENSI_KODE.md) | Setiap kelas penting di sisi Laravel: tanggung jawab & jebakannya |
| [REFERENSI_DATABASE.md](REFERENSI_DATABASE.md) | Setiap tabel, setiap kolom, dan alasan keberadaannya |
| [ENGINE.md](ENGINE.md) | Seluk-beluk engine Node.js: siklus hidup sesi, antrean, store RemoteAuth |
| [KONTRIBUSI.md](KONTRIBUSI.md) | Alur git, standar kode, daftar periksa sebelum menggabungkan |

### Memakai

| Dokumen | Isi |
|---|---|
| [API.md](API.md) | Referensi REST API v1: setiap endpoint, contoh, kode galat, contoh integrasi |
| [INTEGRASI_APP.md](INTEGRASI_APP.md) | Menyambungkan aplikasi Flustra lain ke gateway |
| [VERIFIKASI_NOMOR.md](VERIFIKASI_NOMOR.md) | Alur OTP dan kenapa nomor wajib diverifikasi |

### Menjalankan

| Dokumen | Isi |
|---|---|
| [ENVIRONMENT.md](ENVIRONMENT.md) | Tiga tahap: development, staging, production |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Deploy di Coolify langkah demi langkah, plus uji regresi wajib |
| [OPERASIONAL.md](OPERASIONAL.md) | Pemantauan, pemulihan, pemecahan masalah, skala, keamanan |
| [FAQ.md](FAQ.md) | Pertanyaan yang sering muncul |

---

## Tiga hal yang paling sering ditanyakan

**Kenapa dulu harus scan QR setiap deploy, sekarang tidak?**
Kredensial nomor sekarang disimpan di penyimpanan permanen yang hidup terpisah dari container, **dan** dicadangkan ke database tiap 5 menit. Deploy ulang tidak menyentuh keduanya. → [PENGANTAR.md](PENGANTAR.md#5-bagaimana-masalah-scan-ulang-tiap-deploy-diselesaikan)

**Apakah nomor jadi terkunci?**
Tidak. Putus tautan lalu scan lagi dengan nomor mana pun. Yang dijamin justru kebalikannya: nomor tidak terputus sendiri karena deploy. → [FAQ.md](FAQ.md#apakah-nomor-saya-terkunci-bisa-diganti)

**Kenapa broadcast lambat?**
Setiap pesan diberi jeda acak 3–8 detik. Mengirim beruntun tanpa jeda adalah cara tercepat membuat nomor diblokir. → [FAQ.md](FAQ.md#kenapa-broadcast-saya-lambat-sekali)

---

## Menulis dokumentasi di sini

- **Bahasa Indonesia.** Istilah teknis tanpa padanan mapan (queue, webhook, endpoint, timestamp) boleh tetap bahasa Inggris.
- **Jelaskan alasan, bukan hanya mekanisme.** Skema dan kode sudah menunjukkan *apa*; dokumentasi berguna saat menjelaskan *kenapa begitu* dan *apa akibatnya kalau tidak*.
- **Catat batasnya dengan jujur.** Yang belum dibuat lebih baik ditulis daripada dibiarkan ditemukan sendiri.
- **Perbarui bersama kodenya**, dalam commit yang sama.
