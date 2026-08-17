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
    <meta name="theme-color" content="#f8f5f0">
    
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

    <!-- Theme Initialization Script -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body>
    <div class="auth-page">
        <!-- WebGL Canvas untuk Latar Belakang Smoky. data-color diatur dinamis atau manual -->
        <div class="auth-canvas-wrap" aria-hidden="true">
            <canvas id="auth-smokey-canvas" data-color="{{ $shader_color ?? '#2e7d32' }}"></canvas>
            <div class="auth-canvas-blur"></div>
        </div>

        <header class="auth-topbar">
            <a href="/" class="auth-brand d-inline-flex align-items-center gap-2">
                <img src="{{ asset('images/flustra-wa.png') }}" alt="Logo" style="height: 32px; width: auto; object-fit: contain;">
                <span>Flustra WA Gateway</span>
            </a>
            
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button onclick="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'" 
                        title="Toggle Tema"
                        style="background: transparent; border: none; cursor: pointer; color: var(--color-cream-800); display: flex; align-items: center; justify-content: center; padding: 0.2rem;">
                    <!-- Ikon Matahari (Tampil saat Dark Mode) -->
                    <svg class="h-5 w-5" style="display: none;" id="icon-sun" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <!-- Ikon Bulan (Tampil saat Light Mode) -->
                    <svg class="h-5 w-5" style="display: block;" id="icon-moon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                </button>
                
                @yield('topbar_action')
            </div>
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

        // Logic for icon visibility based on dark mode class on HTML element
        function updateThemeIcons() {
            var isDark = document.documentElement.classList.contains('dark');
            var iconSun = document.getElementById('icon-sun');
            var iconMoon = document.getElementById('icon-moon');
            if (iconSun && iconMoon) {
                iconSun.style.display = isDark ? 'block' : 'none';
                iconMoon.style.display = isDark ? 'none' : 'block';
            }
        }
        
        // Initial setup
        updateThemeIcons();

        // Observe changes to 'class' attribute on HTML to switch icons instantly
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    updateThemeIcons();
                }
            });
        });
        observer.observe(document.documentElement, { attributes: true });
    </script>
    @yield('scripts')
</body>
</html>
