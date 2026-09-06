@extends('layouts.app')
@section('title', 'Bantuan')

@section('content')
    {{-- Tidak ada <x-kunci-langganan> di halaman ini, dan itu disengaja.
         Rutenya memang di luar middleware `subscription`: pelanggan yang
         layanannya berhenti justru yang paling butuh menghubungi kami. --}}
    <x-card title="Ajukan pertanyaan"
            subtitle="Kami balas lewat halaman ini, dan mengabari Anda lewat WhatsApp serta email begitu ada jawabannya.">
        <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" class="space-y-4" data-validasi>
            @csrf

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="subject" class="mb-1 block text-sm font-medium">Judul</label>
                    <input id="subject" name="subject" required maxlength="150" value="{{ old('subject') }}"
                           placeholder="mis. Nomor terputus terus setelah scan QR"
                           class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                </div>

                <div>
                    <label for="category" class="mb-1 block text-sm font-medium">Kategori</label>
                    <select id="category" name="category" required
                            class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                        @foreach ($kategori as $nilai => $label)
                            <option value="{{ $nilai }}" @selected(old('category') === $nilai)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="priority" class="mb-1 block text-sm font-medium">Prioritas</label>
                    <select id="priority" name="priority"
                            class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
                        <option value="low" @selected(old('priority') === 'low')>Rendah</option>
                        <option value="normal" @selected(old('priority', 'normal') === 'normal')>Normal</option>
                        <option value="high" @selected(old('priority') === 'high')>Tinggi — layanan berhenti</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="body" class="mb-1 block text-sm font-medium">Ceritakan masalahnya</label>
                <textarea id="body" name="body" required rows="5" maxlength="5000"
                          placeholder="Sebutkan apa yang Anda lakukan, apa yang terjadi, dan apa yang Anda harapkan. Kalau ada pesan galat, salin apa adanya."
                          class="w-full rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">{{ old('body') }}</textarea>
            </div>

            <div>
                <label for="lampiran" class="mb-1 block text-sm font-medium">Lampiran <span class="font-normal text-muted-foreground">(opsional)</span></label>
                <input id="lampiran" name="lampiran" type="file"
                       accept=".{{ implode(',.', $jenisLampiran) }}"
                       class="w-full rounded-lg border-border bg-background text-sm file:mr-3 file:rounded file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-sm">
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ strtoupper(implode(', ', $jenisLampiran)) }} — maksimal {{ $maksLampiranMb }} MB.
                    Lampiran hanya bisa dibuka oleh anggota workspace ini dan tim kami.
                </p>
            </div>

            @foreach (['subject', 'category', 'priority', 'body', 'lampiran'] as $kolom)
                @error($kolom)
                    <p class="text-sm text-destructive">{{ $message }}</p>
                @enderror
            @endforeach

            <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:opacity-90">
                Kirim tiket
            </button>
        </form>
    </x-card>

    <x-section judul="Tiket Anda" class="mt-5">
        @if ($tickets->isEmpty())
            <p class="px-5 py-4 text-sm text-muted-foreground">Belum ada tiket.</p>
        @else
            <table class="w-full min-w-[44rem] text-sm">
                <thead class="border-y border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-5 py-2.5 font-medium">#</th>
                        <th class="px-5 py-2.5 font-medium">Judul</th>
                        <th class="px-5 py-2.5 font-medium">Kategori</th>
                        <th class="px-5 py-2.5 font-medium">Keadaan</th>
                        <th class="px-5 py-2.5 font-medium">Terakhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach ($tickets as $t)
                        <tr class="transition hover:bg-muted/40">
                            <td class="px-5 py-3 text-muted-foreground">{{ $t->id }}</td>
                            <td class="px-5 py-3">
                                <a href="{{ route('tickets.show', $t->id) }}" class="font-medium hover:underline">{{ $t->subject }}</a>
                                <span class="block text-xs text-muted-foreground">{{ $t->messages_count }} pesan</span>
                            </td>
                            <td class="px-5 py-3 text-muted-foreground">{{ $t->labelKategori() }}</td>
                            <td class="px-5 py-3"><x-badge :warna="$t->warnaStatus()">{{ $t->labelStatus() }}</x-badge></td>
                            <td class="whitespace-nowrap px-5 py-3 text-muted-foreground">
                                {{ $t->last_reply_at?->diffForHumans() ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-section>
@endsection
