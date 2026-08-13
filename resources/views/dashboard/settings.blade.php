@extends('layouts.app')
@section('title', 'Pengaturan')

@section('content')
    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="Workspace">
            <dl class="space-y-3 text-sm">
                @php
                    $rows = [
                        'Nama' => $currentTenant->name,
                        'Slug' => $currentTenant->slug,
                        'Status' => $currentTenant->status,
                        'Paket' => $currentTenant->plan_slug ?? '—',
                        'Batas sesi' => $currentTenant->max_sessions,
                        'Kuota pesan/bulan' => $currentTenant->is_internal ? 'tanpa batas (internal)' : number_format($currentTenant->monthly_message_quota),
                        'Rate limit API' => $currentTenant->api_rate_limit_per_minute.' request/menit',
                    ];
                @endphp
                @foreach ($rows as $label => $value)
                    <div class="flex justify-between gap-4 border-b border-border pb-2 last:border-0">
                        <dt class="text-muted-foreground">{{ $label }}</dt>
                        <dd class="text-right font-medium">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-4 text-xs text-muted-foreground">
                Batas paket dicerminkan dari flustra-pricing. Untuk mengubahnya, ubah langganan di sana —
                bukan di sini — supaya tagihan dan batas pemakaian tidak pernah berbeda.
            </p>
        </x-card>

        <x-card title="Pemakaian">
            <div class="mb-4">
                <p class="text-sm text-muted-foreground">Bulan berjalan ({{ $usage->period }})</p>
                <p class="mt-1 text-2xl font-semibold">{{ number_format($usage->messages_sent) }} <span class="text-base font-normal text-muted-foreground">terkirim</span></p>
            </div>
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-muted-foreground">
                    <tr class="border-b border-border">
                        <th class="py-2">Periode</th>
                        <th class="py-2 text-right">Keluar</th>
                        <th class="py-2 text-right">Masuk</th>
                        <th class="py-2 text-right">Gagal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($history as $row)
                        <tr class="border-b border-border last:border-0 hover:bg-muted/50">
                            <td class="py-2">{{ $row->period }}</td>
                            <td class="py-2 text-right">{{ number_format($row->messages_sent) }}</td>
                            <td class="py-2 text-right">{{ number_format($row->messages_received) }}</td>
                            <td class="py-2 text-right {{ $row->messages_failed > 0 ? 'text-destructive' : '' }}">{{ number_format($row->messages_failed) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
    </div>

    <x-card title="Anggota" subtitle="Anggota harus sudah pernah login ke gateway ini lewat Flustra ID sebelum bisa ditambahkan." class="mt-6">
        <form method="POST" action="{{ route('settings.members.add') }}" class="mb-5 flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-56 flex-1">
                <label class="mb-1 block text-sm font-medium" for="email">Email</label>
                <input id="email" name="email" type="email" required
                       class="w-full rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="role">Peran</label>
                <select id="role" name="role" class="rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
                    <option value="member">Member — hanya melihat &amp; mengirim</option>
                    <option value="admin">Admin — bisa kelola sesi &amp; kunci</option>
                </select>
            </div>
            <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">Tambah</button>
        </form>

        <div class="space-y-2">
            @foreach ($members as $member)
                <div class="flex items-center justify-between border-b border-border py-2 last:border-0">
                    <div>
                        <p class="text-sm font-medium">{{ $member->name }}</p>
                        <p class="text-xs text-muted-foreground">{{ $member->email }} &middot; {{ $member->pivot->role }}</p>
                    </div>
                    @if ($member->pivot->role !== 'owner')
                        <form method="POST" action="{{ route('settings.members.remove', $member->id) }}"
                              onsubmit="return confirm('Keluarkan {{ $member->name }} dari workspace ini?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-destructive hover:underline">Keluarkan</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </x-card>
@endsection
