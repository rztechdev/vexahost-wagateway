@extends('layouts.admin')
@section('title', 'Sesi WhatsApp')

@section('content')
    @php $persen = $kapasitas > 0 ? min(100, round($hidup / $kapasitas * 100)) : 0; @endphp

    <x-card @class(['mb-4', 'border-destructive' => $persen >= 90])>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <p class="text-sm">
                <span class="font-semibold">{{ $hidup }} dari {{ $kapasitas }}</span>
                <span class="text-muted-foreground">slot sesi terpakai. Tiap sesi memakan 300-500 MB RAM.</span>
            </p>
            <div class="h-2 min-w-48 flex-1 overflow-hidden rounded-full bg-muted">
                <div class="h-full rounded-full {{ $persen >= 90 ? 'bg-destructive' : ($persen >= 70 ? 'bg-amber-500' : 'bg-primary') }}"
                     style="width: {{ $persen }}%"></div>
            </div>
        </div>
    </x-card>

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <select name="status" class="rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
            <option value="">Semua status</option>
            @foreach (['connected', 'connecting', 'qr', 'pending', 'failed', 'disconnected'] as $nilai)
                <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $nilai }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90">Saring</button>
    </form>

    <x-card>
        <div class="-mx-5 overflow-x-auto">
            <table class="w-full min-w-[46rem] text-sm">
                <thead class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-5 py-2 font-medium">Sesi</th>
                        <th class="px-5 py-2 font-medium">Workspace</th>
                        <th class="px-5 py-2 font-medium">Status</th>
                        <th class="px-5 py-2 font-medium">Nomor</th>
                        <th class="px-5 py-2 font-medium">Langganan</th>
                        <th class="px-5 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr class="border-b border-border last:border-0">
                            <td class="px-5 py-2.5 font-medium">{{ $session->name }}</td>
                            <td class="px-5 py-2.5 text-muted-foreground">{{ $session->workspace?->name ?? '—' }}</td>
                            <td class="px-5 py-2.5"><x-session-status :status="$session->status" /></td>
                            <td class="px-5 py-2.5 font-mono text-xs text-muted-foreground">{{ $session->phone_number ?? '—' }}</td>
                            <td class="px-5 py-2.5 text-muted-foreground">
                                {{ $session->workspace?->subscription?->statusLabel() ?? '—' }}
                            </td>
                            <td class="px-5 py-2.5 text-right">
                                @if ($session->status !== 'disconnected')
                                    {{-- Memutus, bukan melepas tautan: kredensialnya tetap
                                         tersimpan, jadi pemiliknya menyalakan lagi tanpa scan QR. --}}
                                    <form method="POST" action="{{ route('admin.sessions.disconnect', $session->id) }}"
                                          data-konfirmasi="Putus sesi ini? Pemiliknya bisa menyalakannya lagi tanpa scan ulang.">
                                        @csrf
                                        <button class="text-destructive hover:underline">Putus</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-4 text-muted-foreground">Tidak ada sesi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $sessions->links() }}</div>
@endsection
