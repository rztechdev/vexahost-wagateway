<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Flustra')</title>

    <!-- Primary Meta Tags & Favicon Configuration -->
    <meta name="description" content="Masuk atau daftar ke Flustra WA Gateway - Layanan Notifikasi WhatsApp Terbaik.">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#FDFBF7">
    
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/icon-16x16.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/icon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('images/favicon-48x48.png') }}">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('images/icon-96x96.png') }}">
    <link rel="icon" type="image/png" sizes="144x144" href="{{ asset('images/icon-144x144.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/android-chrome-192x192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/android-chrome-512x512.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Vite CSS & JS -->
    @vite(['resources/css/auth.css', 'resources/js/app.js'])
    @yield('styles')
</head>
<body>
    <div class="auth-page">
        <!-- WebGL Canvas untuk Latar Belakang Smoky. data-color diatur dinamis atau manual -->
        <div class="auth-canvas-wrap" aria-hidden="true">
            <canvas id="auth-smokey-canvas" data-color="{{ $shader_color ?? '#8B5E3C' }}"></canvas>
            <div class="auth-canvas-blur"></div>
        </div>

        <header class="auth-topbar">
            <a href="/" class="auth-brand d-inline-flex align-items-center gap-2">
                <img src="{{ asset('images/flustra-wa.png') }}" alt="Logo" style="height: 32px; width: auto; object-fit: contain;">
                <span>Flustra WA Gateway</span>
            </a>
            @yield('topbar_action')
        </header>

        <main class="auth-main">
            @yield('content')
        </main>
    </div>

    <!-- Panggil Javascript WebGL Shader -->
    <script src="{{ asset('js/auth-smokey-bg.js') }}"></script>
    <script>
        // Mempertahankan label floating jika field terisi
        document.querySelectorAll('.auth-field input').forEach(function (input) {
            var wrap = input.closest('.auth-field');
            if (!wrap) return;
            function sync() {
                wrap.classList.toggle('has-value', input.value.length > 0);
            }
            input.addEventListener('input', sync);
            sync();
        });

        // Toggle password visibility
        function togglePasswordVisibility(inputId, btn) {
            var input = document.getElementById(inputId);
            if (!input) return;
            var icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) {
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                }
            } else {
                input.type = 'password';
                if (icon) {
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            }
        }
    </script>
    @yield('scripts')
</body>
</html>
