# Webhook

Menerima pesan masuk dan perubahan status di aplikasi Anda.

---

## Cara kerjanya

API dipakai untuk **mengirim**. Webhook adalah kebalikannya: saat ada kejadian, kami yang mengirim data ke aplikasi Anda.

```
Pelanggan membalas  →  Flustra WA Gateway  →  POST ke URL Anda
```

Tanpa webhook, satu-satunya cara mengetahui ada balasan adalah memeriksa riwayat berulang-ulang — boros dan selalu terlambat.

## Mendaftarkan

Menu **Webhooks** → isi URL → pilih kejadian → **Simpan**.

**Signing secret** ditampilkan di kartu webhook. Simpan — Anda butuh itu untuk memverifikasi bahwa kiriman benar-benar dari kami.

Gunakan tombol **Kirim uji coba** untuk memastikan endpoint Anda menerimanya dengan benar, sebelum ada trafik sungguhan.

## Kejadian yang tersedia

| Kejadian | Kapan dikirim |
|---|---|
| `message.received` | Ada pesan masuk ke nomor Anda |
| `message.status` | Status pengiriman berubah |
| `session.status` | Nomor tersambung, terputus, atau bermasalah |
| `session.qr` | QR baru tersedia |

## Bentuk kiriman

```json
{
  "event": "message.received",
  "timestamp": "2026-08-13T14:12:44+07:00",
  "delivery_id": "01K2CDEFGH7JKMNPQRSTVWXYZ",
  "data": {
    "message_id": "01K2C…",
    "session_id": "01K2B…",
    "from": "6289999999999",
    "chat_id": "6289999999999@c.us",
    "type": "text",
    "body": "Halo, pesanan saya sudah sampai mana ya?",
    "is_group": false,
    "received_at": "2026-08-13T14:12:44+07:00"
  }
}
```

Isi `data` per kejadian:

| Kejadian | Isi `data` |
|---|---|
| `message.received` | `message_id`, `session_id`, `from`, `chat_id`, `type`, `body`, `is_group`, `received_at` |
| `message.status` | `message_id`, `status`, `to`, `error` (bila gagal) |
| `session.status` | `session_id`, `name`, `status`, `phone_number` |
| `session.qr` | `session_id`, `status` |

## Verifikasi tanda tangan

**Selalu lakukan ini sebelum memproses.** Tanpa verifikasi, siapa pun yang mengetahui URL webhook Anda bisa mengirim data palsu — misalnya berpura-pura menjadi pelanggan yang membatalkan pesanan.

Setiap kiriman membawa dua header:

| Header | Isi |
|---|---|
| `X-Flustra-Signature` | Tanda tangan HMAC-SHA256 dari isi kiriman |
| `X-Flustra-Event` | Nama kejadian |

### PHP / Laravel

```php
Route::post('/webhook/whatsapp', function (Request $request) {
    $expected = hash_hmac('sha256', $request->getContent(), config('services.wa.webhook_secret'));

    if (! hash_equals($expected, (string) $request->header('X-Flustra-Signature'))) {
        abort(401);
    }

    $data = $request->json('data');

    // Proses di latar belakang, jangan di sini.
    ProsesPesanMasuk::dispatch($data);

    return response()->noContent();
});
```

Endpoint webhook tidak melalui sesi browser, jadi kecualikan dari pemeriksaan CSRF.

### Node.js / Express

```js
import crypto from 'node:crypto';

app.post('/webhook/whatsapp',
    express.raw({ type: 'application/json' }),   // WAJIB raw
    (req, res) => {
        const expected = crypto
            .createHmac('sha256', process.env.WA_WEBHOOK_SECRET)
            .update(req.body)
            .digest('hex');

        const given = req.get('X-Flustra-Signature') ?? '';

        if (!crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(given))) {
            return res.sendStatus(401);
        }

        const payload = JSON.parse(req.body);
        res.sendStatus(204);
    });
```

### Python / Flask

```python
import hmac, hashlib

@app.post("/webhook/whatsapp")
def webhook():
    expected = hmac.new(
        WA_WEBHOOK_SECRET.encode(),
        request.get_data(),          # data mentah, bukan hasil parsing
        hashlib.sha256,
    ).hexdigest()

    if not hmac.compare_digest(expected, request.headers.get("X-Flustra-Signature", "")):
        return "", 401

    data = request.get_json()["data"]
    return "", 204
```

> **Tanda tangan dihitung atas isi mentah.** Kalau framework Anda sudah mem-parsing JSON lalu menyusunnya kembali, urutan kunci atau tanda kutipnya bisa berubah dan tanda tangan tidak akan cocok. Ambil isi mentahnya sebelum di-parse.

## Kalau endpoint Anda gagal

Endpoint Anda harus membalas kode `2xx`.

Kiriman yang gagal diulang **3 kali** dengan jeda 30 detik lalu 5 menit.

Setelah **50 kegagalan berturut-turut**, webhook dinonaktifkan otomatis. Ini melindungi Anda sendiri: tanpa itu, antrean akan terus terisi kiriman yang pasti gagal. Nyalakan lagi dari dashboard setelah endpoint pulih.

Pesan masuknya sendiri **tetap tersimpan** di Riwayat Pesan meski webhook gagal — tidak ada data yang hilang.

## Praktik yang baik

**Balas cepat, proses belakangan.** Terima kiriman, simpan ke antrean Anda, lalu balas `204`. Kalau Anda memproses sampai selesai sebelum membalas, kiriman berikutnya menumpuk.

**Siapkan untuk kiriman ganda.** Dalam kondisi jaringan tertentu, satu kejadian bisa terkirim dua kali. Gunakan `delivery_id` atau `message_id` untuk mengabaikan yang sudah pernah diproses.

**Pakai HTTPS.** Isi pesan pelanggan Anda lewat di sana.

**Jangan taruh secret di URL.** Verifikasi lewat header tanda tangan, bukan lewat `?token=…` di alamat — alamat tercatat di log server dan proxy.

## Menguji saat pengembangan

Kalau aplikasi Anda masih berjalan di komputer lokal, gunakan layanan penerus seperti ngrok agar bisa dijangkau dari internet:

```bash
ngrok http 8000
```

Daftarkan alamat yang diberikan ngrok sebagai URL webhook, lalu tekan **Kirim uji coba** di dashboard.

Untuk sekadar melihat bentuk kirimannya tanpa menulis kode apa pun, daftarkan alamat dari webhook.site.
