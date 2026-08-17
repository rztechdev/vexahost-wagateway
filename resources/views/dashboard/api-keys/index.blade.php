@extends('layouts.app')
@section('title', 'API Keys')

@section('content')
    <x-card title="Buat API key" subtitle="Dipakai aplikasi Anda untuk memanggil REST API gateway lewat header X-Api-Key.">
        <form method="POST" action="{{ route('api-keys.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-48 flex-1">
                <label class="mb-1 block text-sm font-medium" for="key-name">Nama</label>
                <input id="key-name" name="name" required maxlength="60" placeholder="mis. Aplikasi kasir — produksi"
                       class="w-full rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Scope</label>
                <div class="flex gap-3 text-sm">
                    <label class="flex items-center gap-1.5"><input type="checkbox" name="scopes[]" value="*" checked> Semua</label>
                    <label class="flex items-center gap-1.5"><input type="checkbox" name="scopes[]" value="otp"> OTP</label>
                </div>
            </div>
            <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">Buat</button>
        </form>
        <p class="mt-3 text-xs text-muted-foreground">
            Scope <code>otp</code> memberi kemampuan mengirim kode verifikasi. Jangan diberikan ke kunci integrasi biasa —
            kunci yang bocor dengan scope ini bisa dipakai membombardir nomor orang lain dan membuat nomor Anda diblokir.
        </p>
    </x-card>

    <x-card class="mt-6" title="Kunci workspace {{ $currentWorkspace->name }}"
            subtitle="Setiap workspace punya kuncinya sendiri. Kunci di sini tidak berlaku untuk workspace lain.">
        @if ($keys->isEmpty())
            <p class="text-sm text-muted-foreground">Belum ada API key di workspace ini.</p>
        @else
            <div class="space-y-4">
                @foreach ($keys as $key)
                    @php $baru = session('kunci_baru_id') == $key->id; @endphp

                    <div class="rounded-xl border p-4 {{ $baru ? 'border-primary/40 bg-primary/5' : 'border-border' }}"
                         x-data="{ terlihat: false, disalin: '' }">

                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium">{{ $key->name }}</p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    Scope {{ implode(', ', $key->scopes ?? ['*']) }}
                                    &middot; {{ $key->last_used_at ? 'terakhir dipakai '.$key->last_used_at->diffForHumans() : 'belum pernah dipakai' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($key->revoked_at)
                                    <span class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">dicabut</span>
                                @else
                                    <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">aktif</span>
                                    @if ($bolehLihatKunci)
                                        <form method="POST" action="{{ route('api-keys.destroy', $key->id) }}"
                                              onsubmit="return confirm('Cabut kunci {{ $key->name }}? Aplikasi yang memakainya akan langsung ditolak.')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs text-destructive hover:underline">Cabut</button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </div>

                        @if (! $bolehLihatKunci)
                            <p class="mt-3 rounded-lg bg-muted px-3 py-2 font-mono text-xs text-muted-foreground">
                                {{ $key->prefix }}…&nbsp;&nbsp;<span class="font-sans">Hanya owner dan admin yang bisa melihat nilai penuhnya.</span>
                            </p>
                        @elseif ($key->revoked_at)
                            <p class="mt-3 rounded-lg bg-muted px-3 py-2 font-mono text-xs text-muted-foreground">
                                {{ $key->prefix }}…&nbsp;&nbsp;<span class="font-sans">Nilai kunci dibuang saat dicabut.</span>
                            </p>
                        @elseif ($key->plainKey() === null)
                            {{-- Kunci lama, dibuat ketika yang tersimpan hanya hash-nya. --}}
                            <p class="mt-3 rounded-lg border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
                                <span class="font-mono">{{ $key->prefix }}…</span> — kunci ini dibuat sebelum nilai penuhnya ikut disimpan,
                                jadi tidak bisa ditampilkan lagi. Kalau catatannya hilang, cabut kunci ini lalu buat yang baru.
                            </p>
                        @else
                            <div class="mt-3 flex gap-2">
                                <div class="relative flex-1">
                                    <input readonly x-ref="kunci" :type="terlihat ? 'text' : 'password'"
                                           value="{{ $key->plainKey() }}"
                                           class="w-full rounded-lg border-input bg-background pr-10 font-mono text-sm focus:border-primary focus:ring-primary">
                                    <button type="button" @click="terlihat = ! terlihat"
                                            class="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground hover:text-foreground"
                                            :aria-label="terlihat ? 'Sembunyikan kunci' : 'Tampilkan kunci'"
                                            :title="terlihat ? 'Sembunyikan kunci' : 'Tampilkan kunci'">
                                        {{-- Mata terbuka: sedang tersembunyi, klik untuk menampilkan --}}
                                        <svg x-show="! terlihat" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        {{-- Mata tercoret: sedang tampil, klik untuk menyembunyikan --}}
                                        <svg x-show="terlihat" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><path d="M1 1l22 22"/>
                                        </svg>
                                    </button>
                                </div>
                                <button type="button"
                                        @click="navigator.clipboard.writeText($refs.kunci.value); disalin = 'kunci'; setTimeout(() => disalin = '', 2000)"
                                        class="shrink-0 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                                        x-text="disalin === 'kunci' ? 'Tersalin' : 'Salin'"></button>
                            </div>

                            {{-- Kunci saja tidak cukup: yang paling sering ditanyakan justru
                                 "ditempel ke mana". Blok ini menjawabnya sekali jalan, dan
                                 ikut tiap kunci — bukan sekali saat pembuatan — supaya yang
                                 punya beberapa kunci tidak perlu menyusunnya sendiri. --}}
                            @php
                                $env = "WA_GATEWAY_URL=".rtrim(config('app.url'), '/')."\n"
                                    ."WA_GATEWAY_KEY=".$key->plainKey()."\n"
                                    ."WA_GATEWAY_SESSION=";
                            @endphp
                            <div class="mt-3" x-data="{ buka: {{ $baru ? 'true' : 'false' }} }">
                                <button type="button" @click="buka = ! buka"
                                        class="text-xs font-medium text-primary hover:underline"
                                        x-text="buka ? 'Sembunyikan cuplikan .env' : 'Tampilkan cuplikan .env'"></button>

                                <div x-show="buka" x-cloak class="mt-2 rounded-lg border border-border bg-muted/40 p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="text-xs text-muted-foreground">
                                            Tempel ke <code>.env</code> aplikasi Anda. Biarkan <code>WA_GATEWAY_SESSION</code> kosong —
                                            pesan dikirim dari nomor yang sedang terhubung di workspace ini. Isi dengan ID sesi hanya kalau
                                            Anda punya beberapa nomor dan ingin mengunci pengirimnya; ID-nya ada di halaman
                                            <a href="{{ route('sessions.index') }}" class="text-primary hover:underline">Sesi WhatsApp</a>.
                                        </p>
                                        <button type="button"
                                                @click="navigator.clipboard.writeText($refs.env.textContent.trim()); disalin = 'env'; setTimeout(() => disalin = '', 2000)"
                                                class="shrink-0 rounded-lg border border-input bg-background px-3 py-1.5 text-xs font-medium hover:bg-muted"
                                                x-text="disalin === 'env' ? 'Tersalin' : 'Salin'"></button>
                                    </div>
                                    <pre class="mt-2 overflow-x-auto rounded-lg bg-muted p-3 font-mono text-xs" x-ref="env">{{ $env }}</pre>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if ($bolehLihatKunci)
            <p class="mt-4 border-t border-border pt-4 text-xs text-muted-foreground">
                Kunci disimpan terenkripsi supaya Anda bisa membukanya lagi kapan saja — tidak perlu mencatatnya di tempat lain,
                dan tempat lain itulah yang paling sering menjadi sumber kebocoran. Siapa pun yang bisa masuk ke akun Anda sebagai
                owner atau admin juga bisa membukanya, jadi jaga akun ini sebaik Anda menjaga kuncinya. Kunci yang sudah terlanjur
                tersebar sebaiknya dicabut, bukan dipakai ulang.
            </p>
        @endif
    </x-card>
@endsection
