@extends('layouts.app')
@section('title', 'Paket')

@section('content')
    @include('dashboard.billing._nav')

    <div x-data="{
        tahunan: '{{ $subscription->period }}' === 'yearly',
        kode: '{{ old('referral') }}',
        referal: null,
        memeriksa: false,

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

        {{-- Di atas kartu, bukan di halaman bayar: potongannya harus terlihat
             SAAT paket dipilih, bukan setelah tagihan terbit. Yang sedang
             diputuskan orang di layar ini adalah berapa yang akan ia bayar. --}}
        @if ($bolehBayar)
            <div class="mb-6 max-w-xl rounded-lg border border-border bg-card p-4">
                <label for="referral" class="block text-sm font-medium">Punya kode referal?</label>
                <div class="mt-2 flex flex-wrap gap-2">
                    <input id="referral" x-model="kode" @keydown.enter.prevent="periksa()"
                           maxlength="16" placeholder="5 huruf, mis. KXPMR"
                           class="min-w-40 flex-1 rounded-lg border-border bg-background text-sm uppercase focus:border-primary focus:ring-primary">
                    <button type="button" @click="periksa()" :disabled="memeriksa || ! kode"
                            class="rounded-lg border border-border px-4 py-2 text-sm font-medium transition hover:bg-muted disabled:opacity-50">
                        <span x-text="memeriksa ? 'Memeriksa…' : 'Periksa'"></span>
                    </button>
                </div>

                <template x-if="referal">
                    <p class="mt-2 text-sm" :class="referal.sah ? 'text-primary' : 'text-destructive'"
                       x-text="referal.pesan"></p>
                </template>

                @error('referral')
                    <p class="mt-2 text-sm text-destructive">{{ $message }}</p>
                @enderror

                <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                    Potongan hanya berlaku untuk tagihan pertama workspace ini, dan satu workspace
                    hanya bisa memakai satu kode.
                </p>
            </div>
        @endif

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

                    {{-- Harga penuh tetap ditampilkan tercoret. Potongan yang
                         cuma mengganti angkanya membuat orang tidak tahu berapa
                         yang sebenarnya ia hemat. --}}
                    <template x-if="referal?.sah">
                        <p class="mt-1.5 text-sm">
                            <span class="text-muted-foreground line-through"
                                  x-text="rupiah(tahunan ? {{ $plan->price('yearly') }} : {{ $plan->price('monthly') }})"></span>
                            <span class="ml-1.5 font-semibold text-primary"
                                  x-text="rupiah((tahunan ? {{ $plan->price('yearly') }} : {{ $plan->price('monthly') }}) - potongan(tahunan ? {{ $plan->price('yearly') }} : {{ $plan->price('monthly') }}))"></span>
                            <span class="text-xs text-muted-foreground">dengan kode referal</span>
                        </p>
                    </template>

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
