<!DOCTYPE html>
<html lang="id" class="scroll-smooth overflow-x-clip">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Gateway WhatsApp untuk Aplikasi Anda</title>
    <meta name="description" content="Kirim notifikasi WhatsApp dari aplikasi Anda lewat satu REST API. Multi-nomor, webhook pesan masuk, riwayat pengiriman, dan sesi yang tidak putus saat server di-deploy ulang.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        if ('IntersectionObserver' in window && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.documentElement.classList.add('animasi-muncul');
        }
    </script>
</head>
<body class="bg-background text-foreground antialiased selection:bg-primary selection:text-primary-foreground overflow-x-clip w-full relative" x-data="{ mobileMenu: false }">

@php
    $menuHeader = [
        'produk' => [
            'label' => 'Produk',
            'grup' => [
                'Gateway ini' => [
                    ['Fitur', '#fitur'],
                    ['Cara kerja', '#cara-kerja'],
                    ['Harga', '#harga'],
                    ['Dokumentasi', route('docs.index')],
                ],
                'Produk Flustra lain' => collect(config('flustra.produk'))
                    ->map(fn ($alamat, $nama) => [$nama, $alamat])
                    ->values()
                    ->all(),
            ],
        ],
        'developer' => [
            'label' => 'Developer',
            'grup' => [
                'Mulai' => [
                    ['Mulai cepat', route('docs.show', 'mulai-cepat')],
                    ['Menautkan nomor', route('docs.show', 'menautkan-nomor')],
                    ['Mengirim pesan', route('docs.show', 'mengirim-pesan')],
                    ['Contoh integrasi', route('docs.show', 'contoh-integrasi')],
                ],
                'Rujukan' => [
                    ['Referensi API', route('docs.show', 'referensi-api')],
                    ['Webhook', route('docs.show', 'webhook')],
                    ['API key', route('docs.show', 'api-key')],
                    ['Batas & kuota', route('docs.show', 'batas-dan-kuota')],
                ],
            ],
        ],
        'sumber' => [
            'label' => 'Sumber daya',
            'grup' => [
                'Panduan' => [
                    ['Praktik baik', route('docs.show', 'praktik-baik')],
                    ['Template pesan', route('docs.show', 'template-pesan')],
                    ['FAQ', route('docs.show', 'faq')],
                    ['Glosarium', route('docs.show', 'glosarium')],
                ],
            ],
        ],
    ];
@endphp

{{-- ===================== Header ===================== --}}
<header class="sticky top-0 z-50 w-full border-b border-border/70 bg-background/80 backdrop-blur-md transition-all">
    <div class="mx-auto flex max-w-[1440px] items-center justify-between gap-4 px-4 sm:px-6 lg:px-8 xl:px-10 py-3 sm:py-3.5">
        <a href="/" class="flex items-center gap-2.5 font-semibold text-foreground transition-opacity hover:opacity-90">
            <img src="{{ asset('images/flustra-wa.png') }}" alt="Logo" class="h-7 w-auto object-contain">
            <span class="text-sm sm:text-base font-semibold tracking-tight">Flustra WA Gateway</span>
        </a>

        <nav class="ml-auto flex items-center gap-1 text-sm">
            <div class="hidden items-center gap-2 md:flex"
                 x-data="{
                     menu: null,
                     jeda: null,
                     buka(nama) { clearTimeout(this.jeda); this.menu = nama },
                     tutup() { clearTimeout(this.jeda); this.menu = null },
                     tundaTutup() { clearTimeout(this.jeda); this.jeda = setTimeout(() => this.menu = null, 140) },
                 }"
                 @keydown.escape.window="tutup()">
                @foreach ($menuHeader as $kunci => $menu)
                    <div class="relative" @mouseenter="buka('{{ $kunci }}')" @mouseleave="tundaTutup()">
                        <button type="button"
                                @click="menu === '{{ $kunci }}' ? tutup() : buka('{{ $kunci }}')"
                                :aria-expanded="menu === '{{ $kunci }}' ? 'true' : 'false'"
                                aria-haspopup="true"
                                class="group relative flex items-center gap-1.5 px-2.5 py-2 text-xs sm:text-sm font-medium transition-colors hover:text-foreground"
                                :class="menu === '{{ $kunci }}' ? 'text-foreground' : 'text-muted-foreground'">
                            <span>{{ $menu['label'] }}</span>
                            <svg class="h-3 w-3 transition-transform duration-200" :class="menu === '{{ $kunci }}' ? 'rotate-180 text-primary' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                            <span class="absolute bottom-0 left-2.5 right-2.5 h-0.5 bg-primary transition-all duration-200"
                                  :class="menu === '{{ $kunci }}' ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"></span>
                        </button>

                        <div x-show="menu === '{{ $kunci }}'" x-cloak
                             x-transition:enter="tirai-masuk"
                             x-transition:leave="tirai-keluar"
                             class="absolute left-0 top-full z-50 mt-1.5 flex gap-1 rounded-xl border border-border bg-popover/95 p-1.5 shadow-lg backdrop-blur-xl">
                            @foreach ($menu['grup'] as $judulGrup => $isiGrup)
                                <div class="min-w-52">
                                    <p class="px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ $judulGrup }}</p>
                                    @foreach ($isiGrup as [$nama, $alamat])
                                        <a href="{{ $alamat }}" @click="tutup()"
                                           class="block whitespace-nowrap rounded-lg px-3 py-1.5 text-xs sm:text-sm text-foreground/80 transition-colors hover:bg-muted hover:text-foreground">
                                            {{ $nama }}
                                        </a>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <a href="#cara-kerja" class="group relative px-2.5 py-2 text-xs sm:text-sm font-medium text-muted-foreground transition-colors hover:text-foreground">
                    <span>Cara Kerja</span>
                    <span class="absolute bottom-0 left-2.5 right-2.5 h-0.5 bg-primary opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                </a>
                <a href="#fitur" class="group relative px-2.5 py-2 text-xs sm:text-sm font-medium text-muted-foreground transition-colors hover:text-foreground">
                    <span>Fitur</span>
                    <span class="absolute bottom-0 left-2.5 right-2.5 h-0.5 bg-primary opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                </a>
                <a href="#harga" class="group relative px-2.5 py-2 text-xs sm:text-sm font-medium text-muted-foreground transition-colors hover:text-foreground">
                    <span>Harga</span>
                    <span class="absolute bottom-0 left-2.5 right-2.5 h-0.5 bg-primary opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                </a>
            </div>

            <button @click="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'"
                    class="ml-1 rounded-lg p-2 text-muted-foreground hover:text-foreground transition-colors"
                    aria-label="Toggle Dark Mode">
                <svg class="hidden h-4 w-4 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                <svg class="block h-4 w-4 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
            </button>

            <a href="{{ route('docs.index') }}" class="group relative hidden md:block px-2.5 py-2 text-xs sm:text-sm font-medium text-foreground transition-colors">
                <span>Docs</span>
                <span class="absolute bottom-0 left-2.5 right-2.5 h-0.5 bg-primary opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
            </a>

            <div class="hidden items-center gap-3 pl-2 md:flex">
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-lg bg-primary px-3.5 py-1.5 text-xs sm:text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90 transition-all">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="group relative px-2.5 py-2 text-xs sm:text-sm font-medium text-muted-foreground transition-colors hover:text-foreground">
                        <span>Masuk</span>
                        <span class="absolute bottom-0 left-2.5 right-2.5 h-0.5 bg-primary opacity-0 transition-opacity duration-200 group-hover:opacity-100"></span>
                    </a>
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-lg bg-primary px-3.5 py-1.5 text-xs sm:text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90 transition-all">
                        Buat akun
                    </a>
                @endauth
            </div>

            <button @click="mobileMenu = !mobileMenu" class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted md:hidden" aria-label="Menu Mobile">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </nav>
    </div>
</header>

{{-- ===================== Mobile Drawer ===================== --}}
<div x-show="mobileMenu" x-cloak
     x-transition:enter="transition-transform duration-300 ease-out"
     x-transition:enter-start="translate-y-full"
     x-transition:enter-end="translate-y-0"
     x-transition:leave="transition-transform duration-250 ease-in"
     x-transition:leave-start="translate-y-0"
     x-transition:leave-end="translate-y-full"
     class="fixed inset-0 z-50 flex flex-col bg-background md:hidden">

    <div class="flex items-center justify-between border-b border-border px-5 py-4">
        <a href="/" class="flex items-center gap-2 font-semibold text-foreground">
            <img src="{{ asset('images/flustra-wa.png') }}" alt="Logo" class="h-6 w-auto object-contain">
            <span class="text-sm">Flustra WA Gateway</span>
        </a>
        <button @click="mobileMenu = false" class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted" aria-label="Tutup Menu">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
        @foreach ($menuHeader as $menu)
            @foreach ($menu['grup'] as $judulGrup => $isiGrup)
                <div class="space-y-1.5">
                    <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ $judulGrup }}</p>
                    <div class="space-y-1 pl-1">
                        @foreach ($isiGrup as [$nama, $alamat])
                            <a href="{{ $alamat }}" @click="mobileMenu = false" class="block py-1 text-sm font-medium text-foreground hover:text-primary transition-colors">{{ $nama }}</a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endforeach

        <div class="border-t border-border pt-3">
            <button @click="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'" class="flex w-full items-center justify-between py-1.5 text-sm font-medium text-foreground">
                <span>Tema Tampilan</span>
                <svg class="hidden h-4 w-4 dark:block text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                <svg class="block h-4 w-4 dark:hidden text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
            </button>
        </div>
    </div>

    {{-- Bottom Bar: Fixed / Pinned Auth Actions --}}
    <div class="shrink-0 border-t border-border bg-card/95 backdrop-blur-md p-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-6">
        @auth
            <a href="{{ route('dashboard') }}" class="block w-full text-center rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-xs hover:bg-primary/90 transition-all">Dashboard</a>
        @else
            <div class="grid grid-cols-2 gap-2.5">
                <a href="{{ route('login') }}" class="flex items-center justify-center rounded-xl border border-input bg-background px-4 py-2.5 text-sm font-semibold text-foreground hover:bg-muted transition-all text-center">Masuk</a>
                <a href="{{ route('register') }}" class="flex items-center justify-center rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow-xs hover:bg-primary/90 transition-all text-center">Buat akun</a>
            </div>
        @endauth
    </div>
</div>

{{-- ===================== Hero Section ===================== --}}
<section class="relative flex min-h-[calc(100vh-4rem)] flex-col justify-center overflow-hidden py-12 sm:py-16 lg:py-20">
    <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid items-center gap-10 lg:grid-cols-12 lg:gap-12 xl:gap-16">
            {{-- Sisi Kiri: Judul, Ringkasan, Aksi --}}
            <div class="min-w-0 lg:col-span-6 xl:col-span-6">
                <h1 class="muncul text-4xl sm:text-5xl lg:text-[2.85rem] xl:text-[3.25rem] font-bold tracking-tight text-foreground leading-[1.15]">
                    Kirim WhatsApp dari aplikasi Anda <span class="text-primary font-bold">lewat satu REST API</span>
                </h1>

                <p class="muncul mt-6 text-base sm:text-lg leading-relaxed text-muted-foreground max-w-xl" style="--tunda: 70ms">
                    Tautkan nomor sekali, lalu kirim notifikasi transaksi, OTP, tagihan, dan pesan pelanggan dari bahasa atau framework apa pun tanpa khawatir sesi terputus saat deploy ulang.
                </p>

                <div class="muncul mt-8 sm:mt-9 flex flex-wrap items-center gap-3.5" style="--tunda: 130ms">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm sm:text-base font-semibold text-primary-foreground shadow-sm hover:bg-primary/90 transition-all active:scale-[0.98]">
                        <span>Buat akun</span>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </a>
                    <a href="#harga" class="inline-flex items-center justify-center rounded-xl border border-input bg-card px-6 py-3 text-sm sm:text-base font-medium text-foreground hover:bg-muted transition-all">
                        Lihat harga
                    </a>
                </div>

                <div class="muncul mt-8 flex flex-wrap items-center gap-x-6 gap-y-2.5 text-xs sm:text-sm text-muted-foreground" style="--tunda: 180ms">
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        Putus tautan kapan saja
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        Tanpa ikatan kontrak
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        Setup instan &lt; 1 menit
                    </span>
                </div>
            </div>

            {{-- Sisi Kanan: Terminal cURL --}}
            <div class="muncul min-w-0 lg:col-span-6 xl:col-span-6" style="--tunda: 200ms">
                <div class="overflow-hidden rounded-2xl border border-border/80 bg-[var(--code-chrome)] shadow-xl">
                    <div class="flex items-center justify-between border-b border-white/10 bg-black/40 px-3.5 sm:px-4 py-3 gap-2">
                        <div class="flex items-center gap-1.5 sm:gap-2 min-w-0">
                            <span class="h-2.5 w-2.5 sm:h-3 sm:w-3 shrink-0 rounded-full bg-[#ff5f56]"></span>
                            <span class="h-2.5 w-2.5 sm:h-3 sm:w-3 shrink-0 rounded-full bg-[#ffbd2e]"></span>
                            <span class="h-2.5 w-2.5 sm:h-3 sm:w-3 shrink-0 rounded-full bg-[#27c93f]"></span>
                            <span class="ml-1 sm:ml-2 font-mono text-xs text-[var(--code-muted)] truncate">kirim-notifikasi.sh</span>
                        </div>
                        <span class="rounded bg-primary/20 border border-primary/30 px-2 sm:px-2.5 py-0.5 font-mono text-[10px] sm:text-[11px] font-semibold text-primary shrink-0">POST /api/v1/messages/text</span>
                    </div>
                    <pre class="overflow-x-auto p-5 font-mono text-xs sm:text-[13px] leading-relaxed text-[var(--code-foreground)]"><code><span class="text-[#f43f5e]">curl</span> -X POST https://wa.flustra.id/api/v1/messages/text \
  -H <span class="text-[var(--code-string)]">"X-Api-Key: fwa_live_9a8b7c6d..."</span> \
  -H <span class="text-[var(--code-string)]">"Content-Type: application/json"</span> \
  -d <span class="text-[var(--code-payload)]">'{
    "to": "081234567890",
    "message": "Invoice INV-001 sudah lunas. Terima kasih!"
  }'</span>

<span class="text-[var(--code-muted)]">→ { "success": true, "data": { "status": "queued", "id": "msg_8921" } }</span></code></pre>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ===================== Social Proof / Ekosistem ===================== --}}
<section class="border-y border-border/60 bg-muted/30 py-7 sm:py-8">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-5 px-4 sm:px-6 lg:px-8">
        <p class="muncul text-xs sm:text-sm font-medium text-muted-foreground">
            Dipakai sendiri oleh produk Flustra
        </p>
        <div class="muncul flex flex-wrap items-center gap-x-7 gap-y-2.5" style="--tunda: 80ms">
            @foreach (config('flustra.produk') as $nama => $alamat)
                <a href="{{ $alamat }}" class="text-xs sm:text-sm font-medium text-foreground/75 hover:text-primary transition-colors">
                    {{ $nama }}
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- ===================== Cara Kerja ===================== --}}
<section id="cara-kerja" class="py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
            <h2 class="muncul text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                Tiga langkah, sekali saja
            </h2>
            <p class="muncul mt-2.5 text-xs sm:text-sm text-muted-foreground" style="--tunda: 60ms">
                Tanpa konfigurasi server mandiri yang merepotkan. Hubungkan nomor dan langsung pakai.
            </p>
        </div>

        <div class="relative mt-12 sm:mt-16">
            <div class="absolute left-0 right-0 top-5 hidden h-px bg-border/80 md:block" aria-hidden="true"></div>

            <div class="grid gap-10 md:grid-cols-3 md:gap-8">
                @php
                    $langkah = [
                        [
                            'nomor' => '01',
                            'judul' => 'Tautkan nomor',
                            'isi' => 'Sekali scan QR di dashboard. Setelah itu sesinya tersimpan permanen di volume terisolasi — termasuk saat server di-deploy ulang.',
                        ],
                        [
                            'nomor' => '02',
                            'judul' => 'Ambil API key',
                            'isi' => 'Satu kunci rahasia per aplikasi atau workspace, bisa dilihat lagi kapan saja dan dicabut sendiri tanpa mempengaruhi nomor lain.',
                        ],
                        [
                            'nomor' => '03',
                            'judul' => 'Panggil API-nya',
                            'isi' => 'Satu permintaan HTTP POST biasa dengan JSON standar. Tidak ada SDK yang wajib dipasang, antrean pesan diurus otomatis oleh gateway.',
                        ],
                    ];
                @endphp
                @foreach ($langkah as $i => $item)
                    <div class="muncul relative" style="--tunda: {{ $i * 80 }}ms">
                        <span class="relative z-10 inline-flex h-9 w-9 items-center justify-center rounded-lg border border-primary/25 bg-background font-mono text-xs font-semibold text-primary shadow-xs">
                            {{ $item['nomor'] }}
                        </span>
                        <h3 class="mt-4 text-sm sm:text-base font-semibold tracking-tight text-foreground">{{ $item['judul'] }}</h3>
                        <p class="mt-1.5 text-xs sm:text-sm leading-relaxed text-muted-foreground">{{ $item['isi'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ===================== Yang Membedakan & Fitur ===================== --}}
<section id="fitur" class="border-y border-border/60 bg-muted/30 py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {{-- Showcase Arsitektur --}}
        <div class="grid items-center gap-10 lg:grid-cols-12 lg:gap-12">
            <div class="min-w-0 lg:col-span-6">
                <h2 class="muncul text-2xl sm:text-3xl font-bold tracking-tight text-foreground leading-snug">
                    Deploy ulang tanpa scan QR lagi
                </h2>
                <p class="muncul mt-4 text-xs sm:text-sm leading-relaxed text-muted-foreground" style="--tunda: 60ms">
                    Gateway WhatsApp yang menempel di dalam aplikasi akan meminta scan ulang setiap kali servernya dinyalakan ulang. Di sini kredensial nomor disimpan terpisah dan dicadangkan berkala, jadi nomor Anda menyala sendiri begitu container kembali hidup.
                </p>
                <p class="muncul mt-3 text-xs sm:text-sm leading-relaxed text-muted-foreground" style="--tunda: 90ms">
                    Nomor tetap bisa Anda ganti kapan saja lewat Putus tautan → Hubungkan.
                </p>
            </div>

            {{-- Timeline Restart --}}
            <div class="muncul lg:col-span-6" style="--tunda: 120ms">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Saat server di-deploy ulang</p>
                <ol class="mt-5 space-y-5">
                    @php
                        $urutan = [
                            ['Container dimatikan', 'Sesi login WhatsApp diserialisasi dan disimpan rapi sebelum proses berhenti.'],
                            ['Versi baru menyala', 'Engine gateway mengontak Laravel untuk memeriksa nomor mana saja yang berstatus aktif.'],
                            ['Nomor tersambung sendiri', 'Koneksi WhatsApp pulih seketika tanpa scan QR dan tanpa tindakan manual dari Anda.'],
                        ];
                    @endphp
                    @foreach ($urutan as [$judul, $isi])
                        <li class="flex gap-3.5">
                            <div class="flex flex-col items-center self-stretch">
                                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-primary text-primary-foreground shadow-xs">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                                </span>
                                @if (! $loop->last)
                                    <span class="mt-1.5 w-px flex-1 bg-border"></span>
                                @endif
                            </div>
                            <div class="pb-1">
                                <p class="text-xs sm:text-sm font-medium text-foreground">{{ $judul }}</p>
                                <p class="mt-0.5 text-xs sm:text-sm leading-relaxed text-muted-foreground">{{ $isi }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>

        {{-- 6 Fitur Inti --}}
        <div class="mt-16 sm:mt-20 grid gap-x-10 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
            @php
                $fitur = [
                    ['M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 7a4 4 0 1 0 0 .01M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75', 'Banyak nomor sekaligus', 'Satu nomor bermasalah tidak menghentikan pengiriman nomor lainnya.'],
                    ['M12 6v6l4 2M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z', 'Jeda kirim otomatis', 'Tiap pesan diberi jeda acak dan diantre per nomor, supaya nomor Anda tidak terbaca sebagai spam.'],
                    ['M4 4h16v12H5.17L4 17.17V4zM8 9h8M8 12h5', 'Webhook pesan masuk', 'Balasan pelanggan diteruskan ke URL aplikasi Anda, bertanda tangan digital.'],
                    ['M9 11l3 3 8-8M21 12a9 9 0 1 1-6.22-8.56', 'Riwayat & status kirim', 'Terkirim, sampai (delivered), atau dibaca (read) tercatat per pesan secara akurat.'],
                    ['M3 7h18M3 12h18M3 17h18', 'Workspace terpisah', 'Cabang atau klien punya nomor, API key, dan riwayat pengiriman sendiri.'],
                    ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z', 'Kunci bisa dicabut sendiri', 'Satu kunci bocor tidak berarti seluruh nomor Anda ikut jatuh atau terancam.'],
                ];
            @endphp
            @foreach ($fitur as $i => [$ikon, $judul, $isi])
                <div class="muncul" style="--tunda: {{ ($i % 3) * 70 }}ms">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $ikon }}"/></svg>
                    </div>
                    <h3 class="mt-3 text-sm sm:text-base font-semibold text-foreground">{{ $judul }}</h3>
                    <p class="mt-1 text-xs sm:text-sm leading-relaxed text-muted-foreground">{{ $isi }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===================== Mitra Bank & Metode Pembayaran ===================== --}}
<section aria-label="Metode Pembayaran yang Didukung"
         class="relative overflow-hidden border-b border-border/60 bg-muted/20 py-3 sm:py-4">
    <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 sm:gap-6 sm:px-6 lg:px-8">
        <!-- Label Tetap -->
        <div class="flex shrink-0 items-center gap-1.5">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            <span class="text-[10px] sm:text-xs font-bold uppercase tracking-wider text-muted-foreground select-none whitespace-nowrap">
                Payment
            </span>
        </div>

        <!-- Track Marquee -->
        <div class="flustra-pay-marquee relative flex-1 overflow-hidden">
            <!-- Fade Kiri & Kanan (adaptif light & dark mode) -->
            <div class="pointer-events-none absolute left-0 top-0 bottom-0 z-10 w-8 sm:w-16 bg-gradient-to-r from-background via-background/80 to-transparent"></div>
            <div class="pointer-events-none absolute right-0 top-0 bottom-0 z-10 w-8 sm:w-16 bg-gradient-to-l from-background via-background/80 to-transparent"></div>

            <div class="flustra-pay-track flex w-max items-center gap-3 sm:gap-4">
                @php
                    $marqueePayments = [
                        ['label' => 'QRIS', 'file' => 'qris.svg'],
                        ['label' => 'Bank BCA', 'file' => 'bca.svg'],
                        ['label' => 'Bank BNI', 'file' => 'bni.svg'],
                        ['label' => 'Bank BRI', 'file' => 'bri.svg'],
                        ['label' => 'Bank Mandiri', 'file' => 'mandiri.svg'],
                        ['label' => 'Bank BSI', 'file' => 'bsi.svg'],
                        ['label' => 'Bank Permata', 'file' => 'permata.svg'],
                        ['label' => 'CIMB Niaga', 'file' => 'cimb.svg'],
                        ['label' => 'Bank Sahabat Sampoerna', 'file' => 'bss.svg'],
                        ['label' => 'Indomaret', 'file' => 'indomaret.svg'],
                        ['label' => 'Alfamart', 'file' => 'alfamart.svg'],
                        ['label' => 'AstraPay', 'file' => 'astrapay.svg'],
                        ['label' => 'OVO', 'file' => 'ovo.svg'],
                        ['label' => 'ShopeePay', 'file' => 'shopeepay.svg'],
                        ['label' => 'Akulaku PayLater', 'file' => 'akulaku.svg'],
                    ];
                @endphp

                {{-- Loop 2x untuk infinite seamless scroll --}}
                @for ($i = 0; $i < 2; $i++)
                    @foreach ($marqueePayments as $payment)
                        <div class="flex h-9 sm:h-10 shrink-0 items-center justify-center rounded-xl border border-border/80 bg-white px-3 sm:px-4 shadow-2xs transition-all duration-200"
                             title="{{ $payment['label'] }}">
                            <img src="{{ asset('images/payments/' . $payment['file']) }}"
                                 alt="{{ $payment['label'] }}"
                                 loading="lazy"
                                 class="h-4 sm:h-5 w-auto max-w-[65px] sm:max-w-[78px] object-contain">
                        </div>
                    @endforeach
                @endfor
            </div>
        </div>
    </div>
</section>

{{-- ===================== Kapan Terpakai ===================== --}}
<section class="py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-[1fr_1.6fr] lg:gap-14">
            <div>
                <h2 class="muncul text-2xl sm:text-3xl font-bold tracking-tight text-foreground leading-snug">
                    Di mana pun aplikasi Anda perlu bicara
                </h2>
                <p class="muncul mt-3 text-xs sm:text-sm leading-relaxed text-muted-foreground" style="--tunda: 60ms">
                    Kirim pesan notifikasi otomatis langsung ke aplikasi yang dibuka pelanggan setiap hari.
                </p>
            </div>

            <div class="divide-y divide-border/70 border-y border-border/70">
                @php
                    $pemakaian = [
                        ['Toko & e-commerce', 'Konfirmasi pesanan masuk, rincian tagihan, nomor resi pengiriman, dan pemberitahuan barang sampai ke kurir.'],
                        ['Penagihan & invoice', 'Invoice terbit, jatuh tempo H-3, atau pemberitahuan pembayaran lunas — terkirim langsung ke nomor pelanggan.'],
                        ['Jasa & reservasi terjadwal', 'Pengingat janji temu dokter, reservasi meja, atau jadwal servis sehari sebelumnya secara otomatis dari sistem Anda.'],
                        ['Verifikasi & OTP', 'Kode verifikasi sekali pakai yang dikirim dari nomor resmi brand Anda sendiri demi menjaga kredibilitas dan keamanan.'],
                    ];
                @endphp
                @foreach ($pemakaian as $i => [$judul, $isi])
                    <div class="muncul flex flex-col gap-1.5 py-5 sm:flex-row sm:gap-8" style="--tunda: {{ $i * 60 }}ms">
                        <h3 class="shrink-0 text-sm sm:text-base font-semibold text-foreground sm:w-52">{{ $judul }}</h3>
                        <p class="text-xs sm:text-sm leading-relaxed text-muted-foreground">{{ $isi }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

{{-- ===================== Harga ===================== --}}
<section id="harga" class="border-y border-border/60 bg-muted/30 py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" x-data="{ tahunan: false }">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="muncul text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                Bayar sesuai besarnya pemakaian
            </h2>
            <p class="muncul mx-auto mt-3 text-xs sm:text-sm leading-relaxed text-muted-foreground" style="--tunda: 60ms">
                Semua paket memakai gateway, API, dan dashboard yang sama. Yang membedakan hanya seberapa besar Anda memakainya.
            </p>

            {{-- Switch Bulanan / Tahunan Modern --}}
            <div class="muncul mt-7 inline-flex items-center rounded-2xl border border-border bg-card p-1.5 shadow-xs" style="--tunda: 90ms">
                <button @click="tahunan = false"
                        type="button"
                        class="rounded-xl px-4 py-1.5 text-xs sm:text-sm font-semibold transition-all"
                        :class="!tahunan ? 'bg-primary text-primary-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'">
                    Bulanan
                </button>
                <button @click="tahunan = true"
                        type="button"
                        class="flex items-center gap-2 rounded-xl px-4 py-1.5 text-xs sm:text-sm font-semibold transition-all"
                        :class="tahunan ? 'bg-primary text-primary-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'">
                    <span>Tahunan</span>
                    <span class="rounded-full bg-emerald-500/20 px-2 py-0.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-300">
                        Hemat 2 Bulan
                    </span>
                </button>
            </div>
        </div>

        @php
            $paket = collect(\App\Support\Plan::all())->map(fn ($plan) => [
                'nama' => $plan->name(),
                'bulanan' => $plan->monthlyPrice(),
                'tahunan' => $plan->price('yearly'),
                'untuk' => $plan->tagline(),
                'sorot' => $plan->isHighlighted(),
                'fitur' => $plan->features(),
            ])->all();
        @endphp

        <div class="mt-12 grid items-stretch gap-8 lg:grid-cols-3">
            @foreach ($paket as $i => $p)
                <div class="muncul relative flex flex-col justify-between rounded-2xl border p-7 sm:p-8 transition-all duration-300 {{ $p['sorot'] ? 'border-primary bg-card ring-2 ring-primary/20 shadow-xl lg:-translate-y-2' : 'border-border/80 bg-card hover:border-primary/40 hover:shadow-md' }}"
                     style="--tunda: {{ $i * 90 }}ms">
                    @if ($p['sorot'])
                        <div class="absolute -top-4 left-1/2 -translate-x-1/2">
                            <span class="inline-flex items-center rounded-full bg-primary px-4 py-1 text-[11px] font-bold uppercase tracking-wider text-primary-foreground shadow-md">
                                Paling Populer
                            </span>
                        </div>
                    @endif

                    <div>
                        <div class="flex items-baseline justify-between">
                            <h3 class="text-xl font-bold tracking-tight text-foreground">{{ $p['nama'] }}</h3>
                        </div>
                        <p class="mt-1.5 text-xs sm:text-sm text-muted-foreground min-h-[38px]">{{ $p['untuk'] }}</p>

                        <div class="mt-6 border-t border-border/60 pt-6">
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-3xl sm:text-4xl font-bold tracking-tight text-foreground"
                                      x-text="tahunan ? 'Rp{{ number_format($p['tahunan'], 0, ',', '.') }}' : 'Rp{{ number_format($p['bulanan'], 0, ',', '.') }}'">
                                    Rp{{ number_format($p['bulanan'], 0, ',', '.') }}
                                </span>
                                <span class="text-xs sm:text-sm font-medium text-muted-foreground" x-text="tahunan ? '/tahun' : '/bulan'">/bulan</span>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground" :class="tahunan ? '' : 'invisible'">
                                Setara Rp{{ number_format($p['tahunan'] / 12, 0, ',', '.') }} per bulan.
                            </p>
                        </div>

                        <a href="{{ route('register') }}"
                           class="mt-6 block w-full rounded-xl py-2.5 text-center text-xs sm:text-sm font-semibold transition-all shadow-xs active:scale-[0.98] {{ $p['sorot'] ? 'bg-primary text-primary-foreground hover:bg-primary/90 hover:shadow-md hover:shadow-primary/20' : 'border border-input bg-background hover:bg-muted text-foreground' }}">
                            Pilih {{ $p['nama'] }}
                        </a>

                        <div class="mt-6 border-t border-border/60 pt-6">
                            <p class="text-xs font-semibold uppercase tracking-wider text-foreground/80 mb-3.5">Fitur yang didapatkan:</p>
                            <ul class="space-y-3 text-xs sm:text-sm">
                                @foreach ($p['fitur'] as $f)
                                    <li class="flex items-start gap-2.5">
                                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                                        <span class="text-muted-foreground leading-relaxed">{{ $f }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="muncul mt-10 rounded-2xl border border-border/80 bg-card p-5 text-center text-xs sm:text-sm text-muted-foreground" style="--tunda: 130ms">
            Butuh nomor atau kuota lebih banyak dari paket Elite?
            <a href="https://about.flustra.id/#contact" class="font-semibold text-primary underline underline-offset-4 hover:opacity-80">Hubungi kami</a> untuk penawaran khusus.
        </div>
    </div>
</section>

{{-- ===================== Untuk Developer ===================== --}}
<section id="untuk-developer" class="py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
            <h2 class="muncul text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                HTTP dan JSON biasa
            </h2>
            <p class="muncul mt-3 text-xs sm:text-sm leading-relaxed text-muted-foreground" style="--tunda: 60ms">
                Tidak ada SDK yang wajib dipasang. Kalau bahasa atau framework Anda bisa memanggil URL, ia bisa memakai gateway ini.
            </p>
        </div>

        <div class="muncul mt-8" style="--tunda: 100ms" x-data="{ bahasa: 'php', disalin: false }">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-2.5">
                <div class="flex flex-wrap gap-1 text-xs">
                    @foreach (['php' => 'PHP / Laravel', 'node' => 'Node.js', 'python' => 'Python'] as $kode => $label)
                        <button @click="bahasa = '{{ $kode }}'"
                                class="rounded-lg px-2.5 sm:px-3 py-1 font-medium transition"
                                :class="bahasa === '{{ $kode }}' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-background hover:text-foreground'">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <button type="button"
                        @click="
                            let text = document.getElementById('code-' + bahasa).innerText;
                            navigator.clipboard.writeText(text);
                            disalin = true;
                            setTimeout(() => disalin = false, 2000);
                        "
                        class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-2.5 py-1 text-xs font-medium text-foreground hover:bg-muted transition-all">
                    <svg x-show="!disalin" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/></svg>
                    <svg x-show="disalin" x-cloak class="h-3 w-3 text-primary" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    <span x-text="disalin ? 'Tersalin!' : 'Salin'">Salin</span>
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border border-border bg-[var(--code-chrome)] shadow-md">
                <div x-show="bahasa === 'php'" id="code-php">
<pre class="overflow-x-auto p-4 font-mono text-xs leading-relaxed text-[var(--code-foreground)]"><code><span class="text-[#f43f5e]">use</span> Illuminate\Support\Facades\Http;

Http::withHeaders([
    <span class="text-[var(--code-string)]">'X-Api-Key'</span> =&gt; config(<span class="text-[var(--code-string)]">'services.wa.key'</span>),
])-&gt;post(<span class="text-[var(--code-string)]">'https://wa.flustra.id/api/v1/messages/text'</span>, [
    <span class="text-[var(--code-payload)]">'to'</span>      =&gt; $invoice-&gt;customer_phone,
    <span class="text-[var(--code-payload)]">'message'</span> =&gt; <span class="text-[var(--code-string)]">"Invoice {$invoice->number} sudah lunas."</span>,
]);</code></pre>
                </div>

                <div x-show="bahasa === 'node'" x-cloak id="code-node">
<pre class="overflow-x-auto p-4 font-mono text-xs leading-relaxed text-[var(--code-foreground)]"><code><span class="text-[#f43f5e]">await</span> fetch(<span class="text-[var(--code-string)]">'https://wa.flustra.id/api/v1/messages/text'</span>, {
  method: <span class="text-[var(--code-string)]">'POST'</span>,
  headers: {
    <span class="text-[var(--code-payload)]">'X-Api-Key'</span>: process.env.WA_KEY,
    <span class="text-[var(--code-payload)]">'Content-Type'</span>: <span class="text-[var(--code-string)]">'application/json'</span>,
  },
  body: JSON.stringify({ to: pesanan.telepon, message: teks }),
});</code></pre>
                </div>

                <div x-show="bahasa === 'python'" x-cloak id="code-python">
<pre class="overflow-x-auto p-4 font-mono text-xs leading-relaxed text-[var(--code-foreground)]"><code>requests.post(
    <span class="text-[var(--code-string)]">"https://wa.flustra.id/api/v1/messages/text"</span>,
    headers={<span class="text-[var(--code-payload)]">"X-Api-Key"</span>: os.environ[<span class="text-[var(--code-string)]">"WA_KEY"</span>]},
    json={<span class="text-[var(--code-payload)]">"to"</span>: pelanggan.telepon, <span class="text-[var(--code-payload)]">"message"</span>: teks},
    timeout=<span class="text-[var(--code-payload)]">10</span>,
)</code></pre>
                </div>
            </div>
        </div>

        <div class="muncul mt-6 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs sm:text-sm font-medium" style="--tunda: 140ms">
            <a href="{{ route('docs.show', 'referensi-api') }}" class="inline-flex items-center gap-1 text-primary hover:underline">
                Referensi API
                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('docs.show', 'contoh-integrasi') }}" class="text-muted-foreground hover:text-foreground">Contoh integrasi</a>
            <a href="{{ route('docs.show', 'webhook') }}" class="text-muted-foreground hover:text-foreground">Webhook</a>
            <a href="{{ route('docs.index') }}" class="text-muted-foreground hover:text-foreground">Semua dokumentasi</a>
        </div>
    </div>
</section>

{{-- ===================== FAQ ===================== --}}
<section class="border-y border-border/60 bg-muted/30 py-20 sm:py-28">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
            <h2 class="muncul text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                Pertanyaan yang sering muncul
            </h2>
            <p class="muncul mt-2 text-xs sm:text-sm text-muted-foreground" style="--tunda: 60ms">
                Semua informasi lengkap yang Anda butuhkan seputar penautan nomor, integrasi API, keamanan data, fitur webhook, dan batas pemakaian gateway.
            </p>
        </div>

        <div class="mt-8 divide-y divide-border/60 border-y border-border/60" x-data="{ terbuka: 0 }">
            @php
                $tanya = [
                    [
                        'Apakah nomor WhatsApp saya terkunci di platform ini?',
                        'Tidak sama sekali. Anda memegang kendali penuh atas nomor WhatsApp yang ditautkan. Anda dapat memutuskan tautan (disconnect) kapan saja melalui dashboard dan menggantinya dengan nomor baru tanpa biaya tambahan atau birokrasi verifikasi yang rumit. Riwayat log pesan sebelumnya tetap tersimpan aman di akun Anda.',
                    ],
                    [
                        'Berapa lama waktu yang dibutuhkan untuk pemasangan pertama kali?',
                        'Proses penautan nomor pertama hanya membutuhkan waktu sekitar 30–60 detik melalui scan kode QR di dashboard Flustra WA. Setelah nomor terhubung dan API Key diterbitkan, integrasi ke aplikasi Anda dapat selesai dalam hitungan menit cukup dengan mengirimkan satu HTTP POST request standar berisi format JSON.',
                    ],
                    [
                        'Apa yang terjadi jika kuota pesan bulanan saya habis?',
                        'Sistem tidak akan membiarkan pesan gagal secara diam-diam (silent failure). Gateway akan mengembalikan respon HTTP 429 Too Many Requests dengan pesan error yang jelas dan detail. Seluruh status pengiriman tercatat secara transparan di dashboard, dan Anda dapat melakukan upgrade paket seketika kapan saja untuk melanjutkan pengiriman tanpa perlu pairing ulang nomor.',
                    ],
                    [
                        'Bagaimana jika aplikasi backend saya tidak menggunakan framework Laravel?',
                        'Flustra WA Gateway dibangun menggunakan standar terbuka REST API berbasis JSON murni. Layanan ini kompatibel 100% dengan bahasa pemrograman, runtime, atau framework apa pun—mulai dari Node.js (Express, NestJS), Python (Django, FastAPI), PHP native, Go, Java (Spring), C# (.NET), hingga platform no-code seperti Make, Zapier, dan n8n. Dokumentasi kami menyediakan contoh kode siap salin untuk berbagai bahasa.',
                    ],
                    [
                        'Apakah nomor WhatsApp saya aman dari risiko pemblokiran (banned)?',
                        'Kami menerapkan arsitektur antrean cerdas (smart queue engine) dengan jeda acak natural (dynamic jitter) antar-pesan untuk mensimulasikan pola interaksi manusia dan menghindari deteksi bot oleh WhatsApp. Selain itu, kami menyarankan pengiriman pesan yang relevan (transaksional, OTP, notifikasi pesanan) dan menghindari spam massal ke nomor yang tidak pernah berinteraksi dengan Anda.',
                    ],
                    [
                        'Bisakah saya menggunakan nomor WhatsApp biasa (Personal) atau wajib WhatsApp Business?',
                        'Anda bebas menggunakan jenis nomor apa pun, baik WhatsApp Personal standar maupun WhatsApp Business biasa. Anda tidak diwajibkan memiliki centang hijau (green tick) ataupun akun Facebook Business Manager yang rumit. Cukup pastikan nomor kartu SIM aktif dan sudah terdaftar di aplikasi WhatsApp resmi ponsel Anda.',
                    ],
                    [
                        'Apakah gateway mendukung pengiriman file media seperti PDF, dokumen, dan gambar?',
                        'Ya, tentu saja. Selain pesan teks reguler, API Flustra WA Gateway mendukung pengiriman berbagai jenis media digital, termasuk gambar (JPEG, PNG), dokumen dokumen faktur/tagihan (PDF, spreadsheet XLSX), audio, hingga pesan lokasi. Cukup sertakan URL media publik yang valid pada payload API pengiriman media.',
                    ],
                    [
                        'Apakah pesan masuk dari pelanggan dapat diterima dan diproses otomatis (Webhook)?',
                        'Ya, sangat bisa. Anda dapat mengonfigurasikan URL Webhook di dashboard untuk menerima notifikasi pesan masuk secara real-time. Setiap kali pelanggan mengirimkan balasan, gateway akan langsung meneruskan payload data ke endpoint server Anda, memungkinkan Anda membangun bot interaktif, integrasi CRM, atau ticketing helpdesk pelanggan.',
                    ],
                    [
                        'Bagaimana dengan keamanan dan privasi data pesan pelanggan saya?',
                        'Keamanan dan privasi data Anda adalah prioritas utama kami. Seluruh lalu lintas data antara aplikasi Anda, gateway kami, dan server WhatsApp dienkripsi menggunakan protokol SSL/TLS 256-bit standar perbankan. Kami tidak pernah membagikan atau menjual isi pesan pelanggan Anda, dan log pesan hanya dapat diakses oleh pemilik akun untuk keperluan audit.',
                    ],
                ];
            @endphp
            @foreach ($tanya as $i => [$judul, $isi])
                <div class="muncul py-4" style="--tunda: {{ $i * 40 }}ms">
                    <button @click="terbuka = terbuka === {{ $i }} ? null : {{ $i }}"
                            type="button"
                            class="flex w-full items-center justify-between gap-4 text-left font-medium text-foreground text-sm sm:text-base">
                        <span>{{ $judul }}</span>
                        <svg class="h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200"
                             :class="terbuka === {{ $i }} ? 'rotate-180 text-primary' : ''"
                             fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="terbuka === {{ $i }}" x-collapse x-cloak>
                        <p class="pt-2.5 text-xs sm:text-sm leading-relaxed text-muted-foreground">{{ $isi }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===================== Integrasi AI Agent ===================== --}}
<section id="ai-agent" class="py-20 sm:py-28 overflow-hidden">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8"
         x-data="{
             agentAktif: 'claude',
             disalin: false,
             prompts: {
                 claude: `Tambahkan integrasi WhatsApp Gateway ke project ini menggunakan REST API Flustra WA (https://wa.flustra.id).

Spesifikasi integrasi:
- Base URL: https://wa.flustra.id/api/v1
- Endpoint kirim teks: POST /messages/text
- Header:
    X-Api-Key: env('FLUSTRA_WA_KEY')
    Content-Type: application/json
- Payload JSON:
    {
      &quot;to&quot;: &quot;081234567890&quot;,
      &quot;message&quot;: &quot;Halo! Pesan transaksional dari sistem.&quot;
    }
- Format respon sukses:
    { &quot;success&quot;: true, &quot;data&quot;: { &quot;id&quot;: &quot;msg_1029&quot;, &quot;status&quot;: &quot;queued&quot; } }

Tolong buatkan helper service yang modular dengan validasi nomor tujuan dan error handling yang aman.`,
                 cursor: `# Aturan Flustra WA Gateway untuk .cursorrules

Ketika membuat fitur pengiriman pesan, notifikasi, atau verifikasi OTP WhatsApp:
1. Panggil endpoint REST API: POST https://wa.flustra.id/api/v1/messages/text
2. Autentikasi: sertakan header 'X-Api-Key' dari variabel lingkungan FLUSTRA_WA_KEY.
3. Payload JSON:
   {
     &quot;to&quot;: &quot;08xxxxxxxxxx&quot;,
     &quot;message&quot;: &quot;Isi pesan WhatsApp&quot;
   }
4. Jangan pernah mengekspos API key di client-side / frontend.
5. Tangani respon galat 401 (kunci salah) dan 422 (data tidak valid) dengan elegan.`,
                 antigravity: `Konfigurasikan integrasi WhatsApp otomatis untuk workspace ini menggunakan Flustra WA Gateway API:
- Base URL: https://wa.flustra.id/api/v1
- Endpoint: POST /messages/text
- Header: X-Api-Key: [API_KEY_ANDA], Content-Type: application/json
- Body: { &quot;to&quot;: &quot;08xxxxxxxxxx&quot;, &quot;message&quot;: &quot;Pesan otomatis sistem&quot; }
- Dokumentasi API lengkap: https://wa.flustra.id/docs
- Pastikan fungsi kirim mengembalikan status antrean pesan tanpa menghentikan thread utama aplikasi.`,
                 opencode: `Integrasikan pengiriman pesan WhatsApp via Flustra WA Gateway.
URL: https://wa.flustra.id/api/v1/messages/text
Method: POST
Headers:
  X-Api-Key: os.getenv('WA_KEY')
  Content-Type: application/json
Body:
  {
    &quot;to&quot;: &quot;081234567890&quot;,
    &quot;message&quot;: &quot;Pesan verifikasi sistem&quot;
  }
Buatkan modul client HTTP yang bersih dan siap diuji.`,
                 codex: `Write a clean and robust service module to send WhatsApp messages using Flustra WA Gateway.
API URL: https://wa.flustra.id/api/v1/messages/text
Method: POST
Headers:
  X-Api-Key: process.env.WA_API_KEY
  Content-Type: application/json
Body:
  { &quot;to&quot;: recipient_phone, &quot;message&quot;: text_message }
Requirements:
- Validate phone number input (supports 08... or 628...)
- Parse JSON response and log queue message ID
- Add exponential retry on 5xx server errors`,
                 windsurf: `Integrasikan Flustra WA Gateway API ke dalam alur aplikasi:
- Endpoint: POST https://wa.flustra.id/api/v1/messages/text
- Header: X-Api-Key: env('WA_API_KEY')
- Request Body: { &quot;to&quot;: &quot;081234567890&quot;, &quot;message&quot;: &quot;Notifikasi pesanan siap dikirim&quot; }
- Tangani status response: 'queued' menandakan pesan telah masuk antrean pengiriman server.`
             },
             salin() {
                 let text = this.prompts[this.agentAktif];
                 navigator.clipboard.writeText(text);
                 this.disalin = true;
                 setTimeout(() => this.disalin = false, 2000);
             }
         }">
        <div class="max-w-3xl">
            <h2 class="muncul text-2xl sm:text-3xl font-bold tracking-tight text-foreground">
                Bangun integrasi WhatsApp lebih cepat dengan AI Agent
            </h2>
            <p class="muncul mt-3 text-xs sm:text-sm leading-relaxed text-muted-foreground" style="--tunda: 50ms">
                Arsitektur REST API Flustra WA berbasis JSON murni tanpa dependensi rumit. Cukup berikan prompt spesifikasi kami ke AI Agent favorit Anda, dan biarkan AI menuliskan service integrasinya dalam hitungan detik.
            </p>
        </div>

        {{-- Pilihan AI Tools / Agent Switcher --}}
        <div class="muncul mt-10 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3" style="--tunda: 80ms">
            {{-- Claude Code --}}
            <button @click="agentAktif = 'claude'"
                    type="button"
                    class="group flex flex-col items-start p-3 sm:p-3.5 rounded-2xl border transition-all text-left min-w-0 w-full"
                    :class="agentAktif === 'claude' ? 'border-primary bg-card ring-2 ring-primary/20 shadow-sm' : 'border-border/80 bg-card/60 hover:bg-card hover:border-primary/40'">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#D97757]/15 text-[#D97757] border border-[#D97757]/20 shadow-xs mb-2.5 transition-transform group-hover:scale-105">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="#D97757">
                        <path d="m4.7144 15.9555 4.7174-2.6471.079-.2307-.079-.1275h-.2307l-.7893-.0486-2.6956-.0729-2.3375-.0971-2.2646-.1214-.5707-.1215-.5343-.7042.0546-.3522.4797-.3218.686.0608 1.5179.1032 2.2767.1578 1.6514.0972 2.4468.255h.3886l.0546-.1579-.1336-.0971-.1032-.0972L6.973 9.8356l-2.55-1.6879-1.3356-.9714-.7225-.4918-.3643-.4614-.1578-1.0078.6557-.7225.8803.0607.2246.0607.8925.686 1.9064 1.4754 2.4893 1.8336.3643.3035.1457-.1032.0182-.0728-.164-.2733-1.3539-2.4467-1.445-2.4893-.6435-1.032-.17-.6194c-.0607-.255-.1032-.4674-.1032-.7285L6.287.1335 6.6997 0l.9957.1336.419.3642.6192 1.4147 1.0018 2.2282 1.5543 3.0296.4553.8985.2429.8318.091.255h.1579v-.1457l.1275-1.706.2368-2.0947.2307-2.6957.0789-.7589.3764-.9107.7468-.4918.5828.2793.4797.686-.0668.4433-.2853 1.8517-.5586 2.9021-.3643 1.9429h.2125l.2429-.2429.9835-1.3053 1.6514-2.0643.7286-.8196.85-.9046.5464-.4311h1.0321l.759 1.1293-.34 1.1657-1.0625 1.3478-.8804 1.1414-1.2628 1.7-.7893 1.36.0729.1093.1882-.0183 2.8535-.607 1.5421-.2794 1.8396-.3157.8318.3886.091.3946-.3278.8075-1.967.4857-2.3072.4614-3.4364.8136-.0425.0304.0486.0607 1.5482.1457.6618.0364h1.621l3.0175.2247.7892.522.4736.6376-.079.4857-1.2142.6193-1.6393-.3886-3.825-.9107-1.3113-.3279h-.1822v.1093l1.0929 1.0686 2.0035 1.8092 2.5075 2.3314.1275.5768-.3218.4554-.34-.0486-2.2039-1.6575-.85-.7468-1.9246-1.621h-.1275v.17l.4432.6496 2.3436 3.5214.1214 1.0807-.17.3521-.6071.2125-.6679-.1214-1.3721-1.9246L14.38 17.959l-1.1414-1.9428-.1397.079-.674 7.2552-.3156.3703-.7286.2793-.6071-.4614-.3218-.7468.3218-1.4753.3886-1.9246.3157-1.53.2853-1.9004.17-.6314-.0121-.0425-.1397.0182-1.4328 1.9672-2.1796 2.9446-1.7243 1.8456-.4128.164-.7164-.3704.0667-.6618.4008-.5889 2.386-3.0357 1.4389-1.882.929-1.0868-.0062-.1579h-.0546l-6.3385 4.1164-1.1293.1457-.4857-.4554.0608-.7467.2307-.2429 1.9064-1.3114Z"/>
                    </svg>
                </div>
                <span class="text-xs sm:text-sm font-semibold text-foreground truncate w-full">Claude Code</span>
                <span class="text-[10px] text-muted-foreground mt-0.5 truncate w-full">Anthropic CLI</span>
            </button>

            {{-- Cursor --}}
            <button @click="agentAktif = 'cursor'"
                    type="button"
                    class="group flex flex-col items-start p-3 sm:p-3.5 rounded-2xl border transition-all text-left min-w-0 w-full"
                    :class="agentAktif === 'cursor' ? 'border-primary bg-card ring-2 ring-primary/20 shadow-sm' : 'border-border/80 bg-card/60 hover:bg-card hover:border-primary/40'">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-950 border border-zinc-800 dark:border-zinc-200 shadow-xs mb-2.5 transition-transform group-hover:scale-105">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M11.503.131 1.891 5.678a.84.84 0 0 0-.42.726v11.188c0 .3.162.575.42.724l9.609 5.55a1 1 0 0 0 .998 0l9.61-5.55a.84.84 0 0 0 .42-.724V6.404a.84.84 0 0 0-.42-.726L12.497.131a1.01 1.01 0 0 0-.996 0M2.657 6.338h18.55c.263 0 .43.287.297.515L12.23 22.918c-.062.107-.229.064-.229-.06V12.335a.59.59 0 0 0-.295-.51l-9.11-5.257c-.109-.063-.064-.23.061-.23"/>
                    </svg>
                </div>
                <span class="text-xs sm:text-sm font-semibold text-foreground truncate w-full">Cursor</span>
                <span class="text-[10px] text-muted-foreground mt-0.5 truncate w-full">.cursorrules</span>
            </button>

            {{-- Antigravity --}}
            <button @click="agentAktif = 'antigravity'"
                    type="button"
                    class="group flex flex-col items-start p-3 sm:p-3.5 rounded-2xl border transition-all text-left min-w-0 w-full"
                    :class="agentAktif === 'antigravity' ? 'border-primary bg-card ring-2 ring-primary/20 shadow-sm' : 'border-border/80 bg-card/60 hover:bg-card hover:border-primary/40'">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-xs mb-2.5 transition-transform group-hover:scale-105">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <mask height="23" id="agy-brand-mask" maskUnits="userSpaceOnUse" width="24" x="0" y="1">
                            <path d="M21.751 22.607c1.34 1.005 3.35.335 1.508-1.508C17.73 15.74 18.904 1 12.037 1 5.17 1 6.342 15.74.815 21.1c-2.01 2.009.167 2.511 1.507 1.506 5.192-3.517 4.857-9.714 9.715-9.714 4.857 0 4.522 6.197 9.714 9.715z" fill="#fff"/>
                        </mask>
                        <g mask="url(#agy-brand-mask)">
                            <g filter="url(#agy-b1)"><path d="M-1.018-3.992c-.408 3.591 2.686 6.89 6.91 7.37 4.225.48 7.98-2.043 8.387-5.633.408-3.59-2.686-6.89-6.91-7.37-4.225-.479-7.98 2.043-8.387 5.633z" fill="#FFE432"/></g>
                            <g filter="url(#agy-b2)"><path d="M15.269 7.747c1.058 4.557 5.691 7.374 10.348 6.293 4.657-1.082 7.575-5.653 6.516-10.21-1.058-4.556-5.691-7.374-10.348-6.292-4.657 1.082-7.575 5.653-6.516 10.21z" fill="#FC413D"/></g>
                            <g filter="url(#agy-b3)"><path d="M-12.443 10.804c1.338 4.703 7.36 7.11 13.453 5.378 6.092-1.733 9.947-6.95 8.61-11.652C8.282-.173 2.26-2.58-3.833-.848-9.925.884-13.78 6.1-12.443 10.804z" fill="#00B95C"/></g>
                            <g filter="url(#agy-b4)"><path d="M-7.608 14.703c3.352 3.424 9.126 3.208 12.896-.483 3.77-3.69 4.108-9.459.756-12.883C2.69-2.087-3.083-1.871-6.853 1.82c-3.77 3.69-4.108 9.458-.755 12.883z" fill="#00B95C"/></g>
                            <g filter="url(#agy-b5)"><path d="M9.932 27.617c1.04 4.482 5.384 7.303 9.7 6.3 4.316-1.002 6.971-5.448 5.93-9.93-1.04-4.483-5.384-7.304-9.7-6.301-4.316 1.002-6.971 5.448-5.93 9.93z" fill="#3186FF"/></g>
                            <g filter="url(#agy-b6)"><path d="M2.572-8.185C.392-3.329 2.778 2.472 7.9 4.771c5.122 2.3 11.042.227 13.222-4.63 2.18-4.855-.205-10.656-5.327-12.955-5.122-2.3-11.042-.227-13.222 4.63z" fill="#FBBC04"/></g>
                            <g filter="url(#agy-b7)"><path d="M-3.267 38.686c-5.277-2.072 3.742-19.117 5.984-24.83 2.243-5.712 8.34-8.664 13.616-6.592 5.278 2.071 11.533 13.482 9.29 19.195-2.242 5.713-23.613 14.298-28.89 12.227z" fill="#3186FF"/></g>
                            <g filter="url(#agy-b8)"><path d="M28.71 17.471c-1.413 1.649-5.1.808-8.236-1.878-3.135-2.687-4.531-6.201-3.118-7.85 1.412-1.649 5.1-.808 8.235 1.878s4.532 6.2 3.119 7.85z" fill="#749BFF"/></g>
                            <g filter="url(#agy-b9)"><path d="M18.163 9.077c5.81 3.93 12.502 4.19 14.946.577 2.443-3.612-.287-9.727-6.098-13.658-5.81-3.931-12.502-4.19-14.946-.577-2.443 3.612.287 9.727 6.098 13.658z" fill="#FC413D"/></g>
                            <g filter="url(#agy-b10)"><path d="M-.915 2.684c-1.44 3.473-.97 6.967 1.05 7.804 2.02.837 4.824-1.3 6.264-4.772 1.44-3.473.97-6.967-1.05-7.804-2.02-.837-4.824 1.3-6.264 4.772z" fill="#FFEE48"/></g>
                        </g>
                        <defs>
                            <filter color-interpolation-filters="sRGB" filterUnits="userSpaceOnUse" height="18" id="agy-b1" width="20" x="-3" y="-12"><feGaussianBlur stdDeviation="1.1"/></filter>
                            <filter color-interpolation-filters="sRGB" filterUnits="userSpaceOnUse" height="39" id="agy-b2" width="39" x="4" y="-13"><feGaussianBlur stdDeviation="5.4"/></filter>
                            <filter color-interpolation-filters="sRGB" filterUnits="userSpaceOnUse" height="37" id="agy-b3" width="41" x="-22" y="-11"><feGaussianBlur stdDeviation="4.6"/></filter>
                            <filter color-interpolation-filters="sRGB" filterUnits="userSpaceOnUse" height="37" id="agy-b4" width="37" x="-19" y="-10"><feGaussianBlur stdDeviation="4.6"/></filter>
                            <filter color-interpolation-filters="sRGB" filterUnits="userSpaceOnUse" height="34" id="agy-b5" width="34" x="1" y="9"><feGaussianBlur stdDeviation="4.4"/></filter>
                            <filter color-interpolation-filters="sRGB" filterUnits="userSpaceOnUse" height="35" id="agy-b6" width="36" x="-6" y="-22"><feGaussianBlur stdDeviation="4"/></filter>
                            <filter color-interpolation-filters="sRGB" filterUnits="userSpaceOnUse" height="47" id="agy-b7" width="45" x="-12" y="-1"><feGaussianBlur stdDeviation="3.5"/></filter>
                            <filter color-interpolation-filters="sRGB" filterUnits="userSpaceOnUse" height="24" id="agy-b8" width="25" x="10" y="1"><feGaussianBlur stdDeviation="3.2"/></filter>
                            <filter color-interpolation-filters="sRGB" filterUnits="userSpaceOnUse" height="30" id="agy-b9" width="34" x="6" y="-12"><feGaussianBlur stdDeviation="2.7"/></filter>
                            <filter color-interpolation-filters="sRGB" filterUnits="userSpaceOnUse" height="26" id="agy-b10" width="22" x="-8" y="-9"><feGaussianBlur stdDeviation="3.3"/></filter>
                        </defs>
                    </svg>
                </div>
                <span class="text-xs sm:text-sm font-semibold text-foreground truncate w-full">Antigravity</span>
                <span class="text-[10px] text-muted-foreground mt-0.5 truncate w-full">Agentic Studio</span>
            </button>

            {{-- OpenCode --}}
            <button @click="agentAktif = 'opencode'"
                    type="button"
                    class="group flex flex-col items-start p-3 sm:p-3.5 rounded-2xl border transition-all text-left min-w-0 w-full"
                    :class="agentAktif === 'opencode' ? 'border-primary bg-card ring-2 ring-primary/20 shadow-sm' : 'border-border/80 bg-card/60 hover:bg-card hover:border-primary/40'">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-zinc-900 text-zinc-100 dark:bg-zinc-800 dark:text-white border border-zinc-700/60 shadow-xs mb-2.5 transition-transform group-hover:scale-105">
                    <svg class="h-5 w-5" viewBox="0 0 512 512" fill="none">
                        <path d="M320 224V352H192V224H320Z" fill="#71717A"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M384 416H128V96H384V416ZM320 160H192V352H320V160Z" fill="currentColor"/>
                    </svg>
                </div>
                <span class="text-xs sm:text-sm font-semibold text-foreground truncate w-full">OpenCode</span>
                <span class="text-[10px] text-muted-foreground mt-0.5 truncate w-full">Open-Source AI</span>
            </button>

            {{-- OpenAI Codex --}}
            <button @click="agentAktif = 'codex'"
                    type="button"
                    class="group flex flex-col items-start p-3 sm:p-3.5 rounded-2xl border transition-all text-left min-w-0 w-full"
                    :class="agentAktif === 'codex' ? 'border-primary bg-card ring-2 ring-primary/20 shadow-sm' : 'border-border/80 bg-card/60 hover:bg-card hover:border-primary/40'">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#6366F1]/10 dark:bg-[#6366F1]/20 border border-[#6366F1]/25 shadow-xs mb-2.5 transition-transform group-hover:scale-105">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path clip-rule="evenodd" fill-rule="evenodd" d="M8.086.457a6.105 6.105 0 013.046-.415c1.333.153 2.521.72 3.564 1.7a.117.117 0 00.107.029c1.408-.346 2.762-.224 4.061.366l.063.03.154.076c1.357.703 2.33 1.77 2.918 3.198.278.679.418 1.388.421 2.126a5.655 5.655 0 01-.18 1.631.167.167 0 00.04.155 5.982 5.982 0 011.578 2.891c.385 1.901-.01 3.615-1.183 5.14l-.182.22a6.063 6.063 0 01-2.934 1.851.162.162 0 00-.108.102c-.255.736-.511 1.364-.987 1.992-1.199 1.582-2.962 2.462-4.948 2.451-1.583-.008-2.986-.587-4.21-1.736a.145.145 0 00-.14-.032c-.518.167-1.04.191-1.604.185a5.924 5.924 0 01-2.595-.622 6.058 6.058 0 01-2.146-1.781c-.203-.269-.404-.522-.551-.821a7.74 7.74 0 01-.495-1.283 6.11 6.11 0 01-.017-3.064.166.166 0 00.008-.074.115.115 0 00-.037-.064 5.958 5.958 0 01-1.38-2.202 5.196 5.196 0 01-.333-1.589 6.915 6.915 0 01.188-2.132c.45-1.484 1.309-2.648 2.577-3.493.282-.188.55-.334.802-.438.286-.12.573-.22.861-.304a.129.129 0 00.087-.087A6.016 6.016 0 015.635 2.31C6.315 1.464 7.132.846 8.086.457zm-.804 7.85a.848.848 0 00-1.473.842l1.694 2.965-1.688 2.848a.849.849 0 001.46.864l1.94-3.272a.849.849 0 00.007-.854l-1.94-3.393zm5.446 6.24a.849.849 0 000 1.695h4.848a.849.849 0 000-1.696h-4.848z" fill="url(#codex-brand-grad)"/>
                        <defs>
                            <linearGradient id="codex-brand-grad" x1="12" y1="0" x2="12" y2="24" gradientUnits="userSpaceOnUse">
                                <stop stop-color="#B1A7FF"/>
                                <stop offset="0.5" stop-color="#7A9DFF"/>
                                <stop offset="1" stop-color="#3941FF"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
                <span class="text-xs sm:text-sm font-semibold text-foreground truncate w-full">OpenAI Codex</span>
                <span class="text-[10px] text-muted-foreground mt-0.5 truncate w-full">Coding Agent</span>
            </button>

            {{-- Windsurf --}}
            <button @click="agentAktif = 'windsurf'"
                    type="button"
                    class="group flex flex-col items-start p-3 sm:p-3.5 rounded-2xl border transition-all text-left min-w-0 w-full"
                    :class="agentAktif === 'windsurf' ? 'border-primary bg-card ring-2 ring-primary/20 shadow-sm' : 'border-border/80 bg-card/60 hover:bg-card hover:border-primary/40'">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#09B6A2] text-[#0B100F] shadow-xs mb-2.5 transition-transform group-hover:scale-105">
                    <svg class="h-5 w-5" viewBox="0 0 1024 1024" fill="currentColor">
                        <path d="M897.246 286.869H889.819C850.735 286.808 819.017 318.46 819.017 357.539V515.589C819.017 547.15 792.93 572.716 761.882 572.716C743.436 572.716 725.02 563.433 714.093 547.85L552.673 317.304C539.28 298.16 517.486 286.747 493.895 286.747C457.094 286.747 423.976 318.034 423.976 356.657V515.619C423.976 547.181 398.103 572.746 366.842 572.746C348.335 572.746 329.949 563.463 319.021 547.881L138.395 289.882C134.316 284.038 125.154 286.93 125.154 294.052V431.892C125.154 438.862 127.285 445.619 131.272 451.34L309.037 705.2C319.539 720.204 335.033 731.344 352.9 735.392C397.616 745.557 438.77 711.135 438.77 667.278V508.406C438.77 476.845 464.339 451.279 495.904 451.279H495.995C515.02 451.279 532.857 460.562 543.785 476.145L705.235 706.661C718.659 725.835 739.327 737.218 763.983 737.218C801.606 737.218 833.841 705.9 833.841 667.308V508.376C833.841 476.815 859.41 451.249 890.975 451.249H897.276C901.233 451.249 904.43 448.053 904.43 444.097V294.021C904.43 290.065 901.233 286.869 897.276 286.869H897.246Z"/>
                    </svg>
                </div>
                <span class="text-xs sm:text-sm font-semibold text-foreground truncate w-full">Windsurf</span>
                <span class="text-[10px] text-muted-foreground mt-0.5 truncate w-full">Cascade Flow</span>
            </button>
        </div>

        {{-- Jendela Terminal Prompt Interaktif --}}
        <div class="muncul mt-6 rounded-2xl border border-border/80 bg-[var(--code-chrome)] shadow-xl overflow-hidden" style="--tunda: 140ms">
            <div class="flex items-center justify-between border-b border-white/10 bg-black/40 px-3.5 sm:px-4 py-3 gap-2">
                <div class="flex items-center gap-1.5 sm:gap-2 min-w-0">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-[#ff5f56]"></span>
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-[#ffbd2e]"></span>
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-[#27c93f]"></span>
                    <span class="ml-1 sm:ml-2 font-mono text-xs text-[var(--code-muted)] truncate"
                          x-text="'prompt-' + agentAktif + (agentAktif === 'cursor' ? '.cursorrules' : (agentAktif === 'claude' ? '-CLAUDE.md' : '.txt'))"></span>
                </div>

                <button type="button"
                        @click="salin()"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-white/15 bg-white/10 px-2.5 sm:px-3 py-1 text-xs font-semibold text-white hover:bg-white/20 transition-all active:scale-[0.98]">
                    <svg x-show="!disalin" class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/></svg>
                    <svg x-show="disalin" x-cloak class="h-3.5 w-3.5 shrink-0 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    <span x-text="disalin ? 'Prompt Tersalin!' : 'Salin Prompt'">Salin Prompt</span>
                </button>
            </div>

            <pre class="overflow-x-auto p-4 sm:p-5 font-mono text-xs sm:text-[13px] leading-relaxed text-[var(--code-foreground)] whitespace-pre-wrap break-words selection:bg-primary/30" x-text="prompts[agentAktif]"></pre>
        </div>

        {{-- Catatan Developer & Tautan ke Docs --}}
        <div class="muncul mt-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4 text-xs sm:text-sm text-muted-foreground" style="--tunda: 170ms">
            <div class="flex items-start sm:items-center gap-2 min-w-0">
                <span class="text-primary font-bold shrink-0">💡 Tip:</span>
                <span class="break-words">Selalu simpan API Key di file <code class="font-mono text-foreground font-medium bg-muted px-1.5 py-0.5 rounded">.env</code> dan jangan pernah hardcode ke dalam berkas repositori.</span>
            </div>

            <a href="{{ route('docs.show', 'integrasi-ai-agent') }}" class="inline-flex items-center gap-1.5 font-semibold text-primary hover:underline shrink-0">
                <span>Panduan Lengkap AI Agent di Docs</span>
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</section>

{{-- ===================== Penutup ===================== --}}
<section class="bg-primary text-primary-foreground">
    <div class="mx-auto max-w-4xl px-6 py-20 text-center sm:px-8 lg:py-24">
        <h2 class="muncul text-2xl sm:text-3xl lg:text-4xl font-bold leading-tight tracking-tight">
            Kirim pesan pertama Anda hari ini
        </h2>
        <p class="muncul mx-auto mt-4 max-w-lg text-xs sm:text-sm leading-relaxed text-primary-foreground/85" style="--tunda: 60ms">
            Buat akun, tautkan satu nomor, dan sambungkan ke aplikasi Anda. Menautkan nomor pertama biasanya di bawah satu menit.
        </p>
        <div class="muncul mt-8 flex flex-wrap justify-center gap-3" style="--tunda: 110ms">
            <a href="{{ route('register') }}"
               class="rounded-lg bg-primary-foreground px-5 py-2.5 text-xs sm:text-sm font-semibold text-primary transition hover:opacity-90 shadow-xs">
                Buat akun
            </a>
            <a href="{{ route('docs.show', 'mulai-cepat') }}"
               class="rounded-lg border border-primary-foreground/30 px-5 py-2.5 text-xs sm:text-sm font-medium transition hover:bg-primary-foreground/10">
                Baca panduan mulai cepat
            </a>
        </div>
    </div>
</section>

{{-- ===================== Footer ===================== --}}
<footer class="border-t border-border bg-background pt-12 sm:pt-16 pb-0">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {{-- Banner Atas: Enterprise Ready & Akselerasi Integrasi --}}
        <div class="pb-10 border-b border-border/70">
            <div class="grid gap-8 lg:grid-cols-12 items-center">
                <div class="lg:col-span-7">
                    <h3 class="text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                        Siap mengotomatiskan pesan WhatsApp skala besar?
                    </h3>
                    <p class="mt-1.5 text-xs sm:text-sm text-muted-foreground max-w-xl leading-relaxed">
                        Bergabunglah dengan ratusan pengembang dan bisnis yang mengandalkan stabilitas gateway Flustra untuk pesan transaksional berkecepatan tinggi tanpa khawatir sesi terputus.
                    </p>
                </div>
                <div class="lg:col-span-5 flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                    <div class="relative flex-1 min-w-0">
                        <input type="email"
                               placeholder="Masukkan email kantor Anda..."
                               class="w-full rounded-xl border border-input bg-card px-3.5 py-2.5 text-xs sm:text-sm text-foreground shadow-xs placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                               readonly
                               onfocus="this.removeAttribute('readonly');">
                    </div>
                    <a href="{{ route('register') }}"
                       class="inline-flex shrink-0 items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-xs sm:text-sm font-semibold text-primary-foreground shadow-xs hover:bg-primary/90 transition-all text-center">
                        Mulai Sekarang
                    </a>
                </div>
            </div>
        </div>

        {{-- Grid Navigasi Utama: Brand + 4 Kolom Menu --}}
        <div class="pt-10 sm:pt-12 pb-8 sm:pb-10 grid gap-10 lg:grid-cols-12">
            {{-- Sisi Kiri: Profil Brand & Security Pillars --}}
            <div class="lg:col-span-4 space-y-5">
                <a href="/" class="flex items-center gap-2.5 text-xl font-bold tracking-tight text-foreground">
                    <img src="{{ asset('images/flustra-wa.png') }}" alt="Logo" class="h-8 w-auto object-contain">
                    <div class="flex flex-col">
                        <span class="leading-none">Flustra WA Gateway</span>
                        <span class="text-[10px] font-mono tracking-wider uppercase text-primary font-semibold mt-1">Enterprise API Platform</span>
                    </div>
                </a>

                <p class="text-xs sm:text-sm text-muted-foreground leading-relaxed max-w-sm">
                    Infrastruktur WhatsApp Gateway berkinerja tinggi untuk pengiriman OTP, tagihan otomatis, notifikasi e-commerce, dan broadcast dengan perlindungan anti-blokir multi-sesi.
                </p>

                {{-- Pilar Keamanan & Rekayasa --}}
                <div class="pt-1 space-y-2 text-xs text-muted-foreground">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                        <span>Enkripsi TLS 1.3 & API Token Scoped</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                        <span>Isolasi Volume Sesi Terenkripsi</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                        <span>Antrean Pesan Jeda Acak Anti-Spam</span>
                    </div>
                </div>
            </div>

            {{-- Sisi Kanan: 4 Kolom Link Enterprise --}}
            <div class="lg:col-span-8 grid grid-cols-2 sm:grid-cols-4 gap-6 sm:gap-8">
                {{-- Kolom 1: Produk API --}}
                <div class="min-w-0">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-primary">Produk API</h4>
                    <ul class="mt-4 space-y-2.5 text-xs sm:text-sm">
                        <li><a href="#fitur" class="text-muted-foreground hover:text-foreground transition-colors">Fitur Gateway</a></li>
                        <li><a href="#cara-kerja" class="text-muted-foreground hover:text-foreground transition-colors">Cara Kerja</a></li>
                        <li><a href="#harga" class="text-muted-foreground hover:text-foreground transition-colors">Paket & Harga</a></li>
                        <li><a href="{{ route('docs.show', 'referensi-api') }}" class="text-muted-foreground hover:text-foreground transition-colors">Kirim Pesan Teks</a></li>
                        <li><a href="{{ route('docs.show', 'webhook') }}" class="text-muted-foreground hover:text-foreground transition-colors">Webhook Dispatcher</a></li>
                        <li><a href="{{ route('docs.show', 'praktik-baik') }}" class="text-muted-foreground hover:text-foreground transition-colors">Proteksi Anti-Blokir</a></li>
                        <li><a href="{{ route('login') }}" class="text-muted-foreground hover:text-foreground transition-colors">Console Masuk</a></li>
                    </ul>
                </div>

                {{-- Kolom 2: Developer --}}
                <div class="min-w-0">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-primary">Developer</h4>
                    <ul class="mt-4 space-y-2.5 text-xs sm:text-sm">
                        <li><a href="{{ route('docs.index') }}" class="text-muted-foreground hover:text-foreground transition-colors">Pusat Dokumentasi</a></li>
                        <li><a href="{{ route('docs.show', 'mulai-cepat') }}" class="text-muted-foreground hover:text-foreground transition-colors">Panduan Mulai Cepat</a></li>
                        <li><a href="{{ route('docs.show', 'referensi-api') }}" class="text-muted-foreground hover:text-foreground transition-colors">Referensi API v1.0</a></li>
                        <li><a href="{{ route('docs.show', 'contoh-integrasi') }}" class="text-muted-foreground hover:text-foreground transition-colors">Integrasi PHP / Laravel</a></li>
                        <li><a href="{{ route('docs.show', 'integrasi-ai-agent') }}" class="text-muted-foreground hover:text-foreground transition-colors">Integrasi AI Agent</a></li>
                        <li><a href="{{ route('docs.show', 'koleksi-postman') }}" class="text-muted-foreground hover:text-foreground transition-colors">Koleksi Postman</a></li>
                    </ul>
                </div>

                {{-- Kolom 3: Ekosistem Flustra --}}
                <div class="min-w-0">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-primary">Ekosistem</h4>
                    <ul class="mt-4 space-y-2.5 text-xs sm:text-sm">
                        @foreach (config('flustra.produk') as $nama => $alamat)
                            <li><a href="{{ $alamat }}" target="_blank" rel="noopener" class="text-muted-foreground hover:text-foreground transition-colors">{{ $nama }}</a></li>
                        @endforeach
                        <li><a href="https://about.flustra.id" target="_blank" rel="noopener" class="text-muted-foreground hover:text-foreground transition-colors">Flustra Financial</a></li>
                        <li><a href="https://lynk.id/flustra.id" target="_blank" rel="noopener" class="text-muted-foreground hover:text-foreground transition-colors">Lynk.id Official</a></li>
                    </ul>
                </div>

                {{-- Kolom 4: Perusahaan & Hubungi Kami --}}
                <div class="min-w-0">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-primary">Perusahaan</h4>
                    <ul class="mt-4 space-y-2.5 text-xs sm:text-sm">
                        <li><a href="https://about.flustra.id" target="_blank" rel="noopener" class="text-muted-foreground hover:text-foreground transition-colors">Tentang Kami</a></li>
                        <li><a href="https://about.flustra.id/#contact" target="_blank" rel="noopener" class="text-muted-foreground hover:text-foreground transition-colors">Hubungi Sales</a></li>
                        <li><a href="https://helpdesk.flustra.id" target="_blank" rel="noopener" class="text-muted-foreground hover:text-foreground transition-colors">Pusat Bantuan</a></li>
                        <li><a href="mailto:flustrasupport@gmail.com" class="text-muted-foreground hover:text-foreground transition-colors break-all">flustrasupport@gmail.com</a></li>
                        <li><a href="https://wa.me/6282318280376" target="_blank" rel="noopener" class="text-muted-foreground hover:text-foreground transition-colors">WhatsApp Helpdesk</a></li>
                        <li><a href="https://about.flustra.id" target="_blank" rel="noopener" class="text-muted-foreground hover:text-foreground transition-colors">Kebijakan Privasi</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Baris Bawah: Legal Metadata, Hak Cipta & Social Icons Lengkap (Full-Width Grounded) --}}
    <div class="border-t border-border/70 bg-muted/40 dark:bg-muted/20 py-3 sm:py-3.5">
        <div class="mx-auto flex max-w-[1440px] flex-col sm:flex-row items-center justify-between gap-3 sm:gap-4 px-4 sm:px-6 lg:px-8 xl:px-10 text-xs text-muted-foreground">
            <div class="text-center sm:text-left">
                <span>&copy; {{ date('Y') }} PT FLUSTRA FINANCES ARTHA. Hak cipta dilindungi undang-undang.</span>
            </div>

            {{-- 7 Ikon Media Sosial Resmi Flustra --}}
            <div class="flex flex-wrap items-center justify-center sm:justify-end gap-3 sm:gap-3.5 text-muted-foreground">
                {{-- Instagram --}}
                <a href="https://www.instagram.com/flustra.id" target="_blank" rel="noopener" class="hover:text-foreground transition-colors" aria-label="Instagram">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="20" x="2" y="2" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"></line>
                    </svg>
                </a>

                {{-- Threads --}}
                <a href="https://www.threads.net/@flustra.id" target="_blank" rel="noopener" class="hover:text-foreground transition-colors" aria-label="Threads">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11c-1.4-4.8-5.3-8.5-9.9-8.5C5.9 2.5 2 7.2 2 12c0 4.9 3.9 9.5 10.2 9.5 2.6 0 5-.8 6.8-2.3 1.3-1 1.9-2.6 2-4.1v-.3c0-1.8-1.1-3.3-2.7-3.3-1 0-1.9.6-2.4 1.5-1.1 1.9-2.1 3-4.2 3-2.1 0-4-1.6-4-4s1.9-4 4-4c1.7 0 3.3 1.1 3.7 2.7.2.7.1 1.4-.3 2-.4.6-1 .9-1.7.9h-2.1"></path>
                    </svg>
                </a>

                {{-- X / Twitter --}}
                <a href="https://x.com/flustraid" target="_blank" rel="noopener" class="hover:text-foreground transition-colors" aria-label="X / Twitter">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4l11.733 16h4.267l-11.733 -16z"></path><path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772"></path>
                    </svg>
                </a>

                {{-- TikTok --}}
                <a href="https://www.tiktok.com/@flustra.id" target="_blank" rel="noopener" class="hover:text-foreground transition-colors" aria-label="TikTok">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"></path>
                    </svg>
                </a>

                {{-- LinkedIn --}}
                <a href="https://www.linkedin.com/company/flustra" target="_blank" rel="noopener" class="hover:text-foreground transition-colors" aria-label="LinkedIn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect width="4" height="12" x="2" y="9"></rect><circle cx="4" cy="4" r="2"></circle>
                    </svg>
                </a>

                {{-- Shopee --}}
                <a href="https://id.shp.ee/ttH9gphS" target="_blank" rel="noopener" class="hover:text-[#EE4D2D] transition-colors" aria-label="Shopee">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 8h14l-1 13H6L5 8Z"></path>
                        <path d="M9 8V6a3 3 0 0 1 6 0v2"></path>
                        <path d="M14.5 11.5c-.7-.5-1.5-.7-2.4-.7-1.2 0-2.1.6-2.1 1.5 0 2.3 4.6 1.1 4.6 3.8 0 1.1-1 1.9-2.5 1.9-.9 0-1.8-.3-2.6-.9"></path>
                    </svg>
                </a>

                {{-- Lynk.id --}}
                <a href="https://lynk.id/flustra.id" target="_blank" rel="noopener" class="hover:text-foreground transition-colors" aria-label="Lynk.id">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="18" viewBox="0 0 38 24" fill="none">
                        <rect x=".75" y=".75" width="36.5" height="22.5" rx="6" stroke="currentColor" stroke-width="1.5"></rect>
                        <text x="19" y="15.6" fill="currentColor" text-anchor="middle" font-size="10" font-weight="700" font-family="Arial, sans-serif">lynk</text>
                    </svg>
                </a>
            </div>
        </div>
    </div>
</footer>

<script>
    (function () {
        var blok = document.querySelectorAll('.muncul');

        if (! document.documentElement.classList.contains('animasi-muncul')) {
            blok.forEach(function (el) { el.classList.add('terlihat'); });
            return;
        }

        var pengamat = new IntersectionObserver(function (entri) {
            entri.forEach(function (e) {
                if (! e.isIntersecting) return;
                e.target.classList.add('terlihat');
                pengamat.unobserve(e.target);
            });
        }, { rootMargin: '0px 0px -10% 0px', threshold: 0.08 });

        blok.forEach(function (el) { pengamat.observe(el); });
    })();
</script>

</body>
</html>
