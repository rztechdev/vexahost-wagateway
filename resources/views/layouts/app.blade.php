<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>
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
<div x-data="{ sidebar: false }" class="min-h-full">

    {{-- Kartu mengambang hanya di layar kecil. Di layar lebar header menyatu
         dengan halaman: tanpa kotak, tanpa garis — cuma latar yang mengabur
         supaya konten yang lewat di bawahnya tidak menabrak tulisan. --}}
    <header class="sticky top-3 z-30 px-3 lg:top-0 lg:bg-background/95 lg:px-0 lg:backdrop-blur-md">
        <div class="mx-auto flex max-w-7xl items-center gap-3 rounded-2xl border border-border bg-background/90 px-4 py-3 shadow-sm backdrop-blur-md lg:rounded-none lg:border-0 lg:bg-transparent lg:py-4 lg:shadow-none lg:backdrop-blur-none">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 font-semibold">
                <img src="{{ asset('images/flustra-wa.png') }}" alt="Logo" class="h-8 w-auto object-contain">
                <span class="hidden sm:inline">Flustra WA Gateway</span>
                <span class="sm:hidden text-base">@yield('title', 'Dashboard')</span>
            </a>

            <div class="ml-auto hidden lg:flex items-center gap-3">
                <a href="{{ route('docs.index') }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground">
                    Docs
                </a>

                @isset($availableWorkspaces)
                    <form method="POST" action="#" x-data class="hidden sm:block">
                        <select
                            class="rounded-lg border-border bg-background py-1.5 text-sm focus:border-primary focus:ring-primary"
                            @change="$el.form.action = '{{ url('workspaces') }}/' + $el.value + '/switch'; $el.form.submit()">
                            @foreach ($availableWorkspaces as $t)
                                <option value="{{ $t->id }}" @selected($t->id === $currentWorkspace->id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                        @csrf
                    </form>

                    {{-- Satu-satunya jalan menuju workspace kedua. Tanpa tautan ini
                         /onboarding hanya terjangkau lewat middleware, yaitu saat
                         pengguna belum punya workspace sama sekali — sehingga "satu
                         workspace per cabang" yang dijanjikan halaman onboarding
                         mustahil dilakukan kecuali dengan membuat akun baru. --}}
                    <a href="{{ route('onboarding.create') }}"
                       class="rounded-lg px-2.5 py-1.5 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                       title="Buat workspace baru">+ Workspace</a>
                @endisset

                <button @click="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'" 
                        class="rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground" 
                        aria-label="Toggle Dark Mode">
                    <!-- Ikon Matahari (Tampil saat Dark Mode) -->
                    <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <!-- Ikon Bulan (Tampil saat Light Mode) -->
                    <svg class="block h-5 w-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                </button>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-sm text-muted-foreground hover:text-foreground">Keluar</button>
                </form>
            </div>

            <!-- Hamburger (Hanya Mobile, di sebelah kanan) -->
            <button @click="sidebar = !sidebar" class="ml-auto rounded-md p-2 text-muted-foreground hover:bg-muted lg:hidden" aria-label="Menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </header>

    {{-- Penutup layar saat sidebar terbuka di layar kecil --}}
    <div x-show="sidebar" x-cloak @click="sidebar = false"
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-30 bg-black/40 backdrop-blur-sm lg:hidden"></div>

    <div class="mx-auto flex max-w-7xl gap-6 px-4 py-6">
        <aside
            class="fixed top-3 bottom-3 left-3 z-40 w-64 shrink-0 overflow-y-auto rounded-2xl border border-sidebar-border bg-sidebar p-5 text-sidebar-foreground shadow-2xl transition-transform duration-300 ease-in-out lg:static lg:bottom-auto lg:left-auto lg:top-auto lg:z-0 lg:block lg:w-60 lg:translate-x-0 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none"
            :class="sidebar ? 'translate-x-0' : '-translate-x-[120%]'">
            
            <!-- Logo inside mobile sidebar -->
            <div class="mb-6 flex items-center gap-2.5 font-semibold lg:hidden">
                <img src="{{ asset('images/flustra-wa.png') }}" alt="Logo" class="h-8 w-auto object-contain">
                <span>Flustra WA Gateway</span>
            </div>

            {{-- Pemilih workspace versi mobile. Di layar kecil header hanya
                 memuat logo dan hamburger, jadi tanpa blok ini pengguna ponsel
                 tidak punya cara apa pun berpindah workspace. --}}
            @isset($availableWorkspaces)
                <div class="mb-6 border-b border-sidebar-border pb-4 lg:hidden">
                    <p class="px-3 pb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">Workspace</p>
                    @foreach ($availableWorkspaces as $t)
                        <form method="POST" action="{{ route('workspaces.switch', $t->id) }}">
                            @csrf
                            <button class="block w-full rounded-lg px-3 py-2 text-left text-sm {{ $t->id === $currentWorkspace->id ? 'bg-sidebar-accent font-medium text-sidebar-accent-foreground' : 'text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground' }}">
                                {{ $t->name }}
                            </button>
                        </form>
                    @endforeach
                    <a href="{{ route('onboarding.create') }}"
                       class="block rounded-lg px-3 py-2 text-sm text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground">
                        + Buat workspace baru
                    </a>
                </div>
            @endisset

            <nav class="space-y-1 text-sm">
                @php
                    $nav = [
                        ['dashboard', 'Ringkasan'],
                        ['sessions.index', 'Sesi WhatsApp'],
                        ['messages.compose', 'Kirim Pesan'],
                        ['messages.index', 'Riwayat Pesan'],
                        ['templates.index', 'Template'],
                        ['api-keys.index', 'API Keys'],
                        ['webhooks.index', 'Webhooks'],
                        ['settings', 'Pengaturan'],
                    ];
                @endphp
                @foreach ($nav as [$route, $label])
                    <a href="{{ route($route) }}"
                       class="block rounded-lg px-3 py-2 {{ request()->routeIs($route) ? 'bg-sidebar-primary font-medium text-sidebar-primary-foreground' : 'text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            <!-- Ekstra menu di Mobile (Docs, Dark Mode, Logout) -->
            <div class="mt-6 border-t border-sidebar-border pt-4 lg:hidden">
                <a href="{{ route('docs.index') }}" class="block rounded-lg px-3 py-2 text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground">
                    Dokumentasi
                </a>
                <button @click="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground">
                    <span>Tema Tampilan</span>
                    <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <svg class="block h-5 w-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                </button>
                <form method="POST" action="{{ route('logout') }}" class="mt-1">
                    @csrf
                    <button class="flex w-full rounded-lg px-3 py-2 text-left text-muted-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground">Keluar</button>
                </form>
            </div>
        </aside>

        <main class="min-w-0 flex-1">
            <h1 class="mb-4 text-xl font-semibold">@yield('title', 'Dashboard')</h1>

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
</div>

@stack('scripts')
</body>
</html>
