<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Flustra WA Gateway')</title>

    <!-- Meta Tags & Favicon -->
    <meta name="description" content="Masuk atau daftar ke Flustra WA Gateway - Layanan Notifikasi & Gateway WhatsApp API Terbaik.">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#2e7d32">
    
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/icon-32x32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Vite CSS & JS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('styles')

    <!-- Theme Initialization Script -->
    <script>
        function applyTheme() {
            if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
        applyTheme();

        function toggleDarkMode() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.theme = isDark ? 'dark' : 'light';
        }
    </script>
</head>
<body class="min-h-full bg-background text-foreground antialiased font-sans selection:bg-primary/20 selection:text-primary">
    <div class="min-h-screen grid lg:grid-cols-[1.35fr_1fr] relative overflow-x-hidden">
        
        {{-- ===================== SISI KIRI: HERO / BRAND PANEL ===================== --}}
        <div class="relative hidden lg:flex flex-col justify-between p-10 xl:p-14 overflow-hidden bg-[#123524] text-white select-none">

            {{-- Header Kiri: Brand Identity --}}
            <div class="relative z-10">
                <a href="{{ route('welcome') }}" class="group inline-flex items-center gap-3 transition">
                    <img src="{{ asset('images/flustra-wa.png') }}" alt="Flustra WA" class="h-9 w-auto object-contain transition group-hover:scale-105">
                    <div class="flex flex-col">
                        <span class="text-xl font-bold tracking-tight text-white">Flustra WA</span>
                        <span class="text-[10px] font-semibold uppercase tracking-widest text-emerald-400">Gateway API Platform</span>
                    </div>
                </a>
            </div>

            {{-- Tengah Kiri: Headline & Value Proposition --}}
            <div class="relative z-10 my-auto py-10 max-w-lg">
                <h1 class="text-3xl xl:text-4xl 2xl:text-[2.75rem] font-extrabold tracking-tight text-white leading-[1.18]">
                    Kelola Pesan &amp; Otomasi WhatsApp Tanpa Batas
                </h1>

                <p class="mt-4 text-sm xl:text-base leading-relaxed text-slate-300 font-normal">
                    Dashboard terpusat untuk integrasi API WhatsApp multi-sesi, broadcast pesan massal, webhook interaktif, dan notifikasi pengingat otomatis dengan performa andal dan terpercaya.
                </p>

                {{-- Stats Row (3 Metrik Berdampingan) --}}
                <div class="mt-10 grid grid-cols-3 gap-6 pt-8 border-t border-white/10">
                    <div>
                        <p class="text-2xl xl:text-3xl font-black text-white tracking-tight">99.9%</p>
                        <p class="mt-1 text-xs text-emerald-300 font-medium">Uptime Gateway</p>
                    </div>
                    <div>
                        <p class="text-2xl xl:text-3xl font-black text-white tracking-tight">Multi-Sesi</p>
                        <p class="mt-1 text-xs text-emerald-300 font-medium">Kuota Fleksibel</p>
                    </div>
                    <div>
                        <p class="text-2xl xl:text-3xl font-black text-white tracking-tight">100%</p>
                        <p class="mt-1 text-xs text-emerald-300 font-medium">Otomatisasi API</p>
                    </div>
                </div>
            </div>

            {{-- Footer Kiri: Copyright Resmi PT --}}
            <div class="relative z-10 text-xs text-slate-400 font-normal flex flex-wrap items-center justify-between gap-2">
                <span>&copy; {{ date('Y') }} PT FLUSTRA FINANCES ARTHA. Hak cipta dilindungi undang-undang.</span>
                <span class="text-slate-500 text-[11px]">Gateway Notifikasi Resmi</span>
            </div>
        </div>

        {{-- ===================== SISI KANAN: FORM PANEL ===================== --}}
        <div class="relative flex flex-col justify-between min-h-screen bg-background p-6 sm:p-8 lg:p-10 xl:p-12 overflow-y-auto">
            
            {{-- Topbar Kanan --}}
            <div class="flex items-center justify-between gap-4 w-full">
                {{-- Logo khusus layar mobile --}}
                <div class="lg:hidden flex items-center gap-2.5">
                    <a href="{{ route('welcome') }}" class="flex items-center gap-2">
                        <img src="{{ asset('images/flustra-wa.png') }}" alt="Flustra WA" class="h-8 w-auto object-contain">
                        <span class="text-base font-bold text-foreground">Flustra WA</span>
                    </a>
                </div>

                <div class="hidden lg:block"></div>

                <div class="flex items-center gap-2.5 ml-auto">
                    {{-- Tombol Ganti Tema (Dark/Light) --}}
                    <button type="button" onclick="toggleDarkMode()"
                            class="grid h-9 w-9 place-items-center rounded-xl border border-border bg-card text-muted-foreground transition hover:bg-muted hover:text-foreground active:scale-95"
                            title="Ganti mode tampilan"
                            aria-label="Ganti mode tampilan">
                        <svg class="hidden h-4 w-4 text-amber-400 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <svg class="block h-4 w-4 text-foreground dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                        </svg>
                    </button>

                    {{-- Link Kembali ke Website --}}
                    <a href="{{ route('welcome') }}" 
                       class="inline-flex items-center gap-1.5 rounded-xl border border-border bg-card px-3.5 py-2 text-xs font-semibold text-muted-foreground transition hover:bg-muted hover:text-foreground shadow-2xs">
                        <i class="bi bi-arrow-left"></i>
                        <span>Kembali ke Website</span>
                    </a>
                </div>
            </div>

            {{-- Form Wrapper (Tengah Vertikal) --}}
            <div class="my-auto py-6 sm:py-8 w-full @yield('container_width', 'max-w-[390px]') mx-auto">
                @yield('content')
            </div>

            {{-- Footer Khusus Mobile --}}
            <div class="lg:hidden text-center text-xs text-muted-foreground pt-4 border-t border-border/70">
                &copy; {{ date('Y') }} PT FLUSTRA FINANCES ARTHA. Hak cipta dilindungi undang-undang.
            </div>
        </div>

    </div>

    <!-- Script pembantu toggle password -->
    <script>
        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
            const icon = btn.querySelector('i');
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
