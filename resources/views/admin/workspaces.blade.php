@extends('layouts.admin')
@section('title', 'Workspace')

@section('content')
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="search" name="cari" value="{{ $cari }}" placeholder="Cari nama, slug, atau email pemilik"
               class="w-full max-w-sm rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
        <select name="status" class="rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
            <option value="">Semua status</option>
            @foreach (['trialing' => 'Masa percobaan', 'active' => 'Aktif', 'past_due' => 'Lewat jatuh tempo', 'suspended' => 'Ditangguhkan', 'canceled' => 'Dihentikan'] as $nilai => $label)
                <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90">Cari</button>
    </form>

    <div class="space-y-3">
        @forelse ($workspaces as $workspace)
            @php
                $sub = $workspace->subscription;
                $terpakai = $pemakaian[$workspace->id] ?? 0;
                $kuota = (int) $workspace->monthly_message_quota;
            @endphp

            <x-card x-data="{ buka: false }">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-2 font-semibold">
                            {{ $workspace->name }}
                            @if ($workspace->is_internal)
                                <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">internal</span>
                            @endif
                            @if ($sub)
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-primary/10 text-primary' => in_array($sub->status, ['active', 'trialing'], true),
                                    'bg-destructive/10 text-destructive' => in_array($sub->status, ['past_due', 'suspended'], true),
                                    'bg-muted text-muted-foreground' => $sub->status === 'canceled',
                                ])>{{ $sub->statusLabel() }}</span>
                            @endif
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ $workspace->owner_email ?? $workspace->owner?->email ?? '—' }}
                            @if ($workspace->billing_phone)
                                · {{ $workspace->billing_phone }}
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                        <div>
                            <p class="text-xs text-muted-foreground">Paket</p>
                            <p class="font-medium">{{ $workspace->plan()->name() }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Sesi</p>
                            <p class="font-medium">{{ $workspace->sessions_count }}/{{ $workspace->max_sessions }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Pesan bulan ini</p>
                            <p class="font-medium {{ $kuota > 0 && $terpakai >= $kuota ? 'text-destructive' : '' }}">
                                {{ number_format($terpakai) }}/{{ $kuota === 0 ? '∞' : number_format($kuota) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Berlaku sampai</p>
                            <p class="font-medium">{{ $sub?->current_period_end?->translatedFormat('j M Y') ?? '—' }}</p>
                        </div>
                        <button type="button" @click="buka = ! buka"
                                class="rounded-lg border border-border px-3 py-1.5 text-sm hover:bg-muted"
                                x-text="buka ? 'Tutup' : 'Kelola'"></button>
                    </div>
                </div>

                <div x-show="buka" x-collapse x-cloak>
                    <div class="mt-4 grid gap-4 border-t border-border pt-4 md:grid-cols-2 lg:grid-cols-4">

                        <form method="POST" action="{{ route('admin.workspaces.plan', $workspace->id) }}" class="space-y-2">
                            @csrf
                            <label class="block text-xs font-medium text-muted-foreground">Pindah paket</label>
                            <select name="plan" class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->slug }}" @selected($workspace->plan()->slug === $plan->slug)>{{ $plan->name() }}</option>
                                @endforeach
                            </select>
                            <button class="w-full rounded-lg border border-border px-3 py-1.5 text-sm hover:bg-muted">Terapkan paket</button>
                            <p class="text-xs text-muted-foreground">Batasnya langsung berlaku. Periodenya tidak berubah.</p>
                        </form>

                        <form method="POST" action="{{ route('admin.workspaces.extend', $workspace->id) }}" class="space-y-2">
                            @csrf
                            <label class="block text-xs font-medium text-muted-foreground">Perpanjang tanpa bayar</label>
                            <input type="number" name="hari" min="1" max="365" value="7" required
                                   class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                            <button class="w-full rounded-lg border border-border px-3 py-1.5 text-sm hover:bg-muted">Perpanjang</button>
                            <p class="text-xs text-muted-foreground">Untuk uang yang sudah masuk tapi buktinya belum sampai.</p>
                        </form>

                        <form method="POST" action="{{ route('admin.workspaces.limits', $workspace->id) }}" class="space-y-2">
                            @csrf
                            <label class="block text-xs font-medium text-muted-foreground">Kelonggaran batas</label>
                            <div class="flex gap-2">
                                <input type="number" name="max_sessions" min="0" max="50" value="{{ $workspace->max_sessions }}" required
                                       title="Jumlah sesi" placeholder="Sesi"
                                       class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                                <input type="number" name="monthly_message_quota" min="0" value="{{ $workspace->monthly_message_quota }}" required
                                       title="Kuota pesan per bulan" placeholder="Kuota"
                                       class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                            </div>
                            <button class="w-full rounded-lg border border-border px-3 py-1.5 text-sm hover:bg-muted">Simpan batas</button>
                            <p class="text-xs text-muted-foreground">0 = tanpa batas. Tertimpa saat pembayaran berikutnya.</p>
                        </form>

                        <form method="POST" action="{{ route('admin.workspaces.suspend', $workspace->id) }}" class="space-y-2"
                              onsubmit="return confirm('Yakin mengubah status workspace ini?')">
                            @csrf
                            <label class="block text-xs font-medium text-muted-foreground">Status layanan</label>
                            @if ($sub && in_array($sub->status, ['suspended', 'past_due'], true))
                                <button class="w-full rounded-lg bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:opacity-90">
                                    Aktifkan kembali
                                </button>
                                <p class="text-xs text-muted-foreground">Sesi tidak dinyalakan otomatis — pemiliknya yang menghubungkan.</p>
                            @else
                                <button class="w-full rounded-lg border border-destructive/40 px-3 py-1.5 text-sm text-destructive hover:bg-destructive/10">
                                    Tangguhkan
                                </button>
                                <p class="text-xs text-muted-foreground">Pengiriman berhenti dan sesinya diputus.</p>
                            @endif
                        </form>
                    </div>
                </div>
            </x-card>
        @empty
            <x-card><p class="text-sm text-muted-foreground">Tidak ada workspace yang cocok.</p></x-card>
        @endforelse
    </div>

    <div class="mt-4">{{ $workspaces->links() }}</div>
@endsection
