@extends('layouts.admin')
@section('title', 'Metode Bayar & Paket')

@section('content')
<div class="space-y-6" x-data="{ 
    tab: '{{ request('tab', 'qris_bank') }}',
    modalTambahBank: false,
    modalEditBank: false,
    editBankData: { id: '', bank_name: '', account_number: '', account_holder: '', type: 'bank', instructions: '', is_active: true, sort_order: 0 }
}">
    {{-- Header Halaman --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-foreground sm:text-3xl">
                Metode Bayar &amp; Paket
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Kelola QRIS, rekening bank, payment gateway, dan harga paket langsung dari dashboard tanpa menyentuh .env.
            </p>
        </div>

        {{-- Navigasi Tab --}}
        <div class="flex rounded-xl border border-border bg-muted/40 p-1 select-none">
            <button type="button" @click="tab = 'qris_bank'" 
                    class="rounded-lg px-3.5 py-1.5 text-xs font-semibold transition"
                    :class="tab === 'qris_bank' ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'">
                QRIS &amp; Rekening / VA
            </button>
            <button type="button" @click="tab = 'gateways'" 
                    class="rounded-lg px-3.5 py-1.5 text-xs font-semibold transition"
                    :class="tab === 'gateways' ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'">
                Payment Gateways
            </button>
            <button type="button" @click="tab = 'harga'" 
                    class="rounded-lg px-3.5 py-1.5 text-xs font-semibold transition"
                    :class="tab === 'harga' ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'">
                Harga Paket
            </button>
        </div>
    </div>

    {{-- =========================================================================
         TAB 1: QRIS & REKENING BANK / VA
         ========================================================================= --}}
    <div x-show="tab === 'qris_bank'" class="space-y-6">
        {{-- Bagian QRIS Dinamis --}}
        <div class="rounded-2xl border border-border bg-card p-5 shadow-xs sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/80 pb-4">
                <div>
                    <h2 class="text-base font-bold text-foreground sm:text-lg">Konfigurasi QRIS Dinamis</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Payload QRIS statis merchant akan dimodifikasi secara dinamis per tagihan dengan nominal yang pas.
                    </p>
                </div>
                @if ($qrisValid)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                        Payload Valid &amp; Aktif
                    </span>
                @elseif (filled($qrisPayload))
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-500/10 px-3 py-1 text-xs font-semibold text-rose-600 dark:text-rose-400">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        Format / CRC Tidak Valid
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground">
                        Belum Dikonfigurasi
                    </span>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.payment-settings.qris') }}" class="mt-5 space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="payload" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Payload QRIS Merchant (EMVCo String)
                        </label>
                        <textarea id="payload" name="payload" rows="3"
                                  placeholder="00020101021126670016ID.CO.QRIS.WWW..."
                                  class="w-full rounded-xl border border-input bg-background px-3.5 py-2.5 font-mono text-xs text-foreground transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">{{ old('payload', $qrisPayload) }}</textarea>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Salin teks QRIS statis utuh dari penyedia Anda. Sistem akan memeriksa keabsahan CRC tag 63 secara otomatis sebelum disimpan.
                        </p>
                    </div>

                    <div>
                        <label for="merchant" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Nama Merchant Tampilan
                        </label>
                        <input id="merchant" name="merchant" type="text" maxlength="100"
                               value="{{ old('merchant', $qrisMerchant) }}"
                               placeholder="VexaHost WA Gateway"
                               class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-xs font-semibold text-primary-foreground shadow-xs transition hover:opacity-90 active:scale-95">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <span>Simpan Pengaturan QRIS</span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Bagian Daftar Rekening Bank & Virtual Account --}}
        <div class="rounded-2xl border border-border bg-card p-5 shadow-xs sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-border/80 pb-4">
                <div>
                    <h2 class="text-base font-bold text-foreground sm:text-lg">Rekening Bank &amp; Virtual Account</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Rekening bank atau Virtual Account yang aktif di sini akan <strong>pasti muncul di halaman checkout tagihan</strong> pelanggan.
                    </p>
                </div>
                <button type="button" @click="modalTambahBank = true"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground shadow-xs transition hover:opacity-90 active:scale-95">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span>Tambah Rekening / VA</span>
                </button>
            </div>

            @if ($bankAccounts->isEmpty())
                <div class="my-6 rounded-xl border border-dashed border-border bg-muted/20 p-6 text-center text-xs text-muted-foreground">
                    <svg class="mx-auto h-8 w-8 text-muted-foreground/60 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>
                    </svg>
                    <p class="font-medium text-foreground">Belum ada rekening bank yang tersimpan di database</p>
                    <p class="mt-0.5">
                        Klik tombol &quot;Tambah Rekening / VA&quot; di atas untuk menambahkan rekening bank atau Virtual Account tujuan transfer.
                    </p>
                </div>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-xs text-foreground">
                        <thead class="border-b border-border text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                            <tr>
                                <th class="py-3 px-3">Bank / Provider</th>
                                <th class="py-3 px-3">Nomor Rekening / VA</th>
                                <th class="py-3 px-3">Atas Nama</th>
                                <th class="py-3 px-3">Tipe</th>
                                <th class="py-3 px-3">Status</th>
                                <th class="py-3 px-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            @foreach ($bankAccounts as $b)
                                <tr class="transition hover:bg-muted/30">
                                    <td class="py-3 px-3 font-semibold">{{ $b->bank_name }}</td>
                                    <td class="py-3 px-3 font-mono font-bold text-foreground">{{ $b->account_number }}</td>
                                    <td class="py-3 px-3 text-muted-foreground">{{ $b->account_holder }}</td>
                                    <td class="py-3 px-3">
                                        <span class="rounded px-2 py-0.5 text-[10px] font-semibold {{ $b->isVirtualAccount() ? 'bg-purple-500/10 text-purple-600 dark:text-purple-400' : 'bg-blue-500/10 text-blue-600 dark:text-blue-400' }}">
                                            {{ $b->typeLabel() }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <form method="POST" action="{{ route('admin.payment-settings.bank.toggle', $b->id) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-semibold transition {{ $b->is_active ? 'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20' : 'bg-muted text-muted-foreground hover:bg-muted/80' }}">
                                                <span class="h-1.5 w-1.5 rounded-full {{ $b->is_active ? 'bg-emerald-500' : 'bg-muted-foreground' }}"></span>
                                                {{ $b->is_active ? 'Aktif di Checkout' : 'Nonaktif' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button type="button" 
                                                    @click="editBankData = {
                                                        id: {{ $b->id }},
                                                        bank_name: '{{ addslashes($b->bank_name) }}',
                                                        account_number: '{{ addslashes($b->account_number) }}',
                                                        account_holder: '{{ addslashes($b->account_holder) }}',
                                                        type: '{{ $b->type }}',
                                                        instructions: '{{ addslashes($b->instructions ?? '') }}',
                                                        is_active: {{ $b->is_active ? 'true' : 'false' }},
                                                        sort_order: {{ $b->sort_order }}
                                                    }; modalEditBank = true;"
                                                    class="rounded-lg p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            </button>
                                            <form method="POST" action="{{ route('admin.payment-settings.bank.destroy', $b->id) }}"
                                                  data-konfirmasi="Hapus rekening {{ $b->bank_name }} ({{ $b->account_number }}) dari daftar pembayaran?"
                                                  data-konfirmasi-judul="Hapus Rekening Bank"
                                                  data-konfirmasi-ya="Ya, Hapus Rekening">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg p-1.5 text-rose-500 transition hover:bg-rose-500/10">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- =========================================================================
         TAB 2: PAYMENT GATEWAYS (Xendit, iPaymu, Midtrans, DOKU)
         ========================================================================= --}}
    <div x-show="tab === 'gateways'" class="space-y-6" style="display: none;">
        <div class="rounded-xl border border-primary/20 bg-primary/5 p-4 text-xs text-foreground">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 shrink-0 text-primary mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <div class="space-y-1">
                    <p class="font-bold">Konfigurasi Gateway Siap Pakai</p>
                    <p class="text-muted-foreground leading-relaxed">
                        Anda dapat mengisi API Key, Base URL, dan secret token seluruh gateway dari sekarang. Saat akun payment gateway Anda telah disetujui, aktifkan switch "Aktifkan Gateway" untuk mulai menerima pembayaran otomatis.
                    </p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.payment-settings.gateways') }}" class="space-y-6">
            @csrf

            {{-- 0. MAYAR.ID (GATEWAY UTAMA / AKTIF) --}}
            <div class="rounded-2xl border border-primary/40 bg-card p-5 shadow-xs sm:p-6 space-y-4 ring-1 ring-primary/20">
                <div class="flex items-center justify-between border-b border-border/80 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="grid h-10 w-10 place-items-center rounded-xl bg-primary/10 text-primary font-bold text-sm">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-base font-bold text-foreground">Mayar.id (Gateway Utama)</h2>
                                <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ filled($gateways['mayar']['api_key']) ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-muted text-muted-foreground' }}">
                                    {{ filled($gateways['mayar']['api_key']) ? 'Tersedia' : 'Belum Terhubung' }}
                                </span>
                            </div>
                            <p class="text-xs text-muted-foreground">Mendukung QRIS, Virtual Account Multi-Bank (BCA, Mandiri, BRI, BNI, Permata, dll), E-Wallet &amp; Verifikasi Otomatis 24/7</p>
                        </div>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center" title="Aktifkan / Nonaktifkan Gateway Mayar">
                        <input type="checkbox" name="mayar_active" value="1" @checked($gateways['mayar']['is_active']) class="peer sr-only">
                        <div class="peer h-6 w-11 rounded-full bg-input peer-checked:bg-primary after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full"></div>
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            API Key Mayar (JWT Token)
                        </label>
                        <input type="password" name="mayar_api_key" value="{{ $gateways['mayar']['api_key'] }}" placeholder="eyJhbGciOiJSUzI1NiIsInR5cCI6..." class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-xs font-mono text-foreground transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <p class="mt-1 text-xs text-muted-foreground">
                            Dapatkan di <strong>Dashboard Mayar &gt; Integrasi &gt; API Keys</strong>. Nilai di sini akan menimpa nilai dari berkas <code>.env</code>.
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Webhook Token / Secret (Opsional)
                        </label>
                        <input type="text" name="mayar_webhook_token" value="{{ $gateways['mayar']['webhook_token'] }}" placeholder="Masukkan secret webhook jika ada" class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm font-mono text-foreground transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <p class="mt-1 text-xs text-muted-foreground">
                            Token atau secret pemverifikasi tanda tangan callback webhook dari Mayar.
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            API URL / Base URL
                        </label>
                        <input type="text" name="mayar_api_url" value="{{ $gateways['mayar']['api_url'] }}" placeholder="https://api.mayar.id/hl/v2" class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <p class="mt-1 text-xs text-muted-foreground">
                            Default: <code>https://api.mayar.id/hl/v2</code>.
                        </p>
                    </div>
                </div>

                {{-- Webhook Callback Info --}}
                <div class="rounded-xl border border-border/80 bg-muted/30 p-3 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="truncate">
                        <span class="font-medium text-muted-foreground">URL Webhook Notifikasi:</span>
                        <code class="ml-1.5 font-mono text-primary font-semibold select-all">{{ url('/api/webhooks/mayar') }}</code>
                    </div>
                    <span class="text-[11px] text-muted-foreground shrink-0">Event: <code>payment.received</code></span>
                </div>
            </div>

            {{-- 1. MIDTRANS --}}
            <div class="rounded-2xl border border-border bg-card p-5 shadow-xs sm:p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-border/80 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="grid h-10 w-10 place-items-center rounded-xl bg-blue-500/10 text-blue-600 font-bold text-sm">
                            M
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-foreground">Midtrans (Snap / Core API)</h2>
                            <p class="text-xs text-muted-foreground">Mendukung GoPay, ShopeePay, Virtual Account semua bank &amp; QRIS</p>
                        </div>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input type="checkbox" name="midtrans_active" value="1" @checked($gateways['midtrans']['is_active']) class="peer sr-only">
                        <div class="peer h-6 w-11 rounded-full bg-input peer-checked:bg-primary after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full"></div>
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Environment</label>
                        <select name="midtrans_environment" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                            <option value="sandbox" @selected($gateways['midtrans']['environment'] === 'sandbox')>Sandbox (Uji Coba)</option>
                            <option value="production" @selected($gateways['midtrans']['environment'] === 'production')>Production (Live)</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Merchant ID</label>
                        <input type="text" name="midtrans_merchant_id" value="{{ $gateways['midtrans']['merchant_id'] }}" placeholder="G123456789" class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Client Key</label>
                        <input type="text" name="midtrans_client_key" value="{{ $gateways['midtrans']['client_key'] }}" placeholder="SB-Mid-client-..." class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm font-mono text-foreground transition focus:border-primary focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Server Key</label>
                        <input type="password" name="midtrans_server_key" value="{{ $gateways['midtrans']['server_key'] }}" placeholder="SB-Mid-server-..." class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm font-mono text-foreground transition focus:border-primary focus:outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Snap URL / Base URL</label>
                        <input type="text" name="midtrans_snap_url" value="{{ $gateways['midtrans']['snap_url'] }}" placeholder="https://app.sandbox.midtrans.com/snap/v1" class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                    </div>
                </div>

                {{-- Webhook Callback Info --}}
                <div class="rounded-xl border border-border/80 bg-muted/30 p-3 text-xs flex items-center justify-between gap-2">
                    <div class="truncate">
                        <span class="font-medium text-muted-foreground">URL Notifikasi Webhook:</span>
                        <code class="ml-1.5 font-mono text-foreground select-all">{{ url('/webhooks/payment/midtrans') }}</code>
                    </div>
                </div>
            </div>

            {{-- 2. XENDIT --}}
            <div class="rounded-2xl border border-border bg-card p-5 shadow-xs sm:p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-border/80 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="grid h-10 w-10 place-items-center rounded-xl bg-indigo-500/10 text-indigo-600 font-bold text-sm">
                            X
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-foreground">Xendit</h2>
                            <p class="text-xs text-muted-foreground">Mendukung Invoice Checkout, VA BCA, Mandiri, BRI, QRIS, e-Wallet</p>
                        </div>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input type="checkbox" name="xendit_active" value="1" @checked($gateways['xendit']['is_active']) class="peer sr-only">
                        <div class="peer h-6 w-11 rounded-full bg-input peer-checked:bg-primary after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full"></div>
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Environment</label>
                        <select name="xendit_environment" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                            <option value="sandbox" @selected($gateways['xendit']['environment'] === 'sandbox')>Development / Sandbox</option>
                            <option value="production" @selected($gateways['xendit']['environment'] === 'production')>Production</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Webhook Verification Token</label>
                        <input type="text" name="xendit_webhook_token" value="{{ $gateways['xendit']['webhook_token'] }}" placeholder="xnd_webhook_verification_token..." class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm font-mono text-foreground transition focus:border-primary focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Secret API Key</label>
                        <input type="password" name="xendit_secret_key" value="{{ $gateways['xendit']['secret_key'] }}" placeholder="xnd_development_..." class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm font-mono text-foreground transition focus:border-primary focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Public API Key</label>
                        <input type="text" name="xendit_public_key" value="{{ $gateways['xendit']['public_key'] }}" placeholder="xnd_public_development_..." class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm font-mono text-foreground transition focus:border-primary focus:outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Base URL</label>
                        <input type="text" name="xendit_base_url" value="{{ $gateways['xendit']['base_url'] }}" placeholder="https://api.xendit.co" class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                    </div>
                </div>

                {{-- Webhook Callback Info --}}
                <div class="rounded-xl border border-border/80 bg-muted/30 p-3 text-xs flex items-center justify-between gap-2">
                    <div class="truncate">
                        <span class="font-medium text-muted-foreground">URL Webhook Xendit:</span>
                        <code class="ml-1.5 font-mono text-foreground select-all">{{ url('/webhooks/payment/xendit') }}</code>
                    </div>
                </div>
            </div>

            {{-- 3. IPAYMU (IPAYKU) --}}
            <div class="rounded-2xl border border-border bg-card p-5 shadow-xs sm:p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-border/80 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="grid h-10 w-10 place-items-center rounded-xl bg-amber-500/10 text-amber-600 font-bold text-sm">
                            iP
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-foreground">iPaymu (iPayku)</h2>
                            <p class="text-xs text-muted-foreground">Payment gateway lokal terpadu untuk QRIS, VA, dan transfer bank</p>
                        </div>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input type="checkbox" name="ipaymu_active" value="1" @checked($gateways['ipaymu']['is_active']) class="peer sr-only">
                        <div class="peer h-6 w-11 rounded-full bg-input peer-checked:bg-primary after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full"></div>
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Environment</label>
                        <select name="ipaymu_environment" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                            <option value="sandbox" @selected($gateways['ipaymu']['environment'] === 'sandbox')>Sandbox (sandbox.ipaymu.com)</option>
                            <option value="production" @selected($gateways['ipaymu']['environment'] === 'production')>Production (my.ipaymu.com)</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Nomor Virtual Account / Akun</label>
                        <input type="text" name="ipaymu_va_number" value="{{ $gateways['ipaymu']['va_number'] }}" placeholder="117900..." class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">API Key</label>
                        <input type="password" name="ipaymu_api_key" value="{{ $gateways['ipaymu']['api_key'] }}" placeholder="QWERTYUIOP..." class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm font-mono text-foreground transition focus:border-primary focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Base URL</label>
                        <input type="text" name="ipaymu_base_url" value="{{ $gateways['ipaymu']['base_url'] }}" placeholder="https://sandbox.ipaymu.com" class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                    </div>
                </div>

                {{-- Webhook Callback Info --}}
                <div class="rounded-xl border border-border/80 bg-muted/30 p-3 text-xs flex items-center justify-between gap-2">
                    <div class="truncate">
                        <span class="font-medium text-muted-foreground">URL Callback / Webhook:</span>
                        <code class="ml-1.5 font-mono text-foreground select-all">{{ url('/webhooks/payment/ipaymu') }}</code>
                    </div>
                </div>
            </div>

            {{-- 4. DOKU (JOKUL) --}}
            <div class="rounded-2xl border border-border bg-card p-5 shadow-xs sm:p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-border/80 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="grid h-10 w-10 place-items-center rounded-xl bg-red-500/10 text-red-600 font-bold text-sm">
                            D
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-foreground">DOKU (Jokul)</h2>
                            <p class="text-xs text-muted-foreground">Payment gateway korporat untuk direct API, checkout, dan QRIS</p>
                        </div>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input type="checkbox" name="doku_active" value="1" @checked($gateways['doku']['is_active']) class="peer sr-only">
                        <div class="peer h-6 w-11 rounded-full bg-input peer-checked:bg-primary after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full"></div>
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Environment</label>
                        <select name="doku_environment" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                            <option value="sandbox" @selected($gateways['doku']['environment'] === 'sandbox')>Sandbox</option>
                            <option value="production" @selected($gateways['doku']['environment'] === 'production')>Production</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Client ID</label>
                        <input type="text" name="doku_client_id" value="{{ $gateways['doku']['client_id'] }}" placeholder="BRN-0123-..." class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Secret Key</label>
                        <input type="password" name="doku_secret_key" value="{{ $gateways['doku']['secret_key'] }}" placeholder="SK-..." class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm font-mono text-foreground transition focus:border-primary focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Base URL</label>
                        <input type="text" name="doku_base_url" value="{{ $gateways['doku']['base_url'] }}" placeholder="https://api-sandbox.doku.com" class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                    </div>
                </div>

                {{-- Webhook Callback Info --}}
                <div class="rounded-xl border border-border/80 bg-muted/30 p-3 text-xs flex items-center justify-between gap-2">
                    <div class="truncate">
                        <span class="font-medium text-muted-foreground">URL Webhook DOKU:</span>
                        <code class="ml-1.5 font-mono text-foreground select-all">{{ url('/webhooks/payment/doku') }}</code>
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-xs font-semibold text-primary-foreground shadow-xs transition hover:opacity-90 active:scale-95">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    <span>Simpan Seluruh Gateway</span>
                </button>
            </div>
        </form>
    </div>

    {{-- =========================================================================
         TAB 3: HARGA SEMUA PAKET
         ========================================================================= --}}
    <div x-show="tab === 'harga'" class="space-y-6" style="display: none;">
        <div class="rounded-xl border border-border bg-card p-4 text-xs text-muted-foreground">
            <p>
                Perubahan harga di bawah ini langsung berlaku secara dinamis ke halaman harga paket (<code class="font-mono text-foreground">/billing/plans</code>), tagihan checkout, dan katalog langganan tanpa perlu mengubah berkas konfigurasi maupun kode program.
            </p>
        </div>

        <form method="POST" action="{{ route('admin.payment-settings.prices') }}" class="space-y-6">
            @csrf

            {{-- Daftar Paket Utama --}}
            <div class="grid gap-5 lg:grid-cols-3">
                @foreach (['essentials', 'prime', 'elite'] as $slug)
                    @php $p = $catalog[$slug] ?? null; @endphp
                    @if ($p)
                        <div class="rounded-2xl border border-border bg-card p-5 shadow-xs flex flex-col justify-between space-y-4">
                            <div>
                                <div class="flex items-center justify-between border-b border-border/80 pb-2.5">
                                    <h3 class="text-base font-bold text-foreground">{{ $p['name'] }}</h3>
                                    <span class="rounded bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary font-mono">{{ $slug }}</span>
                                </div>
                                <p class="mt-1 text-xs text-muted-foreground">{{ $p['tagline'] }}</p>

                                <div class="mt-4 space-y-3">
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-foreground">
                                            Harga Normal Bulanan (Rp)
                                        </label>
                                        <input type="number" step="1000" min="0" required
                                               name="plans[{{ $slug }}][price_monthly]"
                                               value="{{ old("plans.{$slug}.price_monthly", $p['price_monthly'] ?? 0) }}"
                                               class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm font-semibold text-foreground transition focus:border-primary focus:outline-none">
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-foreground">
                                            Promo Perkenalan Bulan Ke-1 (Rp)
                                        </label>
                                        <input type="number" step="1000" min="0"
                                               name="plans[{{ $slug }}][intro_price_monthly]"
                                               value="{{ old("plans.{$slug}.intro_price_monthly", $p['intro_price_monthly'] ?? null) }}"
                                               placeholder="Kosongkan jika tidak ada promo"
                                               class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                                        <p class="mt-0.5 text-[10px] text-muted-foreground">Hanya untuk pembelian pertama workspace.</p>
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-foreground">
                                            Promo Perkenalan Tahun Ke-1 (Rp)
                                        </label>
                                        <input type="number" step="1000" min="0"
                                               name="plans[{{ $slug }}][intro_price_yearly]"
                                               value="{{ old("plans.{$slug}.intro_price_yearly", $p['intro_price_yearly'] ?? null) }}"
                                               placeholder="Kosongkan jika tidak ada promo"
                                               class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-xl border border-border/80 bg-muted/20 p-2.5 text-[11px] text-muted-foreground space-y-1">
                                <div class="flex justify-between">
                                    <span>Kuota pesan:</span>
                                    <span class="font-semibold text-foreground">{{ number_format($p['monthly_message_quota'], 0, ',', '.') }} /bln</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Nomor sesi:</span>
                                    <span class="font-semibold text-foreground">{{ $p['max_sessions'] }} nomor</span>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- Pay As You Go & Pengaturan Global --}}
            <div class="grid gap-5 md:grid-cols-2">
                {{-- PAYG --}}
                <div class="rounded-2xl border border-border bg-card p-5 shadow-xs space-y-4">
                    <div class="border-b border-border/80 pb-2.5">
                        <h3 class="text-base font-bold text-foreground">Pay As You Go</h3>
                        <p class="text-xs text-muted-foreground">Sistem bayar per pesan terkirim dengan saldo top-up</p>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-foreground">Tarif per Pesan Keluar (Rp)</label>
                            <input type="number" min="1" required name="payg_price_per_message"
                                   value="{{ old('payg_price_per_message', $paygPrice) }}"
                                   class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm font-semibold text-foreground transition focus:border-primary focus:outline-none">
                            <p class="mt-1 text-[11px] text-muted-foreground">Standar saat ini: Rp 200/pesan.</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-foreground">Minimum Nominal Isi Saldo (Rp)</label>
                            <input type="number" step="5000" min="1000" required name="payg_min_topup"
                                   value="{{ old('payg_min_topup', $paygMinTopup) }}"
                                   class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm font-semibold text-foreground transition focus:border-primary focus:outline-none">
                            <p class="mt-1 text-[11px] text-muted-foreground">Standar saat ini: Rp 50.000.</p>
                        </div>
                    </div>
                </div>

                {{-- Global Settings --}}
                <div class="rounded-2xl border border-border bg-card p-5 shadow-xs space-y-4">
                    <div class="border-b border-border/80 pb-2.5">
                        <h3 class="text-base font-bold text-foreground">Kebijakan Multiplier &amp; Pajak</h3>
                        <p class="text-xs text-muted-foreground">Perhitungan otomatis harga tahunan dan perpajakan</p>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-foreground">Pengali Harga Tahunan (Bulan)</label>
                            <input type="number" min="1" max="24" required name="yearly_multiplier"
                                   value="{{ old('yearly_multiplier', $yearlyMultiplier) }}"
                                   class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm font-semibold text-foreground transition focus:border-primary focus:outline-none">
                            <p class="mt-1 text-[11px] text-muted-foreground">
                                Nilai <strong>10</strong> berarti bayar 10 bulan gratis 2 bulan untuk langganan 1 tahun.
                            </p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-foreground">Persentase PPN (%)</label>
                            <input type="number" step="0.1" min="0" max="100" required name="tax_percent"
                                   value="{{ old('tax_percent', $taxPercent) }}"
                                   class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm font-semibold text-foreground transition focus:border-primary focus:outline-none">
                            <p class="mt-1 text-[11px] text-muted-foreground">
                                0 berarti harga sudah final tanpa pajak. Isi 11 jika ingin membebankan PPN 11% terpisah.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-xs font-semibold text-primary-foreground shadow-xs transition hover:opacity-90 active:scale-95">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    <span>Simpan Perubahan Harga Paket</span>
                </button>
            </div>
        </form>
    </div>

    {{-- =========================================================================
         MODAL TAMBAH REKENING / VA
         ========================================================================= --}}
    <div x-show="modalTambahBank" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
        <div @click.away="modalTambahBank = false"
             class="w-full max-w-lg rounded-2xl border border-border bg-card p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-border/80 pb-3">
                <h3 class="text-base font-bold text-foreground">Tambah Rekening Bank / Virtual Account</h3>
                <button type="button" @click="modalTambahBank = false" class="text-muted-foreground hover:text-foreground">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.payment-settings.bank.store') }}" class="space-y-3.5">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Bank / Provider</label>
                    <input type="text" name="bank_name" required placeholder="Contoh: Bank Central Asia (BCA)" class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Nomor Rekening / Nomor VA</label>
                    <input type="text" name="account_number" required placeholder="Contoh: 1234567890" class="w-full rounded-xl border border-input bg-background px-3.5 py-2 font-mono text-sm text-foreground transition focus:border-primary focus:outline-none">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Atas Nama / Pemilik Akun</label>
                    <input type="text" name="account_holder" required placeholder="Contoh: PT DESTINARA CHAKRAWALA ARTHA" class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Tipe Akun</label>
                        <select name="type" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                            <option value="bank">Transfer Bank</option>
                            <option value="va">Virtual Account</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Urutan Tampil</label>
                        <input type="number" name="sort_order" value="0" min="0" class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Instruksi Khusus (Opsional)</label>
                    <textarea name="instructions" rows="2" placeholder="Contoh: Transfer dari ATM atau m-Banking BCA" class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-xs text-foreground transition focus:border-primary focus:outline-none"></textarea>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <input type="checkbox" id="tambah_is_active" name="is_active" value="1" checked class="rounded border-input text-primary focus:ring-primary">
                    <label for="tambah_is_active" class="text-xs text-foreground font-medium">Langsung aktifkan di halaman checkout tagihan</label>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-border">
                    <button type="button" @click="modalTambahBank = false" class="rounded-xl border border-border px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-muted">Batal</button>
                    <button type="submit" class="rounded-xl bg-primary px-5 py-2 text-xs font-semibold text-primary-foreground shadow-xs hover:opacity-90">Simpan Rekening</button>
                </div>
            </form>
        </div>
    </div>

    {{-- =========================================================================
         MODAL EDIT REKENING / VA
         ========================================================================= --}}
    <div x-show="modalEditBank" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
        <div @click.away="modalEditBank = false"
             class="w-full max-w-lg rounded-2xl border border-border bg-card p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-border/80 pb-3">
                <h3 class="text-base font-bold text-foreground">Edit Rekening Bank / Virtual Account</h3>
                <button type="button" @click="modalEditBank = false" class="text-muted-foreground hover:text-foreground">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" :action="'{{ url('admin/pembayaran-paket/bank') }}/' + editBankData.id" class="space-y-3.5">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Bank / Provider</label>
                    <input type="text" name="bank_name" x-model="editBankData.bank_name" required class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Nomor Rekening / Nomor VA</label>
                    <input type="text" name="account_number" x-model="editBankData.account_number" required class="w-full rounded-xl border border-input bg-background px-3.5 py-2 font-mono text-sm text-foreground transition focus:border-primary focus:outline-none">
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Atas Nama / Pemilik Akun</label>
                    <input type="text" name="account_holder" x-model="editBankData.account_holder" required class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Tipe Akun</label>
                        <select name="type" x-model="editBankData.type" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                            <option value="bank">Transfer Bank</option>
                            <option value="va">Virtual Account</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Urutan Tampil</label>
                        <input type="number" name="sort_order" x-model="editBankData.sort_order" min="0" class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm text-foreground transition focus:border-primary focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-foreground">Instruksi Khusus (Opsional)</label>
                    <textarea name="instructions" x-model="editBankData.instructions" rows="2" class="w-full rounded-xl border border-input bg-background px-3.5 py-2 text-xs text-foreground transition focus:border-primary focus:outline-none"></textarea>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <input type="checkbox" id="edit_is_active" name="is_active" value="1" :checked="editBankData.is_active" class="rounded border-input text-primary focus:ring-primary">
                    <label for="edit_is_active" class="text-xs text-foreground font-medium">Aktif di halaman checkout tagihan</label>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-border">
                    <button type="button" @click="modalEditBank = false" class="rounded-xl border border-border px-4 py-2 text-xs font-semibold text-muted-foreground hover:bg-muted">Batal</button>
                    <button type="submit" class="rounded-xl bg-primary px-5 py-2 text-xs font-semibold text-primary-foreground shadow-xs hover:opacity-90">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
