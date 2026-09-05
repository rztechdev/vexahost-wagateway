@extends('layouts.admin')
@section('title', 'Ringkasan')

@section('content')
    @php
        $persenKapasitas = $kapasitasSesi > 0 ? min(100, round($sesiHidup / $kapasitasSesi * 100)) : 0;
    @endphp

    {{-- ===================== Kapasitas =====================

         Ditaruh paling atas, sendirian, dengan sengaja. Ini satu-satunya angka
         di panel yang membatasi berapa banyak pelanggan yang bisa dilayani sama
         sekali: tiap sesi berarti satu Chromium yang memakan 300-500 MB, dan
         batasnya ditegakkan engine. Begitu penuh, pelanggan berikutnya yang
         membayar tidak akan bisa menautkan nomornya.
         ============================================================= --}}
    <x-card @class(['mb-6', 'border-destructive' => $persenKapasitas >= 90])>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">Kapasitas sesi WhatsApp</p>
                <p class="mt-1 text-3xl font-semibold">
                    {{ $sesiHidup }}<span class="text-lg font-normal text-muted-foreground">/{{ $kapasitasSesi }}</span>
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ $sesiTersambung }} tersambung penuh · batas dari <code>WA_MAX_SESSIONS</code> milik engine
                </p>
            </div>
            <div class="min-w-48 flex-1">
                <div class="h-2 overflow-hidden rounded-full bg-muted">
                    <div class="h-full rounded-full {{ $persenKapasitas >= 90 ? 'bg-destructive' : ($persenKapasitas >= 70 ? 'bg-amber-500' : 'bg-primary') }}"
                         style="width: {{ $persenKapasitas }}%"></div>
                </div>
                @if ($persenKapasitas >= 90)
                    <p class="mt-2 text-sm font-medium text-destructive">
                        Hampir penuh. Pelanggan berikutnya kemungkinan gagal menautkan nomor —
                        putus sesi yang menganggur, atau naikkan RAM sebelum menjual paket lagi.
                    </p>
                @endif
            </div>
        </div>
    </x-card>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-card>
            <p class="text-sm text-muted-foreground">Pendapatan bulan ini</p>
            <p class="mt-1 text-2xl font-semibold">Rp {{ number_format($pendapatanBulanIni, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-muted-foreground">dari tagihan yang ditandai lunas</p>
        </x-card>
        <x-card @class(['border-primary' => $tagihanPerluDiperiksa > 0])>
            <p class="text-sm text-muted-foreground">Bukti menunggu diperiksa</p>
            <p class="mt-1 text-2xl font-semibold {{ $tagihanPerluDiperiksa > 0 ? 'text-primary' : '' }}">{{ $tagihanPerluDiperiksa }}</p>
            <p class="mt-1 text-xs text-muted-foreground">{{ $tagihanMenunggu }} tagihan menunggu bayar</p>
            @if ($tagihanPerluDiperiksa > 0)
                <a href="{{ route('admin.invoices') }}" class="mt-2 inline-block text-xs text-primary hover:underline">Periksa sekarang →</a>
            @endif
        </x-card>
        <x-card>
            <p class="text-sm text-muted-foreground">Workspace</p>
            <p class="mt-1 text-2xl font-semibold">{{ $workspaceAktif }}<span class="text-base font-normal text-muted-foreground">/{{ $workspaceTotal }}</span></p>
            <p class="mt-1 text-xs text-muted-foreground">aktif dari seluruhnya</p>
        </x-card>
        <x-card>
            <p class="text-sm text-muted-foreground">Pesan keluar bulan ini</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($pesanBulanIni) }}</p>
            <p class="mt-1 text-xs text-muted-foreground">
                antrean {{ $antrean }}{{ $antreanGagal > 0 ? " · {$antreanGagal} gagal" : '' }}
            </p>
        </x-card>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <x-card title="Langganan per status">
            @php
                $label = [
                    'trialing' => 'Masa percobaan',
                    'active' => 'Aktif',
                    'past_due' => 'Lewat jatuh tempo',
                    'suspended' => 'Ditangguhkan',
                    'canceled' => 'Dihentikan',
                ];
            @endphp
            <ul class="space-y-2 text-sm">
                @foreach ($label as $status => $teks)
                    <li class="flex items-center justify-between border-b border-border py-1.5 last:border-0">
                        <span class="text-muted-foreground">{{ $teks }}</span>
                        <span class="font-medium">{{ $langgananPerStatus[$status] ?? 0 }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <x-card title="Berakhir dalam 7 hari"
                subtitle="Yang belum membayar sampai tanggalnya akan berhenti mengirim otomatis.">
            @forelse ($akanBerakhir as $subscription)
                <div class="flex items-center justify-between gap-3 border-b border-border py-2 text-sm last:border-0">
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $subscription->workspace?->name ?? '—' }}</p>
                        <p class="text-xs text-muted-foreground">{{ $subscription->plan()->name() }} · {{ $subscription->statusLabel() }}</p>
                    </div>
                    <span class="shrink-0 text-xs text-muted-foreground">
                        {{ $subscription->current_period_end->translatedFormat('j M') }}
                    </span>
                </div>
            @empty
                <p class="text-sm text-muted-foreground">Tidak ada yang berakhir minggu ini.</p>
            @endforelse
        </x-card>
    </div>
@endsection
