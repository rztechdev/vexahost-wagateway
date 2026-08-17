@extends('layouts.docs')

@section('title', 'Dokumentasi')
@section('description', 'Dokumentasi lengkap Flustra WA Gateway: instalasi, arsitektur, referensi API, dan panduan operasional.')

@section('content')
    <div class="mb-10">
        <h1 class="text-3xl font-semibold tracking-tight text-foreground">Dokumentasi</h1>
        <p class="mt-3 max-w-2xl text-muted-foreground">
            Panduan memakai Flustra WA Gateway — dari menautkan nomor pertama sampai
            mengintegrasikannya ke aplikasi Anda.
        </p>
    </div>

    {{-- Tiga jalur masuk yang paling sering dibutuhkan --}}
    <div class="mb-12 grid gap-4 sm:grid-cols-3">
        @php
            $pintasan = [
                ['mulai-cepat', 'Baru mulai', 'Dari mendaftar sampai pesan pertama terkirim.'],
                ['referensi-api', 'Integrasikan', 'Referensi endpoint dengan contoh siap salin.'],
                ['praktik-baik', 'Jaga nomor Anda', 'Cara memakai gateway tanpa membuat nomor diblokir.'],
            ];
        @endphp
        @foreach ($pintasan as [$slug, $judul, $isi])
            <a href="{{ route('docs.show', $slug) }}"
               class="group rounded-xl border border-border p-5 transition hover:border-primary/50 hover:bg-primary/5">
                <h2 class="font-semibold text-foreground group-hover:text-primary">{{ $judul }}</h2>
                <p class="mt-1.5 text-sm text-muted-foreground">{{ $isi }}</p>
            </a>
        @endforeach
    </div>

    <div class="space-y-10">
        @foreach ($catalogue as $group => $pages)
            <section>
                <h2 class="mb-4 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ $group }}</h2>
                <div class="divide-y divide-border/50">
                    @foreach ($pages as $slug => $page)
                        <a href="{{ route('docs.show', $slug) }}"
                           class="group flex items-baseline gap-4 py-3">
                            <span class="w-52 shrink-0 font-medium text-foreground group-hover:text-primary">
                                {{ $page['title'] }}
                            </span>
                            <span class="text-sm text-muted-foreground">{{ $page['summary'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    <div class="mt-12 rounded-xl border border-border bg-card p-6 text-card-foreground">
        <h2 class="font-semibold">Kirim pesan pertama Anda</h2>
        <p class="mt-2 text-sm text-muted-foreground">
            Satu permintaan HTTP, itu saja yang dibutuhkan aplikasi Anda.
        </p>
        <pre class="mt-4 overflow-x-auto rounded-lg bg-[var(--code-bg)] p-4 text-[13px] leading-relaxed text-[var(--code-foreground)]"><code>curl -X POST {{ config('app.url') }}/api/v1/messages/text \
  -H "X-Api-Key: fwa_xxxxxxxx.xxxxxxxx" \
  -H "Content-Type: application/json" \
  -d '{"to":"081234567890","message":"Halo!"}'</code></pre>
        <div class="mt-4 flex flex-wrap gap-4 text-sm font-medium">
            <a href="{{ route('docs.show', 'mulai-cepat') }}" class="text-primary hover:underline">
                Panduan mulai cepat &rarr;
            </a>
            <a href="{{ route('docs.show', 'referensi-api') }}" class="text-primary hover:underline">
                Referensi API &rarr;
            </a>
        </div>
    </div>
@endsection
