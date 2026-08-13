# Menyambungkan Aplikasi Flustra ke Gateway

Klien-nya disalin per aplikasi, mengikuti pola yang sudah dipakai `CentralAuthClient` dan `SloNotifier`. Sumber aslinya ada di `docs/client/` repo ini — **ubah di sana dulu, lalu salin ulang**.

## Nomor pengirim: platform vs tenant

| Jenis | Nomor | Dipakai untuk |
|---|---|---|
| **Platform** | Nomor resmi Flustra | OTP, undangan anggota, notifikasi billing, balasan tiket — pesan atas nama Flustra |
| **Tenant** | Nomor perusahaan sendiri | Invoice ke customer, PO ke vendor, slip gaji — pesan atas nama perusahaan |

Pemisahan ini bukan kerapian belaka. Customer sebuah perusahaan tidak mengenal Flustra; invoice dari nomor asing terlihat seperti penipuan. Dan ratusan invoice per hari dari satu nomor platform adalah pola yang paling cepat membuat nomor itu diblokir WhatsApp — kalau itu terjadi, OTP dan seluruh notifikasi Flustra ikut mati.

Karena itu, bila perusahaan belum menautkan nomornya sendiri, sistem **tidak** diam-diam memakai nomor platform. Yang terjadi: kembali ke tautan `wa.me` manual seperti sebelumnya.

## Pemasangan

1. Salin dua file:

   ```
   docs/client/config/whatsapp.php          →  <app>/config/whatsapp.php
   docs/client/app/Services/WhatsAppGateway.php  →  <app>/app/Services/WhatsAppGateway.php
   ```

2. Tambahkan ke `.env` aplikasi:

   ```env
   WA_GATEWAY_URL=https://wa.flustra.id
   WA_GATEWAY_KEY=fwa_xxxxxxxx.xxxxxxxxxxxxxxxx
   WA_GATEWAY_SESSION=
   WA_GATEWAY_PLATFORM_SESSION=01K2B...
   ```

3. Buat API key-nya di dashboard gateway, satu kunci per aplikasi per lingkungan.

## Cara pakai

```php
use App\Services\WhatsAppGateway;

WhatsAppGateway::send($user->phone, "Halo {$user->name}, ...");
WhatsAppGateway::broadcast($phones, 'Pengumuman');
WhatsAppGateway::template($phone, 'pengingat-invoice', ['nomor' => 'INV-001']);
```

Semua method mengembalikan `bool` dan **tidak pernah melempar exception**. Notifikasi WhatsApp adalah pelengkap; invoice tetap harus tersimpan meski WhatsApp-nya gagal terkirim.

## Aturan wajib: hanya kirim ke nomor terverifikasi

`config('whatsapp.require_verified_phone')` bernilai `true` dan **jangan dimatikan di produksi**.

Kolom `users.phone` di aplikasi Flustra hanya pernah divalidasi *formatnya*. Kalau ada salah ketik satu digit, pesan mendarat di HP orang asing — dan laporan spam dari mereka bisa membuat nomor platform diblokir. Status verifikasi berasal dari flustra-auth; lihat [VERIFIKASI_NOMOR.md](VERIFIKASI_NOMOR.md).

## Status per aplikasi

### flustra-erp (Flustra Office)

`App\Services\WhatsAppService::sendMessage()` dipertahankan sebagai pembungkus tipis di atas `WhatsAppGateway`, sehingga seluruh pemanggil lama tidak berubah: `SendInvoiceReminders`, `SendTaskReminders`, `MeetingController`, `TaskController`, `AdminSettingsController`, `NotificationTemplateController`.

Yang dihapus dari repo ERP: `whatsapp-server.js`, `nixpacks.toml` (apt package Chromium), peluncuran Node di `start.sh`, dan dependensi `whatsapp-web.js`/`qrcode`/`express`/`cors` di `package.json`.

Halaman `admin/whatsapp` sekarang menampilkan status ringkas dari gateway dan menautkan ke dashboardnya.

### flustra-web

Memakai channel notifikasi Laravel. Notification tinggal menambahkan `'whatsapp'` ke `via()` dan menyediakan `toWhatsApp()`:

```php
public function via($notifiable): array
{
    return ['mail', 'database', 'whatsapp'];
}

public function toWhatsApp($notifiable): string
{
    return "Halo {$notifiable->name}, ...";
}
```

Sudah dipasang di: `FamilyInvitationReceived`, `CircleInvitationReceived`, `TeamMemberAddedNotification`, `TeamMemberInvitedWithNewAccountNotification`.

> Catatan pada notifikasi akun baru: **password sementara sengaja tidak ikut dikirim lewat WhatsApp**. Riwayat chat tersinkron ke semua perangkat tertaut dan terlihat di notifikasi layar kunci. Kredensial tetap hanya lewat email; WhatsApp cuma memberi tahu bahwa undangannya ada.

Untuk dokumen bisnis, `BusinessNotificationTriggerService::dispatchToRecipient()` mengirim otomatis dari nomor perusahaan bila `business_companies.wa_auto_send` menyala dan `wa_session_id` terisi. Kalau tidak, hasilnya tetap membawa `whatsapp_url` untuk tombol `wa.me` seperti sebelumnya.

### flustra-pricing

`App\Services\WhatsAppNotifier` dipanggil berdampingan dengan Mailable yang sudah ada: langganan aktif, langganan akan berakhir, langganan dibatalkan, bukti pembayaran ditolak, pengingat invoice, dan pengumuman maintenance. Admin tetap hanya lewat email.

### flustra-helpdesk

`App\Services\WhatsAppNotifier` untuk tiket dibuat, tiket diperbarui, tiket selesai, dan pengumuman maintenance. Pelapor tamu (tanpa akun) tidak dapat WhatsApp — mereka tidak pernah dimintai nomor telepon saat membuat tiket.

## Menerima pesan masuk

Daftarkan webhook di dashboard gateway, lalu verifikasi tanda tangannya. Lihat [API.md](API.md#menerima-webhook).
