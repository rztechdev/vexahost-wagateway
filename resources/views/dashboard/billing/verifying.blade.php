@extends('layouts.checkout')
@section('title', 'Menunggu Verifikasi '.$invoice->number)

@section('content')
    <div class="mx-auto max-w-xl py-6 sm:py-12"
         x-data="menungguVerifikasi('{{ route('billing.status', $invoice->id) }}')"
         x-init="mulai()">
        <div class="overflow-hidden rounded-2xl border border-border bg-card p-6 text-center shadow-lg sm:p-10">

            <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-primary/10 text-primary ring-8 ring-primary/5">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>

            <h1 class="mt-6 text-2xl font-bold tracking-tight sm:text-3xl">Bukti Anda sudah kami terima</h1>
            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                Tagihan <span class="font-semibold text-foreground">{{ $invoice->number }}</span> sedang diperiksa tim kami.
                Anda tidak perlu mengirim ulang atau membayar lagi.
            </p>

            {{-- Kapan pembayaran ini diterima, hitam di atas putih. Tanpa
                 tanggal dan jam yang bisa ditunjuk, "sudah saya kirim tadi"
                 berubah jadi perdebatan tanpa pegangan bagi kedua pihak. --}}
            <div class="mt-8 rounded-xl border border-border/80 bg-muted/30 p-4 text-left text-sm">
                <div class="flex items-center justify-between py-1.5 text-xs text-muted-foreground">
                    <span>Nomor tagihan</span>
                    <span class="font-mono font-medium text-foreground">{{ $invoice->number }}</span>
                </div>
                <div class="flex items-center justify-between py-1.5 text-xs text-muted-foreground">
                    <span>Paket</span>
                    <span class="font-medium text-foreground">{{ $plan->name() }} &middot; {{ $invoice->periodLabel() }}</span>
                </div>
                <div class="flex items-center justify-between py-1.5 text-xs text-muted-foreground">
                    <span>Bukti diterima</span>
                    <span class="font-medium text-foreground">{{ $invoice->updated_at->translatedFormat('j F Y, H:i') }}</span>
                </div>
                <div class="mt-2 flex items-center justify-between border-t border-border pt-2 font-medium">
                    <span class="text-foreground">Jumlah dibayar</span>
                    <span class="text-base font-bold text-primary">Rp {{ number_format($invoice->total, 0, ',', '.') }}</span>
                </div>
            </div>

            <div class="mt-6 rounded-xl border border-border/80 p-4 text-left">
                <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Selanjutnya</p>
                <ol class="mt-3 space-y-3 text-sm">
                    @foreach ([
                        ['Kami cocokkan dengan mutasi rekening', 'Nominal Anda dibuat unik sampai tiga digit terakhir, jadi pembayaran Anda bisa dikenali di antara yang lain.'],
                        ['Langganan diaktifkan', 'Biasanya dalam beberapa jam pada jam kerja. Halaman ini berubah sendiri begitu selesai.'],
                        ['Anda bisa langsung memakainya', 'Kuota dan batas paket baru berlaku seketika setelah tagihan ditandai lunas.'],
                    ] as $i => [$judul, $isi])
                        <li class="flex gap-3">
                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-muted text-xs font-semibold text-muted-foreground">{{ $i + 1 }}</span>
                            <span>
                                <span class="block font-medium">{{ $judul }}</span>
                                <span class="mt-0.5 block text-xs leading-relaxed text-muted-foreground">{{ $isi }}</span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground shadow-sm transition hover:opacity-90">
                    Buka Dashboard
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                </a>
                <a href="{{ route('billing.invoice', $invoice->id) }}"
                   class="inline-flex items-center justify-center rounded-xl border border-border bg-card px-5 py-3 text-sm font-semibold text-muted-foreground transition hover:bg-muted hover:text-foreground">
                    Lihat rincian tagihan
                </a>
            </div>

            {{-- Penanda bahwa halaman ini benar-benar mengawasi, bukan sekadar
                 mengaku begitu. Tanpa tanda yang bergerak, orang tetap menekan
                 muat ulang berkali-kali karena tidak yakin ada yang berjalan. --}}
            <p class="mt-6 flex items-center justify-center gap-2 text-xs text-muted-foreground">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-60"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-primary"></span>
                </span>
                <span x-text="pesan">Memeriksa status pembayaran…</span>
            </p>

            <p class="mt-6 text-xs leading-relaxed text-muted-foreground">
                Sudah lewat satu hari kerja dan langganan belum aktif?
                <a href="https://about.flustra.id/#contact" class="text-primary hover:underline">Hubungi kami</a>
                dengan menyebut nomor tagihan di atas.
            </p>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    /*
     * Menanyakan status tagihan berkala, lalu berpindah sendiri begitu lunas.
     *
     * Halaman ini menjanjikan "berubah sendiri begitu selesai"; tanpa ini janji
     * itu bohong, dan janji yang tidak ditepati di halaman pembayaran membuat
     * orang menekan tombol yang tidak seharusnya.
     *
     * Berhenti sendiri setelah satu jam. Tab yang ditinggal terbuka semalaman
     * tidak boleh terus memukuli server sampai pagi.
     */
    function menungguVerifikasi(alamat) {
        return {
            pesan: 'Memeriksa status pembayaran…',
            jeda: null,
            sampai: Date.now() + 60 * 60 * 1000,

            mulai() {
                this.jeda = setInterval(() => this.periksa(), 15000);
                document.addEventListener('visibilitychange', () => {
                    // Kembali ke tab ini hampir selalu berarti "sudah selesai
                    // belum?", jadi jangan menunggu giliran berikutnya.
                    if (! document.hidden) this.periksa();
                });
            },

            berhenti(pesan) {
                clearInterval(this.jeda);
                this.pesan = pesan;
            },

            async periksa() {
                if (Date.now() > this.sampai) {
                    return this.berhenti('Pemeriksaan otomatis berhenti. Muat ulang halaman untuk memeriksa lagi.');
                }

                try {
                    const jawab = await fetch(alamat, { headers: { Accept: 'application/json' } });
                    if (! jawab.ok) return;

                    const data = await jawab.json();

                    if (data.lunas) {
                        this.berhenti('Pembayaran diterima. Mengalihkan…');
                        window.location = data.lanjut;
                    }
                } catch (e) {
                    // Jaringan putus sesaat bukan alasan menghentikan pengawasan;
                    // giliran berikutnya akan mencoba lagi.
                }
            },
        };
    }
</script>
@endpush
