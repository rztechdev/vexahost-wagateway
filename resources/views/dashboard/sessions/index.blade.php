@extends('layouts.app')
@section('title', 'Sesi WhatsApp')

@section('content')
<div
    x-data="qrModal(@js(session('open_qr')))"
    x-init="init()">

    <x-card title="Buat sesi baru" subtitle="Satu sesi = satu nomor WhatsApp.">
        <div class="mb-4 rounded-lg border border-border bg-muted/50 px-4 py-3 text-sm text-muted-foreground">
            <strong class="font-medium text-foreground">Nomor bisa diganti kapan saja.</strong>
            Klik <em>Putus tautan</em> pada sesi, lalu <em>Hubungkan</em> dan scan QR dengan nomor yang baru.
            Yang dijamin justru kebalikannya: nomor yang sudah tertaut <strong class="font-medium text-foreground">tidak akan terputus sendiri</strong>
            hanya karena server di-deploy ulang.
        </div>

        <form method="POST" action="{{ route('sessions.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-48 flex-1">
                <label class="mb-1 block text-sm font-medium" for="name">Nama sesi</label>
                <input id="name" name="name" required maxlength="60" value="{{ old('name') }}"
                       placeholder="mis. CS Utama"
                       class="w-full rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="driver">Driver</label>
                <select id="driver" name="driver" class="rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
                    <option value="wwebjs">WhatsApp Web (scan QR)</option>
                    <option value="fonnte">Fonnte (berbayar)</option>
                    <option value="cloud_api" disabled>Cloud API resmi — belum tersedia</option>
                </select>
            </div>
            <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">Buat sesi</button>
        </form>
    </x-card>

    <div class="mt-6 grid gap-4 md:grid-cols-2">
        @forelse ($sessions as $session)
            <x-card>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="truncate font-semibold">{{ $session->name }}</h3>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            {{ $session->phone_number ? '+'.$session->phone_number : 'Belum tertaut ke nomor' }}
                            @if ($session->push_name) &middot; {{ $session->push_name }} @endif
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Driver {{ $session->driver }}
                        </p>

                        {{-- ID sesi ditampilkan supaya tidak perlu ada yang menelusuri
                             URL atau memanggil API hanya untuk mengunci pengirim ke
                             satu nomor tertentu lewat WA_GATEWAY_SESSION. --}}
                        <div class="mt-2 flex items-center gap-1.5" x-data="{ disalin: false }">
                            <code class="truncate rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground"
                                  x-ref="id">{{ $session->id }}</code>
                            <button type="button" title="Salin ID sesi"
                                    @click="navigator.clipboard.writeText($refs.id.textContent.trim()); disalin = true; setTimeout(() => disalin = false, 2000)"
                                    class="shrink-0 text-[11px] font-medium text-primary hover:underline"
                                    x-text="disalin ? 'Tersalin' : 'Salin ID'"></button>
                        </div>
                    </div>
                    <x-session-status :status="$session->status" />
                </div>

                @if ($session->last_error)
                    <p class="mt-3 rounded-lg bg-destructive/10 px-3 py-2 text-xs text-destructive">{{ $session->last_error }}</p>
                @endif

                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($session->status !== 'connected')
                        <form method="POST" action="{{ route('sessions.connect', $session->id) }}">
                            @csrf
                            <button class="rounded-lg bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90">Hubungkan</button>
                        </form>
                    @else
                        <button type="button" @click="open('{{ $session->id }}')"
                                class="rounded-lg border border-input bg-background px-3 py-1.5 text-sm hover:bg-muted">Lihat status</button>
                        <form method="POST" action="{{ route('sessions.disconnect', $session->id) }}">
                            @csrf
                            <button class="rounded-lg border border-input bg-background px-3 py-1.5 text-sm hover:bg-muted">Hentikan</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('sessions.logout', $session->id) }}"
                          onsubmit="return confirm('Putus tautan nomor ini? Setelah itu Anda bisa Hubungkan lagi dan scan QR dengan nomor mana pun — termasuk nomor yang berbeda.')">
                        @csrf
                        <button title="Putus tautan, lalu Hubungkan lagi untuk memakai nomor lain"
                                class="rounded-lg border border-amber-500/50 px-3 py-1.5 text-sm text-amber-600 hover:bg-amber-500/10">
                            Putus tautan / ganti nomor
                        </button>
                    </form>

                    <form method="POST" action="{{ route('sessions.destroy', $session->id) }}"
                          onsubmit="return confirm('Hapus sesi {{ $session->name }} beserta backup kredensialnya?')">
                        @csrf @method('DELETE')
                        <button class="rounded-lg border border-destructive/50 px-3 py-1.5 text-sm text-destructive hover:bg-destructive/10">Hapus</button>
                    </form>
                </div>
            </x-card>
        @empty
            <x-card class="md:col-span-2">
                <p class="text-sm text-muted-foreground">Belum ada sesi. Buat satu di atas, klik <strong>Hubungkan</strong>, lalu scan QR-nya dengan menu <em>Perangkat Tertaut</em> di WhatsApp.</p>
            </x-card>
        @endforelse
    </div>

    {{-- Modal QR: polling tiap 3 detik hanya selama modal terbuka. --}}
    <div x-show="sessionId" x-cloak
         class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
         @click.self="close()" @keydown.escape.window="close()">
        <div class="w-full max-w-sm rounded-2xl bg-card text-card-foreground p-6 text-center shadow-lg">
            <h3 class="font-semibold">Scan QR WhatsApp</h3>

            <template x-if="state.status === 'connected'">
                <div class="py-8">
                    <p class="text-4xl">&#10003;</p>
                    <p class="mt-2 font-medium text-primary">Terhubung</p>
                    <p class="mt-1 text-sm text-muted-foreground" x-text="state.phone_number ? '+' + state.phone_number : ''"></p>
                    <button @click="window.location.reload()" class="mt-4 rounded-lg bg-primary px-4 py-2 text-sm text-primary-foreground hover:bg-primary/90">Selesai</button>
                </div>
            </template>

            <template x-if="state.status !== 'connected' && state.qr">
                <div>
                    <p class="mt-1 text-sm text-muted-foreground">Buka WhatsApp &rarr; Perangkat Tertaut &rarr; Tautkan Perangkat</p>
                    <img :src="state.qr" alt="QR Code" class="mx-auto mt-4 h-64 w-64 rounded-lg bg-white p-2 shadow-sm border border-border">
                    <p class="mt-3 text-xs text-muted-foreground">QR berganti otomatis setiap beberapa detik.</p>
                </div>
            </template>

            <template x-if="state.status !== 'connected' && !state.qr">
                <div class="py-12">
                    <p class="text-sm text-muted-foreground" x-text="state.error || 'Menyiapkan sesi, mohon tunggu…'"></p>
                </div>
            </template>

            <button @click="close()" class="mt-4 text-sm text-muted-foreground hover:underline">Tutup</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function qrModal(autoOpenId) {
    return {
        sessionId: null,
        timer: null,
        state: { status: 'pending', qr: null, phone_number: null, error: null },

        init() {
            // Setelah menekan "Hubungkan", controller menandai sesi mana yang
            // baru dinyalakan supaya modalnya langsung terbuka tanpa klik lagi.
            if (autoOpenId) this.open(autoOpenId);
        },

        open(id) {
            this.sessionId = id;
            this.state = { status: 'connecting', qr: null, phone_number: null, error: null };
            this.poll();
            this.timer = setInterval(() => this.poll(), 3000);
        },

        close() {
            this.sessionId = null;
            clearInterval(this.timer);
            this.timer = null;
        },

        async poll() {
            if (!this.sessionId) return;

            try {
                const res = await fetch(`/sessions/${this.sessionId}/status`, {
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) return;

                this.state = await res.json();

                // Berhenti polling begitu tersambung — membiarkannya jalan
                // hanya menambah request tanpa informasi baru.
                if (this.state.status === 'connected') {
                    clearInterval(this.timer);
                    this.timer = null;
                }
            } catch (e) {
                this.state.error = 'Gagal menghubungi server.';
            }
        },
    };
}
</script>
@endpush
@endsection
