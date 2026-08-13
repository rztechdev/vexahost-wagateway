@extends('layouts.app')
@section('title', 'Kirim Pesan')

@section('content')
    @if ($sessions->isEmpty())
        <x-card>
            <p class="text-sm text-stone-500">
                Belum ada sesi yang terhubung.
                <a href="{{ route('sessions.index') }}" class="text-emerald-600 underline">Hubungkan satu sesi</a> dulu.
            </p>
        </x-card>
    @else
        <x-card title="Uji coba pengiriman" subtitle="Halaman ini memakai jalur yang sama persis dengan REST API — hasilnya bisa dipakai memastikan integrasi bekerja.">
            <form method="POST" action="{{ route('messages.send') }}" class="space-y-4" x-data="{ body: @js(old('message', '')) }">
                @csrf

                <div>
                    <label class="mb-1 block text-sm font-medium" for="session_id">Kirim dari</label>
                    <select id="session_id" name="session_id" class="w-full rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800">
                        @foreach ($sessions as $session)
                            <option value="{{ $session->id }}">
                                {{ $session->name }} @if ($session->phone_number) (+{{ $session->phone_number }}) @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium" for="to">Nomor tujuan</label>
                    <textarea id="to" name="to" rows="2" required
                              placeholder="081234567890&#10;081298765432"
                              class="w-full rounded-lg border-stone-300 font-mono text-sm dark:border-stone-700 dark:bg-stone-800">{{ old('to') }}</textarea>
                    <p class="mt-1 text-xs text-stone-500">Bisa lebih dari satu — pisahkan dengan baris baru atau koma. Format 08xx maupun 62xx sama-sama diterima.</p>
                </div>

                @if ($templates->isNotEmpty())
                    <div>
                        <label class="mb-1 block text-sm font-medium">Isi dari template</label>
                        <select class="w-full rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800"
                                @change="if ($el.value) body = $el.value">
                            <option value="">— pilih template —</option>
                            @foreach ($templates as $template)
                                <option value="{{ $template->body }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label class="mb-1 block text-sm font-medium" for="message">Pesan</label>
                    <textarea id="message" name="message" rows="6" required maxlength="4096" x-model="body"
                              class="w-full rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800"></textarea>
                    <p class="mt-1 text-xs text-stone-500"><span x-text="body.length"></span> / 4096 karakter</p>
                </div>

                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Kirim</button>
            </form>
        </x-card>

        <x-card title="Catatan pengiriman massal" class="mt-6">
            <p class="text-sm text-stone-600 dark:text-stone-400">
                Setiap pesan diberi jeda acak
                {{ config('gateway.throttle.min_delay_ms') / 1000 }}–{{ config('gateway.throttle.max_delay_ms') / 1000 }} detik
                sebelum dikirim. Jeda ini disengaja: mengirim ratusan pesan beruntun tanpa jeda adalah pola tercepat
                membuat nomor diblokir WhatsApp. Broadcast besar wajar berjalan berjam-jam.
            </p>
        </x-card>
    @endif
@endsection
