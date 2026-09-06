@extends('layouts.admin')
@section('title', 'Tagihan')

@section('content')
    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative min-w-64 max-w-sm flex-1">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="cari" value="{{ $cari }}" placeholder="Cari nomor tagihan, nominal, atau workspace"
                   class="w-full rounded-lg border-border bg-background pl-9 text-sm focus:border-primary focus:ring-primary">
        </div>
        <select name="status" class="rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
            @foreach (['perlu-diperiksa' => 'Perlu diperiksa', 'pending' => 'Menunggu bayar', 'paid' => 'Lunas', 'expired' => 'Kedaluwarsa', 'canceled' => 'Dibatalkan', 'semua' => 'Semua'] as $nilai => $label)
                <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">Cari</button>
    </form>

    {{-- Nominal ditampilkan sebagai angka tabular. Mencocokkan pembayaran berarti
         membandingkan angka di mutasi rekening dengan angka di sini, sampai tiga
         digit terakhirnya — itu satu-satunya penanda yang kita punya selama
         pembayaran belum otomatis. --}}
    <x-section :sub="number_format($invoices->total()).' tagihan'">
        <table class="w-full min-w-[64rem] text-sm">
            <thead class="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                <tr>
                    <th class="px-5 py-2.5 font-medium">Tagihan</th>
                    <th class="px-5 py-2.5 font-medium">Workspace</th>
                    <th class="px-5 py-2.5 font-medium">Paket</th>
                    <th class="px-5 py-2.5 text-right font-medium">Jumlah</th>
                    <th class="px-5 py-2.5 font-medium">Status</th>
                    <th class="px-5 py-2.5 font-medium">Jatuh tempo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($invoices as $invoice)
                    @php
                        $li = \App\Support\StatusBadge::invoice($invoice->status);
                        $perluDiperiksa = $invoice->proof_path && ! $invoice->isPaid();
                        $tertutup = $invoice->proof_path && in_array($invoice->status, ['canceled', 'expired'], true);
                    @endphp

                    <tr @class(['transition hover:bg-muted/40', 'bg-primary/5' => $perluDiperiksa])>
                        <td class="px-5 py-3">
                            <p class="font-medium">{{ $invoice->number }}</p>
                            <p class="text-xs text-muted-foreground">terbit {{ $invoice->created_at->translatedFormat('j M Y') }}</p>
                        </td>

                        <td class="px-5 py-3">
                            @if ($invoice->workspace)
                                <a href="{{ route('admin.workspaces.show', $invoice->workspace_id) }}" class="hover:underline">{{ $invoice->workspace->name }}</a>
                            @else
                                <span class="text-muted-foreground">—</span>
                            @endif
                        </td>

                        <td class="px-5 py-3 text-muted-foreground">{{ $invoice->plan()->name() }} · {{ $invoice->periodLabel() }}</td>

                        <td class="px-5 py-3 text-right">
                            <p class="font-semibold tabular-nums">Rp {{ number_format($invoice->total, 0, ',', '.') }}</p>
                            @if ($invoice->unique_code > 0)
                                <p class="text-xs text-muted-foreground">kode {{ str_pad($invoice->unique_code, 3, '0', STR_PAD_LEFT) }}</p>
                            @endif
                        </td>

                        <td class="px-5 py-3">
                            <div class="flex flex-wrap gap-1.5">
                                <x-badge :warna="$li['warna']" titik>{{ $li['label'] }}</x-badge>
                                @if ($perluDiperiksa)
                                    <x-badge warna="solid">bukti masuk</x-badge>
                                @endif
                            </div>
                            @if ($tertutup)
                                {{-- Ada yang membayar lalu tagihannya telanjur ditutup.
                                     Kalau uangnya benar masuk, tandai lunas dari sini. --}}
                                <p class="mt-1 text-xs font-medium text-destructive">sudah bayar tapi tagihan tertutup</p>
                            @endif
                            @if ($invoice->isPaid() && $invoice->paidBy)
                                <p class="mt-1 text-xs text-muted-foreground">oleh {{ $invoice->paidBy->email }}</p>
                            @endif
                        </td>

                        <td class="px-5 py-3 text-muted-foreground">{{ $invoice->due_at?->translatedFormat('j M Y') ?? '—' }}</td>
                    </tr>
                @empty
                    <x-kosong :kolom="6" judul="Tidak ada tagihan yang cocok"
                              pesan="Saringan bawaan 'Perlu diperiksa' menampilkan tagihan yang menunggu bayar dan yang sudah ada buktinya — apa pun statusnya." />
                @endforelse
            </tbody>
        </table>
    </x-section>

    {{-- ===================== Antrean pemeriksaan =====================

         Tindakannya berjajar di bawah tabel, bukan bersembunyi di dalam baris.
         Memeriksa bukti adalah pekerjaan berulang yang dilakukan belasan kali
         berturut-turut; mengerjakannya jauh lebih cepat kalau semuanya terbuka
         sekaligus daripada kalau tiap tagihan harus dibuka dulu satu per satu.
         Tabel di atas tetap murni untuk memindai.
         ============================================================= --}}
    @php $antre = $invoices->getCollection()->filter(fn ($i) => ! $i->isPaid()); @endphp

    @if ($antre->isNotEmpty())
        <div class="mt-6 space-y-3">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                Tindakan &mdash; {{ $antre->count() }} tagihan di halaman ini
            </h3>

            @foreach ($antre as $invoice)
                <x-card @class(['border-primary/50' => $invoice->proof_path])>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium">
                                {{ $invoice->number }}
                                <span class="text-muted-foreground">· {{ $invoice->workspace?->name ?? '—' }}</span>
                            </p>
                            <p class="text-sm text-muted-foreground">
                                Rp {{ number_format($invoice->total, 0, ',', '.') }}
                                @if ($invoice->unique_code > 0)
                                    · kode unik {{ str_pad($invoice->unique_code, 3, '0', STR_PAD_LEFT) }}
                                @endif
                            </p>
                        </div>

                        @if ($invoice->proof_path)
                            <a href="{{ route('admin.invoices.proof', $invoice->id) }}" target="_blank"
                               class="rounded-lg border border-border px-3 py-1.5 text-sm transition hover:bg-muted">Lihat bukti</a>
                        @else
                            <x-badge warna="netral">belum ada bukti</x-badge>
                        @endif
                    </div>

                    <div class="mt-4 flex flex-wrap items-end gap-2 border-t border-border pt-4">
                        <form method="POST" action="{{ route('admin.invoices.paid', $invoice->id) }}"
                              class="flex flex-1 flex-wrap items-end gap-2"
                              data-konfirmasi="Tandai {{ $invoice->number }} lunas? Langganannya akan langsung diperpanjang."
                              data-konfirmasi-ya="Ya, tandai lunas">
                            @csrf
                            <input type="text" name="catatan" maxlength="500" placeholder="Catatan (mis. mutasi BCA 14:32)"
                                   class="min-w-48 flex-1 rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                            <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                                Tandai lunas
                            </button>
                        </form>

                        <form method="POST" action="{{ route('admin.invoices.status', $invoice->id) }}"
                              data-konfirmasi="Batalkan tagihan ini?">
                            @csrf
                            <input type="hidden" name="status" value="canceled">
                            <button class="rounded-lg border border-border px-3 py-2 text-sm text-muted-foreground transition hover:bg-muted">
                                Batalkan
                            </button>
                        </form>
                    </div>

                    @if ($invoice->proof_path)
                        {{-- Jalan keluar saat buktinya tidak bisa diterima: tangkapan
                             layar terpotong, nominal tidak cocok, atau transfer atas
                             nama orang lain. Tanpa ini tagihan seperti itu menggantung
                             tanpa akhir. --}}
                        <details class="mt-3 border-t border-border pt-3">
                            <summary class="cursor-pointer text-sm text-muted-foreground hover:text-foreground">
                                Bukti tidak bisa diterima?
                            </summary>
                            <form method="POST" action="{{ route('admin.invoices.reject-proof', $invoice->id) }}"
                                  class="mt-3 flex flex-wrap items-end gap-2"
                                  data-validasi
                                  data-konfirmasi="Tolak bukti ini dan buka lagi tagihannya? Pelanggan diberi tahu lewat WhatsApp beserta alasannya."
                                  data-konfirmasi-ya="Ya, tolak bukti">
                                @csrf
                                <input type="text" name="alasan" maxlength="500" required
                                       placeholder="Alasan (mis. nominal tidak cocok, gambar tidak terbaca)"
                                       class="min-w-64 flex-1 rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                                <button class="rounded-lg border border-destructive/40 px-3 py-2 text-sm text-destructive transition hover:bg-destructive/10">
                                    Tolak &amp; minta bukti baru
                                </button>
                            </form>
                        </details>
                    @endif
                </x-card>
            @endforeach
        </div>
    @endif

    <div class="mt-4">{{ $invoices->links() }}</div>
@endsection
