<?php

namespace App\Http\Controllers;

use App\Models\EnterpriseLead;
use App\Services\Notifications\EmailNotifier;
use App\Services\Notifications\Notifier;
use App\Services\Notifications\WhatsAppNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Form "Hubungi kami" untuk paket Enterprise.
 *
 * Terbuka untuk pengunjung yang belum punya akun, dan itu disengaja: yang
 * paling sering butuh lebih dari Elite adalah orang yang sedang menimbang
 * apakah produk ini sanggup, bukan pelanggan yang sudah masuk. Memaksa mereka
 * mendaftar lebih dulu berarti kehilangan mereka di langkah yang tidak perlu
 * ada.
 *
 * Rate limit-nya dipasang di rute (`throttle:enterprise`), bukan di sini —
 * form publik tanpa batas adalah undangan bagi bot untuk mengisi tabel ini
 * sampai daftar yang sungguhan tidak bisa ditemukan lagi.
 */
class EnterpriseLeadController extends Controller
{
    public function store(
        Request $request,
        WhatsAppNotifier $notifier,
        EmailNotifier $email,
        Notifier $notifikasi,
    ): RedirectResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['required', 'string', 'max:30'],
            'estimated_sessions' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'estimated_messages' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'needs' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'name' => 'nama',
            'company' => 'perusahaan',
            'phone' => 'nomor WhatsApp',
            'estimated_sessions' => 'perkiraan jumlah nomor',
            'estimated_messages' => 'perkiraan pesan per bulan',
            'needs' => 'kebutuhan',
        ]);

        $lead = EnterpriseLead::create($data + [
            // Diisi kalau kebetulan sedang login, supaya admin tidak perlu
            // mencocokkan email dengan akun secara manual.
            'user_id' => $request->user()?->id,
            'workspace_id' => $request->user() ? session('current_workspace_id') : null,
            'status' => 'baru',
        ]);

        /*
         | Tim dikabari lewat WhatsApp DAN email.
         |
         | Permintaan penawaran adalah calon pelanggan terbesar yang pernah
         | mengetuk, dan yang menentukan ia jadi atau tidak hampir selalu
         | seberapa cepat dijawab. WhatsApp saja berarti satu titik yang kalau
         | mati — nomor Flustra terputus, dan itu memang terjadi saat deploy
         | atau saat WhatsApp memutus perangkat tertaut — membuat seluruh kabar
         | diam tanpa satu pun gejala.
         |
         | Isi kebutuhannya sengaja TIDAK ikut di keduanya: pelanggan sering
         | menempelkan nama klien dan angka pendapatan di sana, dan keduanya
         | diteruskan serta diarsipkan di tempat yang tidak kami kendalikan.
        */
        $ringkas = $lead->name.($lead->company ? " · {$lead->company}" : '')."\n"
            .$lead->email.' · '.$lead->phone."\n"
            .($lead->estimated_sessions ? "Perkiraan nomor: {$lead->estimated_sessions}\n" : '')
            .($lead->estimated_messages
                ? 'Perkiraan pesan/bulan: '.number_format($lead->estimated_messages, 0, ',', '.')."\n"
                : '');

        $notifier->toAdmin(
            "*Permintaan Enterprise baru*\n\n".$ringkas."\nBuka panel admin → Enterprise untuk menjawabnya.",
            "enterprise-lead:{$lead->id}",
        );

        $email->kabarTim(
            'Permintaan Enterprise baru — '.($lead->company ?: $lead->name),
            $ringkas,
            "enterprise-lead:{$lead->id}",
            route('admin.enterprise.show', $lead->id),
        );

        $notifikasi->keAdmin(
            type: 'enterprise.lead',
            title: 'Permintaan Enterprise dari '.($lead->company ?: $lead->name),
            body: $lead->email.' · '.$lead->phone,
            url: route('admin.enterprise.show', $lead->id),
            level: 'warning',
            dedupe: "enterprise-lead:{$lead->id}",
        );

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Permintaan terkirim',
            'pesan' => 'Terima kasih, '.$lead->name.'. Tim kami menghubungi Anda lewat WhatsApp '
                .'atau email dalam 1×24 jam pada hari kerja.',
        ]);
    }
}
