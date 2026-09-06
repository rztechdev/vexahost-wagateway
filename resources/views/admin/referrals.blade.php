@extends('layouts.admin')
@section('title', 'Reseller')

@section('content')
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Komisi terutang"
                :nilai="'Rp '.number_format($totalTerutang, 0, ',', '.')"
                :sub="$terutang->count().' menunggu ditransfer'"
                :nada="$totalTerutang > 0 ? 'perhatian' : 'netral'"
                ikon="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />

        <x-stat label="Komisi sudah dibayar"
                :nilai="'Rp '.number_format($totalDibayar, 0, ',', '.')"
                sub="seluruh waktu"
                ikon="M9 12l2 2 4-4M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />

        <x-stat label="Potongan diberikan"
                :nilai="'Rp '.number_format($totalDiskon, 0, ',', '.')"
                sub="dari tagihan yang lunas"
                ikon="M19 5 5 19M6.5 9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5zm11 11a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z" />

        <x-stat label="Kode aktif"
                :nilai="$kode->where('is_active', true)->count()"
                :sub="$kode->count().' kode seluruhnya'"
                ikon="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.3 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8L12 2z" />
    </div>

    {{-- ===================== Komisi yang menunggu ditransfer =====================

         Paling atas dan terpisah dari daftar kode: ini satu-satunya bagian
         halaman yang menuntut tindakan. Menguburnya di dalam tabel kode berarti
         tidak ada yang tahu ada utang yang belum dibayar — pola yang sama
         dengan saringan "perlu diperiksa" di halaman Tagihan.
         ================================================================== --}}
    <x-section judul="Komisi menunggu ditransfer"
               sub="Tagihannya sudah lunas. Transfernya dilakukan manusia; tombol di sini hanya mencatat bahwa itu sudah terjadi."
               class="mt-5">
        @if ($terutang->isEmpty())
            <p class="px-5 py-4 text-sm text-muted-foreground">Tidak ada komisi yang menunggu.</p>
        @else
            <table class="w-full min-w-[52rem] text-sm">
                <thead class="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-5 py-2.5 font-medium">Kode</th>
                        <th class="px-5 py-2.5 font-medium">Reseller</th>
                        <th class="px-5 py-2.5 font-medium">Dari workspace</th>
                        <th class="px-5 py-2.5 font-medium">Tagihan</th>
                        <th class="px-5 py-2.5 text-right font-medium">Komisi</th>
                        <th class="px-5 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($terutang as $r)
                        <tr>
                            <td class="px-5 py-3 font-mono font-medium">{{ $r->code?->code }}</td>
                            <td class="px-5 py-3">
                                {{ $r->code?->owner?->name }}
                                <span class="block text-xs text-muted-foreground">{{ $r->code?->owner?->email }}</span>
                            </td>
                            <td class="px-5 py-3">{{ $r->workspace?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-muted-foreground">
                                {{ $r->invoice?->number ?? '—' }}
                                <span class="block text-xs">disetujui {{ $r->approved_at?->translatedFormat('j M Y') }}</span>
                            </td>
                            <td class="px-5 py-3 text-right font-medium tabular-nums">
                                Rp {{ number_format($r->commission_amount, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                <form method="POST" action="{{ route('admin.referrals.commission.paid', $r->id) }}"
                                      data-konfirmasi="Tandai komisi Rp {{ number_format($r->commission_amount, 0, ',', '.') }} untuk {{ $r->code?->owner?->name }} sudah ditransfer? Ini hanya mencatat, tidak mengirim uang.">
                                    @csrf
                                    <button class="rounded-lg border border-border px-3 py-1.5 text-xs font-medium transition hover:bg-muted">
                                        Tandai dibayar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-section>

    {{-- ===================== Buat kode ===================== --}}
    <x-card title="Buat kode referal"
            subtitle="Kodenya dibuat acak — 5 huruf kapital tanpa I dan O, karena keduanya tertukar dengan 1 dan 0 saat didiktekan lewat telepon."
            class="mt-5">
        <form method="POST" action="{{ route('admin.referrals.store') }}"
              class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5" data-validasi>
            @csrf
            <div class="sm:col-span-2">
                <label for="owner_user_id" class="mb-1 block text-sm font-medium">Reseller</label>
                <select id="owner_user_id" name="owner_user_id" required
                        class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                    <option value="">— pilih —</option>
                    @foreach ($kandidatPemilik as $u)
                        <option value="{{ $u->id }}" @selected(old('owner_user_id') == $u->id)>{{ $u->name }} · {{ $u->email }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="discount_percent" class="mb-1 block text-sm font-medium">Diskon pembeli</label>
                <input id="discount_percent" name="discount_percent" type="number" min="0" max="100" required
                       value="{{ old('discount_percent', 10) }}"
                       class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
            </div>

            <div>
                <label for="commission_percent" class="mb-1 block text-sm font-medium">Komisi reseller</label>
                <input id="commission_percent" name="commission_percent" type="number" min="0" max="100" required
                       value="{{ old('commission_percent', 20) }}"
                       class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
            </div>

            <div>
                <label for="max_redemptions" class="mb-1 block text-sm font-medium">Batas pemakaian</label>
                <input id="max_redemptions" name="max_redemptions" type="number" min="1" placeholder="tanpa batas"
                       value="{{ old('max_redemptions') }}"
                       class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
            </div>

            <div class="sm:col-span-2">
                <label for="expires_at" class="mb-1 block text-sm font-medium">Kedaluwarsa</label>
                <input id="expires_at" name="expires_at" type="date" value="{{ old('expires_at') }}"
                       class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
            </div>

            <div class="flex items-end sm:col-span-2 lg:col-span-3">
                <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                    Buat kode
                </button>
            </div>
        </form>

        <p class="mt-4 border-t border-border pt-4 text-xs leading-relaxed text-muted-foreground">
            Komisi dihitung dari total <strong>setelah</strong> potongan, dan baru terutang saat
            tagihannya lunas — tagihan yang kedaluwarsa atau dibatalkan tidak menghasilkan apa pun.
            Batas pemakaian juga baru berkurang saat lunas, supaya kode yang ditukar tapi tidak
            pernah dibayar tidak menghabiskan jatah resellernya.
            Potongan hanya berlaku untuk <strong>tagihan pertama</strong> sebuah workspace.
        </p>
    </x-card>

    {{-- ===================== Daftar kode ===================== --}}
    <x-section judul="Kode referal" class="mt-5">
        @if ($kode->isEmpty())
            <p class="px-5 py-4 text-sm text-muted-foreground">Belum ada kode referal.</p>
        @else
            <table class="w-full min-w-[56rem] text-sm">
                <thead class="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-5 py-2.5 font-medium">Kode</th>
                        <th class="px-5 py-2.5 font-medium">Pemilik</th>
                        <th class="px-5 py-2.5 text-right font-medium">Diskon</th>
                        <th class="px-5 py-2.5 text-right font-medium">Komisi</th>
                        <th class="px-5 py-2.5 text-right font-medium">Dipakai</th>
                        <th class="px-5 py-2.5 text-right font-medium">Terutang</th>
                        <th class="px-5 py-2.5 text-right font-medium">Dibayar</th>
                        <th class="px-5 py-2.5 font-medium">Keadaan</th>
                        <th class="px-5 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($kode as $k)
                        <tr>
                            <td class="px-5 py-3 font-mono font-medium">{{ $k->code }}</td>
                            <td class="px-5 py-3">
                                {{ $k->owner?->name }}
                                <span class="block text-xs text-muted-foreground">{{ $k->owner?->email }}</span>
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $k->discount_percent }}%</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $k->commission_percent }}%</td>
                            <td class="px-5 py-3 text-right tabular-nums">
                                {{ $k->redeemed_count }}{{ $k->max_redemptions ? '/'.$k->max_redemptions : '' }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums">Rp {{ number_format($k->komisiTerutang(), 0, ',', '.') }}</td>
                            <td class="px-5 py-3 text-right tabular-nums text-muted-foreground">Rp {{ number_format($k->komisiSudahDibayar(), 0, ',', '.') }}</td>
                            <td class="px-5 py-3">
                                @if (! $k->is_active)
                                    <x-badge warna="netral">dimatikan</x-badge>
                                @elseif ($k->expires_at && $k->expires_at->isPast())
                                    <x-badge warna="netral">kedaluwarsa</x-badge>
                                @elseif ($k->max_redemptions && $k->redeemed_count >= $k->max_redemptions)
                                    <x-badge warna="netral">jatah habis</x-badge>
                                @else
                                    <x-badge warna="hijau">aktif</x-badge>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                <form method="POST" action="{{ route('admin.referrals.toggle', $k->id) }}">
                                    @csrf
                                    <button class="rounded-lg border border-border px-3 py-1.5 text-xs transition hover:bg-muted">
                                        {{ $k->is_active ? 'Matikan' : 'Aktifkan' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-section>
@endsection
