<?php

namespace App\Services;

use App\Jobs\SendMessageJob;
use App\Models\Message;
use App\Models\WaSession;
use App\Models\Workspace;
use App\Services\Notifications\BillingMessages;
use App\Services\Notifications\WhatsAppNotifier;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Satu-satunya pintu masuk pembuatan pesan keluar. Baik REST API, dashboard,
 * maupun OTP semuanya lewat sini supaya pencatatan, kuota, dan antrean
 * diperlakukan sama.
 */
class MessageDispatcher
{
    /**
     * @param  array{type?: string, body?: ?string, media_path?: ?string, media_mime?: ?string, media_filename?: ?string, batch_id?: ?string}  $attributes
     */
    public function queue(WaSession $session, string $to, array $attributes = []): Message
    {
        $workspace = $session->workspace;

        /*
         | Nomor milik perusahaan sendiri lewat tanpa pemeriksaan tagihan.
         |
         | Diperiksa dari SESI-nya, bukan dari workspace-nya: nomor itu bisa
         | menumpang di workspace pelanggan mana pun, dan menumpangnya tidak
         | boleh membuat seluruh workspace itu ikut bebas. Yang dibebaskan
         | nomornya, bukan tempat ia tertaut.
        */
        if (! $session->isSpecial()) {
            $this->guardWorkspace($workspace);
        }

        $normalized = PhoneNumber::normalize($to);

        if ($normalized === null && ! str_ends_with($to, '@g.us')) {
            throw new RuntimeException("Nomor tujuan tidak valid: {$to}");
        }

        $message = Message::create([
            'workspace_id' => $workspace->id,
            'wa_session_id' => $session->id,
            /*
             | API key yang mengantrekan pesan ini, kalau ada.
             |
             | DISIMPAN SAAT ANTRE, bukan dibaca lagi saat kirim. Pengiriman
             | terjadi di dalam job — di sana tidak ada request, jadi tidak ada
             | yang bisa ditanya "API key mana yang sedang dipakai". Tanpa kolom
             | ini, webhook `message.status` tidak punya cara tahu integrasi
             | mana yang berhak menerimanya.
             |
             | `null` berarti dikirim dari dashboard, dan itu benar: statusnya
             | jatuh ke webhook tingkat workspace.
            */
            'api_key_id' => $attributes['api_key_id'] ?? null,
            'direction' => 'outbound',
            'chat_id' => PhoneNumber::toChatId($to),
            'to_number' => $normalized ?? $to,
            'type' => $attributes['type'] ?? 'text',
            'body' => $attributes['body'] ?? null,
            'media_path' => $attributes['media_path'] ?? null,
            'media_mime' => $attributes['media_mime'] ?? null,
            'media_filename' => $attributes['media_filename'] ?? null,
            'batch_id' => $attributes['batch_id'] ?? null,
            'status' => 'queued',
        ]);

        // Kuota dinaikkan saat antre, bukan saat terkirim: kalau dihitung
        // belakangan, satu workspace bisa mengantrekan puluhan ribu pesan dulu
        // lalu baru ketahuan melewati batas.
        $this->incrementUsage($workspace, 'messages_sent');

        SendMessageJob::dispatch($message->id);

        return $message;
    }

    /**
     * Mengantre banyak tujuan sekaligus. Semua pesan berbagi satu batch_id
     * supaya bisa ditelusuri dan dibatalkan sebagai satu kesatuan.
     *
     * @param  array<int, string>  $recipients
     * @return array{batch_id: string, messages: array<int, Message>, rejected: array<int, array{to: string, reason: string}>}
     */
    public function queueBulk(WaSession $session, array $recipients, array $attributes = []): array
    {
        $batchId = (string) Str::ulid();
        $messages = [];
        $rejected = [];

        foreach (array_unique($recipients) as $to) {
            try {
                $messages[] = $this->queue($session, $to, $attributes + ['batch_id' => $batchId]);
            } catch (RuntimeException $e) {
                // Satu nomor rusak tidak boleh menggagalkan seluruh broadcast;
                // pemanggil tetap diberi tahu nomor mana yang ditolak.
                $rejected[] = ['to' => $to, 'reason' => $e->getMessage()];
            }
        }

        return ['batch_id' => $batchId, 'messages' => $messages, 'rejected' => $rejected];
    }

    public function incrementUsage(Workspace $workspace, string $column, int $amount = 1): void
    {
        $period = now()->format('Y-m');

        // upsert + increment mentah supaya aman dari race saat banyak worker
        // memproses pesan workspace yang sama secara bersamaan.
        DB::table('usage_counters')->upsert(
            [[
                'workspace_id' => $workspace->id,
                'period' => $period,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['workspace_id', 'period'],
            ['updated_at']
        );

        DB::table('usage_counters')
            ->where('workspace_id', $workspace->id)
            ->where('period', $period)
            ->increment($column, $amount);
    }

    private function guardWorkspace(Workspace $workspace): void
    {
        if (! $workspace->isActive()) {
            /*
             | Dibedakan supaya pesan galatnya benar di kedua keadaan. Yang
             | masa berlakunya lewat perlu tahu bahwa ini soal perpanjangan,
             | bukan kerusakan — terutama karena yang membacanya sering kali
             | bukan manusia melainkan log aplikasi lain yang memanggil API
             | kami, dan di situlah kalimatnya menentukan apakah ada yang
             | menindaklanjuti atau tidak.
            */
            throw new RuntimeException($workspace->serviceExpired()
                ? 'Masa berlaku langganan workspace ini sudah habis. Perpanjang untuk mengirim lagi.'
                : 'Workspace sedang tidak aktif.');
        }

        /*
         | Jatah coba gratis dihitung seumur hidup workspace, bukan per bulan.
         |
         | Kalau ia ikut jalur kuota bulanan di bawah, angkanya kembali penuh
         | tiap tanggal 1 dan lima pesan gratis diam-diam berubah menjadi lima
         | pesan gratis setiap bulan — selamanya, untuk siapa pun yang tidak
         | pernah membayar. Kueri tambahannya hanya berjalan untuk workspace
         | yang memang sedang memakai paket gratis; pelanggan berbayar tidak
         | membayar ongkosnya.
        */
        if ($workspace->isFreeTier()) {
            $jatah = (int) $workspace->monthly_message_quota;
            $terpakaiSeluruhnya = $workspace->freeMessagesUsed();

            if ($terpakaiSeluruhnya >= $jatah) {
                throw new RuntimeException(
                    "Jatah {$jatah} pesan coba gratis sudah habis. Pilih paket untuk melanjutkan."
                );
            }

            return;
        }

        $kuota = (int) $workspace->monthly_message_quota;

        if ($workspace->isExempt() || $kuota === 0) {
            return;
        }

        /*
         | Pemeriksaan kuota ditulis di sini alih-alih memanggil
         | `hasQuotaRemaining()`, karena baris pemakaiannya dibutuhkan dua kali:
         | untuk menolak saat penuh, dan untuk memutuskan perlu-tidaknya
         | peringatan. Memanggil keduanya berarti dua kueri pada tabel yang
         | paling sering ditulis di sistem ini, untuk setiap pesan yang dikirim.
         |
         | `hasQuotaRemaining()` tetap ada dan tetap dipakai di tempat lain yang
         | hanya butuh jawabannya.
        */
        $terpakai = (int) $workspace->currentUsage()->messages_sent;

        if ($terpakai >= $kuota) {
            throw new RuntimeException(
                "Kuota pesan bulan ini sudah habis ({$kuota} pesan)."
            );
        }

        $this->warnIfQuotaLow($workspace, $terpakai + 1, $kuota);
    }

    /**
     * Memberi tahu saat kuota mendekati dan mencapai batasnya.
     *
     * Kuota yang habis tanpa peringatan terasa seperti kerusakan, bukan seperti
     * batas paket: pengiriman berhenti di tengah hari kerja dan pemiliknya baru
     * tahu dari pelanggan yang tidak menerima apa-apa. Satu pesan di 80% memberi
     * ruang memutuskan sebelum berhenti terjadi.
     *
     * Ini berjalan pada setiap pesan, jadi urutannya sengaja: yang paling murah
     * diperiksa lebih dulu, dan `WhatsAppNotifier` — yang memegang penanda "sudah
     * pernah dikirim" di cache — baru disentuh setelah ambangnya benar terlampaui.
     * Notifier-nya diambil dari container di sini, bukan lewat constructor,
     * karena ia sendiri memakai `MessageDispatcher` dan menyuntikkannya akan
     * menutup lingkaran.
     */
    private function warnIfQuotaLow(Workspace $workspace, int $terpakai, int $kuota): void
    {
        if (blank($workspace->billing_phone)) {
            return;
        }

        $tingkat = match (true) {
            $terpakai >= $kuota => 'habis',
            $terpakai >= (int) ($kuota * 0.8) => 'hampir',
            default => null,
        };

        if ($tingkat === null) {
            return;
        }

        $periode = now()->format('Y-m');

        app(WhatsAppNotifier::class)->toWorkspace(
            $workspace,
            $tingkat === 'habis'
                ? BillingMessages::quotaExhausted($workspace, $kuota)
                : BillingMessages::quotaWarning($workspace, $terpakai, $kuota),
            "quota:{$tingkat}:{$workspace->id}:{$periode}",
            24 * 40,
        );
    }
}
