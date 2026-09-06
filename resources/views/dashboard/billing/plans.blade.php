@extends('layouts.app')
@section('title', 'Paket')

@section('content')
    @include('dashboard.billing._nav')

    <div x-data="{ tahunan: '{{ $subscription->period }}' === 'yearly' }">
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-1 rounded-lg bg-muted p-1">
                <button type="button" @click="tahunan = false"
                        class="rounded-md px-3.5 py-1.5 text-sm font-medium transition"
                        :class="! tahunan ? 'bg-background shadow-sm' : 'text-muted-foreground'">Bulanan</button>
                <button type="button" @click="tahunan = true"
                        class="rounded-md px-3.5 py-1.5 text-sm font-medium transition"
                        :class="tahunan ? 'bg-background shadow-sm' : 'text-muted-foreground'">Tahunan</button>
            </div>
            <span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
                Bayar tahunan, hemat 2 bulan
            </span>
        </div>

        <div class="grid items-start gap-4 lg:grid-cols-3">
            @foreach ($plans as $plan)
                @php $ini = $plan->slug === $subscription->plan_slug; @endphp

                <x-card @class(['relative', 'border-primary' => $plan->isHighlighted() || $ini])>
                    @if ($ini)
                        <span class="absolute -top-2.5 right-4 rounded-full bg-primary px-2.5 py-0.5 text-xs font-medium text-primary-foreground">Paket Anda</span>
                    @elseif ($plan->isHighlighted())
                        <span class="absolute -top-2.5 right-4 rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground">Paling banyak dipilih</span>
                    @endif

                    <p class="font-semibold">{{ $plan->name() }}</p>
                    <p class="mt-0.5 text-sm text-muted-foreground">{{ $plan->tagline() }}</p>

                    <p class="mt-4 flex items-baseline gap-1.5">
                        <span class="text-3xl font-semibold tracking-tight">
                            <span x-show="! tahunan">Rp {{ number_format($plan->price('monthly'), 0, ',', '.') }}</span>
                            <span x-show="tahunan" x-cloak>Rp {{ number_format($plan->price('yearly'), 0, ',', '.') }}</span>
                        </span>
                        <span class="text-sm text-muted-foreground" x-text="tahunan ? '/tahun' : '/bulan'"></span>
                    </p>

                    {{-- Dibuat tak terlihat, bukan disembunyikan: kalau barisnya
                         ikut hilang, ketiga kartu bergeser naik-turun tiap kali
                         sakelar bulanan/tahunan ditekan. --}}
                    <p class="mt-1 text-xs text-muted-foreground" :class="tahunan ? '' : 'invisible'">
                        Setara Rp {{ number_format($plan->price('yearly') / 12, 0, ',', '.') }} per bulan.
                    </p>

                    @if ($bolehBayar)
                        <form method="POST" action="{{ route('billing.checkout') }}" class="mt-5">
                            @csrf
                            <input type="hidden" name="plan" value="{{ $plan->slug }}">
                            <input type="hidden" name="period" :value="tahunan ? 'yearly' : 'monthly'">
                            <button class="w-full rounded-lg px-4 py-2.5 text-sm font-medium transition {{ $plan->isHighlighted() || $ini ? 'bg-primary text-primary-foreground hover:opacity-90' : 'border border-border hover:bg-muted' }}">
                                {{ $ini ? 'Perpanjang' : 'Pilih '.$plan->name() }}
                            </button>
                        </form>
                    @endif

                    <ul class="mt-6 space-y-2 border-t border-border pt-5 text-sm">
                        @foreach ($plan->features() as $fitur)
                            <li class="flex gap-2.5">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                                <span class="text-muted-foreground">{{ $fitur }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endforeach
        </div>

        {{-- Yang paling sering ditanyakan tepat sebelum orang menekan tombol
             bayar. Menjawabnya di sini, bukan di halaman docs terpisah. --}}
        <div class="mt-10 grid gap-x-12 gap-y-5 border-t border-border pt-6 text-sm sm:grid-cols-2">
            <div>
                <p class="font-medium">Paket berpindah saat tagihan lunas</p>
                <p class="mt-1 leading-relaxed text-muted-foreground">
                    Memilih di sini menerbitkan tagihan, belum mengubah batas apa pun.
                </p>
            </div>
            <div>
                <p class="font-medium">Sisa hari Anda tidak hangus</p>
                <p class="mt-1 leading-relaxed text-muted-foreground">
                    Membayar lebih awal menyambung dari tanggal berakhirnya periode berjalan.
                </p>
            </div>
            <div>
                <p class="font-medium">Tidak ada tarikan otomatis</p>
                <p class="mt-1 leading-relaxed text-muted-foreground">
                    Tidak ada kartu yang disimpan. Setiap periode Anda bayar sendiri.
                </p>
            </div>
            <div>
                <p class="font-medium">Butuh lebih dari paket Elite?</p>
                <p class="mt-1 leading-relaxed text-muted-foreground">
                    <a href="https://about.flustra.id/#contact" class="text-primary hover:underline">Hubungi kami</a>
                    untuk penawaran khusus.
                </p>
            </div>
        </div>

        @unless ($bolehBayar)
            <p class="mt-6 text-sm text-muted-foreground">
                Hanya owner atau admin workspace yang bisa mengurus langganan.
            </p>
        @endunless
    </div>
@endsection
