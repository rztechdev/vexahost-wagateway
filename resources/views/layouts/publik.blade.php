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
        if ('IntersectionObserver' in window && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            document.documentElement.classList.add('animasi-muncul');
        }
    </script>
</head>
<body class="bg-background text-foreground antialiased selection:bg-primary selection:text-primary-foreground overflow-x-clip w-full relative" x-data="{ mobileMenu: false }">

@include('partials.header')

<main>
    @yield('content')
</main>

@include('partials.footer')

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

@include('partials.pesan-server')
</body>
</html>
