@extends('layouts.admin')
@section('title', 'Tagihan')

@section('content')
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="search" name="cari" value="{{ $cari }}" placeholder="Cari nomor tagihan, nominal, atau workspace"
               class="w-full max-w-sm rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
        <select name="status" class="rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
            @foreach (['pending' => 'Menunggu bayar', 'paid' => 'Lunas', 'expired' => 'Kedaluwarsa', 'canceled' => 'Dibatalkan', 'semua' => 'Semua'] as $nilai => $label)
                <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90">Cari</button>
    </form>

    {{-- Nominal ditampilkan besar dan bisa disalin. Mencocokkan pembayaran
         berarti membandingkan angka di mutasi rekening dengan angka di sini,
         sampai tiga digit terakhirnya — itu satu-satunya penanda yang kita
         punya selama pembayaran belum otomatis. --}}
    <div class="space-y-3">
        @forelse ($invoices as $invoice)
            <x-card @class(['border-primary' => $invoice->isPending() && $invoice->proof_path])>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-2 font-semibold">
                            {{ $invoice->number }}
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-primary/10 text-primary' => $invoice->status === 'paid',
                                'bg-amber-500/10 text-amber-600 dark:text-amber-400' => $invoice->status === 'pending',
                                'bg-muted text-muted-foreground' => in_array($invoice->status, ['expired', 'canceled'], true),
                            ])>
                                {{ ['pending' => 'Menunggu', 'paid' => 'Lunas', 'expired' => 'Kedaluwarsa', 'canceled' => 'Dibatalkan'][$invoice->status] }}
                            </span>
                            @if ($invoice->isPending() && $invoice->proof_path)
                                <span class="rounded-full bg-primary px-2 py-0.5 text-xs font-medium text-primary-foreground">bukti masuk</span>
                            @endif
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ $invoice->workspace?->name ?? '—' }} ·
                            {{ $invoice->plan()->name() }} · {{ $invoice->periodLabel() }} ·
                            terbit {{ $invoice->created_at->translatedFormat('j M Y') }}
                            @if ($invoice->due_at)
                                · jatuh tempo {{ $invoice->due_at->translatedFormat('j M Y') }}
                            @endif
                        </p>
                        @if ($invoice->isPaid())
                            <p class="mt-1 text-xs text-muted-foreground">
                                Ditandai lunas {{ $invoice->paid_at?->translatedFormat('j M Y, H:i') }}
                                @if ($invoice->paidBy)
                                    oleh {{ $invoice->paidBy->email }}
                                @endif
                                @if ($invoice->note)
                                    — {{ $invoice->note }}
                                @endif
                            </p>
                        @endif
                    </div>

                    <div class="text-right">
                        <p class="text-xl font-semibold tabular-nums">Rp {{ number_format($invoice->total, 0, ',', '.') }}</p>
                        @if ($invoice->unique_code > 0)
                            <p class="text-xs text-muted-foreground">kode unik {{ str_pad($invoice->unique_code, 3, '0', STR_PAD_LEFT) }}</p>
                        @endif
                    </div>
                </div>

                @if (! $invoice->isPaid())
                    <div class="mt-4 flex flex-wrap items-end gap-2 border-t border-border pt-4">
                        @if ($invoice->proof_path)
                            <a href="{{ route('admin.invoices.proof', $invoice->id) }}" target="_blank"
                               class="rounded-lg border border-border px-3 py-1.5 text-sm hover:bg-muted">Lihat bukti</a>
                        @endif

                        <form method="POST" action="{{ route('admin.invoices.paid', $invoice->id) }}"
                              class="flex flex-1 flex-wrap items-end gap-2"
                              onsubmit="return confirm('Tandai {{ $invoice->number }} lunas? Langganannya akan langsung diperpanjang.')">
                            @csrf
                            <input type="text" name="catatan" maxlength="500" placeholder="Catatan (mis. mutasi BCA 14:32)"
                                   class="min-w-48 flex-1 rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                            <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90">
                                Tandai lunas
                            </button>
                        </form>

                        <form method="POST" action="{{ route('admin.invoices.status', $invoice->id) }}"
                              onsubmit="return confirm('Batalkan tagihan ini?')">
                            @csrf
                            <input type="hidden" name="status" value="canceled">
                            <button class="rounded-lg border border-border px-3 py-1.5 text-sm text-muted-foreground hover:bg-muted">
                                Batalkan
                            </button>
                        </form>
                    </div>
                @endif
            </x-card>
        @empty
            <x-card><p class="text-sm text-muted-foreground">Tidak ada tagihan yang cocok.</p></x-card>
        @endforelse
    </div>

    <div class="mt-4">{{ $invoices->links() }}</div>
@endsection
