<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Gateway WhatsApp untuk Aplikasi Anda</title>
    <meta name="description" content="Kirim notifikasi WhatsApp dari aplikasi Anda lewat satu REST API. Multi-nomor, webhook pesan masuk, riwayat pengiriman, dan sesi yang tidak putus saat server di-deploy ulang.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-stone-800 antialiased dark:bg-stone-950 dark:text-stone-200">

<header class="sticky top-0 z-30 border-b border-stone-200 bg-white/85 backdrop-blur dark:border-stone-800 dark:bg-stone-950/85">
    <div class="mx-auto flex max-w-6xl items-center gap-3 px-5 py-3.5">
        <a href="/" class="flex items-center gap-2.5 font-semibold">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-emerald-600 text-sm font-bold text-white">WA</span>
            <span>Flustra WA Gateway</span>
        </a>

        <nav class="ml-auto flex items-center gap-1 text-sm">
            <a href="#cara-kerja" class="hidden rounded-lg px-3 py-2 text-stone-600 hover:bg-stone-100 sm:block dark:text-stone-400 dark:hover:bg-stone-800">Cara kerja</a>
            <a href="#fitur" class="hidden rounded-lg px-3 py-2 text-stone-600 hover:bg-stone-100 sm:block dark:text-stone-400 dark:hover:bg-stone-800">Fitur</a>
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-stone-600 hover:bg-stone-100 dark:text-stone-400 dark:hover:bg-stone-800">Masuk</a>
                <a href="{{ route('register') }}" class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-700">Mulai</a>
            @endauth
        </nav>
    </div>
</header>

<section class="mx-auto max-w-6xl px-5 pb-16 pt-16 sm:pt-24">
    <div class="grid items-center gap-12 lg:grid-cols-2">
        <div>
            <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
                Sesi bertahan saat server di-deploy ulang
            </span>

            <h1 class="mt-5 text-4xl font-semibold leading-tight tracking-tight sm:text-5xl">
                Kirim WhatsApp dari aplikasi Anda lewat satu API
            </h1>

            <p class="mt-5 text-lg leading-relaxed text-stone-600 dark:text-stone-400">
                Tautkan nomor WhatsApp dengan scan QR, lalu kirim notifikasi dari aplikasi mana pun
                dengan satu panggilan HTTP. Tidak perlu mengurus browser, antrean, atau nomor yang
                tiba-tiba terputus.
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="rounded-lg bg-emerald-600 px-5 py-3 text-sm font-medium text-white hover:bg-emerald-700">
                    Mulai gratis
                </a>
                <a href="#cara-kerja" class="rounded-lg border border-stone-300 px-5 py-3 text-sm font-medium hover:bg-stone-50 dark:border-stone-700 dark:hover:bg-stone-900">
                    Lihat cara kerjanya
                </a>
            </div>
        </div>

        <div class="rounded-2xl border border-stone-200 bg-stone-950 p-1 shadow-sm dark:border-stone-800">
            <div class="flex items-center gap-1.5 px-3 py-2.5">
                <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                <span class="ml-2 text-xs text-stone-500">kirim-notifikasi.sh</span>
            </div>
            <pre class="overflow-x-auto rounded-xl bg-stone-900 p-5 text-[13px] leading-relaxed text-stone-300"><code>curl -X POST https://wa.flustra.id/api/v1/messages/text \
  -H <span class="text-emerald-400">"X-Api-Key: fwa_a1b2c3d4.…"</span> \
  -H <span class="text-emerald-400">"Content-Type: application/json"</span> \
  -d <span class="text-amber-300">'{
    "to": "081234567890",
    "message": "Invoice INV-001 sudah lunas. Terima kasih!"
  }'</span>

<span class="text-stone-500">→ { "success": true, "data": { "status": "queued" } }</span></code></pre>
        </div>
    </div>
</section>

<section id="cara-kerja" class="border-y border-stone-200 bg-stone-50 py-16 dark:border-stone-800 dark:bg-stone-900/40">
    <div class="mx-auto max-w-6xl px-5">
        <h2 class="text-2xl font-semibold tracking-tight">Tiga langkah</h2>
        <p class="mt-2 text-stone-600 dark:text-stone-400">Dari daftar sampai pesan pertama terkirim, biasanya di bawah lima menit.</p>

        <div class="mt-10 grid gap-6 md:grid-cols-3">
            @php
                $langkah = [
                    ['1', 'Tautkan nomor', 'Buat sesi di dashboard, lalu scan QR-nya lewat menu Perangkat Tertaut di WhatsApp — sama seperti membuka WhatsApp Web.'],
                    ['2', 'Ambil API key', 'Satu kunci per aplikasi. Kunci bisa dicabut kapan saja tanpa mengganggu aplikasi lain.'],
                    ['3', 'Panggil API-nya', 'Satu permintaan HTTP untuk mengirim. Status pengiriman bisa dipantau lewat riwayat atau webhook.'],
                ];
            @endphp
            @foreach ($langkah as [$no, $judul, $isi])
                <div class="rounded-xl border border-stone-200 bg-white p-6 dark:border-stone-800 dark:bg-stone-900">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-emerald-600 text-sm font-semibold text-white">{{ $no }}</span>
                    <h3 class="mt-4 font-semibold">{{ $judul }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-stone-600 dark:text-stone-400">{{ $isi }}</p>
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
                <div class="rounded-xl border border-stone-200 p-6 dark:border-stone-800">
                    <h3 class="font-semibold">{{ $judul }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-stone-600 dark:text-stone-400">{{ $isi }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="border-t border-stone-200 bg-stone-50 py-16 dark:border-stone-800 dark:bg-stone-900/40">
    <div class="mx-auto max-w-3xl px-5">
        <h2 class="text-2xl font-semibold tracking-tight">Sebelum Anda mulai</h2>
        <p class="mt-2 text-stone-600 dark:text-stone-400">Dua hal yang sebaiknya diketahui sejak awal, supaya tidak ada kejutan.</p>

        <div class="mt-8 space-y-4">
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950/40">
                <h3 class="font-semibold text-amber-900 dark:text-amber-200">Ini memakai WhatsApp Web, bukan API resmi</h3>
                <p class="mt-2 text-sm leading-relaxed text-amber-800 dark:text-amber-300">
                    Nomor Anda tertaut seperti perangkat biasa. Gratis dan bisa langsung dipakai, tapi WhatsApp
                    tidak resmi mengizinkan klien non-resmi — nomor tetap berisiko diblokir bila dipakai
                    mengirim massal ke orang yang tidak mengharapkannya. Kirimlah ke pelanggan Anda sendiri.
                </p>
            </div>

            <div class="rounded-xl border border-stone-200 bg-white p-5 dark:border-stone-800 dark:bg-stone-900">
                <h3 class="font-semibold">Ganti nomor kapan saja</h3>
                <p class="mt-2 text-sm leading-relaxed text-stone-600 dark:text-stone-400">
                    Nomor tidak terkunci. Putuskan tautannya dari dashboard, lalu scan QR dengan nomor baru —
                    selesai. Yang kami jamin adalah kebalikannya: nomor tidak akan terputus sendiri hanya
                    karena server di-deploy ulang.
                </p>
            </div>
        </div>
    </div>
</section>

<section class="py-16">
    <div class="mx-auto max-w-3xl px-5 text-center">
        <h2 class="text-2xl font-semibold tracking-tight">Siap mencoba?</h2>
        <p class="mt-3 text-stone-600 dark:text-stone-400">Buat akun, tautkan satu nomor, dan kirim pesan pertama Anda hari ini.</p>
        <a href="{{ route('register') }}" class="mt-7 inline-block rounded-lg bg-emerald-600 px-6 py-3 text-sm font-medium text-white hover:bg-emerald-700">
            Buat akun
        </a>
    </div>
</section>

<footer class="border-t border-stone-200 py-8 dark:border-stone-800">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-2 px-5 text-sm text-stone-500">
        <span>&copy; {{ date('Y') }} Flustra</span>
        <a href="https://flustra.id" class="hover:text-stone-800 dark:hover:text-stone-300">flustra.id</a>
        <a href="https://helpdesk.flustra.id" class="hover:text-stone-800 dark:hover:text-stone-300">Bantuan</a>
        <span class="ml-auto text-xs">Bukan produk resmi WhatsApp atau Meta.</span>
    </div>
</footer>

</body>
</html>
