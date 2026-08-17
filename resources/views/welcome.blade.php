<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Gateway WhatsApp untuk Aplikasi Anda</title>
    <meta name="description" content="Kirim notifikasi WhatsApp dari aplikasi Anda lewat satu REST API. Multi-nomor, webhook pesan masuk, riwayat pengiriman, dan sesi yang tidak putus saat server di-deploy ulang.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        // Penanda bahwa animasi masuk boleh dipakai. Dipasang di <head> supaya
        // blok sudah tersembunyi sebelum cat pertama — kalau dipasang belakangan,
        // isinya sempat berkedip muncul lalu hilang lagi. Kalau baris ini tidak
        // pernah jalan, CSS tidak menyembunyikan apa pun dan halaman tetap utuh.
        if ('IntersectionObserver' in window && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.documentElement.classList.add('animasi-muncul');
        }
    </script>
</head>
<body class="bg-background text-foreground antialiased" x-data="{ mobileMenu: false }">

@php
    // Isi dropdown header. Ditaruh sebagai data supaya menu desktop dan menu
    // mobile membaca sumber yang sama — dua salinan markup yang isinya harus
    // sama persis adalah cara tercepat membuat keduanya berbeda diam-diam.
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

<header class="relative z-40 lg:pt-2">
    <div class="mx-auto flex max-w-7xl items-center gap-3 px-5 py-4 lg:px-8">
        <a href="/" class="flex items-center gap-2.5 font-semibold">
            <img src="{{ asset('images/flustra-wa.png') }}" alt="Logo" class="h-8 w-auto object-contain">
            <span class="text-base">Flustra WA Gateway</span>
        </a>

        <nav class="ml-auto flex items-center gap-1 text-sm">
            {{-- Dropdown terbuka begitu kursor lewat, dan menutupnya ditunda
                 sesaat — tanpa jeda itu, gerakan turun dari tombol ke dalam
                 panel sempat melewati celah di antaranya dan menutup menu
                 tepat saat pengguna hendak mengkliknya. --}}
            <div class="hidden items-center gap-1 md:flex"
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
                                class="flex items-center gap-1.5 rounded-lg px-3 py-2 transition-colors hover:bg-muted hover:text-foreground"
                                :class="menu === '{{ $kunci }}' ? 'bg-muted text-foreground' : 'text-muted-foreground'">
                            {{ $menu['label'] }}
                            <svg class="h-3 w-3 transition-transform duration-200" :class="menu === '{{ $kunci }}' ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                        </button>

                        <div x-show="menu === '{{ $kunci }}'" x-cloak
                             x-transition:enter="tirai-masuk"
                             x-transition:leave="tirai-keluar"
                             class="absolute left-0 top-full z-50 mt-1.5 flex gap-1 rounded-xl border border-border bg-popover/95 p-1.5 shadow-lg backdrop-blur-xl">
                            @foreach ($menu['grup'] as $judulGrup => $isiGrup)
                                <div class="min-w-52">
                                    <p class="px-3 py-2.5 text-xs text-muted-foreground">{{ $judulGrup }}</p>
                                    @foreach ($isiGrup as [$nama, $alamat])
                                        <a href="{{ $alamat }}" @click="tutup()"
                                           class="block whitespace-nowrap rounded-lg px-3 py-2 text-[15px] text-foreground/80 transition-colors hover:bg-muted hover:text-foreground">
                                            {{ $nama }}
                                        </a>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <a href="#harga" class="rounded-lg px-3 py-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground">Harga</a>
            </div>

            <button @click="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'"
                    class="ml-auto hidden rounded-md p-2 text-muted-foreground hover:bg-muted hover:text-foreground md:ml-2 md:block"
                    aria-label="Toggle Dark Mode">
                <!-- Ikon Matahari (Tampil saat Dark Mode) -->
                <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                <!-- Ikon Bulan (Tampil saat Light Mode) -->
                <svg class="block h-5 w-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
            </button>

            <a href="{{ route('docs.index') }}" class="hidden md:block rounded-lg px-3 py-2 font-medium text-foreground hover:bg-muted">Docs</a>

            @auth
                <a href="{{ route('dashboard') }}" class="hidden md:block rounded-lg bg-primary px-4 py-2 font-medium text-primary-foreground hover:bg-primary/90">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="hidden md:block rounded-lg px-3 py-2 text-muted-foreground hover:bg-muted hover:text-foreground">Masuk</a>
                <a href="{{ route('register') }}" class="hidden md:block rounded-lg bg-primary px-4 py-2 font-medium text-primary-foreground hover:bg-primary/90">Buat akun</a>
            @endauth

            <button @click="mobileMenu = !mobileMenu" class="rounded-md p-2 text-muted-foreground hover:bg-muted lg:hidden" aria-label="Menu Mobile">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </nav>
    </div>
</header>

<!-- Fullscreen Mobile Menu -->
<div x-show="mobileMenu" x-cloak
     x-transition:enter="transition-transform duration-300 ease-in-out"
     x-transition:enter-start="translate-y-full"
     x-transition:enter-end="translate-y-0"
     x-transition:leave="transition-transform duration-300 ease-in-out"
     x-transition:leave-start="translate-y-0"
     x-transition:leave-end="translate-y-full"
     class="fixed inset-0 z-50 flex flex-col bg-background lg:hidden">

    <div class="flex items-center justify-between px-5 py-4">
        <span class="font-semibold text-lg">Menu</span>
        <button @click="mobileMenu = false" class="rounded-md p-2 text-muted-foreground hover:bg-muted" aria-label="Tutup Menu">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <div class="flex-1 space-y-6 overflow-y-auto px-6 py-6">
        {{-- Di layar kecil dropdown-nya dibentangkan jadi daftar bertingkat.
             Panel menggantung tidak punya tempat di sini, dan menyembunyikan
             isinya di balik ketukan kedua hanya menambah langkah. --}}
        @foreach ($menuHeader as $menu)
            @foreach ($menu['grup'] as $judulGrup => $isiGrup)
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ $judulGrup }}</p>
                    <div class="mt-2 space-y-2">
                        @foreach ($isiGrup as [$nama, $alamat])
                            <a href="{{ $alamat }}" @click="mobileMenu = false" class="block text-lg font-medium text-foreground">{{ $nama }}</a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endforeach

        <button @click="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'" class="flex w-full items-center justify-between text-lg font-medium text-foreground">
            <span>Tema Tampilan</span>
            <svg class="hidden h-6 w-6 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            <svg class="block h-6 w-6 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
        </button>

        <div class="mt-6 flex flex-col gap-4 pt-6">
            @auth
                <a href="{{ route('dashboard') }}" class="block text-center rounded-xl bg-primary px-4 py-3 text-lg font-medium text-primary-foreground">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="block text-center rounded-xl border border-input px-4 py-3 text-lg font-medium text-foreground">Masuk</a>
                <a href="{{ route('register') }}" class="block text-center rounded-xl bg-primary px-4 py-3 text-lg font-medium text-primary-foreground">Buat akun</a>
            @endauth
        </div>
    </div>
</div>

{{-- ===================== Hero ===================== --}}
<section class="mx-auto max-w-6xl px-8 pb-16 pt-28 lg:px-10 sm:pt-36">
    <div class="grid items-center gap-12 lg:grid-cols-2">
        <div class="min-w-0">
            <span class="muncul inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                Sesi bertahan saat server di-deploy ulang
            </span>

            <h1 class="muncul mt-5 text-4xl font-semibold leading-tight tracking-tight sm:text-5xl" style="--tunda: 60ms">
                Kirim WhatsApp dari aplikasi Anda lewat satu API
            </h1>

            <p class="muncul mt-5 text-lg leading-relaxed text-muted-foreground" style="--tunda: 120ms">
                Tautkan nomor sekali, lalu kirim notifikasi dari aplikasi mana pun
                dengan satu panggilan HTTP.
            </p>

            <div class="muncul mt-8 flex flex-wrap gap-3" style="--tunda: 180ms">
                <a href="{{ route('register') }}" class="rounded-lg bg-primary px-5 py-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                    Buat akun
                </a>
                <a href="#harga" class="rounded-lg border border-input px-5 py-3 text-sm font-medium hover:bg-accent hover:text-accent-foreground">
                    Lihat harga
                </a>
            </div>

            <p class="muncul mt-5 text-sm text-muted-foreground" style="--tunda: 240ms">
                Dipakai sendiri oleh produk Flustra lainnya.
            </p>
        </div>

        <div class="muncul min-w-0 overflow-hidden rounded-2xl border border-border bg-[var(--code-chrome)] p-1 shadow-sm" style="--tunda: 260ms">
            <div class="flex items-center gap-1.5 px-3 py-2.5">
                <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-[var(--code-string)]"></span>
                <span class="ml-2 text-xs text-[var(--code-muted)]">kirim-notifikasi.sh</span>
            </div>
            <pre class="overflow-x-auto rounded-xl bg-[var(--code-bg)] p-5 text-[13px] leading-relaxed text-[var(--code-foreground)]"><code>curl -X POST https://wa.flustra.id/api/v1/messages/text \
  -H <span class="text-[var(--code-string)]">"X-Api-Key: fwa_a1b2c3d4.…"</span> \
  -H <span class="text-[var(--code-string)]">"Content-Type: application/json"</span> \
  -d <span class="text-[var(--code-payload)]">'{
    "to": "081234567890",
    "message": "Invoice INV-001 sudah lunas. Terima kasih!"
  }'</span>

<span class="text-[var(--code-muted)]">→ { "success": true, "data": { "status": "queued" } }</span></code></pre>
        </div>
    </div>
</section>

{{-- ===================== Cara kerja =====================

     Kartu di halaman ini sengaja tidak berlatar: warnanya sama persis dengan
     halaman, hanya garis tepinya yang membedakan. Kotak berlatar bertumpuk
     membuat halaman terasa penuh padahal isinya sedikit.
     ======================================================= --}}
<section id="cara-kerja" class="py-16">
    <div class="mx-auto max-w-6xl px-8 lg:px-10">
        <h2 class="muncul text-2xl font-semibold tracking-tight">Tiga langkah</h2>

        <div class="mt-8 grid gap-5 md:grid-cols-3">
            @php
                $langkah = [
                    ['1', 'Tautkan nomor', 'Sekali scan QR di dashboard. Setelah itu sesinya tersimpan permanen.'],
                    ['2', 'Ambil API key', 'Satu kunci per aplikasi, bisa dicabut kapan saja.'],
                    ['3', 'Panggil API-nya', 'Satu permintaan HTTP untuk mengirim.'],
                ];
            @endphp
            @foreach ($langkah as $i => [$no, $judul, $isi])
                <div class="muncul rounded-xl border border-border p-5" style="--tunda: {{ $i * 90 }}ms">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary text-sm font-semibold text-primary-foreground">{{ $no }}</span>
                    <h3 class="mt-4 font-semibold">{{ $judul }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-muted-foreground">{{ $isi }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===================== Fitur ===================== --}}
<section id="fitur" class="py-16">
    <div class="mx-auto max-w-6xl px-8 lg:px-10">
        <h2 class="muncul text-2xl font-semibold tracking-tight">Yang membedakan</h2>

        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @php
                // Ikon disimpan sebagai path SVG mentah supaya tidak perlu
                // menambah pustaka ikon hanya untuk satu halaman.
                $fitur = [
                    ['M12 2v4M12 18v4M4.9 4.9l2.9 2.9M16.2 16.2l2.9 2.9M2 12h4M18 12h4M4.9 19.1l2.9-2.9M16.2 7.8l2.9-2.9', 'Sesi tidak putus saat deploy', 'Kredensial nomor tersimpan permanen dan dicadangkan otomatis.'],
                    ['M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 7a4 4 0 1 0 0 .01M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75', 'Banyak nomor sekaligus', 'Satu nomor bermasalah tidak menghentikan nomor lain.'],
                    ['M12 6v6l4 2M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z', 'Jeda kirim otomatis', 'Tiap pesan diberi jeda acak dan diantre per nomor.'],
                    ['M4 4h16v12H5.17L4 17.17V4zM8 9h8M8 12h5', 'Webhook pesan masuk', 'Balasan pelanggan diteruskan ke aplikasi Anda, bertanda tangan.'],
                    ['M9 11l3 3 8-8M21 12a9 9 0 1 1-6.22-8.56', 'Riwayat & status kirim', 'Terkirim, sampai, atau dibaca — tercatat per pesan.'],
                    ['M3 7h18M3 12h18M3 17h18', 'Workspace terpisah', 'Cabang atau klien punya nomor, kunci, dan riwayat sendiri.'],
                ];
            @endphp
            @foreach ($fitur as $i => [$ikon, $judul, $isi])
                <div class="muncul rounded-xl border border-border p-5" style="--tunda: {{ ($i % 3) * 80 }}ms">
                    <span class="grid h-10 w-10 place-items-center rounded-lg bg-primary/10 text-primary">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $ikon }}"/></svg>
                    </span>
                    <h3 class="mt-4 font-semibold">{{ $judul }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-muted-foreground">{{ $isi }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===================== Kapan dipakai ===================== --}}
<section class="py-16">
    <div class="mx-auto max-w-6xl px-8 lg:px-10">
        <h2 class="muncul text-2xl font-semibold tracking-tight">Kapan terpakai</h2>

        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @php
                $pemakaian = [
                    ['Toko & e-commerce', 'Konfirmasi pesanan dan nomor resi.'],
                    ['Penagihan', 'Invoice terbit, lunas, atau lewat jatuh tempo.'],
                    ['Jasa terjadwal', 'Pengingat janji temu sehari sebelumnya.'],
                    ['Verifikasi & OTP', 'Kode sekali pakai dari nomor Anda sendiri.'],
                ];
            @endphp
            @foreach ($pemakaian as $i => [$judul, $isi])
                <div class="muncul rounded-xl border border-border p-5" style="--tunda: {{ ($i % 4) * 70 }}ms">
                    <h3 class="font-semibold">{{ $judul }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-muted-foreground">{{ $isi }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===================== Untuk developer ===================== --}}
<section id="untuk-developer" class="py-16">
    <div class="mx-auto max-w-6xl px-8 lg:px-10">
        <h2 class="muncul text-2xl font-semibold tracking-tight">Untuk developer</h2>
        <p class="muncul mt-2 text-muted-foreground" style="--tunda: 60ms">
            HTTP dan JSON biasa. Tidak ada SDK yang wajib dipasang.
        </p>

        <div class="muncul mt-8" style="--tunda: 120ms" x-data="{ bahasa: 'php' }">
            <div class="flex flex-wrap gap-1 text-sm">
                @foreach (['php' => 'PHP / Laravel', 'node' => 'Node.js', 'python' => 'Python'] as $kode => $label)
                    <button @click="bahasa = '{{ $kode }}'"
                            class="rounded-lg px-3 py-1.5 font-medium transition"
                            :class="bahasa === '{{ $kode }}' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="mt-4 overflow-hidden rounded-2xl border border-border bg-[var(--code-chrome)] p-1">
                <div x-show="bahasa === 'php'">
<pre class="overflow-x-auto rounded-xl bg-[var(--code-bg)] p-5 text-[13px] leading-relaxed text-[var(--code-foreground)]"><code>Http::withHeaders([<span class="text-[var(--code-string)]">'X-Api-Key'</span> =&gt; config(<span class="text-[var(--code-string)]">'services.wa.key'</span>)])
    -&gt;post(<span class="text-[var(--code-string)]">'https://wa.flustra.id/api/v1/messages/text'</span>, [
        <span class="text-[var(--code-payload)]">'to'</span>      =&gt; $invoice-&gt;customer_phone,
        <span class="text-[var(--code-payload)]">'message'</span> =&gt; <span class="text-[var(--code-string)]">"Invoice {$invoice->number} sudah lunas."</span>,
    ]);</code></pre>
                </div>

                <div x-show="bahasa === 'node'" x-cloak>
<pre class="overflow-x-auto rounded-xl bg-[var(--code-bg)] p-5 text-[13px] leading-relaxed text-[var(--code-foreground)]"><code><span class="text-[var(--code-muted)]">await</span> fetch(<span class="text-[var(--code-string)]">'https://wa.flustra.id/api/v1/messages/text'</span>, {
  method: <span class="text-[var(--code-string)]">'POST'</span>,
  headers: {
    <span class="text-[var(--code-payload)]">'X-Api-Key'</span>: process.env.WA_KEY,
    <span class="text-[var(--code-payload)]">'Content-Type'</span>: <span class="text-[var(--code-string)]">'application/json'</span>,
  },
  body: JSON.stringify({ to: pesanan.telepon, message: teks }),
});</code></pre>
                </div>

                <div x-show="bahasa === 'python'" x-cloak>
<pre class="overflow-x-auto rounded-xl bg-[var(--code-bg)] p-5 text-[13px] leading-relaxed text-[var(--code-foreground)]"><code>requests.post(
    <span class="text-[var(--code-string)]">"https://wa.flustra.id/api/v1/messages/text"</span>,
    headers={<span class="text-[var(--code-payload)]">"X-Api-Key"</span>: os.environ[<span class="text-[var(--code-string)]">"WA_KEY"</span>]},
    json={<span class="text-[var(--code-payload)]">"to"</span>: pelanggan.telepon, <span class="text-[var(--code-payload)]">"message"</span>: teks},
    timeout=<span class="text-[var(--code-payload)]">10</span>,
)</code></pre>
                </div>
            </div>
        </div>

        <div class="muncul mt-8 flex flex-wrap gap-3" style="--tunda: 160ms">
            <a href="{{ route('docs.show', 'referensi-api') }}" class="rounded-lg bg-secondary px-5 py-2.5 text-sm font-medium text-secondary-foreground hover:bg-secondary/80">
                Referensi API
            </a>
            <a href="{{ route('docs.show', 'contoh-integrasi') }}" class="rounded-lg border border-border bg-transparent px-5 py-2.5 text-sm font-medium hover:bg-muted">
                Contoh integrasi
            </a>
            <a href="{{ route('docs.index') }}" class="rounded-lg border border-border bg-transparent px-5 py-2.5 text-sm font-medium hover:bg-muted">
                Semua dokumentasi
            </a>
        </div>
    </div>
</section>

{{-- ===================== Harga =====================

     Kartu harga ini masih etalase: belum ada penagihan, langganan, maupun
     penegakan batas di balik angkanya. Tombolnya sengaja mengarah ke
     pendaftaran biasa. Sebelum harga ini benar-benar ditagihkan, batas
     workspace dan kuota pesan per paket harus ditegakkan lebih dulu di
     sisi aplikasi.
     ============================================================= --}}
<section id="harga" class="py-16">
    <div class="mx-auto max-w-6xl px-8 lg:px-10" x-data="{ tahunan: false }">
        <div class="muncul max-w-2xl">
            <h2 class="text-2xl font-semibold tracking-tight">Harga</h2>
            <p class="mt-2 text-muted-foreground">
                Semua paket memakai gateway, API, dan dashboard yang sama. Yang membedakan hanya
                seberapa besar Anda memakainya.
            </p>
        </div>

        <div class="muncul mt-8 flex items-center gap-3" style="--tunda: 60ms">
            <button @click="tahunan = false"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="! tahunan ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'">
                Bulanan
            </button>
            <button @click="tahunan = true"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="tahunan ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'">
                Tahunan
            </button>
            <span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">Bayar tahunan, hemat 2 bulan</span>
        </div>

        @php
            // Harga tahunan = harga bulanan x 10, jadi dua bulan gratis.
            $paket = [
                [
                    'nama' => 'Mulai',
                    'bulanan' => 150_000,
                    'untuk' => 'Satu usaha dengan satu nomor.',
                    'sorot' => false,
                    'fitur' => [
                        '1 workspace',
                        '1 nomor WhatsApp aktif',
                        '3.000 pesan keluar per bulan',
                        '3 API key',
                        'Webhook pesan masuk',
                        'Riwayat pesan 30 hari',
                        'Template pesan',
                        'Dukungan lewat email',
                    ],
                ],
                [
                    'nama' => 'Bisnis',
                    'bulanan' => 300_000,
                    'untuk' => 'Beberapa cabang atau beberapa aplikasi sekaligus.',
                    'sorot' => true,
                    'fitur' => [
                        '3 workspace',
                        '3 nomor WhatsApp aktif',
                        '15.000 pesan keluar per bulan',
                        '10 API key',
                        'Pengiriman massal terjadwal',
                        'Riwayat pesan 90 hari',
                        'Anggota tim dengan peran',
                        'Dukungan lewat WhatsApp',
                    ],
                ],
                [
                    'nama' => 'Skala',
                    'bulanan' => 550_000,
                    'untuk' => 'Agensi dan perusahaan dengan banyak merek.',
                    'sorot' => false,
                    'fitur' => [
                        '10 workspace',
                        '10 nomor WhatsApp aktif',
                        '50.000 pesan keluar per bulan',
                        'API key tanpa batas',
                        'Prioritas antrean pengiriman',
                        'Riwayat pesan 12 bulan',
                        'Laporan pemakaian bulanan',
                        'Pendampingan saat pemasangan',
                    ],
                ],
            ];
        @endphp

        <div class="mt-10 grid items-start gap-6 lg:grid-cols-3">
            @foreach ($paket as $i => $p)
                <div class="muncul relative rounded-2xl border p-6 {{ $p['sorot'] ? 'border-primary bg-primary/5 shadow-sm lg:-mt-3 lg:pb-8' : 'border-border' }}"
                     style="--tunda: {{ $i * 90 }}ms">
                    @if ($p['sorot'])
                        <span class="absolute -top-3 left-6 rounded-full bg-primary px-3 py-1 text-xs font-medium text-primary-foreground">Paling banyak dipilih</span>
                    @endif

                    <h3 class="font-semibold">{{ $p['nama'] }}</h3>
                    <p class="mt-1 text-sm text-muted-foreground">{{ $p['untuk'] }}</p>

                    <p class="mt-5 flex items-baseline gap-1.5">
                        <span class="text-3xl font-semibold tracking-tight"
                              x-text="tahunan ? 'Rp{{ number_format($p['bulanan'] * 10, 0, ',', '.') }}' : 'Rp{{ number_format($p['bulanan'], 0, ',', '.') }}'">Rp{{ number_format($p['bulanan'], 0, ',', '.') }}</span>
                        <span class="text-sm text-muted-foreground" x-text="tahunan ? '/tahun' : '/bulan'">/bulan</span>
                    </p>
                    {{-- Dibuat tak terlihat, bukan disembunyikan: kalau barisnya
                         ikut hilang, ketiga kartu bergeser naik-turun tiap kali
                         sakelar bulanan/tahunan ditekan. --}}
                    <p class="mt-1 text-xs text-muted-foreground" :class="tahunan ? '' : 'invisible'">
                        Setara Rp{{ number_format($p['bulanan'] * 10 / 12, 0, ',', '.') }} per bulan.
                    </p>

                    <a href="{{ route('register') }}"
                       class="mt-6 block rounded-lg px-4 py-2.5 text-center text-sm font-medium {{ $p['sorot'] ? 'bg-primary text-primary-foreground hover:bg-primary/90' : 'border border-input hover:bg-accent hover:text-accent-foreground' }}">
                        Pilih {{ $p['nama'] }}
                    </a>

                    <ul class="mt-6 space-y-2.5 text-sm">
                        @foreach ($p['fitur'] as $f)
                            <li class="flex gap-2.5">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                                <span class="text-muted-foreground">{{ $f }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <p class="muncul mt-6 text-sm text-muted-foreground" style="--tunda: 120ms">
            Butuh nomor atau kuota lebih banyak dari paket Skala?
            <a href="https://about.flustra.id/#contact" class="text-primary hover:underline">Hubungi kami</a> untuk penawaran khusus.
        </p>
    </div>
</section>

{{-- ===================== Pertanyaan umum ===================== --}}
<section class="py-16">
    <div class="mx-auto max-w-3xl px-8 lg:px-10">
        <h2 class="muncul text-2xl font-semibold tracking-tight">Pertanyaan yang sering muncul</h2>

        <div class="mt-8" x-data="{ terbuka: 0 }">
            @php
                $tanya = [
                    ['Apakah nomor saya terkunci di sini?', 'Tidak. Putuskan tautannya dari dashboard, lalu tautkan nomor baru kapan saja.'],
                    ['Berapa lama pemasangannya?', 'Menautkan nomor pertama biasanya di bawah satu menit. Sisanya tinggal menempelkan API key ke aplikasi Anda.'],
                    ['Apa yang terjadi kalau kuota habis?', 'Pengiriman berhenti di batas paket dan tercatat di dashboard, bukan gagal diam-diam.'],
                    ['Bagaimana kalau aplikasi saya bukan Laravel?', 'Antarmukanya REST biasa. Contoh siap salin tersedia untuk PHP, Node.js, dan Python.'],
                ];
            @endphp
            @foreach ($tanya as $i => [$judul, $isi])
                <div class="muncul border-b border-border/60 py-4" style="--tunda: {{ $i * 50 }}ms">
                    <button @click="terbuka = terbuka === {{ $i }} ? null : {{ $i }}"
                            class="flex w-full items-center justify-between gap-4 text-left font-medium">
                        <span>{{ $judul }}</span>
                        <svg class="h-4 w-4 shrink-0 text-muted-foreground transition-transform"
                             :class="terbuka === {{ $i }} ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="terbuka === {{ $i }}" x-collapse x-cloak>
                        <p class="pt-3 text-sm leading-relaxed text-muted-foreground">{{ $isi }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ===================== Penutup ===================== --}}
<section class="py-20">
    <div class="mx-auto max-w-3xl px-8 text-center lg:px-10">
        <h2 class="muncul text-2xl font-semibold tracking-tight">Siap mencoba?</h2>
        <p class="muncul mt-3 text-muted-foreground" style="--tunda: 60ms">Buat akun, tautkan satu nomor, dan kirim pesan pertama Anda hari ini.</p>
        <div class="muncul mt-7 flex flex-wrap justify-center gap-3" style="--tunda: 120ms">
            <a href="{{ route('register') }}" class="rounded-lg bg-primary px-6 py-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                Buat akun
            </a>
            <a href="{{ route('docs.show', 'mulai-cepat') }}" class="rounded-lg border border-input px-6 py-3 text-sm font-medium hover:bg-accent hover:text-accent-foreground">
                Baca panduan mulai cepat
            </a>
        </div>
    </div>
</section>

<footer class="border-t border-border bg-background py-12">
    <div class="mx-auto max-w-7xl px-6">
        <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <a href="/" class="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
                    <img src="{{ asset('images/flustra-wa.png') }}" alt="Logo" class="h-8 w-auto object-contain">
                    FLUSTRA.
                </a>
                <p class="mt-4 max-w-sm text-sm leading-relaxed text-muted-foreground">
                    Meningkatkan komunikasi dan notifikasi pelanggan melalui gateway WhatsApp yang tangguh.
                </p>
            </div>

            <div>
                <h3 class="text-xs font-semibold tracking-wider text-primary uppercase">Gateway ini</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    <li><a href="#fitur" class="text-muted-foreground hover:text-foreground">Fitur</a></li>
                    <li><a href="#harga" class="text-muted-foreground hover:text-foreground">Harga</a></li>
                    <li><a href="{{ route('docs.index') }}" class="text-muted-foreground hover:text-foreground">Dokumentasi</a></li>
                    <li><a href="{{ route('login') }}" class="text-muted-foreground hover:text-foreground">Masuk</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-xs font-semibold tracking-wider text-primary uppercase">Produk Flustra</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    @foreach (config('flustra.produk') as $nama => $alamat)
                        <li><a href="{{ $alamat }}" class="text-muted-foreground hover:text-foreground">{{ $nama }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h3 class="text-xs font-semibold tracking-wider text-primary uppercase">Perusahaan</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    <li><a href="https://about.flustra.id" class="text-muted-foreground hover:text-foreground">Tentang Kami</a></li>
                    <li><a href="https://about.flustra.id/#contact" class="text-muted-foreground hover:text-foreground">Kontak</a></li>
                    <li><a href="#" class="text-muted-foreground hover:text-foreground">Kebijakan Privasi</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-border pt-8 sm:flex-row">
            <p class="text-sm text-muted-foreground">&copy; {{ date('Y') }} Flustra WA Gateway.</p>

            <div class="flex items-center gap-4 text-muted-foreground">
                <a href="https://www.instagram.com/flustra.id" class="hover:text-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="20" x="2" y="2" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"></line>
                    </svg>
                </a>
                <a href="#" class="hover:text-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11c-1.4-4.8-5.3-8.5-9.9-8.5C5.9 2.5 2 7.2 2 12c0 4.9 3.9 9.5 10.2 9.5 2.6 0 5-.8 6.8-2.3 1.3-1 1.9-2.6 2-4.1v-.3c0-1.8-1.1-3.3-2.7-3.3-1 0-1.9.6-2.4 1.5-1.1 1.9-2.1 3-4.2 3-2.1 0-4-1.6-4-4s1.9-4 4-4c1.7 0 3.3 1.1 3.7 2.7.2.7.1 1.4-.3 2-.4.6-1 .9-1.7.9h-2.1"></path>
                    </svg>
                </a>
                <a href="https://x.com/flustraid" class="hover:text-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4l11.733 16h4.267l-11.733 -16z"></path><path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772"></path>
                    </svg>
                </a>
                <a href="https://www.tiktok.com/@flustra.id" class="hover:text-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"></path>
                    </svg>
                </a>
                <a href="#" class="hover:text-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect width="4" height="12" x="2" y="9"></rect><circle cx="4" cy="4" r="2"></circle>
                    </svg>
                </a>
            </div>
        </div>
    </div>
</footer>

<script>
    // Blok halaman muncul saat masuk layar. Sengaja ditulis polos tanpa Alpine:
    // ini berjalan di setiap elemen halaman depan, dan IntersectionObserver
    // sudah cukup. Kelas .animasi-muncul dipasang di <head>; kalau ia tidak
    // ada, CSS tidak menyembunyikan apa pun dan pengamat ini tidak diperlukan.
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
