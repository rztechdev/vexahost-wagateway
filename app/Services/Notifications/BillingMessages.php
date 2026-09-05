<?php

namespace App\Services\Notifications;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\WaSession;
use App\Models\Workspace;

/**
 * Isi seluruh pemberitahuan WhatsApp, di satu tempat.
 *
 * Dikumpulkan begini bukan demi kerapian melainkan demi nada. Pesan yang
 * ditulis satu-satu di tempat masing-masing pelan-pelan berbeda gaya: yang
 * satu menyapa, yang lain memerintah, yang ketiga menyingkat sampai terbaca
 * seperti robot. Pelanggan menerima semuanya dari nomor yang sama, dan
 * perbedaan itu terbaca sebagai ketidakrapian — atau lebih buruk, sebagai
 * penipuan.
 *
 * Aturan yang berlaku untuk semua teks di bawah:
 *
 * - **Selalu sebut nomor tagihan atau nama workspace.** Penerima bisa saja
 *   mengurus beberapa workspace, dan pesan tanpa penanda memaksa mereka menebak.
 * - **Sebut jumlah uang secara lengkap sampai digit terakhir.** Tiga digit
 *   terakhir adalah kode unik yang menentukan pembayaran bisa dikenali.
 * - **Satu ajakan, bukan tiga.** Pesan yang menawarkan banyak pilihan berakhir
 *   tidak dikerjakan sama sekali.
 * - **Jangan pernah menyertakan kata sandi, API key, atau tautan masuk
 *   otomatis.** WhatsApp diteruskan orang jauh lebih sering dari yang dikira.
 */
class BillingMessages
{
    private static function rupiah(int $angka): string
    {
        return 'Rp '.number_format($angka, 0, ',', '.');
    }

    /** Bukti sudah kami terima. Menutup keraguan yang dulu berujung pembatalan. */
    public static function proofReceived(Invoice $invoice): string
    {
        return "*Bukti pembayaran diterima*\n\n"
            ."Tagihan {$invoice->number} sebesar ".self::rupiah($invoice->total)." sedang kami periksa.\n\n"
            .'Anda tidak perlu mengirim ulang atau membayar lagi. Kami beri tahu lewat pesan ini '
            .'begitu langganannya aktif.';
    }

    /** Pembayaran dikonfirmasi — pesan yang paling ditunggu di seluruh alur. */
    public static function paymentConfirmed(Invoice $invoice, Subscription $subscription): string
    {
        $sampai = $subscription->current_period_end?->translatedFormat('j F Y') ?? '-';

        return "*Pembayaran dikonfirmasi* ✅\n\n"
            ."Tagihan {$invoice->number} sebesar ".self::rupiah($invoice->total)." sudah lunas.\n\n"
            ."Paket: {$subscription->plan()->name()}\n"
            ."Workspace: {$invoice->workspace?->name}\n"
            ."Berlaku sampai: {$sampai}\n\n"
            .'Layanan Anda aktif sekarang. Kalau nomor WhatsApp Anda sempat terlepas, '
            .'hubungkan lagi dari halaman Sesi — kredensialnya masih tersimpan, tidak perlu scan QR ulang.';
    }

    /** Bukti tidak bisa diterima. Harus menyebut alasannya, kalau tidak ia cuma penolakan. */
    public static function proofRejected(Invoice $invoice, string $alasan): string
    {
        return "*Bukti pembayaran perlu diperbaiki*\n\n"
            ."Tagihan {$invoice->number} sebesar ".self::rupiah($invoice->total)." kami buka kembali.\n\n"
            ."Alasan: {$alasan}\n\n"
            .'Silakan kirim ulang bukti pembayaran dari halaman tagihan Anda. '
            .'Kalau menurut Anda ini keliru, balas pesan ini.';
    }

    /** Pengingat sebelum masa berlaku habis. */
    public static function expiringSoon(Subscription $subscription, int $sisaHari): string
    {
        $nama = $subscription->workspace?->name;
        $paket = $subscription->plan()->name();
        $tanggal = $subscription->current_period_end->translatedFormat('j F Y');

        $pembuka = match (true) {
            $sisaHari <= 0 => "Langganan {$paket} untuk workspace *{$nama}* berakhir *hari ini* ({$tanggal}).",
            $sisaHari === 1 => "Langganan {$paket} untuk workspace *{$nama}* berakhir *besok* ({$tanggal}).",
            default => "Langganan {$paket} untuk workspace *{$nama}* berakhir *{$sisaHari} hari lagi*, pada {$tanggal}.",
        };

        return "*Masa berlaku langganan*\n\n"
            .$pembuka."\n\n"
            .'Setelah tanggal itu pengiriman pesan berhenti, tapi nomor WhatsApp Anda tetap tertaut dan '
            .'tidak perlu discan ulang selama '.config('billing.grace_days')." hari.\n\n"
            .'Perpanjang dari menu Langganan di dashboard.';
    }

    /** Pengiriman berhenti karena lewat jatuh tempo. */
    public static function serviceStopped(Subscription $subscription): string
    {
        $batas = $subscription->sessionsCutOffAt()?->translatedFormat('j F Y');

        return "*Pengiriman pesan dihentikan sementara*\n\n"
            ."Langganan untuk workspace *{$subscription->workspace?->name}* sudah lewat jatuh tempo, "
            ."jadi pengiriman pesan lewat dashboard maupun API berhenti mulai sekarang.\n\n"
            .'Nomor WhatsApp Anda *masih tertaut* dan tidak perlu discan ulang'
            .($batas ? ", tapi akan dilepas kalau belum dibayar sampai {$batas}." : '.')."\n\n"
            .'Perpanjang dari menu Langganan untuk menyalakannya kembali.';
    }

    /** Sesi dilepas setelah masa tenggang habis. */
    public static function sessionsReleased(Subscription $subscription): string
    {
        return "*Nomor WhatsApp Anda dilepas*\n\n"
            ."Masa tenggang untuk workspace *{$subscription->workspace?->name}* sudah habis, "
            ."jadi nomor yang tertaut kami lepas untuk membebaskan sumber daya.\n\n"
            .'Riwayat pesan, template, API key, dan pengaturan Anda *tetap tersimpan*. '
            .'Setelah pembayaran, hubungkan nomornya lagi dari halaman Sesi.';
    }

    /**
     * Nomor pelanggan terputus dari WhatsApp.
     *
     * Ini pemberitahuan paling berharga di produk ini: gateway yang mati diam
     * membuat pelanggan baru sadar setelah berhari-hari pesan tidak terkirim,
     * saat pelanggan *mereka* yang mengeluh.
     */
    public static function sessionDisconnected(WaSession $session): string
    {
        return "*Nomor WhatsApp Anda terputus* ⚠️\n\n"
            ."Sesi *{$session->name}* di workspace *{$session->workspace?->name}* "
            ."tidak lagi tersambung, jadi pesan keluar dari nomor itu berhenti terkirim.\n\n"
            .'Penyebab tersering: perangkat ditautkan ulang dari ponsel, atau WhatsApp '
            ."memutus tautan karena ponselnya lama tidak online.\n\n"
            .'Buka halaman Sesi di dashboard untuk menghubungkannya lagi.';
    }

    /** Kuota hampir habis. */
    public static function quotaWarning(Workspace $workspace, int $terpakai, int $kuota): string
    {
        $sisa = max(0, $kuota - $terpakai);

        return "*Kuota pesan hampir habis*\n\n"
            ."Workspace *{$workspace->name}* sudah memakai ".number_format($terpakai, 0, ',', '.')
            .' dari '.number_format($kuota, 0, ',', '.')." pesan bulan ini.\n\n"
            .'Sisa *'.number_format($sisa, 0, ',', '.').' pesan*. Setelah itu pengiriman berhenti '
            ."sampai kuota bulan berikutnya, kecuali paketnya dinaikkan.\n\n"
            .'Naikkan paket dari menu Langganan kalau perlu ruang lebih.';
    }

    /** Kuota benar-benar habis. */
    public static function quotaExhausted(Workspace $workspace, int $kuota): string
    {
        return "*Kuota pesan habis* ⚠️\n\n"
            ."Workspace *{$workspace->name}* sudah memakai seluruh ".number_format($kuota, 0, ',', '.')
            ." pesan bulan ini, jadi pengiriman berhenti sampai kuota bulan depan.\n\n"
            .'Nomor Anda tetap tertaut dan pesan masuk tetap diterima. '
            .'Naikkan paket dari menu Langganan kalau perlu melanjutkan sekarang.';
    }

    /**
     * Ke tim kami sendiri: ada bukti pembayaran yang menunggu diperiksa.
     *
     * Selama pencocokan masih manual, tagihan hanya menjadi lunas kalau ada
     * orang yang membukanya di panel — dan tanpa pesan ini, tidak ada apa pun
     * yang memberi tahu bahwa ada yang perlu dibuka.
     */
    public static function adminProofWaiting(Invoice $invoice): string
    {
        return "*Bukti pembayaran baru*\n\n"
            ."{$invoice->number} · ".self::rupiah($invoice->total)."\n"
            ."Workspace: {$invoice->workspace?->name}\n"
            ."Paket: {$invoice->plan()->name()} · {$invoice->periodLabel()}\n"
            .($invoice->unique_code > 0 ? 'Kode unik: '.str_pad((string) $invoice->unique_code, 3, '0', STR_PAD_LEFT)."\n" : '')
            ."\nPeriksa di panel admin → Tagihan.";
    }
}
