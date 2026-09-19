<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Surat percobaan dari halaman Pemberitahuan & Pengecualian.
 *
 * Sengaja TIDAK memakai `Queueable`: yang menekan tombolnya sedang menunggu
 * jawaban, dan email yang masuk antrean akan menjawab "berhasil" sebelum ada
 * satu pun sambungan SMTP dibuka. Itu persis kebalikan dari gunanya tombol
 * ini — kegagalan Brevo (pengirim belum diverifikasi, kredensial salah) hanya
 * terlihat kalau pengirimannya benar-benar terjadi saat itu juga.
 */
class TesEmail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tes email keluar — VexaHost WA Gateway');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.tes', with: [
            'dikirimPada' => now()->translatedFormat('j F Y, H:i'),
            'pengirim' => config('mail.from.address'),
            'mailer' => config('mail.default'),
        ]);
    }
}
