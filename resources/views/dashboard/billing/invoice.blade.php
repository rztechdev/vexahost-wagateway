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
        $daftarBank = (isset($bankAccounts) && $bankAccounts->isNotEmpty()) 
            ? $bankAccounts 
            : (filled($bank['account_number'] ?? null) ? collect([ (object) [
                'id' => 0,
                'bank_name' => $bank['name'] ?? 'Transfer Bank',
                'account_number' => $bank['account_number'],
                'account_holder' => $bank['account_holder'] ?? 'Flustra',
                'type' => 'bank',
                'instructions' => null,
            ]]) : collect());

        $punyaBank = $daftarBank->isNotEmpty();

        $metode = [];
        if ($mayarAvailable ?? false) {
            $metode['mayar'] = [
                'label' => 'Bayar Otomatis (Mayar)',
                'badge' => 'Instan',
                'ket' => 'QRIS, Virtual Account Multi-Bank, & E-Wallet dengan aktivasi otomatis instan',
            ];
        }
        if ($qrisPayload) {
            $metode['qris'] = [
                'label' => 'QRIS (Manual)',
                'badge' => 'Verifikasi Admin',
                'ket' => 'Scan QRIS manual dan konfirmasi atau unggah bukti transfer',
            ];
        }
        if ($punyaBank) {
            $metode['bank'] = [
                'label' => 'Transfer Bank (Manual)',
                'badge' => 'Verifikasi Admin',
                'ket' => 'Transfer via ATM, Mobile Banking, Internet Banking rekening resmi',
            ];
        }
        $metodeAwal = ($mayarAvailable ?? false) ? 'mayar' : ($qrisPayload ? 'qris' : ($punyaBank ? 'bank' : ''));
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
                            <span class="font-medium text-foreground">{{ ($currentWorkspace ?? $invoice->workspace)->name ?? '-' }}</span>
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
            },
            mayarLoading: false,
            mayarUrl: '{{ $mayarPaymentUrl ?? '' }}',
            bayarMayar() {
                if (this.mayarUrl) {
                    window.location.href = this.mayarUrl;
                    return;
                }
                this.mayarLoading = true;
                fetch('{{ route('billing.invoice.mayar', $invoice->id) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    }
                })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'ok' && res.payment_url) {
                        this.mayarUrl = res.payment_url;
                        window.location.href = res.payment_url;
                    } else if (res.status === 'already_paid') {
                        window.location.reload();
                    } else {
                        this.mayarLoading = false;
                        alert(res.message || 'Gagal memuat sesi pembayaran Mayar.');
                    }
                })
                .catch(err => {
                    this.mayarLoading = false;
                    alert('Terjadi kendala jaringan saat menghubungi server pembayaran.');
                });
            },
         }" 
         x-init="
            $nextTick(() => { updateHeight(); });
            window.addEventListener('resize', () => { updateHeight(); });
            if (metode === 'qris') {
                $nextTick(() => {
                    if (window.renderQris) window.renderQris();
                });
            }
            $watch('metode', val => {
                if (val === 'qris') {
                    $nextTick(() => {
                        if (window.renderQris) window.renderQris();
                    });
                }
            });
         "
         class="space-y-6">
        {{-- Tombol Navigasi Kembali --}}
        <div>
            <a href="{{ route('billing.history') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-border bg-card px-3.5 py-2 text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground transition shadow-xs">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Kembali ke Riwayat Tagihan</span>
            </a>
        </div>

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
                                <form method="POST" action="{{ route('billing.details', $invoice->id) }}" data-validasi class="mt-4 space-y-3.5">
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
                                <span class="font-semibold text-foreground">{{ ($currentWorkspace ?? $invoice->workspace)->name ?? '-' }}</span>
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

                                {{-- Dua baris terpisah, bukan satu total.
                                     Pelanggan yang memakai kode referal berhak
                                     tahu berapa yang datang dari kodenya, dan
                                     resellernya berhak tahu itu juga —
                                     satu angka gabungan membuat keduanya
                                     mustahil diperiksa. --}}
                                @if ($invoice->intro_discount_amount > 0)
                                    <div class="flex justify-between text-muted-foreground">
                                        <span>Promo pembelian pertama</span>
                                        <span class="font-medium text-primary tabular-nums">−Rp {{ number_format($invoice->intro_discount_amount, 0, ',', '.') }}</span>
                                    </div>
                                @endif

                                @if ($invoice->referralDiscount() > 0)
                                    <div class="flex justify-between text-muted-foreground">
                                        <span>Potongan referal{{ $invoice->referralCode ? ' ('.$invoice->referralCode->code.')' : '' }}</span>
                                        <span class="font-medium text-primary tabular-nums">−Rp {{ number_format($invoice->referralDiscount(), 0, ',', '.') }}</span>
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

                        {{-- PILIH CARA PEMBAYARAN (Hanya jika ada lebih dari 1 metode pembayaran) --}}
                        @if (count($metode) > 1)
                            <div>
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                    Pilih Cara Pembayaran:
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach ($metode as $kunci => $m)
                                        <label class="flex cursor-pointer items-center justify-between gap-2 rounded-xl border p-2.5 transition text-xs select-none"
                                               :class="metode === '{{ $kunci }}' ? 'border-primary bg-primary/5 ring-1 ring-primary font-bold shadow-xs text-foreground' : 'border-border bg-card hover:bg-muted/50 text-muted-foreground'">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <input type="radio" name="metode_bayar" value="{{ $kunci }}" x-model="metode"
                                                       @checked($metodeAwal === $kunci)
                                                       class="h-3.5 w-3.5 text-primary focus:ring-primary border-input">
                                                <span class="truncate font-semibold">{{ $m['label'] }}</span>
                                            </div>
                                            <span class="shrink-0 rounded bg-muted/80 px-1.5 py-0.5 text-[9px] font-medium text-muted-foreground">{{ $m['badge'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Petunjuk: Jika belum memilih metode pembayaran --}}
                            <div x-show="!metode" @if($metodeAwal) x-cloak @endif class="rounded-xl border border-dashed border-border bg-muted/20 p-4 text-center text-xs text-muted-foreground">
                                <svg class="mx-auto h-6 w-6 text-muted-foreground/60 mb-1.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <rect width="20" height="14" x="2" y="5" rx="2"/>
                                    <line x1="2" y1="10" x2="22" y2="10"/>
                                </svg>
                                <p class="font-medium text-foreground">Silakan pilih cara pembayaran di atas</p>
                                <p class="mt-0.5 text-[11px]">Pilih QRIS atau Transfer bank untuk memunculkan detail pembayaran.</p>
                            </div>
                        @endif

                        {{-- MUNCULNYA PILIHAN PEMBAYARAN (Hanya setelah metode dipilih) --}}
                        
                        {{-- METODE: Mayar (Otomatis) --}}
                        @if ($mayarAvailable ?? false)
                            <div x-show="metode === 'mayar'" @if($metodeAwal !== 'mayar') x-cloak @endif class="space-y-4 border-t border-border/80 pt-3.5">
                                <div class="rounded-xl border border-primary/20 bg-primary/5 p-4 text-xs text-foreground space-y-3">
                                    <div class="flex items-start gap-2.5">
                                        <div class="rounded-lg bg-primary/10 p-2 text-primary shrink-0">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                                            </svg>
                                        </div>
                                        <div class="space-y-1">
                                            <h4 class="font-bold text-foreground">Pembayaran Instan &amp; Otomatis</h4>
                                            <p class="text-muted-foreground text-[11px] leading-relaxed">
                                                Dukung QRIS (BCA, Mandiri, GoPay, OVO, Dana, ShopeePay), Virtual Account Bank otomatis, serta gerai retail.
                                                Layanan aktif otomatis dalam hitungan detik setelah pembayaran berhasil.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between rounded-lg border border-border/60 bg-background/80 px-3.5 py-2.5 text-xs">
                                        <span class="text-muted-foreground">Total yang akan dibayar:</span>
                                        <span class="font-bold text-primary text-sm">Rp {{ number_format($invoice->total, 0, ',', '.') }}</span>
                                    </div>

                                    <div class="pt-1">
                                        <button type="button" 
                                                @click="bayarMayar()"
                                                :disabled="mayarLoading"
                                                class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3.5 text-xs sm:text-sm font-bold text-primary-foreground shadow-md hover:opacity-95 active:scale-[0.98] transition-all disabled:opacity-50">
                                            <template x-if="mayarLoading">
                                                <svg class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                                </svg>
                                            </template>
                                            <span x-text="mayarLoading ? 'Membuka Pembayaran...' : 'Pilih Pembayaran'">Pilih Pembayaran</span>
                                            <svg x-show="!mayarLoading" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                                        </button>
                                    </div>

                                    {{-- Bank & Saluran Pembayaran (Tanpa Background) --}}
                                    <div class="pt-2 border-t border-border/40 space-y-2">
                                        <p class="text-[10px] text-center font-medium text-muted-foreground">
                                            Didukung berbagai pilihan pembayaran resmi:
                                        </p>
                                        <div class="flex flex-wrap items-center justify-center gap-x-3.5 gap-y-2 px-1">
                                            @php
                                                $daftarMetodeLanding = [
                                                    ['label' => 'QRIS', 'file' => 'qris.svg'],
                                                    ['label' => 'Bank BCA', 'file' => 'bca.svg'],
                                                    ['label' => 'Bank Mandiri', 'file' => 'mandiri.svg'],
                                                    ['label' => 'Bank BNI', 'file' => 'bni.svg'],
                                                    ['label' => 'Bank BRI', 'file' => 'bri.svg'],
                                                    ['label' => 'Bank Permata', 'file' => 'permata.svg'],
                                                    ['label' => 'Bank BSI', 'file' => 'bsi.svg'],
                                                    ['label' => 'CIMB Niaga', 'file' => 'cimb.svg'],
                                                    ['label' => 'Bank Sahabat Sampoerna', 'file' => 'bss.svg'],
                                                    ['label' => 'OVO', 'file' => 'ovo.svg'],
                                                    ['label' => 'ShopeePay', 'file' => 'shopeepay.svg'],
                                                    ['label' => 'AstraPay', 'file' => 'astrapay.svg'],
                                                    ['label' => 'Indomaret', 'file' => 'indomaret.svg'],
                                                    ['label' => 'Alfamart', 'file' => 'alfamart.svg'],
                                                    ['label' => 'Akulaku PayLater', 'file' => 'akulaku.svg'],
                                                ];
                                            @endphp
                                            @foreach ($daftarMetodeLanding as $metodeItem)
                                                <img src="{{ asset('images/payments/' . $metodeItem['file']) }}"
                                                     alt="{{ $metodeItem['label'] }}"
                                                     title="{{ $metodeItem['label'] }}"
                                                     loading="lazy"
                                                     class="h-3.5 sm:h-4 w-auto max-w-[58px] object-contain opacity-75 hover:opacity-100 transition-opacity dark:brightness-125">
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- METODE: QRIS --}}
                        @if ($qrisPayload)
                            <div x-show="metode === 'qris'" @if($metodeAwal !== 'qris') x-cloak @endif class="space-y-3 border-t border-border/80 pt-3.5">
                                
                                {{-- Panel QR Code (HANYA QRIS SAJA) --}}
                                <div class="flex flex-col items-center justify-center rounded-xl border border-border bg-muted/30 p-4 text-center">
                                    <div class="inline-flex items-center justify-center rounded-xl bg-white p-3 shadow-xs ring-1 ring-black/5">
                                        <canvas data-qris="{{ $qrisPayload }}" width="260" height="260"
                                                class="mx-auto block aspect-square"
                                                style="width: 180px !important; height: 180px !important; max-width: 100%; aspect-ratio: 1 / 1;"></canvas>
                                    </div>

                                    <p id="qris-fallback-msg" class="hidden mt-2 text-xs text-destructive font-medium">
                                        Kode QR gagal dimuat. Silakan muat ulang halaman atau pilih transfer bank di atas.
                                    </p>
                                </div>
                            </div>
                        @endif

                        {{-- METODE: Transfer Bank / VA --}}
                        @if ($punyaBank)
                            <div x-show="metode === 'bank'" @if($metodeAwal !== 'bank') x-cloak @endif 
                                 x-data="{ bankIndex: 0 }" 
                                 class="space-y-3 border-t border-border/80 pt-3.5">
                                
                                {{-- Jika ada lebih dari 1 rekening/VA, tampilkan pemilih rekening --}}
                                @if ($daftarBank->count() > 1)
                                    <div>
                                        <label class="mb-1.5 block text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                            Pilih Bank / Rekening Tujuan:
                                        </label>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ($daftarBank as $idx => $item)
                                                <button type="button" @click="bankIndex = {{ $idx }}"
                                                        class="rounded-lg px-2.5 py-1.5 text-xs font-semibold transition border select-none flex items-center gap-1.5"
                                                        :class="bankIndex === {{ $idx }} ? 'border-primary bg-primary/10 text-primary ring-1 ring-primary shadow-xs' : 'border-border bg-card text-muted-foreground hover:bg-muted/80'">
                                                    <span>{{ $item->bank_name }}</span>
                                                    @if(isset($item->type) && $item->type === 'va')
                                                        <span class="rounded bg-purple-500/20 px-1 py-0.2 text-[9px] font-bold text-purple-700 dark:text-purple-300">VA</span>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @foreach ($daftarBank as $idx => $item)
                                    <div x-show="bankIndex === {{ $idx }}" @if($idx !== 0) x-cloak @endif class="space-y-3">
                                        <div class="overflow-hidden rounded-xl border border-border bg-muted/30 p-3.5">
                                            <div class="flex items-center justify-between border-b border-border/80 pb-2.5">
                                                <div>
                                                    <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Transfer bank / Rekening Tujuan:</span>
                                                    <p class="text-sm font-bold text-foreground">{{ $item->bank_name }}</p>
                                                </div>
                                                <span class="rounded px-2 py-0.5 text-[10px] font-semibold {{ (isset($item->type) && $item->type === 'va') ? 'bg-purple-500/10 text-purple-600 dark:text-purple-400' : 'bg-primary/10 text-primary' }}">
                                                    {{ (isset($item->type) && $item->type === 'va') ? 'Virtual Account' : 'Akun Resmi' }}
                                                </span>
                                            </div>

                                            <div class="mt-2.5 space-y-2.5">
                                                {{-- Nomor Rekening / VA --}}
                                                <div>
                                                    <span class="text-[11px] text-muted-foreground">
                                                        {{ (isset($item->type) && $item->type === 'va') ? 'Nomor Virtual Account:' : 'Nomor Rekening:' }}
                                                    </span>
                                                    <div class="mt-1 flex items-center justify-between rounded-lg border border-border bg-background px-3 py-2">
                                                        <span class="font-mono text-sm font-bold tracking-wider text-foreground sm:text-base">
                                                            {{ $item->account_number }}
                                                        </span>
                                                        <button type="button" 
                                                                @click="salinTeks('{{ $item->account_number }}', 'copiedRekening')"
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
                                                    <span class="font-semibold text-foreground">{{ $item->account_holder }}</span>
                                                </div>

                                                @if (filled($item->instructions ?? null))
                                                    <div class="rounded-lg bg-background/80 p-2 text-[11px] text-muted-foreground border border-border/60">
                                                        {{ $item->instructions }}
                                                    </div>
                                                @endif

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
                                    </div>
                                @endforeach

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

                        {{-- Peringatan Kode Unik (Hanya jika metode manual dipilih) --}}
                        @if ($invoice->unique_code > 0 && $metode !== [])
                            <div x-show="metode && metode !== 'mayar'" @if(!$metodeAwal || $metodeAwal === 'mayar') x-cloak @endif class="flex gap-2.5 rounded-xl border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-950 dark:text-amber-100 shadow-xs">
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

                        {{-- ===================== BAGIAN: VERIFIKASI PEMBAYARAN (Alur Instan ala Payment Gateway) ===================== --}}
                        @if ($bolehBayar)
                            <div class="border-t border-border/80 pt-4 space-y-3.5">
                                {{-- Jika tagihan sudah pernah dikonfirmasi pelanggan dan sedang diperiksa --}}
                                @if ($invoice->isAwaitingVerification())
                                    <div class="rounded-xl border border-primary/30 bg-primary/10 p-3 space-y-2">
                                        <div class="flex items-center gap-2 text-xs font-semibold text-primary">
                                            <span class="relative flex h-2 w-2">
                                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-60"></span>
                                                <span class="relative inline-flex h-2 w-2 rounded-full bg-primary"></span>
                                            </span>
                                            <span>Pembayaran Anda sedang dalam proses verifikasi</span>
                                        </div>
                                        <p class="text-[11px] text-muted-foreground">
                                            Sistem sedang memeriksa mutasi pembayaran Anda secara otomatis.
                                        </p>
                                        <a href="{{ route('billing.verifying', $invoice->id) }}" 
                                           class="inline-flex items-center justify-center gap-1.5 w-full rounded-lg bg-primary px-3 py-2 text-xs font-bold text-primary-foreground shadow-xs transition hover:opacity-90">
                                            <span>Buka Layar Status Verifikasi</span>
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                                        </a>
                                    </div>
                                @else
                                    {{-- Form Konfirmasi "Saya Sudah Bayar" (Hanya untuk metode manual) --}}
                                    <div x-show="metode !== 'mayar'" class="space-y-3.5">
                                        <div class="flex items-center gap-2">
                                            <span class="grid h-5 w-5 place-items-center rounded-md bg-primary text-[11px] font-bold text-primary-foreground">
                                                ✓
                                            </span>
                                            <h2 class="text-sm font-bold text-foreground sm:text-base">Konfirmasi Pembayaran</h2>
                                        </div>
                                        <p class="text-xs leading-relaxed text-muted-foreground">
                                            Selesaikan transfer atau scan QRIS di atas sesuai nominal tagihan, lalu tekan tombol di bawah untuk verifikasi transaksi Anda.
                                        </p>

                                        <form method="POST" action="{{ route('billing.invoice.confirm', $invoice->id) }}" class="space-y-2.5">
                                            @csrf
                                            <input type="hidden" name="metode_bayar" :value="metode">

                                            <button type="submit" 
                                                    class="group relative inline-flex w-full items-center justify-center gap-2 overflow-hidden rounded-xl bg-primary px-4 py-3 text-sm font-bold text-primary-foreground shadow-md transition-all hover:opacity-95 hover:shadow-lg active:scale-[0.98]">
                                                <svg class="h-4 w-4 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                    <path d="M20 6 9 17l-5-5"/>
                                                </svg>
                                                <span>Saya Sudah Bayar</span>
                                            </button>

                                            <div class="flex items-center justify-center gap-1.5 text-[11px] text-muted-foreground text-center">
                                                <svg class="h-3.5 w-3.5 text-primary/80" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                                </svg>
                                                <span>Verifikasi pembayaran diproses otomatis dan aman</span>
                                            </div>
                                        </form>
                                    </div>
                                @endif

                                {{-- Batalkan Tagihan & Bantuan WhatsApp --}}
                                @if ($invoice->isPending() && $bolehBayar)
                                    <div class="border-t border-border/80 pt-3 flex flex-wrap items-center justify-between gap-2 text-[11px] text-muted-foreground">
                                        @if (! $invoice->isAwaitingVerification())
                                            <form method="POST" action="{{ route('billing.invoice.cancel', $invoice->id) }}"
                                                  data-konfirmasi="Apakah Anda yakin ingin membatalkan tagihan ini?">
                                                @csrf
                                                <button type="submit" class="hover:text-destructive hover:underline">
                                                    Batalkan tagihan ini
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-[10px] text-muted-foreground">Tagihan dalam proses verifikasi</span>
                                        @endif

                                        <a href="https://wa.me/6282318280376?text={{ rawurlencode('Halo Admin Flustra, saya ingin menanyakan perihal tagihan ' . $invoice->number . ' sebesar Rp ' . number_format($invoice->total, 0, ',', '.') . '. Mohon bantuannya.') }}"
                                           target="_blank" class="font-medium text-primary hover:underline inline-flex items-center gap-1">
                                            <span>Butuh Bantuan? Hubungi Admin</span>
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

@if ($qrisPayload)
    @push('scripts')
        <script src="{{ asset('js/qrcode.min.js') }}"></script>
        <script>
            (function() {
                var maxAttempts = 50; // 5 detik

                function renderQris(attempt) {
                    attempt = typeof attempt === 'number' ? attempt : 0;
                    var canvases = document.querySelectorAll('canvas[data-qris]');
                    if (!canvases.length) return;
                    var fallbackMsg = document.getElementById('qris-fallback-msg');

                    if (typeof window.QRCode === 'undefined' || !window.QRCode.toCanvas) {
                        if (attempt >= maxAttempts) {
                            console.error('Pustaka QRCode tidak dapat dimuat.');
                            if (fallbackMsg) fallbackMsg.classList.remove('hidden');
                            return;
                        }
                        setTimeout(function() { renderQris(attempt + 1); }, 100);
                        return;
                    }

                    canvases.forEach(function(el) {
                        var payload = el.getAttribute('data-qris');
                        if (!payload) return;
                        window.QRCode.toCanvas(el, payload, {
                            width: 260,
                            margin: 1,
                            color: {
                                dark: '#000000',
                                light: '#ffffff'
                            }
                        }, function(error) {
                            if (error) {
                                console.error('Gagal merender QRIS:', error);
                                if (fallbackMsg) fallbackMsg.classList.remove('hidden');
                            } else {
                                el.style.width = '160px';
                                el.style.height = '160px';
                                if (fallbackMsg) fallbackMsg.classList.add('hidden');
                            }
                        });
                    });
                }

                window.renderQris = renderQris;

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', renderQris);
                } else {
                    renderQris();
                }
            })();
        </script>
    @endpush
@endif
