@extends('layouts.app')
@section('title', 'Ringkasan')

@section('content')
    @include('partials.sesi-perlu-dihubungkan')

    @php
        $terhubung = $sessions->where('status', 'connected')->count();
        $kuota = $currentWorkspace->is_internal ? null : (int) $currentWorkspace->monthly_message_quota;
        $persen = $kuota ? min(100, round($usage->messages_sent / max($kuota, 1) * 100)) : null;
    @endphp

    {{-- ===================== Angka =====================

         Satu-satunya baris berkotak di halaman ini. Sisanya mengalir langsung
         di atas latar, dipisah garis dan jarak.

         Halaman yang seluruh isinya dibungkus kotak membuat setiap bagian
         tampak sama penting — mata kehilangan tempat berpijak dan halamannya
         terasa melelahkan meski isinya sedikit. Kotak sekarang hanya menandai
         satu hal: angka yang perlu dilihat sekilas sebelum apa pun yang lain.
         ============================================================= --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat label="Sesi terhubung"
                :nilai="$terhubung.'/'.$sessions->count()"
                :sub="$sessions->count() === 0 ? 'belum ada nomor tertaut' : 'nomor aktif'"
                :nada="$sessions->count() > 0 && $terhubung === 0 ? 'bahaya' : 'netral'"
                ikon="M12 2a10 10 0 1 0 4.9 18.7L22 22l-1.3-5.1A10 10 0 0 0 12 2z" />

        <x-stat label="Terkirim bulan ini"
                :nilai="number_format($usage->messages_sent)"
                :sub="$persen !== null ? $persen.'% dari kuota '.number_format($kuota) : 'kuota tanpa batas'"
                :nada="$persen !== null && $persen >= 90 ? 'bahaya' : 'netral'"
                ikon="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z" />

        <x-stat label="Diterima bulan ini"
                :nilai="number_format($usage->messages_received)"
                sub="balasan masuk dari pelanggan"
                ikon="M4 4h16v12H5.17L4 17.17V4zM8 9h8M8 12h5" />

        <x-stat label="Gagal bulan ini"
                :nilai="number_format($usage->messages_failed)"
                :sub="$usage->messages_failed > 0 ? 'periksa riwayat pesan' : 'tidak ada kegagalan'"
                :nada="$usage->messages_failed > 0 ? 'bahaya' : 'netral'"
                ikon="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" />
    </div>

    <div class="grid gap-8 lg:grid-cols-[1.6fr_1fr]">
        {{-- ===================== Aktivitas ===================== --}}
        <x-section judul="Aktivitas 7 hari terakhir" rapat>
            <div class="flex h-44 items-end gap-2">
                @foreach ($chart as $hari)
                    <div class="flex flex-1 flex-col items-center gap-1">
                        <div class="flex w-full flex-col-reverse items-stretch gap-0.5" style="height: 140px">
                            {{-- Tinggi batang relatif terhadap hari tersibuk, bukan nilai
                                 mutlak, supaya bentuk grafik tetap terbaca pada volume
                                 kecil maupun besar. --}}
                            <div class="rounded-t bg-chart-3" style="height: {{ round($hari['outbound'] / $chartMax * 100) }}%" title="Keluar: {{ $hari['outbound'] }}"></div>
                            <div class="rounded-t bg-chart-1" style="height: {{ round($hari['inbound'] / $chartMax * 100) }}%" title="Masuk: {{ $hari['inbound'] }}"></div>
                        </div>
                        <span class="text-xs text-muted-foreground">{{ $hari['label'] }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 flex gap-4 text-xs text-muted-foreground">
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-chart-3"></span> Keluar</span>
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-chart-1"></span> Masuk</span>
            </div>
        </x-section>

        {{-- ===================== Sesi ===================== --}}
        <x-section judul="Sesi WhatsApp" rapat>
            <x-slot:aksi>
                <a href="{{ route('sessions.index') }}" class="text-sm text-primary hover:underline">Kelola</a>
            </x-slot:aksi>

            @forelse ($sessions as $sesi)
                @php $ls = \App\Support\StatusBadge::session($sesi->status); @endphp
                <div class="flex items-center justify-between gap-3 border-b border-border py-2.5 last:border-0">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">{{ $sesi->name }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ $sesi->phone_number ?? 'belum tertaut' }}</p>
                    </div>
                    <x-badge :warna="$ls['warna']" titik>{{ $ls['label'] }}</x-badge>
                </div>
            @empty
                <p class="py-2 text-sm leading-relaxed text-muted-foreground">
                    Belum ada sesi.
                    <a href="{{ route('sessions.index') }}" class="text-primary hover:underline">Tautkan nomor pertama</a>
                    untuk mulai mengirim.
                </p>
            @endforelse
        </x-section>
    </div>

    {{-- ===================== Pesan terbaru ===================== --}}
    <x-section judul="Pesan terbaru">
        <x-slot:aksi>
            <a href="{{ route('messages.index') }}" class="text-sm text-primary hover:underline">Semua riwayat</a>
        </x-slot:aksi>

        <x-tabel class="min-w-[44rem]" :kepala="['Waktu' => '', 'Arah' => '', 'Nomor' => '', 'Isi' => '', 'Status' => '']">
            @forelse ($recentMessages as $pesan)
                @php $lm = \App\Support\StatusBadge::message($pesan->status); @endphp
                <tr class="transition hover:bg-muted/40">
                    <td class="whitespace-nowrap px-4 py-2.5 text-muted-foreground sm:px-3">{{ $pesan->created_at->translatedFormat('j M, H:i') }}</td>
                    <td class="px-4 py-2.5 sm:px-3">
                        <x-badge :warna="$pesan->direction === 'inbound' ? 'biru' : 'netral'">
                            {{ $pesan->direction === 'inbound' ? 'masuk' : 'keluar' }}
                        </x-badge>
                    </td>
                    <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs sm:px-3">{{ $pesan->to_number ?? $pesan->from_number }}</td>
                    <td class="max-w-xs truncate px-4 py-2.5 text-muted-foreground sm:px-3">{{ $pesan->body }}</td>
                    <td class="px-4 py-2.5 sm:px-3"><x-badge :warna="$lm['warna']">{{ $lm['label'] }}</x-badge></td>
                </tr>
            @empty
                <x-kosong :kolom="5" judul="Belum ada pesan"
                          pesan="Pesan yang Anda kirim lewat dashboard maupun API akan muncul di sini." />
            @endforelse
        </x-tabel>
    </x-section>
@endsection
