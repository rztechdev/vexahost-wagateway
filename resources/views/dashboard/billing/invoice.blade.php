@extends('layouts.checkout')
@section('title', 'Checkout Tagihan ' . $invoice->number)

@section('content')
    @php
        $selesai = $invoice->isPaid() || $invoice->isOverdue() || $invoice->status === 'canceled';

        /*
         | Metode mana yang boleh ditawarkan. Yang tidak dikonfigurasi tidak
         | pernah muncul sebagai pilihan — menawarkan cara bayar yang belum ada
         | di baliknya persis kesalahan yang dulu dibuat driver `fonnte` pada
         | halaman pembuatan sesi: pelanggan memilih, lalu menemui jalan buntu.
         */
        $metode = [];
        if ($qrisPayload) {
            $metode['qris'] = [
                'label' => 'QRIS',
                'badge' => 'Otomatis',
                'ket' => 'Scan via BCA, Mandiri, GoPay, OVO, Dana, ShopeePay, atau bank apa pun',
            ];
        }
        if ($bank) {
            $metode['bank'] = [
                'label' => 'Transfer bank',
                'badge' => 'Manual',
                'ket' => 'Transfer antar bank via ATM, Mobile Banking, atau Internet Banking',
            ];
        }
        $metodeAwal = '';
    @endphp

    {{-- ===================== Keadaan Akhir (Paid, Overdue, Canceled) =====================
         Tagihan yang sudah lunas, kedaluwarsa, atau dibatalkan tidak lagi memerlukan form checkout.
         Menampilkan layar konfirmasi/tanda terima mandiri yang bersih dan profesional.
         ===================================================================================== --}}
    @if ($selesai)
        <div class="mx-auto max-w-xl py-6 sm:py-12">
            <div class="overflow-hidden rounded-2xl border border-border bg-card p-6 text-center shadow-lg sm:p-10">
                
                @if ($invoice->isPaid())
                    {{-- Status: Lunas / Sukses --}}
                    <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-primary/10 text-primary ring-8 ring-primary/5">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M20 6 9 17l-5-5"/>
                        </svg>
                    </div>

                    <h1 class="mt-6 text-2xl font-bold tracking-tight text-foreground sm:text-3xl">Pembayaran diterima</h1>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Tagihan <span class="font-semibold text-foreground">{{ $invoice->number }}</span> telah lunas pada 
                        <span class="font-medium text-foreground">{{ $invoice->paid_at ? $invoice->paid_at->translatedFormat('j F Y, H:i') : now()->translatedFormat('j F Y, H:i') }}</span>.
                        Layanan langganan workspace Anda sudah aktif.
                    </p>

                    {{-- Ringkasan Tanda Terima --}}
                    <div class="mt-8 rounded-xl border border-border/80 bg-muted/30 p-4 text-left text-sm">
                        <div class="flex items-center justify-between py-1.5 text-xs text-muted-foreground">
                            <span>Nomor Tagihan</span>
                            <span class="font-mono font-medium text-foreground">{{ $invoice->number }}</span>
                        </div>
                        <div class="flex items-center justify-between py-1.5 text-xs text-muted-foreground">
                            <span>Paket Berlangganan</span>
                            <span class="font-medium text-foreground">{{ $plan->name() }} &middot; {{ $invoice->periodLabel() }}</span>
                        </div>
                        <div class="flex items-center justify-between py-1.5 text-xs text-muted-foreground">
                            <span>Workspace</span>
                            <span class="font-medium text-foreground">{{ $currentWorkspace->name }}</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between border-t border-border pt-2 font-medium">
                            <span class="text-foreground">Total Pembayaran</span>
                            <span class="text-base font-bold text-primary">Rp {{ number_format($invoice->total, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                        <a href="{{ route('dashboard') }}" 
                           class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground shadow-sm transition hover:opacity-90">
                            <span>Buka Dashboard</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                        </a>
                        <a href="{{ route('billing.history') }}" 
                           class="inline-flex items-center justify-center rounded-xl border border-border bg-card px-5 py-3 text-sm font-semibold text-muted-foreground transition hover:bg-muted hover:text-foreground">
                            Riwayat Tagihan
                        </a>
                    </div>

                @elseif ($invoice->isOverdue())
                    {{-- Status: Kedaluwarsa --}}
                    <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-destructive/10 text-destructive ring-8 ring-destructive/5">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>

                    <h1 class="mt-6 text-2xl font-bold tracking-tight text-foreground">Batas waktu pembayaran sudah lewat</h1>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Tagihan ini tidak berlaku lagi. Terbitkan tagihan baru dari halaman paket dengan kode unik yang terbarukan.
                    </p>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                        <a href="{{ route('billing.plans') }}" 
                           class="inline-flex items-center justify-center rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground shadow-sm transition hover:opacity-90">
                            Pilih Paket Baru
                        </a>
                        <a href="{{ route('billing.index') }}" 
                           class="inline-flex items-center justify-center rounded-xl border border-border bg-card px-5 py-3 text-sm font-semibold text-muted-foreground transition hover:bg-muted hover:text-foreground">
                            Kembali ke Ringkasan
                        </a>
                    </div>

                @else
                    {{-- Status: Dibatalkan --}}
                    <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-muted text-muted-foreground ring-8 ring-muted/50">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="15" y1="9" x2="9" y2="15"/>
                            <line x1="9" y1="9" x2="15" y2="15"/>
                        </svg>
                    </div>

                    <h1 class="mt-6 text-2xl font-bold tracking-tight text-foreground">Tagihan ini dibatalkan</h1>
                    <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Tagihan <span class="font-medium text-foreground">{{ $invoice->number }}</span> telah dibatalkan dan tidak memerlukan tindakan lebih lanjut.
                    </p>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                        <a href="{{ route('billing.plans') }}" 
                           class="inline-flex items-center justify-center rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground shadow-sm transition hover:opacity-90">
                            Pilih Paket
                        </a>
                        <a href="{{ route('billing.index') }}" 
                           class="inline-flex items-center justify-center rounded-xl border border-border bg-card px-5 py-3 text-sm font-semibold text-muted-foreground transition hover:bg-muted hover:text-foreground">
                            Kembali ke Ringkasan
                        </a>
                    </div>
                @endif

            </div>
        </div>

    @else

    {{-- ===================== Standalone Full-Page Checkout =====================
         Tata Letak Baru:
         - KIRI ATAS: Paket & Termasuk di Paket Ini
         - KIRI BAWAH: Data Pelanggan / Data Penagihan
         - KANAN ATAS: Ringkasan Biaya, Pilih Metode & Munculnya Pembayaran (QRIS/Bank)
         - KANAN BAWAH (Di bawah Payment): Kirim Bukti Pembayaran
         ========================================================================== --}}
    <div x-data="{ 
            metode: '{{ $metodeAwal }}',
            leftHeight: 0,
            salinTeks(teks, idTarget) {
                navigator.clipboard.writeText(teks).then(() => {
                    this[idTarget] = true;
                    setTimeout(() => { this[idTarget] = false; }, 2000);
                });
            },
            copiedRekening: false,
            copiedTotal: false,
            updateHeight() {
                if (window.innerWidth >= 1024 && this.$refs.leftCol) {
                    this.leftHeight = this.$refs.leftCol.offsetHeight;
                } else {
                    this.leftHeight = 0;
                }
            }
         }" 
         x-init="
            $nextTick(() => { updateHeight(); });
            window.addEventListener('resize', () => { updateHeight(); });
            $watch('metode', val => {
                if (val === 'qris') {
                    $nextTick(() => {
                        if (window.renderQris) window.renderQris();
                    });
                }
            });
         "
         class="space-y-6 sm:space-y-8">

        {{-- Judul Halaman & Status Bar --}}
        <div class="flex flex-col gap-3 border-b border-border/70 pb-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-primary">Tagihan Pembayaran</span>
                    <span class="text-border">&bull;</span>
                    <span class="font-mono text-xs font-medium text-muted-foreground">{{ $invoice->number }}</span>
                </div>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-foreground sm:text-3xl">Selesaikan Langganan</h1>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-500/40 bg-amber-500/10 px-3 py-1 text-xs font-bold text-amber-900 dark:text-amber-200">
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    Menunggu Pembayaran
                </span>
                @if ($invoice->due_at)
                    <span class="text-xs text-muted-foreground">
                        Batas: {{ $invoice->due_at->translatedFormat('j M Y, H:i') }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Grid 2 Kolom --}}
        <div class="grid items-start gap-6 lg:grid-cols-[1.55fr_1fr]">

            {{-- ===================== SISI KIRI ===================== --}}
            <div x-ref="leftCol" class="space-y-6">

                {{-- KIRI 1: Paket & Termasuk di Paket Ini --}}
                <section class="overflow-hidden rounded-2xl border border-border bg-card p-5 shadow-xs transition sm:p-6">
                    <div class="flex items-start justify-between gap-4 border-b border-border/80 pb-4">
                        <div>
                            <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary">
                                {{ $invoice->period === 'yearly' ? 'Tahunan (Hemat 2 Bulan)' : 'Langganan Bulanan' }}
                            </span>
                            <h2 class="mt-2 text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                                Paket {{ $plan->name() }}
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground sm:text-sm">
                                {{ $plan->tagline() }}
                            </p>
                        </div>

                        <div class="text-right">
                            <span class="text-[11px] uppercase tracking-wider text-muted-foreground font-medium">Harga</span>
                            <p class="text-lg font-bold text-foreground sm:text-xl tabular-nums">
                                Rp {{ number_format($invoice->amount, 0, ',', '.') }}
                            </p>
                            <p class="text-[11px] text-muted-foreground">{{ $invoice->periodLabel() }}</p>
                        </div>
                    </div>

                    {{-- Termasuk di paket ini --}}
                    <div class="pt-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-foreground">
                            Termasuk di paket ini:
                        </p>
                        <ul class="mt-3 space-y-2 text-xs sm:text-sm text-muted-foreground">
                            @foreach ($plan->features() as $fitur)
                                <li class="flex items-start gap-2.5">
                                    <span class="grid h-4 w-4 shrink-0 place-items-center rounded-full bg-primary/10 text-primary mt-0.5">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                    </span>
                                    <span class="leading-relaxed">{{ $fitur }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>

                {{-- KIRI 2: Data Pelanggan / Data Penagihan --}}
                <section class="overflow-hidden rounded-2xl border border-border bg-card p-5 shadow-xs transition sm:p-6">
                    <div class="flex items-start gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border/80 pb-3.5">
                                <div>
                                    <h2 class="text-base font-bold text-foreground">Data Pelanggan</h2>
                                    <p class="mt-0.5 text-xs text-muted-foreground">
                                        Informasi penagihan resmi untuk tagihan workspace ini.
                                    </p>
                                </div>
                                @if ($penagihan['lengkap'])
                                    <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                                        Lengkap
                                    </span>
                                @endif
                            </div>

                            @if ($bolehBayar)
                                <form method="POST" action="{{ route('billing.details', $invoice->id) }}" class="mt-4 space-y-3.5">
                                    @csrf
                                    <div>
                                        <label for="billing_name" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">
                                            Nama Lengkap / Perusahaan
                                        </label>
                                        <input id="billing_name" name="billing_name" type="text" required maxlength="120"
                                               value="{{ old('billing_name', $penagihan['nama']) }}"
                                               placeholder="Contoh: PT Flustra Solusi"
                                               class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                    </div>

                                    <div>
                                        <label for="billing_email" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">
                                            Email Penagihan
                                        </label>
                                        <input id="billing_email" name="billing_email" type="email" required maxlength="180"
                                               value="{{ old('billing_email', $penagihan['email']) }}"
                                               placeholder="finance@perusahaan.com"
                                               class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                    </div>

                                    <div>
                                        <label for="billing_phone" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">
                                            Nomor WhatsApp Pengingat <span class="font-normal lowercase text-muted-foreground">(opsional)</span>
                                        </label>
                                        <input id="billing_phone" name="billing_phone" type="tel" maxlength="20" inputmode="tel"
                                               placeholder="081234567890"
                                               value="{{ old('billing_phone', $penagihan['telepon']) }}"
                                               class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                        <p class="mt-1 text-xs text-muted-foreground">
                                            Pengingat tagihan akan dikirim ke nomor ini sebelum masa aktif berakhir.
                                        </p>
                                    </div>

                                    <div class="pt-1">
                                        <button type="submit" 
                                                class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-4 py-2 text-xs font-semibold text-foreground shadow-xs transition hover:bg-muted active:scale-[0.98]">
                                            <svg class="h-3.5 w-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                            <span>Simpan Data Pelanggan</span>
                                        </button>
                                    </div>
                                </form>
                            @else
                                <div class="mt-4 rounded-xl border border-border/80 bg-muted/30 p-3.5 text-sm">
                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                        <div>
                                            <span class="text-muted-foreground">Nama:</span>
                                            <p class="font-medium text-foreground">{{ $penagihan['nama'] }}</p>
                                        </div>
                                        <div>
                                            <span class="text-muted-foreground">Email:</span>
                                            <p class="font-medium text-foreground">{{ $penagihan['email'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="mt-3.5 border-t border-border/80 pt-3 text-xs text-muted-foreground flex justify-between">
                                <span>Workspace Penerima:</span>
                                <span class="font-semibold text-foreground">{{ $currentWorkspace->name }}</span>
                            </div>
                        </div>
                    </div>
                </section>

            </div>

            {{-- ===================== SISI KANAN ===================== --}}
            <div class="lg:sticky lg:top-20">

                {{-- KANAN: SATU KARTU TERPADU (Ringkasan Biaya, Cara Pembayaran & Kirim Bukti Pembayaran) --}}
                <section class="flex flex-col rounded-2xl border border-border bg-card shadow-sm transition overflow-y-auto [scrollbar-width:thin] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-border"
                         :style="leftHeight > 0 ? 'max-height: ' + leftHeight + 'px;' : ''">
                    
                    <div class="p-4 sm:p-5 space-y-4">
                        
                        {{-- Header Ringkasan Biaya --}}
                        <div class="border-b border-border/80 pb-3">
                            <div class="flex items-center justify-between">
                                <h2 class="text-sm font-bold text-foreground sm:text-base">Ringkasan &amp; Pembayaran</h2>
                                <span class="font-mono text-xs font-semibold text-muted-foreground">{{ $invoice->number }}</span>
                            </div>

                            {{-- Rincian Biaya --}}
                            <div class="mt-3 space-y-1.5 text-xs">
                                <div class="flex justify-between text-muted-foreground">
                                    <span>Subtotal Paket ({{ $plan->name() }})</span>
                                    <span class="font-medium text-foreground tabular-nums">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</span>
                                </div>

                                @if ($invoice->tax_amount > 0)
                                    <div class="flex justify-between text-muted-foreground">
                                        <span>PPN {{ rtrim(rtrim(number_format(config('billing.tax_percent'), 2, ',', '.'), '0'), ',') }}%</span>
                                        <span class="font-medium text-foreground tabular-nums">Rp {{ number_format($invoice->tax_amount, 0, ',', '.') }}</span>
                                    </div>
                                @endif

                                @if ($invoice->unique_code > 0)
                                    <div class="flex justify-between text-muted-foreground">
                                        <span>Kode Unik Verifikasi</span>
                                        <span class="font-mono font-bold text-amber-800 dark:text-amber-300 tabular-nums">+Rp {{ number_format($invoice->unique_code, 0, ',', '.') }}</span>
                                    </div>
                                @endif

                                <div class="flex items-baseline justify-between border-t border-border/80 pt-2.5">
                                    <div>
                                        <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Total Pembayaran</span>
                                        @if ($invoice->unique_code > 0)
                                            <p class="text-[10px] text-muted-foreground">Termasuk kode unik</p>
                                        @endif
                                    </div>
                                    <p class="text-xl font-bold tracking-tight text-primary tabular-nums sm:text-2xl">
                                        Rp {{ number_format($invoice->total, 0, ',', '.') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Keadaan jika belum ada metode pembayaran aktif --}}
                        @if ($metode === [])
                            <div class="rounded-xl border border-destructive/20 bg-destructive/10 p-3 text-xs text-destructive">
                                Belum ada metode pembayaran yang aktif. Hubungi tim kami untuk menyelesaikan pembayaran tagihan ini.
                            </div>
                        @endif

                        {{-- PILIH CARA PEMBAYARAN (Di Sisi Kanan) --}}
                        @if (count($metode) > 0)
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    Pilih Cara Pembayaran:
                                </label>
                                <div class="grid {{ count($metode) > 1 ? 'grid-cols-2' : 'grid-cols-1' }} gap-2">
                                    @foreach ($metode as $kunci => $m)
                                        <label class="flex cursor-pointer items-center justify-between gap-2 rounded-xl border p-2.5 transition text-xs select-none"
                                               :class="metode === '{{ $kunci }}' ? 'border-primary bg-primary/5 ring-1 ring-primary font-bold shadow-xs text-foreground' : 'border-border bg-card hover:bg-muted/50 text-muted-foreground'">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <input type="radio" name="metode_bayar" value="{{ $kunci }}" x-model="metode"
                                                       class="h-3.5 w-3.5 text-primary focus:ring-primary border-input">
                                                <span class="truncate font-semibold">{{ $m['label'] }}</span>
                                            </div>
                                            <span class="shrink-0 rounded bg-muted/80 px-1.5 py-0.5 text-[9px] font-medium text-muted-foreground">{{ $m['badge'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Petunjuk: Jika belum memilih metode pembayaran --}}
                        <div x-show="!metode" x-cloak class="rounded-xl border border-dashed border-border bg-muted/20 p-4 text-center text-xs text-muted-foreground">
                            <svg class="mx-auto h-6 w-6 text-muted-foreground/60 mb-1.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <rect width="20" height="14" x="2" y="5" rx="2"/>
                                <line x1="2" y1="10" x2="22" y2="10"/>
                            </svg>
                            <p class="font-medium text-foreground">Silakan pilih cara pembayaran di atas</p>
                            <p class="mt-0.5 text-[11px]">Pilih QRIS atau Transfer bank untuk memunculkan detail pembayaran.</p>
                        </div>

                        {{-- MUNCULNYA PILIHAN PEMBAYARAN (Hanya setelah metode dipilih) --}}
                        
                        {{-- METODE: QRIS --}}
                        @if ($qrisPayload)
                            <div x-show="metode === 'qris'" x-cloak class="space-y-3 border-t border-border/80 pt-3.5">
                                
                                {{-- Panel QR Code --}}
                                <div class="flex flex-col items-center rounded-xl border border-border bg-muted/30 p-3.5 text-center sm:p-4">
                                    {{-- Kotak QR Code (Pasti Persegi 1:1, tidak terdistorsi) --}}
                                    <div class="inline-flex flex-col items-center rounded-xl bg-white p-3 shadow-xs ring-1 ring-black/5" style="width: 184px;">
                                        <canvas data-qris="{{ $qrisPayload }}" width="260" height="260"
                                                class="mx-auto block aspect-square"
                                                style="width: 160px !important; height: 160px !important; max-width: 100%; aspect-ratio: 1 / 1;"></canvas>
                                        <div class="mt-1.5 text-center text-[9px] font-bold tracking-widest text-slate-400 uppercase">
                                            QRIS Standar Nasional
                                        </div>
                                    </div>

                                    {{-- Info Merchant & Nominal --}}
                                    <div class="mt-2.5 inline-flex items-center gap-1 rounded-full bg-background px-2.5 py-0.5 text-[11px] font-medium text-foreground border border-border shadow-xs">
                                        <svg class="h-3 w-3 text-primary shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        <span class="truncate">{{ $merchant }}</span>
                                    </div>

                                    <div class="mt-2">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <p class="text-lg font-bold tracking-tight text-foreground sm:text-xl tabular-nums">
                                                Rp {{ number_format($invoice->total, 0, ',', '.') }}
                                            </p>
                                            <button type="button" 
                                                    @click="salinTeks('{{ $invoice->total }}', 'copiedTotal')"
                                                    class="rounded-md p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                    title="Salin nominal">
                                                <svg x-show="!copiedTotal" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                                <span x-show="copiedTotal" x-cloak class="text-[10px] font-semibold text-primary">Tersalin!</span>
                                            </button>
                                        </div>
                                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                                            Nominal otomatis tertera saat dipindai.
                                        </p>
                                    </div>
                                </div>

                                {{-- Langkah Scan QRIS --}}
                                <div class="rounded-xl border border-border/80 bg-card p-3 text-xs">
                                    <h3 class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Cara Scan QRIS:</h3>
                                    <ol class="mt-1.5 space-y-1 text-[11px] leading-relaxed text-muted-foreground">
                                        <li class="flex items-start gap-1.5">
                                            <span class="font-bold text-primary">1.</span>
                                            <span>Buka aplikasi m-Banking atau e-Wallet (BCA, GoPay, OVO, Dana, dll).</span>
                                        </li>
                                        <li class="flex items-start gap-1.5">
                                            <span class="font-bold text-primary">2.</span>
                                            <span>Pilih menu <strong>Scan / QRIS</strong>, lalu pindai kode QR di atas.</span>
                                        </li>
                                        <li class="flex items-start gap-1.5">
                                            <span class="font-bold text-primary">3.</span>
                                            <span>Periksa nama merchant <strong>{{ $merchant }}</strong> dan konfirmasi bayar.</span>
                                        </li>
                                    </ol>
                                </div>
                            </div>
                        @endif

                        {{-- METODE: Transfer Bank --}}
                        @if ($bank)
                            <div x-show="metode === 'bank'" x-cloak class="space-y-3 border-t border-border/80 pt-3.5">
                                <div class="overflow-hidden rounded-xl border border-border bg-muted/30 p-3.5">
                                    <div class="flex items-center justify-between border-b border-border/80 pb-2.5">
                                        <div>
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Bank Tujuan:</span>
                                            <p class="text-sm font-bold text-foreground">{{ $bank['name'] }}</p>
                                        </div>
                                        <span class="rounded bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary">
                                            Akun Resmi
                                        </span>
                                    </div>

                                    <div class="mt-2.5 space-y-2.5">
                                        {{-- Nomor Rekening --}}
                                        <div>
                                            <span class="text-[11px] text-muted-foreground">Nomor Rekening:</span>
                                            <div class="mt-1 flex items-center justify-between rounded-lg border border-border bg-background px-3 py-2">
                                                <span class="font-mono text-sm font-bold tracking-wider text-foreground sm:text-base">
                                                    {{ $bank['account_number'] }}
                                                </span>
                                                <button type="button" 
                                                        @click="salinTeks('{{ $bank['account_number'] }}', 'copiedRekening')"
                                                        class="inline-flex items-center gap-1 rounded-md border border-border bg-card px-2.5 py-1 text-xs font-semibold text-foreground shadow-xs transition hover:bg-muted active:scale-95">
                                                    <svg x-show="!copiedRekening" class="h-3.5 w-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                                    <svg x-show="copiedRekening" x-cloak class="h-3.5 w-3.5 text-primary" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                                    <span x-text="copiedRekening ? 'Tersalin!' : 'Salin'" class="text-[11px]">Salin</span>
                                                </button>
                                            </div>
                                        </div>

                                        {{-- Atas Nama --}}
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="text-muted-foreground">Atas Nama:</span>
                                            <span class="font-semibold text-foreground">{{ $bank['account_holder'] }}</span>
                                        </div>

                                        {{-- Jumlah Transfer --}}
                                        <div class="border-t border-border/80 pt-2 flex items-center justify-between">
                                            <div>
                                                <span class="text-[11px] text-muted-foreground">Jumlah Transfer:</span>
                                                <p class="text-base font-bold tracking-tight text-primary sm:text-lg tabular-nums">
                                                    Rp {{ number_format($invoice->total, 0, ',', '.') }}
                                                </p>
                                            </div>
                                            <button type="button" 
                                                    @click="salinTeks('{{ $invoice->total }}', 'copiedTotal')"
                                                    class="inline-flex items-center gap-1 rounded-md border border-border bg-card px-2.5 py-1 text-xs font-semibold text-foreground shadow-xs transition hover:bg-muted active:scale-95">
                                                <span x-text="copiedTotal ? 'Tersalin!' : 'Salin Jumlah'" class="text-[11px]">Salin Jumlah</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Panduan Transfer --}}
                                <div class="rounded-xl border border-border/80 bg-card p-3 text-xs">
                                    <h3 class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Panduan Transfer:</h3>
                                    <ol class="mt-1.5 space-y-1 text-[11px] leading-relaxed text-muted-foreground">
                                        <li class="flex items-start gap-1.5">
                                            <span class="font-bold text-primary">1.</span>
                                            <span>Transfer tepat hingga 3 digit terakhir untuk verifikasi otomatis.</span>
                                        </li>
                                        <li class="flex items-start gap-1.5">
                                            <span class="font-bold text-primary">2.</span>
                                            <span>Simpan tangkapan layar bukti transfer yang berhasil.</span>
                                        </li>
                                    </ol>
                                </div>
                            </div>
                        @endif

                        {{-- Peringatan Kode Unik (Hanya jika metode sudah dipilih) --}}
                        @if ($invoice->unique_code > 0 && $metode !== [])
                            <div x-show="metode" x-cloak class="flex gap-2.5 rounded-xl border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-950 dark:text-amber-100 shadow-xs">
                                <svg class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                    <line x1="12" y1="9" x2="12" y2="13"/>
                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                                <div class="leading-relaxed">
                                    <strong class="font-bold text-amber-950 dark:text-white">Bayar tepat sampai angka terakhirnya.</strong>
                                    Tiga digit terakhir (<span class="font-mono font-extrabold underline decoration-amber-500 underline-offset-2 text-amber-950 dark:text-amber-200">{{ str_pad($invoice->unique_code, 3, '0', STR_PAD_LEFT) }}</span>)
                                    adalah kode unik tagihan ini untuk mengenali kiriman dana Anda secara cepat.
                                </div>
                            </div>
                        @endif

                        {{-- ===================== BAGIAN: KIRIM BUKTI PEMBAYARAN (Di Dalam Kartu yang Sama) ===================== --}}
                        @if ($bolehBayar)
                            <div class="border-t border-border/80 pt-4 space-y-3">
                                <div class="flex items-center gap-2">
                                    <span class="grid h-5 w-5 place-items-center rounded-md bg-primary text-[11px] font-bold text-primary-foreground">
                                        ✓
                                    </span>
                                    <h2 class="text-sm font-bold text-foreground sm:text-base">Kirim Bukti Pembayaran</h2>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Setelah pembayaran selesai, lampirkan bukti transfer untuk segera diverifikasi dan langganan diaktifkan.
                                </p>

                                {{-- Jika bukti sudah pernah diunggah --}}
                                @if ($invoice->proof_path)
                                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-primary/30 bg-primary/10 p-2.5 text-xs text-primary">
                                        <div class="flex items-center gap-1.5">
                                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                                            <span class="font-medium">Bukti transfer tersimpan &amp; sedang diverifikasi.</span>
                                        </div>
                                        <a href="{{ route('billing.proof', $invoice->id) }}" target="_blank" 
                                           class="font-semibold underline hover:opacity-80">
                                            Lihat berkas
                                        </a>
                                    </div>
                                @endif

                                <form method="POST" action="{{ route('billing.proof.upload', $invoice->id) }}" enctype="multipart/form-data" class="space-y-3">
                                    @csrf
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">
                                            Pilih Berkas Bukti Transfer
                                        </label>
                                        <input type="file" name="bukti" accept="image/*,.pdf" required
                                               class="block w-full text-xs text-muted-foreground file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-primary transition hover:file:bg-primary/20 file:cursor-pointer">
                                        <p class="mt-1 text-[11px] text-muted-foreground">
                                            Format: JPG, PNG, WEBP, atau PDF (maks. 5 MB).
                                        </p>
                                    </div>

                                    <div>
                                        <button type="submit" 
                                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-semibold text-primary-foreground shadow-sm transition hover:opacity-90 active:scale-[0.98]">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                            <span>{{ $invoice->proof_path ? 'Ganti Berkas Bukti' : 'Kirim Bukti Pembayaran' }}</span>
                                        </button>
                                    </div>
                                </form>

                                {{-- Batalkan Tagihan & Bantuan --}}
                                @if ($invoice->isPending() && $bolehBayar)
                                    <div class="border-t border-border/80 pt-3 flex flex-wrap items-center justify-between gap-2 text-[11px] text-muted-foreground">
                                        <form method="POST" action="{{ route('billing.invoice.cancel', $invoice->id) }}"
                                              onsubmit="return confirm('Apakah Anda yakin ingin membatalkan tagihan ini?')">
                                            @csrf
                                            <button type="submit" class="hover:text-destructive hover:underline">
                                                Batalkan tagihan ini
                                            </button>
                                        </form>

                                        <a href="https://about.flustra.id/#contact" target="_blank" class="hover:text-foreground hover:underline">
                                            Ada Kendala? Hubungi Dukungan
                                        </a>
                                    </div>
                                @endif

                            </div>
                        @endif

                    </div>
                </section>

            </div>

        </div>
    </div>
    @endif
@endsection
