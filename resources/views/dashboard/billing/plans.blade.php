@extends('layouts.app')
@section('title', 'Paket')

@section('content')
    @include('dashboard.billing._nav')

    <div x-data="{
        tahunan: '{{ $subscription->period }}' === 'yearly',
        kode: '{{ old('referral', session('referral_code')) }}',
        referal: null,
        memeriksa: false,

        init() {
            if (this.kode) {
                this.periksa();
            }
        },

        /*
         | Diperiksa terhadap SATU paket, tapi yang dipakai menghitung ulang tiap
         | kartu cuma persennya. Persen tidak bergantung paket, jadi satu
         | pemeriksaan sudah cukup — dan angka rupiah tiap kartu dihitung dengan
         | rumus yang sama persis dengan yang di server (persen lalu dibulatkan
         | ke bawah), supaya yang dibaca pelanggan di sini sama dengan yang
         | tertulis di tagihannya nanti.
        */
        async periksa() {
            if (! this.kode) { this.referal = null; return; }

            this.memeriksa = true;

            try {
                const jawaban = await fetch('{{ route('billing.referral.review') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        code: this.kode,
                        plan: '{{ collect($plans)->first()->slug }}',
                        period: this.tahunan ? 'yearly' : 'monthly',
                    }),
                });

                this.referal = await jawaban.json();
            } catch (e) {
                this.referal = { sah: false, pesan: 'Tidak bisa memeriksa kode sekarang. Coba lagi.' };
            }

            this.memeriksa = false;
        },

        potongan(harga) {
            return this.referal?.sah ? Math.floor(harga * this.referal.persen / 100) : 0;
        },

        rupiah(angka) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(angka);
        },
    }">

        {{-- Card Kode Referal: Modern, Lebar, dan Rapi di atas pilihan siklus --}}
        @if ($bolehBayar)
            <div class="mb-6 w-full rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-primary/[0.03] p-4 sm:p-5 shadow-xs">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm sm:text-base font-semibold text-foreground">Punya Kode Referal / Promo?</h3>
                                <span class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-medium text-primary">
                                    Diskon Khusus
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-muted-foreground leading-relaxed">
                                Masukkan kode referal atau promo untuk mendapatkan potongan harga spesial pada tagihan pertama workspace ini.
                            </p>
                        </div>
                    </div>

                    <div class="w-full lg:w-auto lg:min-w-[380px]">
                        <div class="relative flex items-center rounded-xl border border-border bg-background p-1 shadow-xs transition focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
                            <div class="pointer-events-none pl-3 text-muted-foreground">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                                </svg>
                            </div>
                            <input id="referral" x-model="kode" @keydown.enter.prevent="periksa()"
                                   maxlength="16" placeholder="Masukkan kode (misal: KXPMR)"
                                   class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm font-semibold uppercase tracking-wider text-foreground placeholder:normal-case placeholder:font-normal placeholder:tracking-normal placeholder:text-muted-foreground/60 focus:outline-none focus:ring-0">
                            <button type="button" @click="periksa()" :disabled="memeriksa || ! kode"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-xs sm:text-sm font-semibold text-primary-foreground shadow-xs transition hover:bg-primary/90 disabled:opacity-40 disabled:cursor-not-allowed shrink-0 whitespace-nowrap">
                                <svg x-show="memeriksa" class="h-3.5 w-3.5 animate-spin text-primary-foreground" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span x-text="memeriksa ? 'Memeriksa…' : 'Gunakan Kode'">Gunakan Kode</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Status Hasil Pengecekan Kupon --}}
                <template x-if="referal">
                    <div class="mt-3.5 flex items-center justify-between rounded-xl px-4 py-2.5 text-xs sm:text-sm font-medium transition"
                         :class="referal.sah ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20' : 'bg-destructive/10 text-destructive border border-destructive/20'">
                        <div class="flex items-center gap-2">
                            <svg x-show="referal.sah" class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                            <svg x-show="!referal.sah" class="h-4 w-4 shrink-0 text-destructive" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <span x-text="referal.pesan"></span>
                        </div>
                        <button type="button" x-show="referal.sah" @click="kode = ''; referal = null;"
                                class="text-xs font-semibold text-muted-foreground hover:text-foreground underline transition ml-3 shrink-0">
                            Hapus
                        </button>
                    </div>
                </template>

                @error('referral')
                    <p class="mt-2 text-xs text-destructive">{{ $message }}</p>
                @enderror
            </div>
        @endif

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

                    <template x-if="referal?.sah">
                        <div class="mt-3">
                            <p class="text-xs sm:text-sm font-semibold text-muted-foreground line-through decoration-destructive">
                                <span x-text="rupiah(tahunan ? {{ $plan->price('yearly') }} : {{ $plan->price('monthly') }})"></span>
                            </p>
                            <p class="mt-0.5 flex items-baseline gap-1.5 flex-wrap">
                                <span class="text-3xl font-extrabold tracking-tight text-primary"
                                      x-text="rupiah((tahunan ? {{ $plan->price('yearly') }} : {{ $plan->price('monthly') }}) - potongan(tahunan ? {{ $plan->price('yearly') }} : {{ $plan->price('monthly') }}))"></span>
                                <span class="text-sm text-muted-foreground" x-text="tahunan ? '/tahun' : '/bulan'"></span>
                                <span class="rounded-md bg-emerald-500/10 px-2 py-0.5 text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                                    Hemat <span x-text="referal.persen"></span>%
                                </span>
                            </p>
                        </div>
                    </template>
                    <template x-if="! referal?.sah">
                        <p class="mt-4 flex items-baseline gap-1.5">
                            <span class="text-3xl font-semibold tracking-tight">
                                <span x-show="! tahunan">Rp {{ number_format($plan->price('monthly'), 0, ',', '.') }}</span>
                                <span x-show="tahunan" x-cloak>Rp {{ number_format($plan->price('yearly'), 0, ',', '.') }}</span>
                            </span>
                            <span class="text-sm text-muted-foreground" x-text="tahunan ? '/tahun' : '/bulan'"></span>
                        </p>
                    </template>

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
                            {{-- Hanya dikirim kalau kodenya lolos pemeriksaan:
                                 kode salah ketik yang ikut terkirim membuat
                                 checkout ditolak dan pilihan paketnya hilang. --}}
                            <input type="hidden" name="referral" :value="referal?.sah ? kode : ''">
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

        {{-- ===================== Pay as you go =====================

             Sengaja DI LUAR grid tiga kartu dan dengan bentuk yang berbeda.
             Harganya per pesan, bukan per bulan; memaksanya masuk cetakan
             kartu bulanan menghasilkan kartu bertuliskan "Rp 0/bulan" — dan
             sakelar bulanan/tahunan di atas tidak berarti apa-apa untuknya.
             ======================================================== --}}
        @php $payg = \App\Support\Plan::payg(); @endphp

        <div class="mt-4 rounded-xl border border-border bg-muted/30 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="font-semibold">{{ $payg->name() }}</p>
                    <p class="mt-0.5 text-sm text-muted-foreground">{{ $payg->tagline() }}</p>
                </div>

                <p class="flex items-baseline gap-1.5">
                    <span class="text-3xl font-semibold tracking-tight">
                        Rp {{ number_format(config('billing.payg.price_per_message'), 0, ',', '.') }}
                    </span>
                    <span class="text-sm text-muted-foreground">/pesan terkirim</span>
                </p>
            </div>

            <ul class="mt-4 grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($payg->features() as $fitur)
                    <li class="flex gap-2.5">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                        <span class="text-muted-foreground">{{ $fitur }}</span>
                    </li>
                @endforeach
            </ul>

            @if ($bolehBayar)
                <a href="{{ route('balance.index') }}"
                   class="mt-5 inline-block rounded-lg border border-border bg-background px-4 py-2.5 text-sm font-medium transition hover:bg-muted">
                    {{ ($currentWorkspace ?? null)?->isPayg() ? 'Buka halaman Saldo' : 'Mulai dengan isi saldo' }}
                </a>
            @endif

            {{-- Titik impasnya disebut apa adanya. Menyembunyikannya berarti
                 menjual PAYG ke orang yang seharusnya ambil paket, lalu
                 kehilangan mereka saat tagihannya ternyata lebih mahal. --}}
            <p class="mt-4 border-t border-border pt-4 text-xs leading-relaxed text-muted-foreground">
                Cocok kalau pengiriman Anda sedikit dan tidak tentu. Di atas sekitar
                <strong>745 pesan per bulan</strong>, paket Essentials selalu lebih murah —
                saldo tidak punya masa berlaku, tapi paket memberi jauh lebih banyak pesan
                untuk uang yang sama.
            </p>
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
