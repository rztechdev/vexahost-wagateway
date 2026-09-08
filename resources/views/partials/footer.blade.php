@php
    $beranda = request()->routeIs('welcome') ? '' : route('welcome');
@endphp

{{-- ===================== Footer ===================== --}}
<footer class="border-t border-border bg-background pt-12 sm:pt-16 pb-0">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {{-- Banner Atas: Enterprise Ready & Akselerasi Integrasi --}}
        <div class="pb-10 border-b border-border/70">
            <div class="grid gap-8 lg:grid-cols-12 items-center">
                <div class="lg:col-span-7">
                    <h3 class="text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                        Siap mengotomatiskan pesan WhatsApp skala besar?
                    </h3>
                    <p class="mt-1.5 text-xs sm:text-sm text-muted-foreground max-w-xl leading-relaxed">
                        Bergabunglah dengan ratusan pengembang dan bisnis yang mengandalkan stabilitas gateway Flustra untuk pesan transaksional berkecepatan tinggi tanpa khawatir sesi terputus.
                    </p>
                </div>
                <div class="lg:col-span-5 flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                    <div class="relative flex-1 min-w-0">
                        <input type="email"
                               placeholder="Masukkan email kantor Anda..."
                               class="w-full rounded-xl border border-input bg-card px-3.5 py-2.5 text-xs sm:text-sm text-foreground shadow-xs placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                               readonly
                               onfocus="this.removeAttribute('readonly');">
                    </div>
                    <a href="{{ route('register') }}"
                       class="inline-flex shrink-0 items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-xs sm:text-sm font-semibold text-primary-foreground shadow-xs hover:bg-primary/90 transition-all text-center">
                        Mulai Sekarang
                    </a>
                </div>
            </div>
        </div>

        {{-- Grid Navigasi Utama: Brand + 4 Kolom Menu --}}
        <div class="pt-10 sm:pt-12 pb-8 sm:pb-10 grid gap-10 lg:grid-cols-12">
            {{-- Sisi Kiri: Profil Brand & Security Pillars --}}
            <div class="lg:col-span-4 space-y-5">
                <a href="{{ route('welcome') }}" class="flex items-center gap-2.5 text-xl font-bold tracking-tight text-foreground">
                    <img src="{{ asset('images/flustra-wa.png') }}" alt="Logo" class="h-8 w-auto object-contain">
                    <div class="flex flex-col">
                        <span class="leading-none">Flustra WA Gateway</span>
                        <span class="text-[10px] font-mono tracking-wider uppercase text-primary font-semibold mt-1">Enterprise API Platform</span>
                    </div>
                </a>

                <p class="text-xs sm:text-sm text-muted-foreground leading-relaxed max-w-sm">
                    Infrastruktur WhatsApp Gateway berkinerja tinggi untuk pengiriman OTP, tagihan otomatis, notifikasi e-commerce, dan broadcast dengan perlindungan anti-blokir multi-sesi.
                </p>

                {{-- Pilar Keamanan & Rekayasa --}}
                <div class="pt-1 space-y-2 text-xs text-muted-foreground">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                        <span>Enkripsi TLS 1.3 & API Token Scoped</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                        <span>Isolasi Volume Sesi Terenkripsi</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                        <span>Antrean Pesan Jeda Acak Anti-Spam</span>
                    </div>
                </div>
            </div>

            {{-- Sisi Kanan: 4 Kolom Link Enterprise --}}
            <div class="lg:col-span-8 grid grid-cols-2 sm:grid-cols-4 gap-6 sm:gap-8">
                {{-- Kolom 1: Produk API --}}
                <div class="min-w-0">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-primary">Produk API</h4>
                    <ul class="mt-4 space-y-2.5 text-xs sm:text-sm">
                        <li><a href="{{ $beranda }}#fitur" class="text-muted-foreground hover:text-foreground transition-colors">Fitur Gateway</a></li>
                        <li><a href="{{ $beranda }}#cara-kerja" class="text-muted-foreground hover:text-foreground transition-colors">Cara Kerja</a></li>
                        <li><a href="{{ $beranda }}#harga" class="text-muted-foreground hover:text-foreground transition-colors">Paket & Harga</a></li>
                        <li><a href="{{ route('mitra.landing') }}" class="text-muted-foreground hover:text-foreground transition-colors">Program Mitra (Reseller)</a></li>
                        <li><a href="{{ route('docs.show', 'referensi-api') }}" class="text-muted-foreground hover:text-foreground transition-colors">Kirim Pesan Teks</a></li>
                        <li><a href="{{ route('docs.show', 'webhook') }}" class="text-muted-foreground hover:text-foreground transition-colors">Webhook Dispatcher</a></li>
                        <li><a href="{{ route('docs.show', 'praktik-baik') }}" class="text-muted-foreground hover:text-foreground transition-colors">Proteksi Anti-Blokir</a></li>
                        <li><a href="{{ route('login') }}" class="text-muted-foreground hover:text-foreground transition-colors">Console Masuk</a></li>
                    </ul>
                </div>

                {{-- Kolom 2: Developer --}}
                <div class="min-w-0">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-primary">Developer</h4>
                    <ul class="mt-4 space-y-2.5 text-xs sm:text-sm">
                        <li><a href="{{ route('docs.index') }}" class="text-muted-foreground hover:text-foreground transition-colors">Pusat Dokumentasi</a></li>
                        <li><a href="{{ route('docs.show', 'mulai-cepat') }}" class="text-muted-foreground hover:text-foreground transition-colors">Panduan Mulai Cepat</a></li>
                        <li><a href="{{ route('docs.show', 'referensi-api') }}" class="text-muted-foreground hover:text-foreground transition-colors">Referensi API v1.0</a></li>
                        <li><a href="{{ route('docs.show', 'contoh-integrasi') }}" class="text-muted-foreground hover:text-foreground transition-colors">Integrasi PHP / Laravel</a></li>
                        <li><a href="{{ route('docs.show', 'integrasi-ai-agent') }}" class="text-muted-foreground hover:text-foreground transition-colors">Integrasi AI Agent</a></li>
                        <li><a href="{{ route('docs.show', 'koleksi-postman') }}" class="text-muted-foreground hover:text-foreground transition-colors">Koleksi Postman</a></li>
                    </ul>
                </div>

                {{-- Kolom 3: Ekosistem Flustra --}}
                <div class="min-w-0">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-primary">Ekosistem</h4>
                    <ul class="mt-4 space-y-2.5 text-xs sm:text-sm">
                        @foreach (config('flustra.produk') as $nama => $alamat)
                            <li><a href="{{ $alamat }}" target="_blank" rel="noopener" class="text-muted-foreground hover:text-foreground transition-colors">{{ $nama }}</a></li>
                        @endforeach
                        <li><a href="https://about.flustra.id" target="_blank" rel="noopener" class="text-muted-foreground hover:text-foreground transition-colors">Flustra Financial</a></li>
                        <li><a href="https://lynk.id/flustra.id" target="_blank" rel="noopener" class="text-muted-foreground hover:text-foreground transition-colors">Lynk.id Official</a></li>
                    </ul>
                </div>

                {{-- Kolom 4: Perusahaan & Hubungi Kami --}}
                <div class="min-w-0">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-primary">Perusahaan</h4>
                    <ul class="mt-4 space-y-2.5 text-xs sm:text-sm">
                        <li><a href="https://about.flustra.id" target="_blank" rel="noopener" class="text-muted-foreground hover:text-foreground transition-colors">Tentang Kami</a></li>
                        <li><a href="https://about.flustra.id/#contact" target="_blank" rel="noopener" class="text-muted-foreground hover:text-foreground transition-colors">Hubungi Sales</a></li>
                        <li><a href="{{ route('docs.show', 'bantuan') }}" class="text-muted-foreground hover:text-foreground transition-colors">Pusat Bantuan</a></li>
                        <li><a href="mailto:flustrasupport@gmail.com" class="text-muted-foreground hover:text-foreground transition-colors break-all">flustrasupport@gmail.com</a></li>
                        <li><a href="https://wa.me/6282318280376" target="_blank" rel="noopener" class="text-muted-foreground hover:text-foreground transition-colors">WhatsApp Helpdesk</a></li>
                        <li><a href="{{ route('status') }}" class="text-muted-foreground hover:text-foreground transition-colors">Status Layanan</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Baris Bawah: Legal Metadata, Hak Cipta & Social Icons Lengkap (Full-Width Grounded) --}}
    <div class="border-t border-border/70 bg-muted/40 dark:bg-muted/20 py-3 sm:py-3.5">
        <div class="mx-auto flex max-w-[1440px] flex-col sm:flex-row items-center justify-between gap-3 sm:gap-4 px-4 sm:px-6 lg:px-8 xl:px-10 text-xs text-muted-foreground">
            {{-- Dokumen hukum ditaruh di baris paling bawah bersama hak cipta,
                 bukan di kolom Perusahaan. Di situlah orang mencarinya, dan
                 bagian pengadaan calon pelanggan menggulung sampai bawah persis
                 untuk menemukan keenamnya berjajar. --}}
            <div class="flex flex-col items-center gap-2 sm:items-start">
                <span>&copy; {{ date('Y') }} PT FLUSTRA FINANCES ARTHA. Hak cipta dilindungi undang-undang.</span>
                <nav class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 sm:justify-start" aria-label="Dokumen hukum">
                    @foreach ([
                        'syarat-layanan' => 'Syarat Layanan',
                        'kebijakan-privasi' => 'Privasi',
                        'dpa' => 'DPA',
                        'penggunaan-wajar' => 'Penggunaan Wajar',
                        'sla' => 'SLA',
                        'kebijakan-refund' => 'Refund',
                    ] as $slug => $label)
                        <a href="{{ route('docs.show', $slug) }}" class="hover:text-foreground transition-colors">{{ $label }}</a>
                    @endforeach
                </nav>
            </div>

            {{-- 7 Ikon Media Sosial Resmi Flustra --}}
            <div class="flex flex-wrap items-center justify-center sm:justify-end gap-3 sm:gap-3.5 text-muted-foreground">
                {{-- Instagram --}}
                <a href="https://www.instagram.com/flustra.id" target="_blank" rel="noopener" class="hover:text-foreground transition-colors" aria-label="Instagram">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="20" x="2" y="2" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"></line>
                    </svg>
                </a>

                {{-- Threads --}}
                <a href="https://www.threads.net/@flustra.id" target="_blank" rel="noopener" class="hover:text-foreground transition-colors" aria-label="Threads">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11c-1.4-4.8-5.3-8.5-9.9-8.5C5.9 2.5 2 7.2 2 12c0 4.9 3.9 9.5 10.2 9.5 2.6 0 5-.8 6.8-2.3 1.3-1 1.9-2.6 2-4.1v-.3c0-1.8-1.1-3.3-2.7-3.3-1 0-1.9.6-2.4 1.5-1.1 1.9-2.1 3-4.2 3-2.1 0-4-1.6-4-4s1.9-4 4-4c1.7 0 3.3 1.1 3.7 2.7.2.7.1 1.4-.3 2-.4.6-1 .9-1.7.9h-2.1"></path>
                    </svg>
                </a>

                {{-- X / Twitter --}}
                <a href="https://x.com/flustraid" target="_blank" rel="noopener" class="hover:text-foreground transition-colors" aria-label="X / Twitter">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4l11.733 16h4.267l-11.733 -16z"></path><path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772"></path>
                    </svg>
                </a>

                {{-- TikTok --}}
                <a href="https://www.tiktok.com/@flustra.id" target="_blank" rel="noopener" class="hover:text-foreground transition-colors" aria-label="TikTok">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"></path>
                    </svg>
                </a>

                {{-- LinkedIn --}}
                <a href="https://www.linkedin.com/company/flustra" target="_blank" rel="noopener" class="hover:text-foreground transition-colors" aria-label="LinkedIn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect width="4" height="12" x="2" y="9"></rect><circle cx="4" cy="4" r="2"></circle>
                    </svg>
                </a>

                {{-- Shopee --}}
                <a href="https://id.shp.ee/ttH9gphS" target="_blank" rel="noopener" class="hover:text-[#EE4D2D] transition-colors" aria-label="Shopee">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 8h14l-1 13H6L5 8Z"></path>
                        <path d="M9 8V6a3 3 0 0 1 6 0v2"></path>
                        <path d="M14.5 11.5c-.7-.5-1.5-.7-2.4-.7-1.2 0-2.1.6-2.1 1.5 0 2.3 4.6 1.1 4.6 3.8 0 1.1-1 1.9-2.5 1.9-.9 0-1.8-.3-2.6-.9"></path>
                    </svg>
                </a>

                {{-- Lynk.id --}}
                <a href="https://lynk.id/flustra.id" target="_blank" rel="noopener" class="hover:text-foreground transition-colors" aria-label="Lynk.id">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="18" viewBox="0 0 38 24" fill="none">
                        <rect x=".75" y=".75" width="36.5" height="22.5" rx="6" stroke="currentColor" stroke-width="1.5"></rect>
                        <text x="19" y="15.6" fill="currentColor" text-anchor="middle" font-size="10" font-weight="700" font-family="Arial, sans-serif">lynk</text>
                    </svg>
                </a>
            </div>
        </div>
    </div>
</footer>
