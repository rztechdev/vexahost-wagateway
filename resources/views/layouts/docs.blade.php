<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') &middot; Dokumentasi {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', 'Dokumentasi Flustra WA Gateway')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background text-foreground antialiased" x-data="{ sidebar: false }">

<header class="sticky top-0 z-40 border-b border-border bg-background/90 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center gap-3 px-5 py-3.5">
        <button @click="sidebar = !sidebar"
                class="rounded-lg p-2 text-muted-foreground hover:bg-muted lg:hidden"
                aria-label="Daftar dokumen">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        <a href="{{ route('welcome') }}" class="flex items-center gap-2.5 font-semibold">
            <img src="{{ asset('images/flustra-wa.png') }}" alt="Logo" class="h-8 w-auto object-contain">
            <span class="hidden sm:inline">Flustra WA Gateway</span>
        </a>

        <a href="{{ route('docs.index') }}"
           class="rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
            Docs
        </a>

        <nav class="ml-auto flex items-center gap-1 text-sm">
            <a href="{{ route('docs.show', 'mulai-cepat') }}"
               class="hidden rounded-lg px-3 py-2 text-muted-foreground hover:bg-muted hover:text-foreground sm:block">
                Mulai Cepat
            </a>
            <a href="{{ route('docs.show', 'referensi-api') }}"
               class="hidden rounded-lg px-3 py-2 text-muted-foreground hover:bg-muted hover:text-foreground sm:block">
                API
            </a>
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-lg bg-primary px-4 py-2 font-medium text-primary-foreground hover:bg-primary/90">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-muted-foreground hover:bg-muted hover:text-foreground">Masuk</a>
                <a href="{{ route('register') }}" class="rounded-lg bg-primary px-4 py-2 font-medium text-primary-foreground hover:bg-primary/90">Mulai</a>
            @endauth
        </nav>
    </div>
</header>

<div class="mx-auto flex max-w-7xl gap-8 px-5 py-8">

    {{-- Daftar dokumen --}}
    <aside class="fixed inset-y-0 left-0 z-30 w-72 shrink-0 overflow-y-auto border-r border-border bg-background p-5 pt-20 lg:sticky lg:top-16 lg:z-0 lg:block lg:h-[calc(100vh-5rem)] lg:border-0 lg:bg-transparent lg:p-0"
           :class="sidebar ? 'block' : 'hidden'">
        <nav class="space-y-6 text-sm">
            @foreach ($catalogue as $group => $pages)
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ $group }}</p>
                    <ul class="space-y-0.5">
                        @foreach ($pages as $slug => $page)
                            <li>
                                <a href="{{ route('docs.show', $slug) }}"
                                   class="block rounded-lg px-3 py-1.5 {{ ($activeSlug ?? null) === $slug
                                        ? 'bg-primary/10 font-medium text-primary'
                                        : 'text-muted-foreground hover:bg-muted hover:text-foreground' }}">
                                    {{ $page['title'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>
    </aside>

    {{-- Penutup layar saat daftar dokumen terbuka di layar kecil --}}
    <div x-show="sidebar" x-cloak @click="sidebar = false"
         class="fixed inset-0 z-20 bg-black/40 lg:hidden"></div>

    <main class="min-w-0 flex-1">
        @yield('content')
    </main>
</div>

<footer class="border-t border-border py-12 bg-background">
    <div class="mx-auto max-w-6xl px-5">
        <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-4">
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
                <h3 class="text-xs font-semibold tracking-wider text-primary uppercase">Produk</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    <li><a href="{{ route('welcome') }}#fitur" class="text-muted-foreground hover:text-foreground">Fitur</a></li>
                    <li><a href="{{ route('docs.index') }}" class="text-muted-foreground hover:text-foreground">Dokumentasi</a></li>
                    <li><a href="https://flustra.id" class="text-muted-foreground hover:text-foreground">flustra.id</a></li>
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
            <p class="text-sm text-muted-foreground">&copy; {{ date('Y') }} Flustra WA Gateway. Bukan bagian dari WhatsApp/Meta.</p>
            
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

</body>
</html>
