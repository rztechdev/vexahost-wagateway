@extends('layouts.app')
@section('title', 'Riwayat tagihan')

@section('content')
    @include('dashboard.billing._nav')

    @if ($invoices->isEmpty())
        <x-card>
            <p class="text-sm text-muted-foreground">
                Belum ada tagihan. Tagihan pertama terbit beberapa hari sebelum masa percobaan Anda berakhir.
            </p>
        </x-card>
    @else
        {{-- Div biasa, bukan <x-card>: komponen itu membawa padding sendiri,
             dan tabel yang menempel ke tepi kartu terbaca jauh lebih rapi. --}}
        <div class="overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[42rem] text-sm">
                    <thead class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-medium">Nomor</th>
                            <th class="px-5 py-3 font-medium">Paket</th>
                            <th class="px-5 py-3 font-medium">Jumlah</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Tanggal</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr class="border-b border-border last:border-0">
                                <td class="px-5 py-3 font-medium">{{ $invoice->number }}</td>
                                <td class="px-5 py-3 text-muted-foreground">
                                    {{ $invoice->plan()->name() }} · {{ $invoice->periodLabel() }}
                                </td>
                                <td class="px-5 py-3 tabular-nums">Rp {{ number_format($invoice->total, 0, ',', '.') }}</td>
                                <td class="px-5 py-3">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-primary/10 text-primary' => $invoice->status === 'paid',
                                        'bg-amber-500/10 text-amber-600 dark:text-amber-400' => $invoice->status === 'pending',
                                        'bg-muted text-muted-foreground' => in_array($invoice->status, ['expired', 'canceled'], true),
                                    ])>
                                        {{ ['pending' => 'Menunggu', 'paid' => 'Lunas', 'expired' => 'Kedaluwarsa', 'canceled' => 'Dibatalkan'][$invoice->status] }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-muted-foreground">
                                    {{ ($invoice->paid_at ?? $invoice->created_at)->translatedFormat('j M Y') }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    {{-- Tagihan lunas tetap bisa dibuka: rinciannya
                                         adalah bukti pembayaran yang sewaktu-waktu
                                         dibutuhkan untuk pembukuan. --}}
                                    <a href="{{ route('billing.invoice', $invoice->id) }}"
                                       class="text-primary hover:underline">
                                        {{ $invoice->isPending() ? 'Bayar' : 'Rincian' }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $invoices->links() }}</div>
    @endif
@endsection
