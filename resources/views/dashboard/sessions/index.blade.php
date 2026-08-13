@extends('layouts.app')
@section('title', 'Sesi WhatsApp')

@section('content')
<div
    x-data="qrModal(@js(session('open_qr')))"
    x-init="init()">

    <x-card title="Buat sesi baru" subtitle="Satu sesi = satu nomor WhatsApp.">
        <div class="mb-4 rounded-lg border border-stone-200 bg-stone-50 px-4 py-3 text-sm text-stone-600 dark:border-stone-800 dark:bg-stone-800/50 dark:text-stone-400">
            <strong class="font-medium text-stone-800 dark:text-stone-200">Nomor bisa diganti kapan saja.</strong>
            Klik <em>Putus tautan</em> pada sesi, lalu <em>Hubungkan</em> dan scan QR dengan nomor yang baru.
            Yang dijamin justru kebalikannya: nomor yang sudah tertaut <strong class="font-medium text-stone-800 dark:text-stone-200">tidak akan terputus sendiri</strong>
            hanya karena server di-deploy ulang.
        </div>

        <form method="POST" action="{{ route('sessions.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-48 flex-1">
                <label class="mb-1 block text-sm font-medium" for="name">Nama sesi</label>
                <input id="name" name="name" required maxlength="60" value="{{ old('name') }}"
                       placeholder="mis. CS Utama"
                       class="w-full rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="driver">Driver</label>
                <select id="driver" name="driver" class="rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800">
                    <option value="wwebjs">WhatsApp Web (scan QR)</option>
                    <option value="fonnte">Fonnte (berbayar)</option>
                    <option value="cloud_api" disabled>Cloud API resmi — belum tersedia</option>
                </select>
            </div>
            <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Buat sesi</button>
        </form>
    </x-card>

    <div class="mt-6 grid gap-4 md:grid-cols-2">
        @forelse ($sessions as $session)
            <x-card>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="truncate font-semibold">{{ $session->name }}</h3>
                        <p class="mt-0.5 text-sm text-stone-500">
                            {{ $session->phone_number ? '+'.$session->phone_number : 'Belum tertaut ke nomor' }}
                            @if ($session->push_name) &middot; {{ $session->push_name }} @endif
                        </p>
                        <p class="mt-1 text-xs text-stone-400">
                            Driver {{ $session->driver }} &middot;
                            @if ($session->kind === 'platform')
                                <span class="font-medium text-amber-600">Nomor platform Flustra</span>
                            @else
                                Nomor tenant
                            @endif
                        </p>
                    </div>
                    <x-session-status :status="$session->status" />
                </div>

                @if ($session->last_error)
                    <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700 dark:bg-red-950 dark:text-red-300">{{ $session->last_error }}</p>
                @endif

                <div class="mt-4 flex flex-wrap gap-2">
                    @if ($session->status !== 'connected')
                        <form method="POST" action="{{ route('sessions.connect', $session->id) }}">
                            @csrf
                            <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">Hubungkan</button>
                        </form>
                    @else
                        <button type="button" @click="open('{{ $session->id }}')"
                                class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm dark:border-stone-700">Lihat status</button>
                        <form method="POST" action="{{ route('sessions.disconnect', $session->id) }}">
                            @csrf
                            <button class="rounded-lg border border-stone-300 px-3 py-1.5 text-sm dark:border-stone-700">Hentikan</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('sessions.logout', $session->id) }}"
                          onsubmit="return confirm('Putus tautan nomor ini? Setelah itu Anda bisa Hubungkan lagi dan scan QR dengan nomor mana pun — termasuk nomor yang berbeda.')">
                        @csrf
                        <button title="Putus tautan, lalu Hubungkan lagi untuk memakai nomor lain"
                                class="rounded-lg border border-amber-300 px-3 py-1.5 text-sm text-amber-700 dark:border-amber-800 dark:text-amber-400">
                            Putus tautan / ganti nomor
                        </button>
                    </form>

                    <form method="POST" action="{{ route('sessions.destroy', $session->id) }}"
                          onsubmit="return confirm('Hapus sesi {{ $session->name }} beserta backup kredensialnya?')">
                        @csrf @method('DELETE')
                        <button class="rounded-lg border border-red-300 px-3 py-1.5 text-sm text-red-700 dark:border-red-900 dark:text-red-400">Hapus</button>
                    </form>
                </div>
            </x-card>
        @empty
            <x-card class="md:col-span-2">
                <p class="text-sm text-stone-500">Belum ada sesi. Buat satu di atas, klik <strong>Hubungkan</strong>, lalu scan QR-nya dengan menu <em>Perangkat Tertaut</em> di WhatsApp.</p>
            </x-card>
        @endforelse
    </div>

    {{-- Modal QR: polling tiap 3 detik hanya selama modal terbuka. --}}
    <div x-show="sessionId" x-cloak
         class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4"
         @click.self="close()" @keydown.escape.window="close()">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center dark:bg-stone-900">
            <h3 class="font-semibold">Scan QR WhatsApp</h3>

            <template x-if="state.status === 'connected'">
                <div class="py-8">
                    <p class="text-4xl">&#10003;</p>
                    <p class="mt-2 font-medium text-emerald-600">Terhubung</p>
                    <p class="mt-1 text-sm text-stone-500" x-text="state.phone_number ? '+' + state.phone_number : ''"></p>
                    <button @click="window.location.reload()" class="mt-4 rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white">Selesai</button>
                </div>
            </template>

            <template x-if="state.status !== 'connected' && state.qr">
                <div>
                    <p class="mt-1 text-sm text-stone-500">Buka WhatsApp &rarr; Perangkat Tertaut &rarr; Tautkan Perangkat</p>
                    <img :src="state.qr" alt="QR Code" class="mx-auto mt-4 h-64 w-64 rounded-lg bg-white p-2">
                    <p class="mt-3 text-xs text-stone-400">QR berganti otomatis setiap beberapa detik.</p>
                </div>
            </template>

            <template x-if="state.status !== 'connected' && !state.qr">
                <div class="py-12">
                    <p class="text-sm text-stone-500" x-text="state.error || 'Menyiapkan sesi, mohon tunggu…'"></p>
                </div>
            </template>

            <button @click="close()" class="mt-4 text-sm text-stone-500 hover:underline">Tutup</button>
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
