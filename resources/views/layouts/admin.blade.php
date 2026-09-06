<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Panel internal: tidak ada gunanya muncul di hasil pencarian. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Admin') &middot; Admin {{ config('app.name') }}</title>
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
<body class="h-full bg-background text-foreground antialiased" x-data="{ sidebar: false }">

@php
    $adminMenu = [
        'Ikhtisar' => [
            ['rute' => 'admin.overview', 'label' => 'Ringkasan', 'ikon' => 'M3 12l9-9 9 9M5 10v10h14V10'],
        ],
        'Operasional' => [
            ['rute' => 'admin.workspaces', 'label' => 'Workspace', 'cocok' => ['admin.workspaces', 'admin.workspaces.*'], 'ikon' => 'M3 21h18M5 21V7l8-4v18M19 21V11l-6-3M9 9h1M9 13h1M9 17h1M15 13h1M15 17h1'],
            ['rute' => 'admin.invoices', 'label' => 'Tagihan', 'cocok' => ['admin.invoices', 'admin.invoices.*'], 'ikon' => 'M4 2v20l3-2 3 2 3-2 3 2 3-2 3 2V2l-3 2-3-2-3 2-3-2-3 2-3-2zM8 8h8M8 12h8M8 16h5'],
            ['rute' => 'admin.sessions', 'label' => 'Sesi WhatsApp', 'cocok' => ['admin.sessions', 'admin.sessions.*'], 'ikon' => 'M12 2a10 10 0 1 0 4.9 18.7L22 22l-1.3-5.1A10 10 0 0 0 12 2z'],
            ['rute' => 'admin.messages', 'label' => 'Lalu Lintas Pesan', 'ikon' => 'M4 4h16v12H5.17L4 17.17V4zM8 9h8M8 12h5'],
        ],
        'Akun & Jejak' => [
            ['rute' => 'admin.users', 'label' => 'Pengguna', 'cocok' => ['admin.users', 'admin.users.*'], 'ikon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm10 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
            ['rute' => 'admin.audit', 'label' => 'Catatan Audit', 'ikon' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M9 13h6M9 17h4'],
            ['rute' => 'admin.exemptions', 'label' => 'Pengecualian', 'cocok' => ['admin.exemptions', 'admin.exemptions.*'], 'ikon' => 'M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.3 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8L12 2z'],
            ['rute' => 'admin.system', 'label' => 'Sistem', 'ikon' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM3 12h2m14 0h2M12 3v2m0 14v2M5.6 5.6l1.4 1.4m10 10 1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4'],
        ],
    ];

    /*
     | Angka pada menu.
     |
     | Hanya satu yang ditampilkan: tagihan yang menunggu tindakan manusia.
     | Lencana pada setiap menu berubah jadi hiasan yang diabaikan mata; satu
     | lencana yang muncul hanya saat memang ada pekerjaan justru terbaca.
    */
    $perluDiperiksa = \App\Models\Invoice::whereNotNull('proof_path')->where('status', '!=', 'paid')->count()
        + \App\Models\Invoice::where('status', 'pending')->whereNull('proof_path')->count();

    $lencanaMenu = ['admin.invoices' => $perluDiperiksa];

    $aktif = function (array $item): bool {
        return request()->routeIs(...($item['cocok'] ?? [$item['rute']]));
    };
@endphp

    {{-- Penutup layar saat menu terbuka di layar kecil --}}
    <div x-show="sidebar" x-cloak @click="sidebar = false"
         x-transition:enter="transition-opacity ease-linear duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-200"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-40 bg-black/50 lg:hidden"></div>

    {{-- ===================== Sidebar Admin (Warna Hijau Hutan Gelap / Forest Green) =====================
         Tetap berada di rumpun hijau Flustra, tetapi memakai hijau hutan gelap pekat (`#0c1f15`) yang
         langsung membedakannya dari sidebar pengguna (yang berwarna sage/mint terang di light mode).
         ============================================================================================= --}}
    <aside class="panel-sidebar fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-[#173826] bg-[#0c1f15] text-emerald-100 transition-transform duration-200 ease-out select-none"
           :class="sidebar && 'terbuka'">

        {{-- Header Sidebar: Logo & Badge Admin --}}
        <div class="flex h-16 shrink-0 items-center gap-2.5 px-5">
            <a href="{{ route('admin.overview') }}" class="flex min-w-0 items-center gap-2.5 font-semibold">
                <img src="{{ asset('images/flustra-wa.png') }}" alt="" class="h-7 w-auto shrink-0 object-contain">
                <span class="truncate text-white">Flustra WA</span>
                <span class="rounded-full bg-emerald-500/20 border border-emerald-500/30 px-2 py-0.5 text-[10px] font-bold text-emerald-300 uppercase tracking-wider">
                    Admin
                </span>
            </a>
            <button @click="sidebar = false" class="ml-auto rounded-md p-1 text-emerald-200/60 hover:bg-[#143323] hover:text-white lg:hidden" aria-label="Tutup menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Navigasi Menu Admin --}}
        <nav class="flex-1 space-y-6 overflow-y-auto p-3">
            @foreach ($adminMenu as $kelompok => $tautan)
                <div>
                    <p class="px-3 pb-1.5 text-[0.7rem] font-semibold uppercase tracking-wider text-emerald-400/60">{{ $kelompok }}</p>
                    <div class="space-y-0.5">
                        @foreach ($tautan as $item)
                            @php $isAktif = $aktif($item); @endphp
                            <a href="{{ route($item['rute']) }}"
                               class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition {{ $isAktif 
                                    ? 'bg-[#1a442e] font-medium text-white shadow-xs border border-emerald-500/25' 
                                    : 'text-emerald-100/70 hover:bg-[#143323] hover:text-white' }}">
                                <svg class="h-[1.05rem] w-[1.05rem] shrink-0 text-current" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="{{ $item['ikon'] }}"/>
                                </svg>
                                <span class="truncate">{{ $item['label'] }}</span>
                                @if (($lencanaMenu[$item['rute']] ?? 0) > 0)
                                    <span class="ml-auto shrink-0 rounded-full bg-emerald-500 px-1.5 py-0.5 text-[10px] font-bold tabular-nums text-emerald-950">
                                        {{ $lencanaMenu[$item['rute']] > 99 ? '99+' : $lencanaMenu[$item['rute']] }}
                                    </span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div>
                <p class="px-3 pb-1.5 text-[0.7rem] font-semibold uppercase tracking-wider text-emerald-400/60">Pintasan</p>
                <div class="space-y-0.5">
                    <a href="{{ route('dashboard') }}"
                       class="group flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-emerald-100/70 transition hover:bg-[#143323] hover:text-white">
                        <svg class="h-[1.05rem] w-[1.05rem] shrink-0 text-current" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                        <span class="truncate">Ke Dashboard User</span>
                    </a>
                    <a href="{{ route('docs.index') }}" target="_blank" rel="noopener"
                       class="group flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-emerald-100/70 transition hover:bg-[#143323] hover:text-white">
                        <svg class="h-[1.05rem] w-[1.05rem] shrink-0 text-current" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5A2.5 2.5 0 0 0 6.5 22H20V2H6.5A2.5 2.5 0 0 0 4 4.5v15z"/>
                        </svg>
                        <span class="truncate">Dokumentasi</span>
                        <svg class="ml-auto h-3.5 w-3.5 shrink-0 text-emerald-400/50 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M7 17L17 7M17 7H7M17 7V17"/>
                        </svg>
                    </a>
                </div>
            </div>
        </nav>

        {{-- Footer Sidebar: Profil Super Admin & Logout --}}
        <div class="shrink-0 border-t border-[#173826] p-3 space-y-1">
            <div class="flex items-center gap-2.5 px-3 py-1.5">
                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-emerald-500/20 text-xs font-semibold text-emerald-300">
                    {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                </span>
                <span class="min-w-0 flex-1 truncate text-xs font-medium text-emerald-100/90">{{ auth()->user()->name }}</span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-emerald-100/70 transition hover:bg-[#143323] hover:text-white">
                    <svg class="h-[1.05rem] w-[1.05rem] shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    Keluar
                </button>
            </form>
        </div>
    </aside>

    {{-- ===================== Isi Halaman Admin ===================== --}}
    <div class="lg:pl-72">
        <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-border bg-background/85 px-4 backdrop-blur-md sm:px-6">
            <button @click="sidebar = true" class="-ml-1 rounded-md p-2 text-muted-foreground hover:bg-muted hover:text-foreground lg:hidden" aria-label="Buka menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <h1 class="min-w-0 truncate text-lg font-semibold">@yield('title', 'Admin')</h1>

            <div class="ml-auto flex items-center gap-2">
                <a href="{{ route('dashboard') }}" class="hidden sm:inline-flex items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    <span>Ke dashboard user</span>
                </a>

                <button @click="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'"
                        class="rounded-lg p-2 text-muted-foreground hover:bg-muted hover:text-foreground"
                        aria-label="Ganti tema tampilan">
                    <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <svg class="block h-5 w-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                </button>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <h1 class="mb-4 text-xl font-semibold lg:hidden">@yield('title', 'Admin')</h1>

            @if (session('status'))
                <div class="mb-4 rounded-lg border border-primary/20 bg-primary/10 px-4 py-3 text-sm font-medium text-primary">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-destructive/20 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive">
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

    @include('partials.pesan-server')

@stack('scripts')
</body>
</html>
