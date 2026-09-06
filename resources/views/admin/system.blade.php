@extends('layouts.admin')
@section('title', 'Sistem')

@section('content')
    @php
        $persenKapasitas = $kapasitas['batas'] > 0 ? min(100, round($kapasitas['hidup'] / $kapasitas['batas'] * 100)) : 0;

        /*
         | Daftar hal yang bisa berhenti bekerja TANPA gejala. Ketiganya punya
         | bentuk kegagalan yang sama: tidak ada galat, tidak ada keluhan, dan
         | tidak ada yang menyadarinya sampai ada pelanggan yang dirugikan.
        */
        $pemeriksaan = [
            [
                'nama' => 'Engine WhatsApp',
                'baik' => $engine['terjangkau'],
                'kabar' => $engine['pesan'],
                'akibat' => 'Semua sesi berhenti mengirim, dan dashboard tetap menampilkannya hijau sampai penyelaras berikutnya berjalan.',
            ],
            [
                'nama' => 'Pemberitahuan WhatsApp',
                'baik' => $notifikasi['siap'],
                'kabar' => $notifikasi['siap']
                    ? 'Sesi pengirim siap.'
                    : 'Tidak ada sesi pengirim. Isi BILLING_NOTIFY_WORKSPACE_ID dan pastikan salah satu nomornya terhubung.',
                'akibat' => 'Pelanggan tidak dikabari saat pembayarannya lunas, kuotanya habis, atau nomornya terputus.',
            ],
            [
                'nama' => 'Nomor admin',
                'baik' => filled($notifikasi['nomorAdmin']),
                'kabar' => filled($notifikasi['nomorAdmin']) ? $notifikasi['nomorAdmin'] : 'BILLING_ADMIN_PHONE kosong.',
                'akibat' => 'Tidak ada yang memberi tahu tim saat bukti pembayaran baru masuk — dan tagihan hanya lunas kalau ada yang membukanya.',
            ],
            [
                'nama' => 'QRIS',
                'baik' => $pembayaran['qris'],
                'kabar' => $pembayaran['qris']
                    ? 'Payload sah atas nama '.$pembayaran['merchant'].'.'
                    : 'Belum diisi atau CRC-nya tidak cocok.',
                'akibat' => 'Halaman pembayaran diam-diam jatuh ke transfer bank; kalau rekening juga kosong, tidak ada cara membayar sama sekali.',
            ],
            [
                'nama' => 'Rekening bank',
                'baik' => (bool) $pembayaran['bank'],
                'kabar' => $pembayaran['bank']
                    ? $pembayaran['bank']['name'].' · '.$pembayaran['bank']['account_number']
                    : 'Belum diisi.',
                'akibat' => 'Paket tahunan bisa melampaui batas QRIS per transaksi, dan tanpa rekening tidak ada jalan lain.',
            ],
            [
                'nama' => 'Antrean bergerak',
                'baik' => ! $antrean['macet'],
                'kabar' => $antrean['macet']
                    ? $antrean['menunggu'].' menunggu, tidak ada yang terkirim sejak '
                        .($antrean['terakhirBergerak']?->diffForHumans() ?? 'entah kapan').'.'
                    : ($antrean['menunggu'] === 0
                        ? 'Kosong.'
                        : $antrean['menunggu'].' menunggu dan bergerak normal.'),
                'akibat' => 'Worker mati berarti pesan diam berstatus Mengantre selamanya — tanpa galat, '
                    .'tanpa baris log baru. Pengirimnya mengira gateway lambat; penerimanya tidak menerima apa-apa. '
                    .'Dianggap macet kalau tidak ada pesan yang terkirim selama '.$antrean['ambang'].' menit '
                    .'sementara masih ada pekerjaan menunggu — panjangnya antrean sendiri bukan tanda kerusakan, '
                    .'broadcast besar memang wajar berjam-jam.',
            ],
            [
                'nama' => 'Job gagal',
                'baik' => $antrean['gagal'] === 0,
                'kabar' => $antrean['gagal'] === 0
                    ? 'Tidak ada yang gagal.'
                    : $antrean['gagal'].' job gagal menumpuk.',
                'akibat' => 'Job yang gagal tidak dicoba lagi sendiri; pesannya tidak akan pernah terkirim.',
            ],
        ];

        $bermasalah = collect($pemeriksaan)->reject(fn ($p) => $p['baik'])->count();
    @endphp

    {{-- ===================== Ikhtisar kesehatan ===================== --}}
    <x-section :judul="$bermasalah === 0 ? 'Semua pemeriksaan lolos' : $bermasalah.' hal perlu dibereskan'"
             sub="Semua yang di bawah ini bisa berhenti bekerja tanpa satu pun galat yang terlihat."
             @class(['mb-5', 'border-destructive' => $bermasalah > 0])>
        <table class="w-full min-w-[52rem] text-sm">
            <tbody class="divide-y divide-border">
                @foreach ($pemeriksaan as $p)
                    <tr>
                        <td class="w-8 px-5 py-3">
                            @if ($p['baik'])
                                <span class="grid h-6 w-6 place-items-center rounded-full bg-primary/10 text-primary">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                                </span>
                            @else
                                <span class="grid h-6 w-6 place-items-center rounded-full bg-destructive/10 text-destructive">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 8v5M12 17h.01"/></svg>
                                </span>
                            @endif
                        </td>
                        <td class="px-2 py-3 font-medium">{{ $p['nama'] }}</td>
                        <td class="px-5 py-3 text-muted-foreground">{{ $p['kabar'] }}</td>
                        <td class="px-5 py-3">
                            @unless ($p['baik'])
                                <span class="text-xs leading-relaxed text-destructive">{{ $p['akibat'] }}</span>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-section>

    <div class="grid gap-5 lg:grid-cols-2">
        {{-- ===================== Kapasitas ===================== --}}
        <x-section judul="Kapasitas sesi" sub="Batas yang menentukan berapa pelanggan bisa dilayani sama sekali." rapat>
            <p class="text-3xl font-semibold tabular-nums">
                {{ $kapasitas['hidup'] }}<span class="text-lg font-normal text-muted-foreground">/{{ $kapasitas['batas'] }}</span>
            </p>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                <div class="h-full rounded-full {{ $persenKapasitas >= 90 ? 'bg-destructive' : ($persenKapasitas >= 70 ? 'bg-amber-500' : 'bg-primary') }}"
                     style="width: {{ $persenKapasitas }}%"></div>
            </div>
            <p class="mt-3 text-sm leading-relaxed text-muted-foreground">
                Tiap sesi berarti satu Chromium yang memakan 300–500 MB. Batasnya ditegakkan engine lewat
                <code>WA_MAX_SESSIONS</code>, bukan oleh Laravel — begitu penuh, pelanggan berikutnya yang
                membayar tidak akan bisa menautkan nomornya.
            </p>
            <p class="mt-2 text-sm text-muted-foreground">
                Pesan gagal 24 jam terakhir: <strong class="{{ $pesanGagal24Jam > 0 ? 'text-destructive' : '' }}">{{ number_format($pesanGagal24Jam) }}</strong>
            </p>
        </x-section>

        {{-- ===================== Irama siklus ===================== --}}
        <x-section judul="Irama penagihan" sub="Angka yang menentukan kapan pelanggan ditagih dan kapan layanannya berhenti." rapat>
            <dl class="space-y-2.5 text-sm">
                @foreach ([
                    'Tagihan terbit sebelum habis' => $siklus['terbit_sebelum'].' hari',
                    'Batas bayar tiap tagihan' => $siklus['tenggat_tagihan'].' hari',
                    'Masa tenggang sebelum sesi dilepas' => $siklus['masa_tenggang'].' hari',
                    'Pengingat dikirim H-' => $siklus['pengingat'],
                    'Jeda antar pesan (anti-ban)' => $siklus['jeda_kirim'],
                    'PPN' => $pembayaran['pajak'] > 0 ? $pembayaran['pajak'].'%' : 'tidak dipungut',
                ] as $label => $nilai)
                    <div class="flex justify-between gap-4 border-b border-border pb-2.5 last:border-0 last:pb-0">
                        <dt class="text-muted-foreground">{{ $label }}</dt>
                        <dd class="text-right font-medium">{{ $nilai }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-section>
    </div>

    {{-- ===================== Paket ===================== --}}
    <x-section judul="Paket yang sedang berlaku"
             sub="Dibaca dari config/plans.php — sumber yang sama dengan halaman harga dan penegakan batas."
             class="mt-5">
        <table class="w-full min-w-[52rem] text-sm">
            <thead class="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                <tr>
                    <th class="px-5 py-2.5 font-medium">Paket</th>
                    <th class="px-5 py-2.5 text-right font-medium">Bulanan</th>
                    <th class="px-5 py-2.5 text-right font-medium">Tahunan</th>
                    <th class="px-5 py-2.5 text-right font-medium">Nomor</th>
                    <th class="px-5 py-2.5 text-right font-medium">Pesan/bln</th>
                    <th class="px-5 py-2.5 text-right font-medium">API key</th>
                    <th class="px-5 py-2.5 text-right font-medium">Retensi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @foreach ($paket as $p)
                    <tr>
                        <td class="px-5 py-2.5 font-medium">{{ $p->name() }}</td>
                        <td class="px-5 py-2.5 text-right tabular-nums">Rp {{ number_format($p->price('monthly'), 0, ',', '.') }}</td>
                        <td class="px-5 py-2.5 text-right tabular-nums">Rp {{ number_format($p->price('yearly'), 0, ',', '.') }}</td>
                        <td class="px-5 py-2.5 text-right tabular-nums">{{ $p->limits()['max_sessions'] }}</td>
                        <td class="px-5 py-2.5 text-right tabular-nums">{{ number_format($p->limits()['monthly_message_quota']) }}</td>
                        <td class="px-5 py-2.5 text-right tabular-nums">{{ $p->maxApiKeys() === 0 ? '∞' : $p->maxApiKeys() }}</td>
                        <td class="px-5 py-2.5 text-right tabular-nums">{{ $p->messageRetentionDays() }} hari</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-section>

    {{-- ===================== Lingkungan ===================== --}}
    <x-section judul="Lingkungan" class="mt-5" rapat>
        <dl class="grid gap-x-8 gap-y-2.5 text-sm sm:grid-cols-2">
            @foreach ([
                'Tahap' => $lingkungan['app_env'],
                'Zona waktu' => $lingkungan['zona_waktu'],
                'PHP' => $lingkungan['php'],
                'Laravel' => $lingkungan['laravel'],
            ] as $label => $nilai)
                <div class="flex justify-between gap-4 border-b border-border pb-2.5">
                    <dt class="text-muted-foreground">{{ $label }}</dt>
                    <dd class="font-medium">{{ $nilai }}</dd>
                </div>
            @endforeach

            <div class="flex justify-between gap-4 border-b border-border pb-2.5">
                <dt class="text-muted-foreground">APP_DEBUG</dt>
                <dd>
                    @if ($lingkungan['app_debug'] && $lingkungan['app_env'] === 'production')
                        {{-- Halaman galat menampilkan isi environment, termasuk
                             kata sandi database dan secret HMAC. --}}
                        <x-badge warna="merah">menyala di produksi</x-badge>
                    @else
                        <span class="font-medium">{{ $lingkungan['app_debug'] ? 'menyala' : 'mati' }}</span>
                    @endif
                </dd>
            </div>
        </dl>
    </x-section>
@endsection
