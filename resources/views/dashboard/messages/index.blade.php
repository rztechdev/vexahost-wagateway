@extends('layouts.app')
@section('title', 'Riwayat Pesan')

@section('content')
    <x-card>
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div class="min-w-40 flex-1">
                <label class="mb-1 block text-sm font-medium" for="q">Cari</label>
                <input id="q" name="q" value="{{ request('q') }}" placeholder="nomor atau isi pesan"
                       class="w-full rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="session">Sesi</label>
                <select id="session" name="session" class="rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
                    <option value="">Semua</option>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}" @selected(request('session') === $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="direction">Arah</label>
                <select id="direction" name="direction" class="rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
                    <option value="">Semua</option>
                    <option value="outbound" @selected(request('direction') === 'outbound')>Keluar</option>
                    <option value="inbound" @selected(request('direction') === 'inbound')>Masuk</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="status">Status</label>
                <select id="status" name="status" class="rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
                    <option value="">Semua</option>
                    @foreach (['queued' => 'Antre', 'sending' => 'Mengirim', 'sent' => 'Terkirim', 'delivered' => 'Sampai', 'read' => 'Dibaca', 'failed' => 'Gagal'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="rounded-lg border border-input bg-background px-4 py-2 text-sm hover:bg-muted">Filter</button>
        </form>
    </x-card>

    <x-card class="mt-4">
        @if ($messages->isEmpty())
            <p class="text-sm text-muted-foreground">Tidak ada pesan yang cocok.</p>
        @else
            <div class="-mx-5 overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="text-left text-xs uppercase text-muted-foreground">
                        <tr class="border-b border-border">
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
                            <tr class="border-b border-border hover:bg-muted/50 last:border-0">
                                <td class="whitespace-nowrap px-5 py-2 text-muted-foreground">
                                    <a href="{{ route('messages.show', $m->id) }}" class="hover:underline">{{ $m->created_at->format('d M H:i:s') }}</a>
                                </td>
                                <td class="whitespace-nowrap px-5 py-2">{{ $m->session?->name ?? '—' }}</td>
                                <td class="px-5 py-2">{{ $m->direction === 'outbound' ? 'Keluar' : 'Masuk' }}</td>
                                <td class="whitespace-nowrap px-5 py-2 font-mono text-xs">{{ $m->to_number ?? $m->from_number }}</td>
                                <td class="max-w-sm truncate px-5 py-2 text-muted-foreground">{{ $m->body }}</td>
                                <td class="px-5 py-2">
                                    <span class="rounded-full px-2 py-0.5 text-xs {{ $m->status === 'failed' ? 'bg-destructive/10 text-destructive' : ($m->status === 'queued' ? 'bg-muted text-muted-foreground' : 'bg-primary/10 text-primary') }}">
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
