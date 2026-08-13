<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Gateway WhatsApp untuk Aplikasi Anda</title>
    <meta name="description" content="Kirim notifikasi WhatsApp dari aplikasi Anda lewat satu REST API. Multi-nomor, webhook pesan masuk, riwayat pengiriman, dan sesi yang tidak putus saat server di-deploy ulang.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background text-foreground antialiased">

<header class="relative z-30 pt-4" x-data="{ activeMenu: null }">
    <div class="mx-auto flex max-w-6xl items-center gap-3 px-5 py-3.5">
        <a href="/" class="flex items-center gap-2.5 font-semibold">
            <img src="{{ asset('images/flustra-wa.png') }}" alt="Logo" class="h-8 w-auto object-contain">
            <span>Flustra WA Gateway</span>
        </a>

        <nav class="ml-auto flex items-center gap-1 text-sm">
            <!-- Nav Item: Cara kerja -->
            <div class="hidden items-center md:flex" @click.away="if(activeMenu === 'cara-kerja') activeMenu = null">
                <a href="#cara-kerja" class="rounded-l-lg px-3 py-2 text-muted-foreground hover:bg-muted hover:text-foreground">Cara kerja</a>
                <button @click="activeMenu = activeMenu === 'cara-kerja' ? null : 'cara-kerja'" class="rounded-r-lg py-2 pr-3 pl-1 text-muted-foreground hover:bg-muted hover:text-foreground">
                    <svg class="h-3 w-3 transition-transform" :class="activeMenu === 'cara-kerja' ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </button>
            </div>

            <!-- Nav Item: Fitur -->
            <div class="hidden items-center md:flex" @click.away="if(activeMenu === 'fitur') activeMenu = null">
                <a href="#fitur" class="rounded-l-lg px-3 py-2 text-muted-foreground hover:bg-muted hover:text-foreground">Fitur</a>
                <button @click="activeMenu = activeMenu === 'fitur' ? null : 'fitur'" class="rounded-r-lg py-2 pr-3 pl-1 text-muted-foreground hover:bg-muted hover:text-foreground">
                    <svg class="h-3 w-3 transition-transform" :class="activeMenu === 'fitur' ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </button>
            </div>

            <!-- Nav Item: Developer -->
            <div class="hidden items-center md:flex" @click.away="if(activeMenu === 'developer') activeMenu = null">
                <a href="#untuk-developer" class="rounded-l-lg px-3 py-2 text-muted-foreground hover:bg-muted hover:text-foreground">Developer</a>
                <button @click="activeMenu = activeMenu === 'developer' ? null : 'developer'" class="rounded-r-lg py-2 pr-3 pl-1 text-muted-foreground hover:bg-muted hover:text-foreground">
                    <svg class="h-3 w-3 transition-transform" :class="activeMenu === 'developer' ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                </button>
            </div>

            <a href="{{ route('docs.index') }}" class="rounded-lg px-3 py-2 font-medium text-foreground hover:bg-muted">Docs</a>

            @auth
                <a href="{{ route('dashboard') }}" class="rounded-lg bg-primary px-4 py-2 font-medium text-primary-foreground hover:bg-primary/90">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-muted-foreground hover:bg-muted hover:text-foreground">Masuk</a>
                <a href="{{ route('register') }}" class="rounded-lg bg-primary px-4 py-2 font-medium text-primary-foreground hover:bg-primary/90">Mulai</a>
            @endauth
        </nav>
    </div>

    <!-- Collapsible Mega Menus -->
    <div class="w-full bg-background/50 backdrop-blur-sm">
        
        <!-- Cara Kerja Content -->
        <div x-show="activeMenu === 'cara-kerja'" x-collapse.duration.300ms x-cloak class="border-b border-border/50">
            <div class="mx-auto max-w-6xl px-5 py-8 grid gap-x-8 gap-y-6 md:grid-cols-3">
                <div>
                    <h3 class="flex items-center gap-2 font-semibold text-foreground"><span class="grid h-6 w-6 place-items-center rounded bg-primary/10 text-xs text-primary">1</span> Tautkan nomor</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Buat sesi di dashboard, lalu scan QR-nya lewat menu Perangkat Tertaut di WhatsApp — sama seperti membuka WhatsApp Web.</p>
                </div>
                <div>
                    <h3 class="flex items-center gap-2 font-semibold text-foreground"><span class="grid h-6 w-6 place-items-center rounded bg-primary/10 text-xs text-primary">2</span> Ambil API key</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Satu kunci per aplikasi. Kunci bisa dicabut kapan saja tanpa mengganggu aplikasi lain.</p>
                </div>
                <div>
                    <h3 class="flex items-center gap-2 font-semibold text-foreground"><span class="grid h-6 w-6 place-items-center rounded bg-primary/10 text-xs text-primary">3</span> Panggil API-nya</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Satu permintaan HTTP untuk mengirim. Status pengiriman bisa dipantau lewat riwayat atau webhook.</p>
                </div>
            </div>
        </div>

        <!-- Fitur Content -->
        <div x-show="activeMenu === 'fitur'" x-collapse.duration.300ms x-cloak class="border-b border-border/50">
            <div class="mx-auto max-w-6xl px-5 py-8 grid gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <h3 class="font-semibold text-foreground">Sesi tangguh</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Kredensial disimpan permanen. Deploy ulang server tidak memaksa Anda scan QR lagi.</p>
                </div>
                <div>
                    <h3 class="font-semibold text-foreground">Multi-nomor</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Tiap nomor berjalan terpisah. Satu nomor bermasalah tidak menghentikan yang lain.</p>
                </div>
                <div>
                    <h3 class="font-semibold text-foreground">Jeda otomatis</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Setiap pesan diberi jeda acak beberapa detik untuk mencegah nomor diblokir.</p>
                </div>
                <div>
                    <h3 class="font-semibold text-foreground">Webhook realtime</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Balasan pelanggan diteruskan ke aplikasi Anda, lengkap dengan tanda tangan verifikasi.</p>
                </div>
                <div>
                    <h3 class="font-semibold text-foreground">Tracking status</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Terkirim, sampai, atau dibaca — semuanya tercatat per pesan.</p>
                </div>
                <div>
                    <h3 class="font-semibold text-foreground">Multi-driver API</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Siap pindah ke WhatsApp Business API resmi nanti tanpa mengubah kode aplikasi Anda.</p>
                </div>
            </div>
        </div>

        <!-- Developer Content -->
        <div x-show="activeMenu === 'developer'" x-collapse.duration.300ms x-cloak class="border-b border-border/50">
            <div class="mx-auto max-w-6xl px-5 py-8 grid gap-x-8 gap-y-6 md:grid-cols-3">
                <div>
                    <h3 class="font-semibold text-foreground">REST API</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Satu endpoint untuk kirim teks, lampiran, pengiriman massal, dan template.</p>
                </div>
                <div>
                    <h3 class="font-semibold text-foreground">Webhook Integrasi</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Balasan pelanggan diteruskan ke aplikasi Anda, lengkap dengan tanda tangan yang bisa diverifikasi.</p>
                </div>
                <div>
                    <h3 class="font-semibold text-foreground">Contoh Kode Siap Salin</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Kode integrasi tersedia untuk PHP/Laravel, Node.js, Python, dan bahasa populer lainnya.</p>
                </div>
            </div>
        </div>

    </div>
</header>

<section class="mx-auto max-w-6xl px-5 pb-16 pt-16 sm:pt-24">
    <div class="grid items-center gap-12 lg:grid-cols-2">
        <div>
            <span class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                Sesi bertahan saat server di-deploy ulang
            </span>

            <h1 class="mt-5 text-4xl font-semibold leading-tight tracking-tight sm:text-5xl">
                Kirim WhatsApp dari aplikasi Anda lewat satu API
            </h1>

            <p class="mt-5 text-lg leading-relaxed text-muted-foreground">
                Tautkan nomor WhatsApp dengan scan QR, lalu kirim notifikasi dari aplikasi mana pun
                dengan satu panggilan HTTP. Tidak perlu mengurus browser, antrean, atau nomor yang
                tiba-tiba terputus.
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="rounded-lg bg-primary px-5 py-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                    Mulai gratis
                </a>
                <a href="#cara-kerja" class="rounded-lg border border-input px-5 py-3 text-sm font-medium hover:bg-accent hover:text-accent-foreground">
                    Lihat cara kerjanya
                </a>
            </div>
        </div>

        <div class="rounded-2xl border border-border bg-[#09090b] p-1 shadow-sm">
            <div class="flex items-center gap-1.5 px-3 py-2.5">
                <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                <span class="ml-2 text-xs text-zinc-500">kirim-notifikasi.sh</span>
            </div>
            <pre class="overflow-x-auto rounded-xl bg-[#18181b] p-5 text-[13px] leading-relaxed text-zinc-300"><code>curl -X POST https://wa.flustra.id/api/v1/messages/text \
  -H <span class="text-emerald-400">"X-Api-Key: fwa_a1b2c3d4.…"</span> \
  -H <span class="text-emerald-400">"Content-Type: application/json"</span> \
  -d <span class="text-amber-300">'{
    "to": "081234567890",
    "message": "Invoice INV-001 sudah lunas. Terima kasih!"
  }'</span>

<span class="text-zinc-500">→ { "success": true, "data": { "status": "queued" } }</span></code></pre>
        </div>
    </div>
</section>

<section id="cara-kerja" class="py-16">
    <div class="mx-auto max-w-6xl px-5">
        <h2 class="text-2xl font-semibold tracking-tight">Tiga langkah</h2>
        <p class="mt-2 text-muted-foreground">Dari daftar sampai pesan pertama terkirim, biasanya di bawah lima menit.</p>

        <div class="mt-10 grid gap-6 md:grid-cols-3">
            @php
                $langkah = [
                    ['1', 'Tautkan nomor', 'Buat sesi di dashboard, lalu scan QR-nya lewat menu Perangkat Tertaut di WhatsApp — sama seperti membuka WhatsApp Web.'],
                    ['2', 'Ambil API key', 'Satu kunci per aplikasi. Kunci bisa dicabut kapan saja tanpa mengganggu aplikasi lain.'],
                    ['3', 'Panggil API-nya', 'Satu permintaan HTTP untuk mengirim. Status pengiriman bisa dipantau lewat riwayat atau webhook.'],
                ];
            @endphp
            @foreach ($langkah as [$no, $judul, $isi])
                <div class="rounded-xl border border-border bg-card p-6 text-card-foreground">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary text-sm font-semibold text-primary-foreground">{{ $no }}</span>
                    <h3 class="mt-4 font-semibold">{{ $judul }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ $isi }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section id="fitur" class="py-16">
    <div class="mx-auto max-w-6xl px-5">
        <h2 class="text-2xl font-semibold tracking-tight">Yang membedakan</h2>

        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @php
                $fitur = [
                    ['Sesi tidak putus saat deploy', 'Kredensial nomor disimpan di penyimpanan permanen dan dicadangkan otomatis. Deploy ulang server tidak memaksa Anda scan QR lagi.'],
                    ['Banyak nomor sekaligus', 'Tiap nomor berjalan terpisah. Satu nomor bermasalah tidak menghentikan nomor lain.'],
                    ['Jeda kirim otomatis', 'Setiap pesan diberi jeda acak beberapa detik. Mengirim beruntun tanpa jeda adalah cara tercepat membuat nomor diblokir.'],
                    ['Webhook pesan masuk', 'Balasan pelanggan diteruskan ke aplikasi Anda, lengkap dengan tanda tangan yang bisa diverifikasi.'],
                    ['Riwayat & status kirim', 'Terkirim, sampai, atau dibaca — semuanya tercatat per pesan, bukan cuma "berhasil".'],
                    ['Siap pindah ke API resmi', 'Arsitekturnya multi-driver. Pindah ke WhatsApp Business API resmi nanti tidak mengubah cara aplikasi Anda memanggilnya.'],
                ];
            @endphp
            @foreach ($fitur as [$judul, $isi])
                <div class="rounded-xl border border-border bg-card p-6 text-card-foreground">
                    <h3 class="font-semibold">{{ $judul }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ $isi }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="py-16">
    <div class="mx-auto max-w-3xl px-5">
        <h2 class="text-2xl font-semibold tracking-tight">Sebelum Anda mulai</h2>
        <p class="mt-2 text-muted-foreground">Dua hal yang sebaiknya diketahui sejak awal, supaya tidak ada kejutan.</p>

        <div class="mt-8 space-y-4">
            <div class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-5">
                <h3 class="font-semibold text-amber-600 dark:text-amber-500">Ini memakai WhatsApp Web, bukan API resmi</h3>
                <p class="mt-2 text-sm leading-relaxed text-amber-700/80 dark:text-amber-400/80">
                    Nomor Anda tertaut seperti perangkat biasa. Gratis dan bisa langsung dipakai, tapi WhatsApp
                    tidak resmi mengizinkan klien non-resmi — nomor tetap berisiko diblokir bila dipakai
                    mengirim massal ke orang yang tidak mengharapkannya. Kirimlah ke pelanggan Anda sendiri.
                </p>
            </div>

            <div class="rounded-xl border border-border bg-card p-5 text-card-foreground">
                <h3 class="font-semibold">Ganti nomor kapan saja</h3>
                <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                    Nomor tidak terkunci. Putuskan tautannya dari dashboard, lalu scan QR dengan nomor baru —
                    selesai. Yang kami jamin adalah kebalikannya: nomor tidak akan terputus sendiri hanya
                    karena server di-deploy ulang.
                </p>
            </div>
        </div>
    </div>
</section>

<section id="untuk-developer" class="py-16">
    <div class="mx-auto max-w-4xl px-5">
        <h2 class="text-2xl font-semibold tracking-tight">Untuk developer</h2>
        <p class="mt-2 text-muted-foreground">
            HTTP dan JSON biasa. Tidak ada SDK yang wajib dipasang, tidak ada protokol khusus yang perlu dipelajari.
        </p>

        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            @php
                $devPoin = [
                    ['REST API', 'Satu endpoint untuk kirim teks, lampiran, pengiriman massal, dan template.'],
                    ['Webhook', 'Balasan pelanggan diteruskan ke aplikasi Anda, lengkap dengan tanda tangan yang bisa diverifikasi.'],
                    ['Contoh siap salin', 'Kode integrasi untuk PHP/Laravel, Node.js, dan Python.'],
                ];
            @endphp
            @foreach ($devPoin as [$judul, $isi])
                <div class="rounded-xl border border-border bg-card p-5 text-card-foreground">
                    <h3 class="font-semibold">{{ $judul }}</h3>
                    <p class="mt-1.5 text-sm text-muted-foreground">{{ $isi }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('docs.show', 'referensi-api') }}" class="rounded-lg bg-secondary px-5 py-2.5 text-sm font-medium text-secondary-foreground hover:bg-secondary/80">
                Referensi API
            </a>
            <a href="{{ route('docs.show', 'contoh-integrasi') }}" class="rounded-lg border border-border bg-transparent px-5 py-2.5 text-sm font-medium hover:bg-muted">
                Contoh integrasi
            </a>
            <a href="{{ route('docs.index') }}" class="rounded-lg border border-border bg-transparent px-5 py-2.5 text-sm font-medium hover:bg-muted">
                Semua dokumentasi
            </a>
        </div>
    </div>
</section>

<section class="py-16">
    <div class="mx-auto max-w-3xl px-5 text-center">
        <h2 class="text-2xl font-semibold tracking-tight">Siap mencoba?</h2>
        <p class="mt-3 text-muted-foreground">Buat akun, tautkan satu nomor, dan kirim pesan pertama Anda hari ini.</p>
        <a href="{{ route('register') }}" class="mt-7 inline-block rounded-lg bg-primary px-6 py-3 text-sm font-medium text-primary-foreground hover:bg-primary/90">
            Buat akun
        </a>
    </div>
</section>

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
                    <li><a href="#fitur" class="text-muted-foreground hover:text-foreground">Fitur</a></li>
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
