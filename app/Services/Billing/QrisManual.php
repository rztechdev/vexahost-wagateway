<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Support\Qris;
use Illuminate\Support\Facades\Log;

/**
 * Saluran pembayaran yang berlaku sekarang: QRIS dinamis, dicocokkan manusia.
 *
 * Tidak ada notifikasi otomatis dari mana pun. Yang menghubungkan uang masuk
 * dengan tagihan cuma dua: nominal yang dibuat unik lewat kode tiga digit, dan
 * bukti transfer yang diunggah pelanggan. Admin yang memutuskan.
 *
 * Kelas ini sengaja tidak diberi antarmuka bersama meski iPaymu sudah
 * direncanakan. Antarmuka dengan satu implementasi adalah slot kosong yang
 * menjanjikan sesuatu yang belum ada — persis pola `cloud_api` pada sesi, yang
 * akhirnya dihapus karena muncul sebagai pilihan di depan pelanggan padahal
 * tidak pernah berfungsi. Saat iPaymu benar-benar jalan, saat itulah antarmuka
 * dibuat, dengan dua implementasi nyata di tangan. Yang sudah disiapkan dari
 * sekarang hanya bekasnya di data: `invoices.channel` dan `invoices.external_id`.
 */
class QrisManual
{
    /**
     * Apakah kode QR bisa ditampilkan.
     *
     * Payload yang salah ketik atau terpotong saat disalin ke env menghasilkan
     * kode QR yang tampak wajar tapi ditolak setiap aplikasi bank. Diperiksa di
     * sini, sekali, supaya kegagalannya muncul sebagai instruksi transfer bank
     * yang benar — bukan sebagai pelanggan yang berdiri di depan layar sambil
     * memindai kode yang tidak akan pernah terbaca.
     */
    public function available(): bool
    {
        $payload = config('billing.qris.payload');

        if (blank($payload)) {
            return false;
        }

        if (! Qris::valid($payload)) {
            Log::warning('QRIS_PAYLOAD tidak lolos pemeriksaan CRC — kode QR disembunyikan.');

            return false;
        }

        return true;
    }

    /**
     * Payload QRIS dinamis untuk satu tagihan, siap dirender jadi kode QR.
     * Null berarti QRIS belum disiapkan; halaman pembayaran menampilkan
     * instruksi transfer bank sebagai gantinya.
     */
    public function payload(Invoice $invoice): ?string
    {
        if (! $this->available()) {
            return null;
        }

        try {
            return Qris::dinamis(config('billing.qris.payload'), $invoice->total);
        } catch (\Throwable $e) {
            Log::warning('Gagal menyusun QRIS dinamis.', [
                'invoice' => $invoice->number,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function merchantName(): string
    {
        return (string) config('billing.qris.merchant');
    }

    /**
     * Rekening bank untuk pembayaran di luar QRIS.
     *
     * Selalu ditampilkan berdampingan dengan kode QR, bukan disembunyikan
     * sebagai jalur darurat: batas QRIS per transaksi mengikuti kebijakan tiap
     * penerbit dompet digital dan bisa berhenti di angka yang lebih rendah dari
     * paket tahunan kami.
     *
     * @return array{name: ?string, account_number: ?string, account_holder: ?string}|null
     */
    public function bankAccount(): ?array
    {
        $bank = config('billing.bank');

        return blank($bank['account_number']) ? null : $bank;
    }
}
