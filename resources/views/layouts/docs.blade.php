<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') &middot; Dokumentasi {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', 'Dokumentasi Flustra WA Gateway')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-stone-800 antialiased dark:bg-stone-950 dark:text-stone-200"
      x-data="{ sidebar: false }">

<header class="sticky top-0 z-40 border-b border-stone-200 bg-white/90 backdrop-blur dark:border-stone-800 dark:bg-stone-950/90">
    <div class="mx-auto flex max-w-7xl items-center gap-3 px-5 py-3.5">
        <button @click="sidebar = !sidebar"
                class="rounded-lg p-2 text-stone-500 hover:bg-stone-100 lg:hidden dark:hover:bg-stone-800"
                aria-label="Daftar dokumen">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        <a href="{{ route('welcome') }}" class="flex items-center gap-2.5 font-semibold">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-emerald-600 text-sm font-bold text-white">WA</span>
            <span class="hidden sm:inline">Flustra WA Gateway</span>
        </a>

        <a href="{{ route('docs.index') }}"
           class="rounded-md bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-600 dark:bg-stone-800 dark:text-stone-400">
            Docs
        </a>

        <nav class="ml-auto flex items-center gap-1 text-sm">
            <a href="{{ route('docs.show', 'mulai-cepat') }}"
               class="hidden rounded-lg px-3 py-2 text-stone-600 hover:bg-stone-100 sm:block dark:text-stone-400 dark:hover:bg-stone-800">
                Mulai Cepat
            </a>
            <a href="{{ route('docs.show', 'referensi-api') }}"
               class="hidden rounded-lg px-3 py-2 text-stone-600 hover:bg-stone-100 sm:block dark:text-stone-400 dark:hover:bg-stone-800">
                API
            </a>
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-stone-600 hover:bg-stone-100 dark:text-stone-400 dark:hover:bg-stone-800">Masuk</a>
            @endauth
        </nav>
    </div>
</header>

<div class="mx-auto flex max-w-7xl gap-8 px-5 py-8">

    {{-- Daftar dokumen --}}
    <aside class="fixed inset-y-0 left-0 z-30 w-72 shrink-0 overflow-y-auto border-r border-stone-200 bg-white p-5 pt-20 lg:sticky lg:top-16 lg:z-0 lg:block lg:h-[calc(100vh-5rem)] lg:border-0 lg:bg-transparent lg:p-0 dark:border-stone-800 dark:bg-stone-950 lg:dark:bg-transparent"
           :class="sidebar ? 'block' : 'hidden'">
        <nav class="space-y-6 text-sm">
            @foreach ($catalogue as $group => $pages)
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-400">{{ $group }}</p>
                    <ul class="space-y-0.5">
                        @foreach ($pages as $slug => $page)
                            <li>
                                <a href="{{ route('docs.show', $slug) }}"
                                   class="block rounded-lg px-3 py-1.5 {{ ($activeSlug ?? null) === $slug
                                        ? 'bg-emerald-50 font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                        : 'text-stone-600 hover:bg-stone-100 dark:text-stone-400 dark:hover:bg-stone-800' }}">
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

<footer class="border-t border-stone-200 py-8 dark:border-stone-800">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-6 gap-y-2 px-5 text-sm text-stone-500">
        <span>&copy; {{ date('Y') }} Flustra</span>
        <a href="{{ route('welcome') }}" class="hover:text-stone-800 dark:hover:text-stone-300">Beranda</a>
        <a href="{{ route('docs.index') }}" class="hover:text-stone-800 dark:hover:text-stone-300">Dokumentasi</a>
        <span class="ml-auto text-xs">Bukan produk resmi WhatsApp atau Meta.</span>
    </div>
</footer>

</body>
</html>
