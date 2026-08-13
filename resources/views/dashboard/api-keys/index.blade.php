@extends('layouts.app')
@section('title', 'API Keys')

@section('content')
    @if (session('new_api_key'))
        <div class="mb-4 rounded-xl border border-amber-500/20 bg-amber-500/10 p-5">
            <p class="font-semibold text-amber-600">Salin kunci ini sekarang</p>
            <p class="mt-1 text-sm text-amber-600/80">
                Yang tersimpan di server hanya hash-nya, jadi nilai penuh ini tidak akan pernah bisa ditampilkan lagi.
            </p>
            <div class="mt-3 flex gap-2" x-data="{ copied: false }">
                <input readonly value="{{ session('new_api_key') }}" x-ref="key"
                       class="flex-1 rounded-lg border-input bg-background font-mono text-sm focus:border-primary focus:ring-primary">
                <button type="button"
                        @click="navigator.clipboard.writeText($refs.key.value); copied = true; setTimeout(() => copied = false, 2000)"
                        class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                    <span x-text="copied ? 'Tersalin' : 'Salin'"></span>
                </button>
            </div>
        </div>

        {{-- Kunci saja tidak cukup: yang paling sering ditanyakan justru
             "ditempel ke mana". Blok ini menjawabnya sekali jalan. --}}
        @php
            $env = "WA_GATEWAY_URL=".rtrim(config('app.url'), '/')."\n"
                ."WA_GATEWAY_KEY=".session('new_api_key')."\n"
                ."WA_GATEWAY_SESSION=";
        @endphp
        <div class="mb-6 rounded-xl border border-border bg-card p-5" x-data="{ disalin: false }">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-semibold">Tempel ke <code>.env</code> aplikasi Anda</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Biarkan <code>WA_GATEWAY_SESSION</code> kosong — pesan akan dikirim dari sesi yang sedang
                        terhubung. Isi dengan ID sesi hanya kalau Anda punya beberapa nomor dan ingin mengunci
                        pengirimnya. ID sesi ada di halaman <a href="{{ route('sessions.index') }}" class="text-primary hover:underline">Sesi WhatsApp</a>.
                    </p>
                </div>
                <button type="button"
                        @click="navigator.clipboard.writeText($refs.env.textContent.trim()); disalin = true; setTimeout(() => disalin = false, 2000)"
                        class="shrink-0 rounded-lg border border-input px-3 py-1.5 text-sm font-medium hover:bg-muted"
                        x-text="disalin ? 'Tersalin' : 'Salin'"></button>
            </div>
            <pre class="mt-3 overflow-x-auto rounded-lg bg-muted p-3 font-mono text-xs" x-ref="env">{{ $env }}</pre>
        </div>
    @endif

    <x-card title="Buat API key" subtitle="Dipakai aplikasi lain untuk memanggil REST API gateway lewat header X-Api-Key.">
        <form method="POST" action="{{ route('api-keys.store') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-48 flex-1">
                <label class="mb-1 block text-sm font-medium" for="key-name">Nama</label>
                <input id="key-name" name="name" required maxlength="60" placeholder="mis. flustra-erp produksi"
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
            kunci yang bocor dengan scope ini bisa dipakai membombardir nomor orang lain dan membuat nomor platform diblokir.
        </p>
    </x-card>

    <x-card title="Kunci aktif" class="mt-6">
        @if ($keys->isEmpty())
            <p class="text-sm text-muted-foreground">Belum ada API key.</p>
        @else
            <div class="-mx-5 overflow-x-auto">
                <table class="w-full min-w-[700px] text-sm">
                    <thead class="text-left text-xs uppercase text-muted-foreground">
                        <tr class="border-b border-border">
                            <th class="px-5 py-2">Nama</th>
                            <th class="px-5 py-2">Prefix</th>
                            <th class="px-5 py-2">Scope</th>
                            <th class="px-5 py-2">Terakhir dipakai</th>
                            <th class="px-5 py-2">Status</th>
                            <th class="px-5 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($keys as $key)
                            <tr class="border-b border-border last:border-0 hover:bg-muted/50">
                                <td class="px-5 py-2 font-medium">{{ $key->name }}</td>
                                <td class="px-5 py-2 font-mono text-xs">{{ $key->prefix }}…</td>
                                <td class="px-5 py-2 text-xs">{{ implode(', ', $key->scopes ?? ['*']) }}</td>
                                <td class="whitespace-nowrap px-5 py-2 text-muted-foreground">
                                    {{ $key->last_used_at?->diffForHumans() ?? 'belum pernah' }}
                                </td>
                                <td class="px-5 py-2">
                                    @if ($key->revoked_at)
                                        <span class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">dicabut</span>
                                    @else
                                        <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">aktif</span>
                                    @endif
                                </td>
                                <td class="px-5 py-2 text-right">
                                    @unless ($key->revoked_at)
                                        <form method="POST" action="{{ route('api-keys.destroy', $key->id) }}"
                                              onsubmit="return confirm('Cabut kunci {{ $key->name }}? Aplikasi yang memakainya akan langsung ditolak.')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs text-destructive hover:underline">Cabut</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection
