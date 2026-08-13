@extends('layouts.app')
@section('title', 'Riwayat Pesan')

@section('content')
    <x-card>
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div class="min-w-40 flex-1">
                <label class="mb-1 block text-sm font-medium" for="q">Cari</label>
                <input id="q" name="q" value="{{ request('q') }}" placeholder="nomor atau isi pesan"
                       class="w-full rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="session">Sesi</label>
                <select id="session" name="session" class="rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800">
                    <option value="">Semua</option>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}" @selected(request('session') === $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="direction">Arah</label>
                <select id="direction" name="direction" class="rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800">
                    <option value="">Semua</option>
                    <option value="outbound" @selected(request('direction') === 'outbound')>Keluar</option>
                    <option value="inbound" @selected(request('direction') === 'inbound')>Masuk</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="status">Status</label>
                <select id="status" name="status" class="rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800">
                    <option value="">Semua</option>
                    @foreach (['queued' => 'Antre', 'sending' => 'Mengirim', 'sent' => 'Terkirim', 'delivered' => 'Sampai', 'read' => 'Dibaca', 'failed' => 'Gagal'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="rounded-lg border border-stone-300 px-4 py-2 text-sm dark:border-stone-700">Filter</button>
        </form>
    </x-card>

    <x-card class="mt-4">
        @if ($messages->isEmpty())
            <p class="text-sm text-stone-500">Tidak ada pesan yang cocok.</p>
        @else
            <div class="-mx-5 overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="text-left text-xs uppercase text-stone-500">
                        <tr class="border-b border-stone-200 dark:border-stone-800">
                            <th class="px-5 py-2">Waktu</th>
                            <th class="px-5 py-2">Sesi</th>
                            <th class="px-5 py-2">Arah</th>
                            <th class="px-5 py-2">Nomor</th>
                            <th class="px-5 py-2">Isi</th>
                            <th class="px-5 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($messages as $m)
                            <tr class="border-b border-stone-100 hover:bg-stone-50 last:border-0 dark:border-stone-800 dark:hover:bg-stone-800/50">
                                <td class="whitespace-nowrap px-5 py-2 text-stone-500">
                                    <a href="{{ route('messages.show', $m->id) }}" class="hover:underline">{{ $m->created_at->format('d M H:i:s') }}</a>
                                </td>
                                <td class="whitespace-nowrap px-5 py-2">{{ $m->session?->name ?? '—' }}</td>
                                <td class="px-5 py-2">{{ $m->direction === 'outbound' ? 'Keluar' : 'Masuk' }}</td>
                                <td class="whitespace-nowrap px-5 py-2 font-mono text-xs">{{ $m->to_number ?? $m->from_number }}</td>
                                <td class="max-w-sm truncate px-5 py-2 text-stone-600 dark:text-stone-400">{{ $m->body }}</td>
                                <td class="px-5 py-2">
                                    <span class="rounded-full px-2 py-0.5 text-xs {{ $m->status === 'failed' ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' : ($m->status === 'queued' ? 'bg-stone-100 text-stone-600 dark:bg-stone-800' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300') }}">
                                        {{ $m->status }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $messages->links() }}</div>
        @endif
    </x-card>
@endsection
