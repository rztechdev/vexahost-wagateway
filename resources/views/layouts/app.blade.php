<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>
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
    </script>
</head>
<body class="h-full bg-background text-foreground antialiased">

@php
    /*
     | Menu samping, dikelompokkan.
     |
     | Sembilan tautan dalam satu daftar rata membuat orang membaca seluruhnya
     | setiap kali mencari satu. Dikelompokkan, mata cukup memilih kelompoknya
     | dulu. Pengelompokannya mengikuti apa yang sedang dikerjakan pengguna,
     | bukan urutan pembuatan fiturnya.
     |
     | `cocok` dipisah dari `rute` karena beberapa halaman anak punya nama rute
     | sendiri — membuka satu pesan (`messages.show`) atau satu tagihan
     | (`billing.invoice`) harus tetap menyalakan menu induknya. Tanpa itu,
     | pengguna di halaman anak melihat seluruh menu padam dan kehilangan
     | petunjuk di mana ia berada.
     */
    $menu = [
        'Ikhtisar' => [
            ['rute' => 'dashboard', 'label' => 'Dashboard', 'ikon' => 'M3 12l9-9 9 9M5 10v10h14V10'],
            ['rute' => 'sessions.index', 'label' => 'Sesi WhatsApp', 'ikon' => 'M12 2a10 10 0 1 0 4.9 18.7L22 22l-1.3-5.1A10 10 0 0 0 12 2z'],
        ],
        'Pesan' => [
            ['rute' => 'messages.compose', 'label' => 'Kirim Pesan', 'ikon' => 'M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z'],
            ['rute' => 'messages.index', 'label' => 'Riwayat Pesan', 'cocok' => ['messages.index', 'messages.show'], 'ikon' => 'M12 8v4l3 2M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z'],
            ['rute' => 'templates.index', 'label' => 'Template', 'ikon' => 'M4 4h16v4H4zM4 12h10v8H4zM18 12h2v8h-2z'],
        ],
        'Integrasi' => [
            ['rute' => 'api-keys.index', 'label' => 'API Keys', 'ikon' => 'M21 2l-2 2m-7.6 7.6a5 5 0 1 1-7 7 5 5 0 0 1 7-7zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3'],
            ['rute' => 'webhooks.index', 'label' => 'Webhooks', 'ikon' => 'M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0'],
        ],
        'Akun' => [
            ['rute' => 'billing.index', 'label' => 'Langganan', 'cocok' => ['billing.*'], 'ikon' => 'M2 7h20v12H2zM2 11h20M6 15h4'],
            ['rute' => 'settings', 'label' => 'Pengaturan', 'cocok' => ['settings', 'settings.*'], 'ikon' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 7 19.4a1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0-1.2-2.9H1a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 2.6 7a1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H7a1.7 1.7 0 0 0 1-1.5V1a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V7a1.7 1.7 0 0 0 1.5 1H23a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z'],
        ],
    ];

    $aktif = function (array $item): bool {
        return request()->routeIs(...($item['cocok'] ?? [$item['rute']]));
    };
@endphp

<div x-data="{ sidebar: false }">

    {{-- Penutup layar saat menu terbuka di layar kecil --}}
    <div x-show="sidebar" x-cloak @click="sidebar = false"
         x-transition:enter="transition-opacity ease-linear duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-200"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-40 bg-black/50 lg:hidden"></div>

    {{-- ===================== Sidebar =====================

         Menempel di tepi kiri dengan latar dan garisnya sendiri, bukan menyatu
         dengan halaman seperti sebelumnya. Bedanya bukan selera: sidebar yang
         berlatar sama dengan konten tidak terbaca sebagai navigasi tetap —
         orang membacanya sebagai kolom teks pertama halaman, lalu mencari menu
         di tempat lain.
         ============================================================= --}}
    {{-- Buka-tutupnya diatur `.panel-sidebar` di app.css, bukan utilitas
         `-translate-x-full` Tailwind. Dua aturan pendek yang menyebut
         `transform` secara langsung lebih mudah ditelusuri daripada utilitas v4
         yang menyusun nilainya dari beberapa variabel (`--tw-translate-x/y/z`)
         — dan di layar lebar sidebar tidak bergeser sama sekali, jadi tidak ada
         yang hilang dengan tidak memakai varian `lg:`. --}}
    <aside class="panel-sidebar fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-sidebar-border bg-sidebar text-sidebar-foreground transition-transform duration-200 ease-out"
           :class="sidebar && 'terbuka'">

        <div class="flex h-16 shrink-0 items-center gap-2.5 px-5">
            <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2.5 font-semibold">
                <img src="{{ asset('images/flustra-wa.png') }}" alt="" class="h-7 w-auto shrink-0 object-contain">
                <span class="truncate">Flustra WA</span>
            </a>
            <button @click="sidebar = false" class="ml-auto rounded-md p-1 text-muted-foreground hover:bg-sidebar-accent lg:hidden" aria-label="Tutup menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- ===================== Pemilih workspace =====================

             Ditaruh di sidebar, di atas menu, karena seluruh isi menu di
             bawahnya adalah milik workspace yang sedang terpilih. Menaruhnya di
             header kanan seperti sebelumnya memutus hubungan itu: orang
             berpindah workspace lalu heran kenapa daftar sesinya berubah.
             ============================================================= --}}
        @isset($availableWorkspaces)
            <div class="p-3" x-data="{ terbuka: false }" @click.outside="terbuka = false">
                <button @click="terbuka = ! terbuka"
                        class="flex w-full items-center gap-2 rounded-lg border border-sidebar-border bg-background/40 px-3 py-2 text-left text-sm transition hover:bg-sidebar-accent hover:text-sidebar-accent-foreground">
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-sidebar-primary text-xs font-semibold text-sidebar-primary-foreground">
                        {{ mb_strtoupper(mb_substr($currentWorkspace->name, 0, 1)) }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium">{{ $currentWorkspace->name }}</span>
                    </span>
                    <svg class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" :class="terbuka ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>

                <div x-show="terbuka" x-cloak x-transition.opacity.duration.150ms class="mt-1.5 space-y-0.5">
                    @foreach ($availableWorkspaces as $t)
                        <form method="POST" action="{{ route('workspaces.switch', $t->id) }}">
                            @csrf
                            <button class="block w-full truncate rounded-lg px-3 py-1.5 text-left text-sm {{ $t->id === $currentWorkspace->id ? 'bg-sidebar-accent font-medium text-sidebar-accent-foreground' : 'text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground' }}">
                                {{ $t->name }}
                            </button>
                        </form>
                    @endforeach

                    <a href="{{ route('onboarding.create') }}"
                       class="block rounded-lg px-3 py-1.5 text-sm text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground">
                        + Workspace baru
                    </a>
                </div>
            </div>
        @endisset

        {{-- Menu selalu tampil, termasuk sebelum pengguna punya workspace.

             Sebelum ada workspace tidak ada satu pun halaman di dalamnya yang
             bisa dibuka — middleware memantulkan semuanya — jadi tautannya
             diarahkan ke onboarding, tempat satu-satunya yang bisa dituju.
             Menyembunyikan menunya sama sekali membuat pendaftar baru tidak
             pernah tahu apa yang sebenarnya mereka dapat. --}}
        <nav class="flex-1 space-y-6 overflow-y-auto p-3">
            @php $punyaWorkspace = isset($availableWorkspaces); @endphp
            @foreach ($menu as $kelompok => $tautan)
                <div>
                    <p class="px-3 pb-1.5 text-[0.7rem] font-semibold uppercase tracking-wider text-muted-foreground">{{ $kelompok }}</p>
                    <div class="space-y-0.5">
                        @foreach ($tautan as $item)
                            <a href="{{ $punyaWorkspace ? route($item['rute']) : route('onboarding.create') }}"
                               class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm {{ $punyaWorkspace && $aktif($item) ? 'bg-sidebar-primary font-medium text-sidebar-primary-foreground' : 'text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground' }}">
                                <svg class="h-[1.05rem] w-[1.05rem] shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ $item['ikon'] }}"/></svg>
                                <span class="truncate">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div>
                <p class="px-3 pb-1.5 text-[0.7rem] font-semibold uppercase tracking-wider text-muted-foreground">Bantuan</p>
                <div class="space-y-0.5">
                    <a href="{{ route('docs.index') }}" target="_blank" rel="noopener"
                       class="group flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-muted-foreground transition hover:bg-sidebar-accent hover:text-sidebar-accent-foreground">
                        <svg class="h-[1.05rem] w-[1.05rem] shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5A2.5 2.5 0 0 0 6.5 22H20V2H6.5A2.5 2.5 0 0 0 4 4.5v15z"/>
                        </svg>
                        <span class="truncate">Dokumentasi</span>
                        <svg class="ml-auto h-3.5 w-3.5 shrink-0 text-muted-foreground/70 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M7 17L17 7M17 7H7M17 7V17"/>
                        </svg>
                    </a>
                </div>
            </div>
        </nav>

        {{-- Kaki sidebar hanya memuat satu tombol. Identitas pemakai dan menu
             akunnya ada di kanan atas, tempat orang mencarinya di hampir semua
             aplikasi sejenis; menaruhnya di dua tempat berarti dua daftar yang
             harus dijaga tetap sama. --}}
        <div class="shrink-0 border-t border-sidebar-border p-3">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-muted-foreground transition hover:bg-sidebar-accent hover:text-sidebar-accent-foreground">
                    <svg class="h-[1.05rem] w-[1.05rem] shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    Keluar
                </button>
            </form>
        </div>
    </aside>

    {{-- ===================== Isi halaman ===================== --}}
    <div class="lg:pl-72">

        {{-- Judul halaman tinggal di bilah atas, bukan lagi sebagai <h1> di
             dalam konten. Dengan begitu ia tetap terlihat saat halaman digulir,
             dan tiap halaman mulai langsung dari isinya. --}}
        <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-border bg-background/85 px-4 backdrop-blur-md sm:px-6">
            <button @click="sidebar = true" class="-ml-1 rounded-md p-2 text-muted-foreground hover:bg-muted hover:text-foreground lg:hidden" aria-label="Buka menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <h1 class="min-w-0 truncate text-lg font-semibold">@yield('title', 'Dashboard')</h1>

            <div class="ml-auto flex items-center gap-1">
                <button @click="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'"
                        class="rounded-lg p-2 text-muted-foreground hover:bg-muted hover:text-foreground"
                        aria-label="Ganti tema tampilan">
                    <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <svg class="block h-5 w-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                </button>

                {{-- ===================== Profil =====================

                     Di kanan atas, bukan di kaki sidebar. Sidebar menampung apa
                     yang bisa dikerjakan di dalam workspace; akun bukan bagian
                     dari itu, dan di ponsel kaki sidebar hanya terlihat setelah
                     menunya dibuka.
                     ============================================ --}}
                <div class="relative ml-1" x-data="{ profil: false }" @click.outside="profil = false" @keydown.escape.window="profil = false">
                    {{-- Hanya avatar. Namanya tetap ada, tapi di dalam dropdown-nya
                         bersama email — di bilah atas ia cuma mengulang sesuatu yang
                         sudah pasti diketahui orang yang sedang login. --}}
                    <button @click="profil = ! profil"
                            class="grid h-9 w-9 place-items-center rounded-full bg-primary/10 text-sm font-semibold text-primary transition hover:bg-primary/20"
                            :aria-expanded="profil" aria-haspopup="true"
                            aria-label="Menu akun">
                        {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                    </button>

                    <div x-show="profil" x-cloak x-transition.opacity.duration.150ms
                         class="absolute right-0 z-40 mt-1.5 w-60 overflow-hidden rounded-xl border border-border bg-popover text-popover-foreground shadow-lg">
                        <div class="border-b border-border px-4 py-3">
                            <p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-muted-foreground">{{ auth()->user()->email }}</p>
                        </div>

                        <div class="p-1.5">
                            @isset($availableWorkspaces)
                                <a href="{{ route('settings') }}" class="block rounded-lg px-3 py-2 text-sm text-muted-foreground hover:bg-muted hover:text-foreground">
                                    Pengaturan workspace
                                </a>
                                <a href="{{ route('billing.index') }}" class="block rounded-lg px-3 py-2 text-sm text-muted-foreground hover:bg-muted hover:text-foreground">
                                    Langganan &amp; tagihan
                                </a>
                            @endisset

                            @if (auth()->user()->is_super_admin)
                                <a href="{{ route('admin.overview') }}" class="block rounded-lg px-3 py-2 text-sm text-muted-foreground hover:bg-muted hover:text-foreground">
                                    Panel admin
                                </a>
                            @endif

                            <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-border pt-1.5">
                                @csrf
                                <button class="block w-full rounded-lg px-3 py-2 text-left text-sm text-muted-foreground hover:bg-muted hover:text-foreground">
                                    Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6">

            {{-- Spanduk langganan.

                 Ini satu-satunya pemberitahuan yang pasti sampai: aplikasi tidak
                 punya konfigurasi email, dan pengingat WhatsApp cuma terkirim ke
                 workspace yang mengisi nomor tagihannya. Karena itu ia muncul di
                 SETIAP halaman dashboard, bukan hanya di halaman Langganan —
                 pelanggan yang gateway-nya berjalan lancar justru yang paling
                 jarang membuka halaman itu. --}}
            {{-- Spanduk ini menyusul pengguna ke SETIAP halaman, kecuali dua yang
                 sudah punya penjelasannya sendiri yang jauh lebih lengkap:
                 Langganan (dengan tanggal dan tangga apa yang terjadi
                 selanjutnya) dan Sesi (dengan nasib nomor yang sudah tertaut).
                 Tanpa pengecualian ini, halaman Sesi menampilkan tiga kotak
                 beruntun yang mengatakan hal yang sama dengan kalimat berbeda —
                 dan ketiganya berhenti dibaca. --}}
            @if (isset($currentSubscription) && ! request()->routeIs('billing.*', 'sessions.*'))
                {{-- Yang belum pernah berlangganan butuh kalimat yang berbeda dari
                     yang langganannya berhenti. "Layanan sedang berhenti"
                     tidak masuk akal bagi orang yang belum pernah mengirim apa pun,
                     dan kalimat yang salah di layar pertama membuat pendaftar baru
                     mengira ada yang rusak. --}}
                @if ($currentSubscription->isFreeTier())
                    {{-- Sisa jatah, bukan sisa hari. Masa coba ini tidak punya
                         tanggal berakhir; yang menghabiskannya adalah pesan
                         kelima, dan angka itulah yang harus terlihat sebelum
                         seseorang membangun integrasi di atasnya. --}}
                    @php
                        $jatahGratis = (int) $currentWorkspace->monthly_message_quota;
                        $terpakaiGratis = $currentWorkspace->freeMessagesUsed();
                        $sisaGratis = max(0, $jatahGratis - $terpakaiGratis);
                    @endphp
                    <div @class([
                        'mb-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border px-4 py-3 text-sm',
                        'border-primary/20 bg-primary/10 text-primary' => $sisaGratis > 0,
                        'border-destructive/20 bg-destructive/10 text-destructive' => $sisaGratis === 0,
                    ])>
                        <span>
                            @if ($sisaGratis > 0)
                                <strong>Masa coba gratis: sisa {{ $sisaGratis }} dari {{ $jatahGratis }} pesan.</strong>
                                Nomor Anda tetap tertaut setelah jatahnya habis — yang berhenti hanya pengirimannya.
                            @else
                                <strong>Jatah {{ $jatahGratis }} pesan gratis sudah habis.</strong>
                                Nomor Anda masih tertaut dan tidak perlu discan ulang. Pilih paket untuk mengirim lagi.
                            @endif
                        </span>
                        <a href="{{ route('billing.plans') }}" @class([
                            'shrink-0 rounded-lg px-3 py-1.5 font-medium',
                            'bg-primary text-primary-foreground hover:opacity-90' => $sisaGratis > 0,
                            'bg-destructive text-white hover:opacity-90' => $sisaGratis === 0,
                        ])>
                            {{ $sisaGratis > 0 ? 'Lihat paket' : 'Pilih paket' }}
                        </a>
                    </div>
                @elseif ($currentSubscription->isUnpaid())
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-primary/20 bg-primary/10 px-4 py-3 text-sm text-primary">
                        <span>
                            <strong>Pilih paket untuk mulai.</strong>
                            Menautkan nomor dan mengirim pesan terbuka setelah langganan pertama Anda aktif.
                        </span>
                        <a href="{{ route('billing.plans') }}" class="shrink-0 rounded-lg bg-primary px-3 py-1.5 font-medium text-primary-foreground hover:opacity-90">
                            Lihat paket
                        </a>
                    </div>
                @elseif (! $currentSubscription->isUsable())
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-destructive/20 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                        <span>
                            <strong>Layanan sedang berhenti.</strong>
                            @if ($currentSubscription->status === 'suspended')
                                Nomor sudah dilepas dari gateway. WhatsApp di ponsel Anda tidak terpengaruh.
                            @else
                                Pengiriman, pesan masuk, dan webhook berhenti — WhatsApp di ponsel Anda tidak terpengaruh.
                            @endif
                        </span>
                        {{-- Menuju halaman paket, bukan ringkasan: yang dibutuhkan
                             orang yang menekan tombol ini adalah memilih dan membayar,
                             bukan membaca lagi bahwa langganannya habis. --}}
                        <a href="{{ route('billing.plans') }}" class="shrink-0 rounded-lg bg-destructive px-3 py-1.5 font-medium text-white hover:opacity-90">
                            Perpanjang sekarang
                        </a>
                    </div>
                @elseif ($currentSubscription->isExpiringSoon())
                    @php $sisaHari = $currentSubscription->daysRemaining(); @endphp
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-300">
                        <span>
                            Langganan {{ $currentSubscription->plan()->name() }} berakhir
                            <strong>{{ $sisaHari === 0 ? 'hari ini' : ($sisaHari === 1 ? 'besok' : "{$sisaHari} hari lagi") }}</strong>
                            ({{ $currentSubscription->current_period_end->translatedFormat('j F Y') }}).
                        </span>
                        <a href="{{ route('billing.plans') }}" class="shrink-0 rounded-lg border border-amber-500/40 px-3 py-1.5 font-medium hover:bg-amber-500/10">
                            Perpanjang
                        </a>
                    </div>
                @endif
            @endif

            @if (session('status'))
                <div class="mb-5 rounded-lg border border-primary/20 bg-primary/10 px-4 py-3 text-sm font-medium text-primary">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 rounded-lg border border-destructive/20 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

@include('partials.pesan-server')

@stack('scripts')
</body>
</html>
