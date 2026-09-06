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
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/icon-16x16.png') }}">
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
    <div class="min-h-screen grid lg:grid-cols-[1.6fr_1fr] xl:grid-cols-[1.75fr_1fr] 2xl:grid-cols-[1.9fr_1fr] relative overflow-x-hidden">
        
        {{-- ===================== SISI KIRI: HERO / BRAND PANEL ===================== --}}
        <div class="relative hidden lg:flex flex-col justify-between p-10 xl:p-14 overflow-hidden bg-[#123524] text-white select-none">
            {{-- Visual 3D Hero Render (Tajam, Minimalis, Warna Asli #123524, Tanpa Blur) --}}
            <img src="{{ asset('images/auth-hero.jpg') }}" alt="Flustra WA Enterprise Gateway" class="absolute inset-0 h-full w-full object-cover object-center pointer-events-none select-none">
            
            {{-- Subtle Vignette agar kontras teks tetap optimal tanpa merusak warna asli #123524 --}}
            <div class="absolute inset-0 bg-gradient-to-t from-[#123524]/90 via-[#123524]/30 to-[#123524]/60 pointer-events-none"></div>

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

                <p class="mt-4 text-sm xl:text-base leading-relaxed text-slate-200 font-normal">
                    Dashboard terpusat untuk integrasi API WhatsApp multi-sesi, broadcast pesan massal, webhook interaktif, dan notifikasi pengingat otomatis dengan performa andal dan terpercaya.
                </p>

                {{-- Stats Row (3 Metrik Berdampingan) --}}
                <div class="mt-10 grid grid-cols-3 gap-6 pt-8 border-t border-white/15">
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
            <div class="relative z-10 text-xs text-slate-300/80 font-normal flex flex-wrap items-center justify-between gap-2">
                <span>&copy; {{ date('Y') }} PT FLUSTRA FINANCES ARTHA. Hak cipta dilindungi undang-undang.</span>
                <span class="text-slate-400 text-[11px]">Gateway Notifikasi Resmi</span>
            </div>
        </div>

        {{-- ===================== SISI KANAN: FORM PANEL ===================== --}}
        <div class="relative flex flex-col min-h-screen bg-background p-6 sm:p-8 lg:p-10 xl:p-12 overflow-y-auto">
            
            {{-- Topbar Khusus Layar Mobile (Logo Brand) --}}
            <div class="lg:hidden flex items-center justify-between gap-4 w-full mb-4">
                <a href="{{ route('welcome') }}" class="flex items-center gap-2">
                    <img src="{{ asset('images/flustra-wa.png') }}" alt="Flustra WA" class="h-8 w-auto object-contain">
                    <span class="text-base font-bold text-foreground">Flustra WA</span>
                </a>
            </div>

            {{-- Spacer atas fleksibel agar form terdorong jauh lebih ke bawah di layar --}}
            <div class="flex-1 min-h-[40px] sm:min-h-[80px] lg:min-h-[140px] xl:min-h-[180px]"></div>

            {{-- Form Wrapper (Dibuat Lebih ke Bawah) --}}
            <div class="w-full @yield('container_width', 'max-w-[390px]') mx-auto pb-6 sm:pb-8 lg:pb-10">
                @yield('content')

                {{-- Tombol Kembali ke Website (Paling Bawah Form, Full Width seukuran Google) --}}
                <div class="mt-6 pt-5 border-t border-border/60">
                    <a href="{{ route('welcome') }}" 
                       class="inline-flex w-full items-center justify-center gap-2.5 rounded-xl border border-border bg-card px-4 py-2.5 text-sm font-semibold text-foreground shadow-2xs transition hover:bg-muted hover:border-border/80 active:scale-[0.99]">
                        <i class="bi bi-arrow-left text-base"></i>
                        <span>Kembali ke Website</span>
                    </a>
                </div>
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
