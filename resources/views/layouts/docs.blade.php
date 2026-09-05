<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') &middot; Dokumentasi {{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', 'Dokumentasi Flustra WA Gateway: Panduan integrasi WhatsApp API, automasi broadcast, webhook, dan multi-sesi.')">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="bg-background text-foreground antialiased font-sans"
      x-data="{
          sidebar: false,
          searchModal: false,
          searchQuery: '',
          pages: @js(array_values(\App\Support\DocsRepository::flat())),
          get searchResults() {
              if (!this.searchQuery.trim()) return this.pages.slice(0, 6);
              const q = this.searchQuery.toLowerCase();
              return this.pages.filter(p => 
                  p.title.toLowerCase().includes(q) || 
                  p.summary.toLowerCase().includes(q) || 
                  p.group.toLowerCase().includes(q)
              );
          }
      }"
      @keydown.window.prevent.ctrl.k="searchModal = true"
      @keydown.window.prevent.cmd.k="searchModal = true">

    {{-- ===================== MODAL PENCARIAN (CTRL + K) ===================== --}}
    <div x-show="searchModal" x-cloak
         @keydown.escape.window="searchModal = false"
         class="fixed inset-0 z-50 flex items-start justify-center p-4 sm:pt-20">
        {{-- Backdrop --}}
        <div x-show="searchModal" 
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="searchModal = false" 
             class="fixed inset-0 bg-black/60 backdrop-blur-xs"></div>

        {{-- Panel Modal --}}
        <div x-show="searchModal"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="relative w-full max-w-xl rounded-2xl border border-border bg-popover shadow-2xl overflow-hidden z-10">
            
            {{-- Input Pencarian --}}
            <div class="flex items-center gap-3 border-b border-border px-4 py-3.5 bg-muted/30">
                <i class="bi bi-search text-muted-foreground text-base"></i>
                <input type="text" 
                       x-model="searchQuery" 
                       x-ref="searchInput"
                       x-init="$watch('searchModal', value => { if (value) setTimeout(() => $refs.searchInput.focus(), 50); else searchQuery = ''; })"
                       placeholder="Cari dokumentasi atau topik (mis: kirim pesan, webhook, api)..."
                       class="w-full bg-transparent text-sm placeholder:text-muted-foreground focus:outline-none text-foreground">
                <button @click="searchModal = false" class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground text-xs font-semibold px-2 border border-border">
                    ESC
                </button>
            </div>

            {{-- Hasil Pencarian --}}
            <div class="max-h-96 overflow-y-auto p-2 divide-y divide-border/40">
                <template x-for="item in searchResults" :key="item.slug">
                    <a :href="'/docs/' + item.slug" 
                       class="flex flex-col gap-1 p-3 rounded-xl transition hover:bg-muted group">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-sm text-foreground group-hover:text-primary transition" x-text="item.title"></span>
                            <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full bg-muted text-muted-foreground" x-text="item.group"></span>
                        </div>
                        <p class="text-xs text-muted-foreground line-clamp-1" x-text="item.summary"></p>
                    </a>
                </template>
                <div x-show="searchResults.length === 0" class="p-8 text-center text-sm text-muted-foreground">
                    <i class="bi bi-search text-2xl d-block mb-2 text-muted-foreground/50"></i>
                    <p>Tidak ada hasil untuk "<span x-text="searchQuery" class="font-semibold text-foreground"></span>"</p>
                </div>
            </div>

            {{-- Footer Hint --}}
            <div class="border-t border-border bg-muted/40 px-4 py-2 text-[11px] text-muted-foreground flex items-center justify-between">
                <span>Pencarian Cepat Dokumentasi</span>
                <span class="flex items-center gap-1.5">
                    <kbd class="px-1.5 py-0.5 rounded bg-muted border border-border text-[10px]">↵</kbd> untuk memilih
                </span>
            </div>
        </div>
    </div>

    {{-- ===================== BILAH ATAS (NAVBAR STICKY) ===================== --}}
    <header class="sticky top-0 z-40 border-b border-border/70 bg-background/85 backdrop-blur-md">
        <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 sm:px-6 py-3">

            {{-- Brand Identity --}}
            <a href="{{ route('welcome') }}" class="flex items-center gap-3 font-semibold group shrink-0">
                <img src="{{ asset('images/flustra-wa.png') }}" alt="Flustra WA" class="h-8 w-auto object-contain transition group-hover:scale-105">
                <div class="flex items-center gap-2">
                    <span class="text-base font-bold tracking-tight text-foreground">Flustra WA</span>
                    <span class="rounded-full bg-primary/15 border border-primary/30 px-2 py-0.5 text-[10px] font-bold text-primary tracking-wider uppercase">
                        Docs
                    </span>
                </div>
            </a>

            {{-- Search Bar Trigger (Tengah / Desktop) --}}
            <button type="button" 
                    @click="searchModal = true"
                    class="hidden sm:flex items-center gap-3 w-64 md:w-72 lg:w-80 rounded-xl border border-border bg-card/60 px-3.5 py-2 text-xs text-muted-foreground transition hover:border-primary/40 hover:bg-card shadow-2xs">
                <i class="bi bi-search text-xs"></i>
                <span class="flex-1 text-left">Cari dokumentasi...</span>
                <kbd class="rounded border border-border bg-muted px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground">Ctrl K</kbd>
            </button>

            {{-- Menu Kanan --}}
            <div class="ml-auto flex items-center gap-2">
                {{-- Quick Nav Link --}}
                <a href="{{ route('docs.show', 'mulai-cepat') }}" 
                   class="hidden md:inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-muted-foreground transition hover:bg-muted hover:text-foreground">
                    <i class="bi bi-rocket-takeoff text-xs"></i>
                    <span>Mulai Cepat</span>
                </a>
                <a href="{{ route('docs.show', 'referensi-api') }}" 
                   class="hidden md:inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-muted-foreground transition hover:bg-muted hover:text-foreground">
                    <i class="bi bi-code-slash text-xs"></i>
                    <span>Referensi API</span>
                </a>

                <div class="hidden md:block h-4 w-px bg-border/80 mx-1"></div>

                {{-- Tombol Ganti Tema --}}
                <button type="button"
                        @click="document.documentElement.classList.toggle('dark'); localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'"
                        class="grid h-9 w-9 place-items-center rounded-xl border border-border bg-card text-muted-foreground transition hover:bg-muted hover:text-foreground active:scale-95 shadow-2xs"
                        aria-label="Ganti mode tema">
                    <svg class="hidden h-4 w-4 text-amber-400 dark:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <svg class="block h-4 w-4 text-foreground dark:hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                </button>

                {{-- Action / Auth Button --}}
                @auth
                    <a href="{{ route('dashboard') }}" class="hidden sm:inline-flex items-center gap-1.5 rounded-xl bg-primary px-3.5 py-2 text-xs font-semibold text-primary-foreground shadow-2xs transition hover:opacity-90 active:scale-95">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="hidden sm:inline-flex items-center rounded-xl border border-border bg-card px-3.5 py-2 text-xs font-semibold text-foreground transition hover:bg-muted shadow-2xs">
                        Masuk
                    </a>
                    <a href="{{ route('register') }}" class="hidden sm:inline-flex items-center rounded-xl bg-primary px-3.5 py-2 text-xs font-semibold text-primary-foreground shadow-2xs transition hover:opacity-90 active:scale-95">
                        Mulai Gratis
                    </a>
                @endauth

                {{-- Search Trigger di Mobile --}}
                <button type="button" @click="searchModal = true" class="grid h-9 w-9 place-items-center rounded-xl border border-border bg-card text-muted-foreground sm:hidden" aria-label="Cari">
                    <i class="bi bi-search text-xs"></i>
                </button>

                {{-- Hamburger Menu di Mobile --}}
                <button type="button" @click="sidebar = !sidebar" class="grid h-9 w-9 place-items-center rounded-xl border border-border bg-card text-muted-foreground lg:hidden" aria-label="Buka Menu">
                    <i class="bi bi-list text-lg"></i>
                </button>
            </div>
        </div>
    </header>

    {{-- ===================== DOKUMEN & SIDEBAR CONTAINER ===================== --}}
    <div class="mx-auto max-w-7xl px-4 sm:px-6">
        <div class="flex gap-8 py-8">

            {{-- SIDEBAR KIRI (Navigasi Katalog) --}}
            <aside class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-border bg-background p-5 transition-transform duration-200 lg:sticky lg:top-16 lg:z-0 lg:flex lg:h-[calc(100vh-4rem)] lg:w-64 xl:w-72 lg:border-r lg:border-border/60 lg:bg-transparent lg:p-0 lg:pt-2 lg:pr-6"
                   :class="sidebar ? 'translate-x-0 shadow-2xl' : '-translate-x-full lg:translate-x-0'"
                   x-cloak>
                
                {{-- Mobile Close Header --}}
                <div class="flex items-center justify-between pb-4 mb-3 border-b border-border lg:hidden">
                    <div class="flex items-center gap-2 font-bold text-base">
                        <img src="{{ asset('images/flustra-wa.png') }}" alt="" class="h-6 w-auto">
                        <span>Dokumentasi</span>
                    </div>
                    <button @click="sidebar = false" class="rounded-lg p-1 text-muted-foreground hover:bg-muted" aria-label="Tutup">
                        <i class="bi bi-x-lg text-base"></i>
                    </button>
                </div>

                {{-- Daftar Menu Dokumen --}}
                <div class="flex-1 overflow-y-auto space-y-6 pr-2 scrollbar-thin">
                    <nav class="space-y-6 text-sm">
                        @php
                            $groupIcons = [
                                'Mulai' => 'bi-rocket-takeoff',
                                'Untuk Developer' => 'bi-code-slash',
                                'Panduan' => 'bi-book',
                            ];
                        @endphp
                        @foreach ($catalogue as $group => $pages)
                            <div>
                                <div class="flex items-center gap-2 mb-2 px-2 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                                    <i class="bi {{ $groupIcons[$group] ?? 'bi-folder2' }} text-primary"></i>
                                    <span>{{ $group }}</span>
                                </div>
                                <ul class="space-y-0.5 border-l border-border/60 ml-3 pl-2">
                                    @foreach ($pages as $slug => $item)
                                        @php $isCurrent = ($activeSlug ?? null) === $slug; @endphp
                                        <li>
                                            <a href="{{ route('docs.show', $slug) }}"
                                               class="group flex items-center justify-between rounded-lg px-2.5 py-1.5 text-xs transition {{ $isCurrent
                                                    ? 'bg-primary/10 font-semibold text-primary'
                                                    : 'text-muted-foreground hover:bg-muted/70 hover:text-foreground' }}">
                                                <span class="truncate">{{ $item['title'] }}</span>
                                                @if ($isCurrent)
                                                    <span class="h-1.5 w-1.5 rounded-full bg-primary shrink-0"></span>
                                                @endif
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </nav>

                    {{-- Card Gateway Status & Shortcut --}}
                    <div class="pt-4 mt-6 border-t border-border/70">
                        <div class="rounded-xl border border-border/80 bg-card p-3.5 text-xs shadow-2xs">
                            <div class="flex items-center gap-2 text-foreground font-semibold">
                                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span>API v1.0 Gateway</span>
                            </div>
                            <p class="mt-1 text-[11px] text-muted-foreground leading-normal">
                                Didukung multi-sesi WhatsApp dan sistem webhook otomatis.
                            </p>
                            <a href="{{ route('docs.index') }}" class="mt-2.5 inline-flex items-center gap-1 font-semibold text-primary hover:underline text-[11px]">
                                <span>Semua Direktori</span>
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Mobile Drawer Footer --}}
                <div class="pt-4 mt-auto border-t border-border lg:hidden space-y-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-semibold text-primary-foreground">
                            <span>Buka Dashboard</span>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="flex w-full items-center justify-center rounded-xl border border-border bg-card px-4 py-2.5 text-xs font-semibold text-foreground">
                            Masuk
                        </a>
                        <a href="{{ route('register') }}" class="flex w-full items-center justify-center rounded-xl bg-primary px-4 py-2.5 text-xs font-semibold text-primary-foreground">
                            Mulai Gratis
                        </a>
                    @endauth
                </div>
            </aside>

            {{-- Backdrop Mobile Saat Sidebar Terbuka --}}
            <div x-show="sidebar" x-cloak @click="sidebar = false" class="fixed inset-0 z-40 bg-black/50 lg:hidden"></div>

            {{-- KONTEN UTAMA --}}
            <main class="min-w-0 flex-1 max-w-full">
                @yield('content')
            </main>
        </div>
    </div>

<footer class="border-t border-border py-12 bg-background">
    <div class="mx-auto max-w-6xl px-5">
        <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-5">
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
                <h3 class="text-xs font-semibold tracking-wider text-primary uppercase">Gateway ini</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    <li><a href="{{ route('welcome') }}#fitur" class="text-muted-foreground hover:text-foreground">Fitur</a></li>
                    <li><a href="{{ route('welcome') }}#harga" class="text-muted-foreground hover:text-foreground">Harga</a></li>
                    <li><a href="{{ route('docs.index') }}" class="text-muted-foreground hover:text-foreground">Dokumentasi</a></li>
                    <li><a href="{{ route('login') }}" class="text-muted-foreground hover:text-foreground">Masuk</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-xs font-semibold tracking-wider text-primary uppercase">Produk Flustra</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    @foreach (config('flustra.produk') as $nama => $alamat)
                        <li><a href="{{ $alamat }}" class="text-muted-foreground hover:text-foreground">{{ $nama }}</a></li>
                    @endforeach
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
            <p class="text-sm text-muted-foreground">&copy; {{ date('Y') }} Flustra WA Gateway.</p>
            
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

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Otomatis tambahkan tombol salin ke setiap blok pre di dalam .doc-body
        document.querySelectorAll('.doc-body pre').forEach((pre) => {
            if (pre.querySelector('.doc-copy-btn')) return;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'doc-copy-btn absolute top-3 right-3 inline-flex items-center gap-1.5 rounded-lg border border-slate-700/80 bg-slate-800/90 px-2.5 py-1 text-[11px] font-medium text-slate-300 backdrop-blur-xs transition hover:bg-slate-700 hover:text-white cursor-pointer select-none';
            btn.innerHTML = '<i class="bi bi-clipboard"></i> <span>Salin</span>';
            btn.title = 'Salin kode';

            btn.addEventListener('click', async () => {
                const codeEl = pre.querySelector('code');
                const text = codeEl ? codeEl.innerText : pre.innerText;
                try {
                    await navigator.clipboard.writeText(text.trim());
                    btn.innerHTML = '<i class="bi bi-check2 text-emerald-400"></i> <span class="text-emerald-400">Tersalin!</span>';
                    setTimeout(() => {
                        btn.innerHTML = '<i class="bi bi-clipboard"></i> <span>Salin</span>';
                    }, 2000);
                } catch (err) {
                    console.error('Gagal menyalin:', err);
                }
            });

            pre.classList.add('relative', 'group');
            pre.appendChild(btn);
        });
    });
</script>
</body>
</html>
