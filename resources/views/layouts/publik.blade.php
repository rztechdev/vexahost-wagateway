<!DOCTYPE html>
<html lang="id" class="scroll-smooth overflow-x-clip">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('description', 'Gateway WhatsApp terpusat untuk aplikasi Anda — satu REST API, multi-nomor, webhook pesan masuk.')">

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/icon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/icon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Tema dibaca SEBELUM halaman digambar. Dipindahkan ke @push berarti ia
         berjalan setelah CSS terpasang, dan halaman berkedip terang sebelum
         berubah gelap. --}}
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="relative w-full overflow-x-clip bg-background text-foreground antialiased selection:bg-primary selection:text-primary-foreground">

{{--
    Layout untuk halaman publik selain halaman depan.

    Sengaja TIDAK memakai navigasi lengkap milik welcome.blade.php: yang membuka
    halaman ini datang dari satu tautan dengan satu pertanyaan, dan sepuluh menu
    di atasnya cuma menawarkan jalan keluar dari hal yang sedang mereka
    pertimbangkan. Yang tersisa cukup untuk kembali dan untuk masuk.
--}}
<header class="sticky top-0 z-40 border-b border-border/60 bg-background/80 backdrop-blur">
    <div class="mx-auto flex h-14 max-w-7xl items-center gap-4 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('welcome') }}" class="flex items-center gap-2 font-semibold">
            <img src="{{ asset('images/icon-32x32.png') }}" alt="" class="h-6 w-6 rounded">
            <span class="text-sm">{{ config('app.name') }}</span>
        </a>

        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('welcome') }}#harga"
               class="hidden rounded-lg px-3 py-1.5 text-sm text-muted-foreground transition hover:bg-muted hover:text-foreground sm:block">
                Harga
            </a>
            <a href="{{ route('docs.index') }}"
               class="hidden rounded-lg px-3 py-1.5 text-sm text-muted-foreground transition hover:bg-muted hover:text-foreground sm:block">
                Dokumentasi
            </a>

            <button type="button"
                    @click="const d = document.documentElement.classList.toggle('dark'); localStorage.theme = d ? 'dark' : 'light';"
                    x-data
                    class="rounded-lg p-2 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    aria-label="Ganti tema tampilan">
                <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <svg class="block h-5 w-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            </button>

            @auth
                <a href="{{ route('dashboard') }}"
                   class="rounded-lg bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                    Dashboard
                </a>
            @else
                <a href="{{ route('login') }}"
                   class="rounded-lg px-3 py-1.5 text-sm text-muted-foreground transition hover:bg-muted hover:text-foreground">
                    Masuk
                </a>
                <a href="{{ route('register') }}"
                   class="rounded-lg bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                    Buat akun
                </a>
            @endauth
        </div>
    </div>
</header>

<main>
    @yield('content')
</main>

<footer class="border-t border-border/60 py-8">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 text-xs text-muted-foreground sm:px-6 lg:px-8">
        <p>&copy; {{ date('Y') }} {{ config('app.name') }}</p>
        <div class="flex flex-wrap gap-4">
            <a href="{{ route('welcome') }}#harga" class="transition hover:text-foreground">Paket &amp; Harga</a>
            <a href="{{ route('docs.index') }}" class="transition hover:text-foreground">Dokumentasi</a>
            <a href="{{ route('docs.show', 'bantuan') }}" class="transition hover:text-foreground">Pusat Bantuan</a>
        </div>
    </div>
</footer>

@include('partials.pesan-server')
</body>
</html>
