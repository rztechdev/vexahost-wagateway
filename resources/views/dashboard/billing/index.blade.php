@extends('layouts.app')
@section('title', 'Langganan')

@section('content')
    @include('dashboard.billing._nav')

    @php
        $sisa = $subscription->daysRemaining();
        $paket = $subscription->plan();
        $kuota = (int) $currentWorkspace->monthly_message_quota;
        $persen = $kuota > 0 ? min(100, round($usage->messages_sent / max($kuota, 1) * 100)) : null;
    @endphp

    {{-- ===================== Keadaan langganan =====================

         Pertanyaan yang paling sering dibawa orang ke halaman ini cuma satu:
         "berapa lama lagi ini berlaku?" Jawabannya karena itu ditaruh paling
         atas dan paling besar, bukan diselipkan di antara daftar paket.
         ============================================================= --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-muted-foreground">Paket sekarang</p>
                    <p class="mt-1 flex flex-wrap items-center gap-2.5 text-3xl font-semibold tracking-tight">
                        {{ $paket->name() }}
                        <span @class([
                            'rounded-full px-2.5 py-1 text-xs font-medium',
                            'bg-primary/10 text-primary' => in_array($subscription->status, ['active', 'trialing'], true),
                            'bg-destructive/10 text-destructive' => in_array($subscription->status, ['past_due', 'suspended'], true),
                            'bg-muted text-muted-foreground' => $subscription->status === 'canceled',
                        ])>{{ $subscription->statusLabel() }}</span>
                    </p>
                </div>

                @if ($bolehBayar)
                    <a href="{{ route('billing.plans') }}"
                       class="rounded-lg border border-border px-4 py-2 text-sm font-medium transition hover:bg-muted">
                        Ganti paket
                    </a>
                @endif
            </div>

            @if ($subscription->current_period_end && ! $subscription->isUnpaid())
                <div class="mt-6 border-t border-border pt-5">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p class="text-sm text-muted-foreground">
                                {{ $subscription->isUsable() ? 'Berlaku sampai' : 'Berakhir' }}
                            </p>
                            <p class="mt-1 text-lg font-medium">
                                {{ $subscription->current_period_end->translatedFormat('j F Y') }}
                            </p>
                        </div>

                        @if ($subscription->isUsable() && $sisa >= 0)
                            <p class="text-sm text-muted-foreground">
                                {{ $sisa === 0 ? 'berakhir hari ini' : ($sisa === 1 ? 'tinggal 1 hari' : "tinggal {$sisa} hari") }}
                            </p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Keadaan yang butuh tindakan diberi warna; keadaan normal tidak.
                 Kalau semuanya berwarna, tidak ada yang menonjol. --}}
            @if ($subscription->isUnpaid())
                <div class="mt-5 rounded-lg border border-primary/20 bg-primary/10 px-4 py-3.5 text-sm text-primary">
                    <p class="font-medium">Workspace ini belum berlangganan.</p>
                    <p class="mt-1 leading-relaxed">
                        Pilih paket untuk menautkan nomor WhatsApp dan mulai mengirim pesan.
                        Riwayat, template, dan API key tetap bisa Anda siapkan lebih dulu.
                    </p>
                </div>
            @elseif ($subscription->status === 'past_due')
                <div class="mt-5 rounded-lg border border-destructive/20 bg-destructive/10 px-4 py-3.5 text-sm text-destructive">
                    <p class="font-medium">Pengiriman pesan sedang berhenti.</p>
                    <p class="mt-1 leading-relaxed">
                        Nomor WhatsApp Anda <strong>masih tertaut</strong> dan tidak perlu discan ulang.
                        @if ($subscription->sessionsCutOffAt())
                            Sesi baru akan dilepas kalau belum dibayar sampai
                            {{ $subscription->sessionsCutOffAt()->translatedFormat('j F Y') }}.
                        @endif
                    </p>
                </div>
            @elseif ($subscription->status === 'suspended')
                <div class="mt-5 rounded-lg border border-destructive/20 bg-destructive/10 px-4 py-3.5 text-sm text-destructive">
                    <p class="font-medium">Langganan ditangguhkan dan sesi sudah dilepas.</p>
                    <p class="mt-1 leading-relaxed">
                        Riwayat dan pengaturan Anda tetap tersimpan. Setelah pembayaran,
                        nomor bisa dihubungkan lagi dari halaman Sesi.
                    </p>
                </div>
            @elseif ($subscription->isExpiringSoon())
                <div class="mt-5 rounded-lg border border-amber-500/20 bg-amber-500/10 px-4 py-3.5 text-sm text-amber-700 dark:text-amber-300">
                    Perpanjang sebelum {{ $subscription->current_period_end->translatedFormat('j F Y') }}
                    supaya pengiriman tidak terputus.
                </div>
            @endif
        </x-card>

        {{-- ===================== Pemakaian ===================== --}}
        <x-card>
            <p class="text-sm text-muted-foreground">Pesan keluar bulan ini</p>
            <p class="mt-1 text-3xl font-semibold tracking-tight">
                {{ number_format($usage->messages_sent) }}
            </p>
            <p class="mt-0.5 text-sm text-muted-foreground">
                dari {{ $kuota === 0 ? 'kuota tanpa batas' : number_format($kuota).' kuota paket' }}
            </p>

            @if ($persen !== null)
                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-muted">
                    <div class="h-full rounded-full {{ $persen >= 90 ? 'bg-destructive' : 'bg-primary' }}"
                         style="width: {{ $persen }}%"></div>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">{{ $persen }}% terpakai</p>
            @endif

            <dl class="mt-6 space-y-2.5 border-t border-border pt-5 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Nomor aktif</dt>
                    <dd class="font-medium">{{ $currentWorkspace->sessions()->count() }} / {{ $currentWorkspace->max_sessions }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Riwayat pesan</dt>
                    <dd class="font-medium">{{ $currentWorkspace->messageRetentionDays() }} hari</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-muted-foreground">Batas API</dt>
                    <dd class="font-medium">{{ $currentWorkspace->api_rate_limit_per_minute }}/menit</dd>
                </div>
            </dl>
        </x-card>
    </div>

    {{-- ===================== Tagihan yang menunggu =====================

         Yang butuh tindakan sekarang tidak berbagi tempat dengan yang sudah
         selesai; riwayat punya halamannya sendiri.
         ============================================================= --}}
    @if ($tagihanTerbuka)
        <x-card class="mt-4 border-primary/50">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="font-semibold">Tagihan {{ $tagihanTerbuka->number }} menunggu pembayaran</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ $tagihanTerbuka->plan()->name() }} · {{ $tagihanTerbuka->periodLabel() }} ·
                        Rp {{ number_format($tagihanTerbuka->total, 0, ',', '.') }}
                        @if ($tagihanTerbuka->due_at)
                            · bayar sebelum {{ $tagihanTerbuka->due_at->translatedFormat('j F Y, H:i') }}
                        @endif
                    </p>
                </div>
                <a href="{{ route('billing.invoice', $tagihanTerbuka->id) }}"
                   class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                    Lihat cara bayar
                </a>
            </div>
        </x-card>
    @elseif ($bolehBayar && ! $subscription->isUsable())
        <x-card class="mt-4 border-primary/50">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <p class="text-sm">Pilih paket untuk menyalakan kembali layanan Anda.</p>
                <a href="{{ route('billing.plans') }}"
                   class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                    Pilih paket
                </a>
            </div>
        </x-card>
    @endif

    @unless ($bolehBayar)
        <p class="mt-4 text-sm text-muted-foreground">
            Hanya owner atau admin workspace yang bisa mengurus langganan.
        </p>
    @endunless
@endsection
