<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Throwable;

/**
 * Menerjemahkan kegagalan engine menjadi kalimat yang boleh dibaca pelanggan.
 *
 * Sebelum ini pesan mentahnya dilempar apa adanya ke layar:
 *
 *   "Gagal menghubungi engine: cURL error 7: Failed to connect to 127.0.0.1
 *    port 3100 after 2034 ms: Couldn't connect to server (see
 *    https://curl.se/libcurl/c/libcurl-errors.html) for
 *    http://127.0.0.1:3100/sessions/01m1ttj.../start"
 *
 * Tiga hal salah sekaligus di situ. Pelanggan tidak tahu apa itu "engine",
 * "cURL error 7", atau port 3100 — yang ia butuhkan cuma tahu apakah nomornya
 * hilang dan apa yang harus dilakukan sekarang. Kalimat itu juga membocorkan
 * alamat dan port internal kami beserta ULID sesi ke layar yang bisa
 * di-screenshot siapa saja. Dan yang paling merugikan: ia terbaca seperti
 * kerusakan permanen, padahal engine yang sedang di-deploy ulang adalah
 * keadaan sementara yang tidak memutus satu pun nomor yang sudah tertaut.
 *
 * Rinciannya tidak dibuang — ia tetap masuk log lewat pemanggilnya. Yang
 * berpindah cuma tempatnya: ke log, bukan ke muka pengguna.
 */
class EngineError
{
    /**
     * Kalimat untuk pelanggan. Selalu menyebut apa yang terjadi pada nomornya,
     * karena itu satu-satunya pertanyaan yang benar-benar ada di kepala orang
     * saat layar ini muncul.
     */
    public static function pesan(Throwable $e): string
    {
        if ($e instanceof ConnectionException) {
            return 'Layanan WhatsApp sedang tidak bisa dihubungi. '
                .'Nomor yang sudah tertaut tidak terputus dan tidak perlu discan ulang — '
                .'coba lagi beberapa menit lagi.';
        }

        $asli = $e->getMessage();

        // Batas kapasitas engine disebut dengan nama variabel envnya; itu
        // informasi untuk kami, bukan untuk pelanggan yang cuma perlu tahu
        // bahwa ini antrean, bukan kesalahan mereka.
        if (str_contains($asli, 'WA_MAX_SESSIONS') || str_contains($asli, 'batas')) {
            return 'Kapasitas nomor aktif sedang penuh. '
                .'Tim kami sudah diberi tahu — coba lagi beberapa saat lagi.';
        }

        // Pesan yang memang sudah ditulis untuk dibaca manusia dan tidak
        // membocorkan apa pun boleh lewat apa adanya. Patokannya sederhana dan
        // sengaja konservatif: tidak ada jejak teknis di dalamnya.
        if ($asli !== '' && ! self::teknis($asli)) {
            return $asli;
        }

        return 'Layanan WhatsApp gagal menjalankan permintaan ini. '
            .'Nomor yang sudah tertaut tidak terpengaruh. Kalau berulang, hubungi kami.';
    }

    /**
     * Kalimat yang memuat jejak teknis: alamat, port, nama pustaka, kode galat.
     *
     * Daftarnya sengaja tidak mencoba lengkap — apa pun yang tidak dikenali
     * jatuh ke kalimat umum, jadi yang lolos ke layar hanya yang benar-benar
     * sudah ditulis untuk dibaca orang.
     */
    private static function teknis(string $pesan): bool
    {
        foreach (['cURL', 'http://', 'https://', '127.0.0.1', 'localhost', 'Exception', 'SQLSTATE', 'port '] as $jejak) {
            if (str_contains($pesan, $jejak)) {
                return true;
            }
        }

        return false;
    }
}
