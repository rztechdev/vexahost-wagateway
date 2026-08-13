@extends('layouts.app')
@section('title', 'Ringkasan')

@section('content')
    @php
        $connected = $sessions->where('status', 'connected')->count();
        $quota = $currentWorkspace->is_internal ? null : $currentWorkspace->monthly_message_quota;
        $percent = $quota ? min(100, round($usage->messages_sent / max($quota, 1) * 100)) : null;
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-card>
            <p class="text-sm text-muted-foreground">Sesi terhubung</p>
            <p class="mt-1 text-2xl font-semibold">{{ $connected }}<span class="text-base font-normal text-muted-foreground">/{{ $sessions->count() }}</span></p>
        </x-card>
        <x-card>
            <p class="text-sm text-muted-foreground">Terkirim bulan ini</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($usage->messages_sent) }}</p>
            @if ($percent !== null)
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                    <div class="h-full rounded-full {{ $percent >= 90 ? 'bg-destructive' : 'bg-primary' }}" style="width: {{ $percent }}%"></div>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">{{ $percent }}% dari kuota {{ number_format($quota) }}</p>
            @endif
        </x-card>
        <x-card>
            <p class="text-sm text-muted-foreground">Diterima bulan ini</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($usage->messages_received) }}</p>
        </x-card>
        <x-card>
            <p class="text-sm text-muted-foreground">Gagal bulan ini</p>
            <p class="mt-1 text-2xl font-semibold {{ $usage->messages_failed > 0 ? 'text-destructive' : '' }}">{{ number_format($usage->messages_failed) }}</p>
        </x-card>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <x-card title="Aktivitas 7 hari terakhir" class="lg:col-span-2">
            <div class="flex h-44 items-end gap-2">
                @foreach ($chart as $day)
                    <div class="flex flex-1 flex-col items-center gap-1">
                        <div class="flex w-full flex-col-reverse items-stretch gap-0.5" style="height: 140px">
                            {{-- Tinggi batang relatif terhadap hari tersibuk, bukan nilai mutlak,
                                 supaya bentuk grafik tetap terbaca pada volume kecil maupun besar. --}}
                            <div class="rounded-t bg-primary" style="height: {{ round($day['outbound'] / $chartMax * 100) }}%" title="Keluar: {{ $day['outbound'] }}"></div>
                            <div class="rounded-t bg-sky-400" style="height: {{ round($day['inbound'] / $chartMax * 100) }}%" title="Masuk: {{ $day['inbound'] }}"></div>
                        </div>
                        <span class="text-xs text-muted-foreground">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 flex gap-4 text-xs text-muted-foreground">
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-primary"></span> Keluar</span>
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-sky-400"></span> Masuk</span>
            </div>
        </x-card>

        <x-card title="Sesi">
            @forelse ($sessions as $session)
                <div class="flex items-center justify-between border-b border-border py-2 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">{{ $session->name }}</p>
                        <p class="text-xs text-muted-foreground">{{ $session->phone_number ?? 'belum tertaut' }}</p>
                    </div>
                    <x-session-status :status="$session->status" />
                </div>
            @empty
                <p class="text-sm text-muted-foreground">Belum ada sesi. <a href="{{ route('sessions.index') }}" class="text-primary hover:underline">Buat sesi pertama</a>.</p>
            @endforelse
        </x-card>
    </div>

    <x-card title="Pesan terbaru" class="mt-6">
        @if ($recentMessages->isEmpty())
            <p class="text-sm text-muted-foreground">Belum ada pesan.</p>
        @else
            <div class="-mx-5 overflow-x-auto">
                <table class="w-full min-w-[600px] text-sm">
                    <thead class="text-left text-xs uppercase text-muted-foreground">
                        <tr class="border-b border-border">
                            <th class="px-5 py-2">Waktu</th>
                            <th class="px-5 py-2">Arah</th>
                            <th class="px-5 py-2">Nomor</th>
                            <th class="px-5 py-2">Isi</th>
                            <th class="px-5 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentMessages as $m)
                            <tr class="border-b border-border last:border-0 hover:bg-muted/50">
                                <td class="whitespace-nowrap px-5 py-2 text-muted-foreground">{{ $m->created_at->format('d M H:i') }}</td>
                                <td class="px-5 py-2">{{ $m->direction === 'outbound' ? 'Keluar' : 'Masuk' }}</td>
                                <td class="whitespace-nowrap px-5 py-2">{{ $m->to_number ?? $m->from_number }}</td>
                                <td class="max-w-xs truncate px-5 py-2 text-muted-foreground">{{ $m->body }}</td>
                                <td class="px-5 py-2">{{ $m->status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection
